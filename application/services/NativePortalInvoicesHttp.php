<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalInvoiceList.php';
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';

/** Binds the original public invoice list to the authenticated contact. */
final class NativePortalInvoicesHttp
{
 public function __construct(private$ci){}
 public function index($status=false):void{
  if(!PortalRequestScope::sealedFor($this->ci)||strtolower((string)$this->ci->router->fetch_class())!=='clients'||strtolower((string)$this->ci->router->fetch_method())!=='invoices'||!in_array($this->ci->input->method(true),['GET','HEAD'],true))throw new DomainException('company_portal_invoice_unavailable');
  $issuer=PortalRequestScope::issuerId();$secret=$this->ci->config->item('encryption_key');$identity=(new NativePortalIdentity($this->ci->db,db_prefix(),$secret))->current($this->ci->session,$issuer);if(!$identity){redirect(NativePortalPresentation::url('authentication/login'),'location',303);return;}
  $grants=IssuerStorage::query($this->ci->db,'SELECT permission_id FROM '.db_prefix().'contact_permissions WHERE userid=? AND permission_id=1',[$identity['binding']['contact_id']])->result_array();
  if(count($grants)!==1){redirect(NativePortalPresentation::url('clients'),'location',303);return;}
  $status=($status===false||$status==='')?0:$status;$before=$this->ci->input->get('before',false)??0;
  try{$page=(new NativePortalInvoiceList($this->ci->db,db_prefix(),new CompanyPortal($this->ci->db,db_prefix(),$secret)))->page($identity['binding'],$issuer,$before,$status);}catch(DomainException $error){if($error->getMessage()!=='company_portal_invoice_unavailable')throw $error;redirect(NativePortalPresentation::url('clients'),'location',303);return;}
  if((new NativePortalIdentity($this->ci->db,db_prefix(),$secret))->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_invoice_unavailable');
  $this->ci->data(['title'=>_l('clients_my_invoices'),'native_portal_invoices'=>$page]);$this->ci->view('invoices');$this->ci->layout();
 }
}
