<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceRead.php';
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/CompanyInvoiceDrafts.php';
/** Original modal selection only; no reservation, cancellation, copying or merge authority. */
final class NativeCompanyInvoiceMergeCandidates
{
 public function __construct(private$db,private string$p){}
 private function authorize(int$i,int$a):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit')))throw new DomainException('native_invoice_merge_denied');}
 public function current(int$i,int$a,$id):?array
 {
  $this->authorize($i,$a);if($id===null||$id==='')return null;$source=NativeCompanyResourceId::decode('invoice',$id);
  if($source['source_kind']==='company_invoice_draft'){$draft=(new CompanyInvoiceDrafts($this->db,$this->p))->document($i,$a,$source['source_id']);if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');return['id'=>$source['canonical_id'],'status'=>6,'revision'=>$draft['revision'],'source'=>$draft];}
  $ref=(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($i,$a,$source['source_id']);if($ref['lineage']!=='named_company')throw new DomainException('native_invoice_merge_denied');$doc=(new CompanyInvoices($this->db,$this->p))->document($i,$a,$source['source_id'])->data();return['id'=>$source['canonical_id'],'status'=>$doc['status'],'source'=>$doc];
 }
 public function forCustomer(int$i,int$a,$customer,$current=''):array
 {
  $this->authorize($i,$a);$client=IssuerPolicy::id($customer);$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'customer',$client);$active=$this->current($i,$a,$current);$items=[];$pages=[];$reader=new NativeCompanyInvoiceRead($this->db,$this->p);
  // The original excludes paid/partly-paid/cancelled targets before selecting source invoices.
  if($active===null||!in_array($active['status'],[2,3,5],true))foreach([1,4,6]as$status){$query=['client_id'=>$client,'status'=>$status,'length'=>501];$page=$reader->page($i,$a,$query);if($page['recordsFiltered']>500)throw new DomainException('native_invoice_merge_limit');$pages[]=[$query,$page];foreach($page['documents']as$doc){if($active!==null&&$doc['invoice_id']===$active['id'])continue;
   if($doc['lineage']==='private_draft'){$payload=$doc['draft']['payload'];$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));$currency=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($payload['currency']);$row=['id'=>$doc['invoice_id'],'status'=>6,'total'=>$amounts['total'],'discount_total'=>$amounts['discount_total'],'currency_name'=>$currency['iso_code'],'native_formatted_number'=>'DRAFT','source_revision'=>$doc['draft']['revision']];}
   elseif($doc['lineage']==='named_company')$row=['id'=>$doc['invoice_id'],'status'=>$doc['status'],'total'=>$doc['totals']['total'],'discount_total'=>$doc['totals']['discount_total'],'currency_name'=>$doc['currency'],'native_formatted_number'=>$doc['number']];
   else throw new DomainException('native_invoice_merge_denied');
   $row['basis']='selection_only_not_merge_authority';$items[]=(object)$row;
  }}
  foreach($pages as[$query,$before])if($reader->page($i,$a,$query)!==$before)throw new DomainException('native_invoice_merge_changed');if($this->current($i,$a,$current)!==$active||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'customer',$client)!==$ref)throw new DomainException('native_invoice_merge_changed');$this->authorize($i,$a);return['items'=>$items,'current'=>$active,'basis'=>'selection_only_not_merge_authority'];
 }
}
