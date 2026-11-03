<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalHome.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativePortalIdentity.php';

/** Adapter for the existing Clients::index and perfex home view. */
final class NativePortalHomeHttp
{
 public function __construct(private $ci){}
 public function index():void{
  if(!PortalRequestScope::sealedFor($this->ci)||strtolower((string)$this->ci->router->fetch_class())!=='clients'||strtolower((string)$this->ci->router->fetch_method())!=='index'||!in_array($this->ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_portal_home_denied');
  $issuer=PortalRequestScope::issuerId();$secret=$this->ci->config->item('encryption_key');$identity=(new NativePortalIdentity($this->ci->db,db_prefix(),$secret))->current($this->ci->session,$issuer);
  if(!$identity){redirect(NativePortalPresentation::url('authentication/login'),'location',303);return;}
  $portal=new CompanyPortal($this->ci->db,db_prefix(),$secret);$home=(new NativePortalHome($this->ci->db,db_prefix(),$portal))->snapshot($identity['binding'],$issuer);
  $this->ci->data(['title'=>$home['identity']['company'],'is_home'=>true,'native_portal_home'=>$home]);$this->ci->view('home');$this->ci->layout();
 }
}
