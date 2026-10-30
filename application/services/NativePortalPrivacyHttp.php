<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalConsentCapability.php';
require_once __DIR__.'/NativeCompanyPrivacyRequests.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
/** Original privacy page. A committed removal request is not a deletion or an email receipt. */
final class NativePortalPrivacyHttp
{
 public function __construct(private$ci){if(!PortalRequestScope::sealedFor($ci)||strtolower($ci->router->fetch_class())!=='clients'||strtolower($ci->router->fetch_method())!=='gdpr')throw new DomainException('native_privacy_denied');}
 public function index():void{
  $ci=$this->ci;$issuer=PortalRequestScope::issuerId();$secret=$ci->config->item('encryption_key');$identity=(new NativePortalIdentity($ci->db,db_prefix(),$secret))->current($ci->session,$issuer);if(!$identity)throw new DomainException('native_privacy_denied');$portal=new CompanyPortal($ci->db,db_prefix(),$secret);$who=$portal->invoiceIdentity($identity['binding'],$issuer);if(!$who)throw new DomainException('native_privacy_denied');$store=new CompanySettingsStorage($ci->db,db_prefix());$options=[];foreach(['enable_gdpr','gdpr_enable_terms_and_conditions','gdpr_contact_enable_right_to_be_forgotten','gdpr_data_portability_contacts','gdpr_enable_consent_for_contacts']as$key)$options[$key]=$store->get($issuer,$key);
  if($options['enable_gdpr']!=='1')throw new DomainException('native_privacy_disabled');
  if($ci->input->method(true)==='POST'){
   PortalRequestScope::assertMutation($ci);$flag=$ci->input->post('removal_request',false);$description=$ci->input->post('removal_description',false);$key=$ci->input->post('native_privacy_request',false);if(!is_scalar($flag)||!(string)$flag||!is_string($description)||!is_string($key))throw new DomainException('native_privacy_invalid');
   (new NativeCompanyPrivacyRequests($ci->db,db_prefix()))->request($portal,$identity['binding'],$issuer,$description,$key);set_alert('success',_l('data_removal_request_sent'));redirect(NativePortalPresentation::url('clients/gdpr'),'location',303);return;
  }
  if(!in_array($ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_privacy_denied');
  $consent='';if($options['gdpr_enable_consent_for_contacts']==='1'){$token=(new NativePortalConsentCapability($ci->db,db_prefix(),$secret))->issueForBinding($portal,$identity['binding'],$issuer);$consent=NativePortalPresentation::url('consent/contact/'.$token);}
  $deliveries=IssuerStorage::query($ci->db,'SELECT r.id,r.status,o.state FROM '.db_prefix().'gdpr_requests r JOIN '.db_prefix().'company_native_gdpr_request_owners owner ON owner.request_id=r.id JOIN '.db_prefix().NativeCompanyPrivacyMailSchema::OUTBOX.' o ON o.request_id=r.id AND o.issuer_id=owner.issuer_id WHERE owner.issuer_id=? AND owner.source_kind=? AND owner.source_id=? AND r.contact_id=? AND r.clientid=? ORDER BY r.id DESC LIMIT 1',[$issuer,'contact',$who['contact_id'],$who['contact_id'],$who['client_id']])->result_array();
  $delivery='';if($deliveries){$latest=$deliveries[0];$delivery=in_array($latest['state'],['pending','disabled','review'],true)?$latest['state']:'review';if($latest['state']==='queued'){$rows=IssuerStorage::query($ci->db,'SELECT f.state,q.status FROM '.db_prefix().NativeCompanyPrivacyMailSchema::RECEIPTS.' receipt LEFT JOIN '.db_prefix().'company_native_mail_dispatch_fences f ON f.queue_id=receipt.queue_id AND f.issuer_id=receipt.issuer_id LEFT JOIN '.db_prefix().'mail_queue q ON q.id=receipt.queue_id AND q.issuer_id=receipt.issuer_id WHERE receipt.issuer_id=? AND receipt.request_id=?',[$issuer,(int)$latest['id']])->result_array();$delivery=$rows?'sent':'review';foreach($rows as$row){if($row['state']==='unknown'||$row['state']===null||in_array($row['status'],['failed'],true)){$delivery='review';break;}if($row['state']!=='sent')$delivery='pending';}}}
  if(!$portal->invoiceIdentity($identity['binding'],$issuer))throw new DomainException('native_privacy_denied');$ci->data(['title'=>_l('gdpr'),'native_portal_privacy'=>true,'native_privacy_options'=>$options,'native_privacy_top'=>NativeCompanyContentHtml::purify($store->get($issuer,'gdpr_page_top_information_block')),'native_privacy_request'=>bin2hex(random_bytes(32)),'native_privacy_delivery'=>$delivery,'native_privacy_consent_url'=>$consent]);$ci->view('gdpr');$ci->layout();
 }
}
