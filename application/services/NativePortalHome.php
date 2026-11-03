<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/NativeCompanyWorkStatusCatalog.php';
require_once __DIR__.'/ClientIssuerScope.php';
require_once __DIR__.'/CustomerRelationBoundary.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';

/** Data for the original customer dashboard, bounded to one verified contact and issuer. */
final class NativePortalHome
{
 public function __construct(private $db,private string $p,private CompanyPortal $portal){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$portal->assertStorageBinding($db,$p);}
 private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function identity(array $binding,int $issuer):array{
  $who=$this->portal->invoiceIdentity($binding,$issuer,true);if(!$who)throw new DomainException('native_portal_home_denied');
  if(!$this->db->field_exists('email_verified_at',$this->p.'contacts'))throw new RuntimeException('issuer_portal_verification_schema_required');
  $v=$this->rows('SELECT email_verified_at FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$who['contact_id'],$who['client_id']]);
  if(count($v)!==1||$v[0]['email_verified_at']===null)throw new DomainException('native_portal_verification_required');return $who;
 }
 private function permissions(array $who):array{
  $rows=$this->rows('SELECT permission_id,COUNT(*) n FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id IN (1,6) GROUP BY permission_id',[$who['contact_id']]);$out=['invoices'=>false,'projects'=>false];
  foreach($rows as$r){if((int)$r['n']!==1)throw new DomainException('native_portal_home_denied');if((int)$r['permission_id']===1)$out['invoices']=true;if((int)$r['permission_id']===6)$out['projects']=true;}return $out;
 }
 public function snapshot(array $binding,int $issuer):array{
  $issuer=IssuerPolicy::id($issuer);$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','clients','contacts','contact_permissions','options','invoices','issuers_invoice_documents','currencies','company_currency_units','projects','company_projects','company_portal_projects'];
  $metadata=$this->db->table_exists($this->p.'company_project_native_metadata');if($metadata)$tables[]='company_project_native_metadata';
  return IssuerTransaction::run($this->db,$this->p,$tables,function()use($binding,$issuer,$metadata){
   $who=$this->identity($binding,$issuer);$permissions=$this->permissions($who);$primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer;
   $invoiceRows=[];$projectRows=[];$legacyProjects=[];$invoicePolicy=null;
   if($permissions['invoices']){
    // Named invoices require their persisted document owner. The installation
    // principal additionally sees its historical NULL-owned invoices.
    $ownership='(i.issuer_id=? AND EXISTS (SELECT 1 FROM '.$this->p.'issuers_invoice_documents d WHERE d.invoice_id=i.id AND d.issuer_id=i.issuer_id))';
    if($primary)$ownership='('.$ownership.' OR (i.issuer_id IS NULL AND NOT EXISTS (SELECT 1 FROM '.$this->p.'issuers_clients owner WHERE owner.client_id=i.clientid)))';
    $settings=new CompanySettingsStorage($this->db,$this->p);$invoicePolicy=$settings->exists($issuer,'exclude_invoice_from_client_area_with_draft_status')?$settings->get($issuer,'exclude_invoice_from_client_area_with_draft_status'):'0';if(!in_array($invoicePolicy,['0','1'],true))throw new DomainException('native_portal_home_denied');
    $invoiceRows=$this->rows('SELECT i.status,SUBSTR(i.date,1,4) year,SUBSTR(i.date,6,2) month,i.currency,COUNT(*) amount_count,SUM(i.total) amount FROM '.$this->p.'invoices i WHERE i.clientid=? AND '.$ownership.' AND i.status IN (1,2,3,4'.($invoicePolicy==='0'?',6':'').') GROUP BY i.status,SUBSTR(i.date,1,4),SUBSTR(i.date,6,2),i.currency ORDER BY i.status,year,month,i.currency',[$who['client_id'],$issuer]);
   }
   if($permissions['projects']){
    $projectSql='SELECT p.status,'.($metadata?'m.workflow_status':'NULL workflow_status').',COUNT(*) n FROM '.$this->p.'company_projects p JOIN '.$this->p.'company_portal_projects v ON v.issuer_id=p.issuer_id AND v.project_id=p.id AND v.visible=1'.($metadata?' LEFT JOIN '.$this->p.'company_project_native_metadata m ON m.issuer_id=p.issuer_id AND m.project_id=p.id':'').' WHERE p.issuer_id=? AND p.client_id=? AND p.status IN (\'active\',\'completed\',\'cancelled\') GROUP BY p.status,'.($metadata?'m.workflow_status':'workflow_status').' ORDER BY p.status,workflow_status';
    $projectRows=$this->rows($projectSql,[$issuer,$who['client_id']]);
    if($primary){$boundary=\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'projects','p');$legacyProjects=$this->rows('SELECT p.status,COUNT(*) n FROM '.$this->p.'projects p WHERE p.clientid=? AND '.$boundary.' GROUP BY p.status ORDER BY p.status',[$who['client_id']]);}
   }
   if($this->identity($binding,$issuer)!==$who||$this->permissions($who)!==$permissions)throw new DomainException('native_portal_home_denied');
   if($invoicePolicy!==null){$settings=new CompanySettingsStorage($this->db,$this->p);$after=$settings->exists($issuer,'exclude_invoice_from_client_area_with_draft_status')?$settings->get($issuer,'exclude_invoice_from_client_area_with_draft_status'):'0';if($after!==$invoicePolicy)throw new DomainException('native_portal_home_denied');}
   $invoiceCounts=[1=>0,2=>0,3=>0,4=>0];$invoiceTotal=0;$years=[];$currencies=[];$namedCurrencies=[];$chart=[];
   foreach($invoiceRows as$row){$status=(int)$row['status'];$year=(string)$row['year'];$month=(int)$row['month'];$currency=(int)$row['currency'];if(!in_array($status,[1,2,3,4,6],true)||!preg_match('/^[0-9]{4}$/D',$year)||$month<1||$month>12||$currency<1||!is_numeric($row['amount']))throw new RuntimeException('issuer_portal_home_data_invalid');$invoiceTotal+=(int)$row['amount_count'];$years[$year]=true;$currencies[$currency]=true;if($status===6)continue;$invoiceCounts[$status]+=(int)$row['amount_count'];$chart[$year][$currency][$status][$month]=(string)$row['amount'];}
   if($permissions['invoices'])foreach($this->rows('SELECT DISTINCT i.currency FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND i.clientid=?',[$issuer,$who['client_id']]) as$row)$namedCurrencies[(int)$row['currency']]=true;
   $owned=[];foreach($projectRows as$row){$state=NativeCompanyWorkStatusCatalog::projectState((string)$row['status'],$row['workflow_status']);$owned[$state]=($owned[$state]??0)+(int)$row['n'];}
   $legacy=[];foreach($legacyProjects as$row){$state=(int)$row['status'];if($state<1||$state>5)throw new RuntimeException('issuer_portal_home_data_invalid');$legacy[$state]=(int)$row['n'];}
   $years=array_keys($years);rsort($years,SORT_STRING);$currencyIds=array_keys($currencies);sort($currencyIds,SORT_NUMERIC);$currencies=[];
   if($currencyIds){$currencies=$this->rows('SELECT id,name,symbol FROM '.$this->p.'currencies WHERE id IN ('.implode(',',array_fill(0,count($currencyIds),'?')).') ORDER BY id',$currencyIds);if(count($currencies)!==count($currencyIds))throw new RuntimeException('issuer_portal_home_currency_invalid');$units=new CompanyCurrencyUnits($this->db,$this->p);foreach($currencies as$currency)if(isset($namedCurrencies[(int)$currency['id']])&&$units->resolveRead($currency['id'])['iso_code']!==$currency['name'])throw new DomainException('issuer_portal_home_currency_invalid');}
   return ['identity'=>$who,'permissions'=>$permissions,'invoice_counts'=>$invoiceCounts,'invoice_total'=>$invoiceTotal,'invoice_years'=>$years,'invoice_currencies'=>$currencies,'invoice_chart'=>$chart,'project_owned'=>$owned,'project_legacy'=>$legacy,'primary'=>$primary];
  });
 }
}
