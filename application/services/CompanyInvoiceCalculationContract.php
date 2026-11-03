<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyFinancialJournal.php';require_once __DIR__.'/CompanyInvoiceNativeAmounts.php';
/** Immutable calculation provenance. Absence means the historical algorithm, never an inference from totals. */
final class CompanyInvoiceCalculationContract
{
 public static function options($db,string$p,int$i,int$a,int$invoice):array
 {
  $acl=new IssuerResourceAccess($db,$p);$authorize=function()use($i,$a,$acl):void{if(!$acl->can($i,$a,'invoices','view'))throw new DomainException('company_invoice_access_denied');};return self::read($db,$p,$i,$invoice,$authorize)['options'];
 }
 /** Capability-free proof only inside an already authorized staff/portal balance transaction. */
 public static function nativeZeroInOwned($db,string$p,int$i,int$invoice):void
 {
  IssuerTransaction::assertOwned($db,$p,['issuers_lock','invoices','company_financial_events','issuers_audit']);$proof=self::read($db,$p,$i,$invoice,static function():void{});$event=$proof['event'];
  if($proof['options']!==['calculation_contract'=>CompanyInvoiceNativeAmounts::CONTRACT]||!$event||$event['amount']!=='0.00'||($event['after']['total']??null)!=='0.00'||($event['after']['status']??null)!==2)throw new DomainException('company_invoice_balance_review');
 }
 private static function read($db,string$p,int$i,int$invoice,callable$permission):array
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$event=null;$authorize=function()use($db,$p,$i,$invoice,$permission):void{$permission();if(!IssuerStorage::query($db,'SELECT id FROM '.$p.'invoices WHERE id=? AND issuer_id=?',[$invoice,$i])->row())throw new DomainException('company_invoice_access_denied');};$authorize();
  $sql="SELECT * FROM ".$p."company_financial_events WHERE issuer_id=? AND source_type='invoice' AND source_id=? AND kind='invoice_issued' ORDER BY issuer_sequence";$rows=IssuerStorage::query($db,$sql,[$i,(string)$invoice])->result_array();if(count($rows)>1)throw new DomainException('company_invoice_calculation_proof_invalid');$out=[];
  if($rows){$event=(new CompanyFinancialJournal($db,$p))->decode($rows[0]);if($event['basis']!==CompanyFinancialJournal::CAPTURED)throw new DomainException('company_invoice_calculation_proof_invalid');
   $metadata=json_encode(['evidence_key'=>$event['evidence_key'],'kind'=>'invoice_issued','source_type'=>'invoice','source_id'=>$event['source_id'],'payload_hash'=>$event['payload_hash']],JSON_THROW_ON_ERROR);
   $auditSql='SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?';$auditArgs=[$i,$event['actor_id'],$i,'company_financial_event_recorded',$metadata];$proof=IssuerStorage::query($db,$auditSql,$auditArgs)->result_array();if(count($proof)!==1)throw new DomainException('company_invoice_calculation_proof_invalid');
   if(array_key_exists('calculation_contract',$event['after'])){if($event['after']['calculation_contract']!==CompanyInvoiceNativeAmounts::CONTRACT)throw new DomainException('company_invoice_calculation_contract_invalid');$out=['calculation_contract'=>$event['after']['calculation_contract']];}
  }
  $authorize();if($rows&&IssuerStorage::query($db,$auditSql,$auditArgs)->result_array()!==$proof)throw new DomainException('company_invoice_calculation_proof_changed');if(IssuerStorage::query($db,$sql,[$i,(string)$invoice])->result_array()!==$rows)throw new DomainException('company_invoice_calculation_proof_changed');return['options'=>$out,'event'=>$event];
 }
}
