<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseBilling.php';
require_once __DIR__.'/NativeCompanyExpenseBillability.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
/** Original expense-modal selection. Classification is explicit; conversion remains the canonical billing writer. */
final class NativeCompanyInvoiceExpenseCandidates
{
 public function __construct(private$db,private string$p){}
 public function forCustomer(int$i,int$a,$customer):array
 {
  $client=IssuerPolicy::id($customer);$acl=new IssuerResourceAccess($this->db,$this->p);$check=static function()use($acl,$i,$a):void{foreach(['expenses','customers','invoices']as$feature)if(!$acl->can($i,$a,$feature,'view'))throw new DomainException('native_invoice_expense_denied');if(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit'))throw new DomainException('native_invoice_expense_denied');};$check();$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'customer',$client,'view');NativeCompanyExpenseBillabilitySchema::assertValid($this->db,$this->p);
  $expenses=new CompanyExpenses($this->db,$this->p);$billing=new CompanyExpenseBilling($this->db,$this->p);$meta=new NativeCompanyExpenseBillability($this->db,$this->p);$read=fn($id)=>IssuerTransaction::run($this->db,$this->p,$expenses->tables(),fn()=>$meta->readInOwned($i,$a,$id));
  $sql='SELECT id FROM '.$this->p.'company_expenses WHERE issuer_id=? AND client_id=? AND state=? ORDER BY id LIMIT 501';$params=[$i,$client,'recorded'];$ids=IssuerStorage::query($this->db,$sql,$params)->result_array();if(count($ids)>500)throw new DomainException('native_invoice_expense_limit');$items=[];$unknown=[];$blocked=[];$snapshots=[];$billingSnapshots=[];
  foreach($ids as$source){$id=(int)$source['id'];$row=$expenses->get($i,$a,$id);if(!$row||(int)$row['client_id']!==$client||$row['state']!=='recorded')throw new DomainException('native_invoice_expense_changed');$classification=$read($id);$snapshots[]=[$id,$row,$classification];$canonical=NativeCompanyResourceId::encode('company_expense',$id);
   if($classification===null){$unknown[]=['id'=>$canonical,'reason'=>'billable_undeclared'];continue;}if($classification['billable']!==1)continue;
   $status=$billing->status($i,$a,$id);$billingSnapshots[]=[$id,$status];if($status!==null&&(in_array($status['state'],['converting','converted'],true)||$status['invoice_id']!==null||$status['invoice_current']!==null)){$blocked[]=['id'=>$canonical,'reason'=>'billing_'.$status['state']];continue;}
   $currency=IssuerStorage::query($this->db,'SELECT id,name,symbol,isdefault FROM '.$this->p.'currencies WHERE name=?',[$row['currency']])->result_array();if(count($currency)!==1)throw new DomainException('native_invoice_expense_currency_ambiguous');
   $items[]=['id'=>$canonical,'source_kind'=>'company_expense','source_id'=>$id,'source_revision'=>$row['revision_token'],'billable_revision'=>$classification['revision'],'clientid'=>$client,'expense_name'=>$row['native_form']['name']??$row['description'],'note'=>$row['note'],'category_name'=>$row['category_name'],'amount'=>CompanyExpenses::money((int)$row['amount_cents']),'total'=>CompanyExpenses::money((int)$row['total_cents']),'currency'=>(int)$currency[0]['id'],'currency_data'=>(object)$currency[0],'basis'=>'explicit_billable_selection_not_conversion']+(isset($row['native_form'])?['native_expense_taxes'=>$row['native_form']['taxes']]:['native_expense_tax_rate'=>CompanyExpenses::money((int)$row['tax_rate'])]);
   if($billing->status($i,$a,$id)!==$status||IssuerStorage::query($this->db,'SELECT id,name,symbol,isdefault FROM '.$this->p.'currencies WHERE name=?',[$row['currency']])->result_array()!==$currency)throw new DomainException('native_invoice_expense_changed');
  }
  foreach($billingSnapshots as[$id,$status])if($billing->status($i,$a,$id)!==$status)throw new DomainException('native_invoice_expense_changed');
  foreach($snapshots as[$id,$row,$classification])if($expenses->get($i,$a,$id)!==$row||$read($id)!==$classification)throw new DomainException('native_invoice_expense_changed');if(IssuerStorage::query($this->db,$sql,$params)->result_array()!==$ids||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'customer',$client,'view')!==$ref)throw new DomainException('native_invoice_expense_changed');$check();return['items'=>$items,'undeclared'=>$unknown,'blocked'=>$blocked,'basis'=>'explicit_billable_selection_not_conversion'];
 }
}
