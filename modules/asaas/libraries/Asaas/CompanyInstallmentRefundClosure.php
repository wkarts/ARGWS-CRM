<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyRefundContract.php';
require_once APPPATH.'services/CompanyAsaasRefundPostings.php';
/** Full native reversal plus canonical remote closure. Never infers a partial refund allocation. */
final class CompanyInstallmentRefundClosure
{
 public static function evidence($db,string $p,int $issuer,int $actor,array $member,string $revision,bool $fresh,?array $frozen=null):array
 {
  $rows=static fn($sql,$args=[])=>\IssuerStorage::query($db,$sql,$args)->result_array();$origin=(int)$member['origin']['id'];$invoice=(int)$member['origin']['invoice_id'];
  if(!in_array($member['origin']['state'],['installment_bound','review_required'],true)||($member['origin']['state']==='review_required'&&$member['origin']['last_code']!=='company_adverse_event'))throw new \DomainException('asaas_installment_refund_closure_conflict');
  if(!$db->table_exists($p.'company_asaas_refund_postings'))throw new \DomainException('asaas_installment_full_refund_required');
  $postings=(new \CompanyAsaasRefundPostings($db,$p))->forInvoiceInOwned($issuer,$actor,$invoice);
  $receipt=$rows('SELECT payment_id FROM '.$p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$origin,$issuer])[0]??null;
  $data=$receipt?($postings[(int)$receipt['payment_id']]??null):null;
  $posting=$receipt?($rows('SELECT request_key,payload_hash FROM '.$p.'company_asaas_refund_postings WHERE issuer_id=? AND invoice_id=? AND payment_id=? AND origin_id=?',[$issuer,$invoice,$receipt['payment_id'],$origin])[0]??null):null;
  if(!$data||!$posting||$data['origin_id']!==$origin||$data['returned_paid']!==$member['member']['value']||$data['principal_reinstated']!==$member['member']['value'])throw new \DomainException('asaas_installment_full_refund_required');
  $request=$rows('SELECT * FROM '.$p.'asaas_company_refund_requests WHERE request_key=? AND issuer_id=? AND origin_id=?',[$data['refund_request_key'],$issuer,$origin])[0]??null;
  if(!$request||$request['account_revision']!==$revision)throw new \DomainException('asaas_installment_refund_closure_conflict');
  $id=$frozen['proof_id']??$request['latest_observation'];$o=$rows('SELECT * FROM '.$p.'asaas_company_refund_observations WHERE id=? AND issuer_id=? AND origin_id=? AND request_key=?',[$id,$issuer,$origin,$request['request_key']])[0]??null;
  if(!$o||$o['operation']!=='query'||$o['account_revision']!==$revision||$o['source_hash']!==$request['source_hash'])throw new \DomainException('asaas_installment_refund_closure_conflict');
  $safe=json_decode($o['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!hash_equals($o['evidence_hash'],CompanyRefundContract::hash($safe))||$safe['payment_id']!==$member['member']['id']||$safe['nominal']!==$member['member']['value']||$safe['done_total']!==$member['member']['value']||$safe['pending_total']!=='0.00'||$safe['unsupported']!==[]||$safe['deleted']!==false||$safe['not_deleted_confirmed']!==true||!in_array($safe['status'],['REFUNDED','RECEIVED','CONFIRMED'],true))throw new \DomainException('asaas_installment_refund_closure_conflict');
  if($fresh&&((int)$id!==(int)$request['latest_observation']||$o['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$o['created_at']>gmdate('Y-m-d H:i:s')))throw new \DomainException('asaas_installment_refund_closure_stale');
  $refundEvent=false;foreach($rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=?',[$request['account_row'],$member['member']['id']]) as $e){if(in_array($e['event_type'],['PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_PARTIALLY_REFUNDED'],true))$refundEvent=true;if($e['state']==='conflict'||$e['created_at']>$o['created_at']||!in_array($e['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED','PAYMENT_CONFIRMED','PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_PARTIALLY_REFUNDED'],true))throw new \DomainException('asaas_installment_refund_closure_conflict');}
  if($member['origin']['state']==='review_required'&&!$refundEvent)throw new \DomainException('asaas_installment_refund_closure_conflict');
  $result=['type'=>'refunded','posting_key'=>$posting['request_key'],'posting_hash'=>$posting['payload_hash'],'proof_id'=>(int)$o['id'],'proof_hash'=>$o['evidence_hash'],'refund_request'=>$request['request_key']];
  if($frozen!==null&&$frozen!==$result)throw new \DomainException('asaas_installment_refund_closure_conflict');return $result;
 }
}
