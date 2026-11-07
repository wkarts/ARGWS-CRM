<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInstallmentEditJournal.php';
/** Immutable receipt anchor; retaining paid principal never creates another receipt. */
final class CompanyInstallmentPaidRetention
{
 public static function evidence($db,string $p,int $issuer,array $member,string $revision,bool $fresh,?array $frozen=null):array
 {
  $rows=static fn($sql,$args=[])=>\IssuerStorage::query($db,$sql,$args)->result_array();$o=$member['origin'];$m=$member['member'];$id=(int)$o['id'];
  $r=$rows('SELECT * FROM '.$p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$id,$issuer])[0]??null;
  $payment=$r?($rows('SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=? AND invoiceid=?',[$r['payment_id'],$o['invoice_id']])[0]??null):null;
  $core=$r?($rows('SELECT * FROM '.$p.'company_payment_receipts WHERE payment_id=? AND issuer_id=? AND invoice_id=?',[$r['payment_id'],$issuer,$o['invoice_id']])[0]??null):null;
  if(!$r||!$payment||!$core||$payment['paymentmode']!=='asaas_company_received'||\CompanyInvoiceAmounts::decimal(\CompanyInvoiceAmounts::minor((string)$payment['amount']))!==$m['value']||$core['request_key']!==$r['canonical_key'])throw new \DomainException('asaas_installment_retained_receipt_conflict');
  $hash=static fn($v)=>CompanyInstallmentEditJournal::hash($v);$key=$hash(['company-asaas-received',$issuer,$o['account_id'],$o['environment'],$m['id']]);
  $payload=['invoiceid'=>(int)$o['invoice_id'],'amount'=>$m['value'],'paymentmode'=>$payment['paymentmode'],'paymentmethod'=>$payment['paymentmethod'],'date'=>$payment['date'],'transactionid'=>$payment['transactionid'],'note'=>$payment['note']];
  if($key!==$r['canonical_key']||$payment['transactionid']!=='ASAAS-'.$key||!hash_equals($core['input_hash'],hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))))throw new \DomainException('asaas_installment_retained_receipt_conflict');
  $proof=$rows("SELECT * FROM {$p}asaas_company_proofs WHERE id=? AND origin_id=? AND operation='parcel_query'",[$r['proof_id'],$id])[0]??null;
  $safe=$proof?json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR):[];
  self::canonical($proof,$safe,$m,$hash);
  if($safe['payment_date']!==$payment['date']||$r['evidence_hash']!==$hash([$member['meta']['snapshot_hash'],$safe['value'],$safe['net_value'],$safe['fee'],$safe['payment_date'],$safe['billing_type'],$m['id']]))throw new \DomainException('asaas_installment_retained_receipt_conflict');
  $result=['type'=>'retained','origin_id'=>$id,'payment_id'=>(int)$r['payment_id'],'receipt_hash'=>$hash($r),'native_hash'=>$hash($payment),'core_hash'=>$hash($core),'proof_id'=>(int)$proof['id'],'proof_hash'=>$proof['evidence_hash'],'amount'=>$m['value']];
  if($frozen!==null&&$frozen!==$result)throw new \DomainException('asaas_installment_retained_receipt_conflict');
  if($frozen!==null){
   // Refund events have their own durable writer; other new/adverse facts require review.
   foreach($rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=?',[$member['meta']['account_row'],$m['id']]) as $e){
    $refund=in_array($e['event_type'],['PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_PARTIALLY_REFUNDED'],true);
    if($e['state']==='conflict'||!$refund&&(!in_array($e['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED'],true)||$e['state']==='review_required'||$e['created_at']>$proof['created_at']))throw new \DomainException('asaas_installment_retained_financial_review');
   }
  }
  if($fresh){
   foreach(['asaas_company_remote_operations','asaas_company_refund_requests'] as $table)if($db->table_exists($p.$table)&&$rows('SELECT origin_id FROM '.$p.$table.' WHERE origin_id=?',[$id]))throw new \DomainException('asaas_installment_retained_financial_review');
   $latest=$rows("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_query' ORDER BY id DESC LIMIT 1",[$id])[0]??null;$current=$latest?json_decode($latest['result_json'],true,32,JSON_THROW_ON_ERROR):[];self::canonical($latest,$current,$m,$hash);
   if($latest['account_revision']!==$revision||$latest['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$latest['created_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_installment_retained_query_stale');
   foreach($rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=?',[$member['meta']['account_row'],$m['id']]) as $e)if(!in_array($e['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED'],true)||in_array($e['state'],['conflict','review_required'],true)||$e['created_at']>$latest['created_at'])throw new \DomainException('asaas_installment_retained_financial_review');
  }
  return $result;
 }
 private static function canonical(?array $proof,array $safe,array $member,callable $hash):void
 {
  if(!$proof||$proof['state']!=='installment_observed'||!hash_equals($proof['evidence_hash'],$hash($safe)))throw new \DomainException('asaas_installment_retained_receipt_conflict');
  foreach(['id','customer','billing_type','value','due_date','external_reference'] as $field)if(($safe[$field]??null)!==$member[$field])throw new \DomainException('asaas_installment_retained_receipt_conflict');
  if(($safe['status']??null)!=='RECEIVED'||($safe['adverse']??true)||($safe['deleted']??true)||($safe['payment_date']??'')==='')throw new \DomainException('asaas_installment_retained_receipt_conflict');
 }
}
