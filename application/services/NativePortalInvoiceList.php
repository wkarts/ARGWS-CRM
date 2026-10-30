<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortalInvoices.php';
require_once __DIR__.'/SalesNumberPresentation.php';

/** Canonical invoice list: owned documents plus proven primary historical rows. */
final class NativePortalInvoiceList
{
 public function __construct(private $db,private string $p,private CompanyPortal $portal){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$portal->assertStorageBinding($db,$p);}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function authorized(array$binding,int$issuer):array{
  $who=$this->portal->invoiceIdentity($binding,$issuer,true);if(!$who)throw new DomainException('company_portal_invoice_unavailable');
  $grants=$this->rows('SELECT permission_id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1',[$who['contact_id']]);
  $verified=$this->rows('SELECT email_verified_at FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$who['contact_id'],$who['client_id']]);
  if(count($grants)!==1||count($verified)!==1||$verified[0]['email_verified_at']===null)throw new DomainException('company_portal_invoice_unavailable');return$who;
 }
 private function legacyInOwned(array$binding,int$issuer,int$before,int$status):array{
   IssuerTransaction::assertOwned($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','clients','contacts','contact_permissions','options','invoices','currencies']);
   $who=$this->authorized($binding,$issuer);if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)return[];
   if($this->rows('SELECT client_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$who['client_id']]))return[];
   $settings=new CompanySettingsStorage($this->db,$this->p);$exclude=$settings->exists($issuer,'exclude_invoice_from_client_area_with_draft_status')?$settings->get($issuer,'exclude_invoice_from_client_area_with_draft_status'):'0';if(!in_array($exclude,['0','1'],true))throw new DomainException('company_portal_invoice_unavailable');
   $padding=$settings->exists($issuer,'number_padding_prefixes')?$settings->get($issuer,'number_padding_prefixes'):'0';if(!preg_match('/^(?:[0-9]|1[0-9]|20)$/D',(string)$padding))throw new DomainException('company_portal_invoice_unavailable');
   $sql='SELECT i.id,i.number raw_number,i.prefix,i.number_format,i.date,i.duedate,i.status,i.total,i.hash,c.name currency FROM '.$this->p.'invoices i JOIN '.$this->p.'currencies c ON c.id=i.currency WHERE i.clientid=? AND i.issuer_id IS NULL AND i.status IN (1,2,3,4,5'.($exclude==='0'?',6':'').')'.($before?' AND i.id<?':'').($status?' AND i.status=?':'').' ORDER BY i.id DESC LIMIT 51';$args=[$who['client_id']];if($before)$args[]=$before;if($status)$args[]=$status;$rows=$this->rows($sql,$args);
   foreach($rows as&$row){$row['number']=(int)$row['status']===6?$row['prefix'].'DRAFT':SalesNumberPresentation::format($row['raw_number'],$row['number_format'],$row['prefix'],$row['date'],(int)$padding);$row['attachments']=[];unset($row['raw_number'],$row['prefix'],$row['number_format']);$row['lineage']='legacy';}unset($row);
   if($this->authorized($binding,$issuer)!==$who||(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('company_portal_invoice_unavailable');return$rows;
 }
 public function page(array$binding,int$issuer,$before=0,$status=0):array{
  $issuer=IssuerPolicy::id($issuer);foreach(['before'=>$before,'status'=>$status]as$key=>$value)if((!is_int($value)&&!is_string($value))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value)||(string)(int)$value!==(string)$value)throw new DomainException('company_portal_invoice_unavailable');$before=(int)$before;$status=(int)$status;if($status>6)throw new DomainException('company_portal_invoice_unavailable');
  $documents=new CompanyPortalInvoices($this->db,$this->p,$this->portal);$tables=array_values(array_unique(array_merge($documents->tableNames(),['options'])));
  return IssuerTransaction::run($this->db,$this->p,$tables,function()use($documents,$binding,$issuer,$before,$status){
  $who=$this->authorized($binding,$issuer);$owned=$documents->pageInOwnedTransaction($binding,$issuer,$before,$status);if($owned===null)throw new DomainException('company_portal_invoice_unavailable');$legacy=$this->legacyInOwned($binding,$issuer,$before,$status);
  $ownedItems=$owned['items'];foreach($ownedItems as&$row)$row['lineage']='owned';unset($row);$items=array_merge($ownedItems,$legacy);usort($items,static fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);
  $hasMore=count($items)>50||$owned['next']!==null;$items=array_slice($items,0,50);$next=$hasMore&&$items?(int)$items[count($items)-1]['id']:null;
  foreach($items as$row)if(!is_string($row['hash'])||!preg_match('/^[A-Za-z0-9]{32}$/D',$row['hash']))throw new DomainException('company_portal_invoice_unavailable');
  if($this->authorized($binding,$issuer)!==$who)throw new DomainException('company_portal_invoice_unavailable');
  return ['items'=>$items,'next'=>$next,'status'=>$status];
  });
 }
}
