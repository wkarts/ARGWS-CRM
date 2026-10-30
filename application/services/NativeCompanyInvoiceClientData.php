<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyClients.php';
require_once __DIR__.'/CompanyClientNativeFields.php';
require_once __DIR__.'/NativeCompanyBillableTaskRead.php';
require_once __DIR__.'/NativeCompanyInvoiceMergeCandidates.php';
require_once __DIR__.'/NativeCompanyInvoiceExpenseCandidates.php';
require_once __DIR__.'/CompanyProjects.php';
/** Data for the original customer-change callback. No global model and no financial mutation. */
final class NativeCompanyInvoiceClientData
{
 public function __construct(private$db,private string$p){}
 public function read(int$i,int$a,$customer,$current=''):array
 {
  $client=IssuerPolicy::id($customer);$acl=new IssuerResourceAccess($this->db,$this->p);$check=static function()use($acl,$i,$a):void{if($acl->primaryId()===$i||!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit'))||!$acl->can($i,$a,'projects','view')||!$acl->can($i,$a,'tasks','view')||!$acl->can($i,$a,'expenses','view')||!$acl->can($i,$a,'customers','view'))throw new DomainException('native_invoice_client_data_denied');};$check();
  $clients=new NativeCompanyClients($this->db,$this->p,$i,$a);$details=$clients->details($client);if(!$details||!isset($details['native_profile']['default_currency']))throw new DomainException('company_client_access_denied');$billing=$clients->billing($client);
  $currency=$details['native_profile']['default_currency'];if(!is_int($currency)&&(!is_string($currency)||!preg_match('/^(?:0|[1-9][0-9]*)$/D',$currency)))throw new DomainException('native_invoice_client_currency_invalid');$currency=(int)$currency;
  if($currency!==0){$currencyRows=IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array();if(count($currencyRows)!==1)throw new DomainException('native_invoice_client_currency_invalid');}
  $sql='SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? ORDER BY id LIMIT 1';$params=[$i,$client];$projects=IssuerStorage::query($this->db,$sql,$params)->result_array();$project=null;if($projects){$project=(new CompanyProjects($this->db,$this->p))->get($i,$a,(int)$projects[0]['id']);if((int)$project['client_id']!==$client)throw new DomainException('native_invoice_client_data_changed');}
  $tasks=(new NativeCompanyBillableTaskRead($this->db,$this->p))->forCustomer($i,$a,$client);$merge=(new NativeCompanyInvoiceMergeCandidates($this->db,$this->p))->forCustomer($i,$a,$client,$current);
  // Cancellation suppresses the expense modal in the original controller; other targets retain real selection.
  $expenses=$merge['current']!==null&&$merge['current']['status']===5?['items'=>[],'basis'=>'cancelled_invoice_target']:(new NativeCompanyInvoiceExpenseCandidates($this->db,$this->p))->forCustomer($i,$a,$client);
  if($clients->details($client)!==$details||$clients->billing($client)!==$billing||IssuerStorage::query($this->db,$sql,$params)->result_array()!==$projects||($project!==null&&(new CompanyProjects($this->db,$this->p))->get($i,$a,(int)$projects[0]['id'])!==$project))throw new DomainException('native_invoice_client_data_changed');
  if($currency!==0&&IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array()!==$currencyRows)throw new DomainException('native_invoice_client_data_changed');if((new NativeCompanyInvoiceMergeCandidates($this->db,$this->p))->current($i,$a,$current)!==$merge['current'])throw new DomainException('native_invoice_client_data_changed');$check();
  return ['billing_shipping'=>$billing,'client_currency'=>$currency,'customer_has_projects'=>(bool)$projects,'billable_tasks'=>$tasks['items'],'invoices_to_merge'=>$merge['items'],'expenses_to_bill'=>$expenses['items']];
 }
}
