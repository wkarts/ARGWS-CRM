<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPortalAnnouncements.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
final class NativePortalAnnouncementsHttp
{
 private int$issuer;private array$binding;private NativeCompanyPortalAnnouncements$reader;
 public function __construct(private$ci){if(!PortalRequestScope::sealedFor($ci)||strtolower($ci->router->fetch_class())!=='clients'||!in_array(strtolower($ci->router->fetch_method()),['announcements','announcement','dismiss_announcement'],true))throw new DomainException('native_portal_context_required');$this->issuer=PortalRequestScope::issuerId();$secret=$ci->config->item('encryption_key');$identity=(new NativePortalIdentity($ci->db,db_prefix(),$secret))->current($ci->session,$this->issuer);if(!$identity)throw new DomainException('company_portal_announcement_denied');$this->binding=$identity['binding'];$this->reader=new NativeCompanyPortalAnnouncements($ci->db,db_prefix(),new CompanyPortal($ci->db,db_prefix(),$secret));}
 private function readMethod():void{if(!in_array($this->ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_portal_context_invalid');}
 public function index():void{$this->readMethod();$this->ci->data(['title'=>_l('announcements'),'announcements'=>$this->reader->all($this->binding,$this->issuer)]);$this->ci->view('announcements');$this->ci->layout();}
 public function detail($id):void{$this->readMethod();$row=$this->reader->detail($this->binding,$this->issuer,$id);$row['message']=NativeCompanyContentHtml::purify($row['message']);$this->ci->data(['title'=>$row['name'],'announcement'=>(object)$row]);$this->ci->view('announcement');$this->ci->layout();}
 public function dismiss($id):void{PortalRequestScope::assertMutation($this->ci);$this->reader->dismiss($this->binding,$this->issuer,$id);redirect(NativePortalPresentation::url('clients/announcements'),'location',303);}
}
