<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalConsentLedger.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
/** The original consent route and template, with purpose-bound authority rather than a login. */
final class NativePortalConsentHttp
{
 public function __construct(private$ci){if(!PortalRequestScope::sealedFor($ci)||strtolower($ci->router->fetch_class())!=='consent'||strtolower($ci->router->fetch_method())!=='contact')throw new DomainException('native_consent_capability_denied');}
 public function contact(string$key):void{
  $ci=$this->ci;$issuer=PortalRequestScope::issuerId();$cap=new NativePortalConsentCapability($ci->db,db_prefix(),$ci->config->item('encryption_key'));$ledger=new NativePortalConsentLedger($ci->db,db_prefix(),$cap);$state=$ledger->page($issuer,$key);
  $ci->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer')->set_header('X-Robots-Tag: noindex, nofollow');
  if($ci->input->method(true)==='POST'){
   PortalRequestScope::assertMutation($ci);$actions=$ci->input->post('action',false)??[];$revision=$ci->input->post('native_consent_revision',false);$request=$ci->input->post('native_consent_request',false);
   if(!is_array($actions)||!is_string($revision)||!is_string($request))throw new DomainException('native_consent_invalid');
   $ledger->record($issuer,$key,$revision,$request,$actions,(string)$ci->input->ip_address());redirect(NativePortalPresentation::url('consent/contact/'.$key),'location',303);return;
  }
  if(!in_array($ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_consent_invalid');
  $store=new CompanySettingsStorage($ci->db,db_prefix());$heading=$store->get($issuer,'companyname');$top=NativeCompanyContentHtml::purify($store->get($issuer,'gdpr_consent_public_page_top_block'));$cap->resolve($issuer,$key);
  $ci->data(['native_portal_consent'=>true,'native_consent_heading'=>$heading,'native_consent_top'=>$top,'native_consent_revision'=>$state['revision'],'native_consent_request'=>bin2hex(random_bytes(32)),'native_portal_csrf'=>PortalRequestScope::csrfToken($ci),'native_portal_issuer'=>$issuer,'contact'=>(object)$state['contact'],'purposes'=>$state['purposes'],'title'=>_l('gdpr').' - '.$state['contact']['firstname'].' '.$state['contact']['lastname'],'bodyclass'=>'consent']);$ci->view('consent');$ci->disableNavigation();$ci->disableSubMenu();$ci->layout();
 }
}
