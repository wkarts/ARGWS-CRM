<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyOriginPolicy.php';
require_once __DIR__.'/CompanyInstallmentEditJournal.php';
/** Historical identity only. Callers own the transaction and independently authorize every operation. */
final class CompanyInstallmentIdentity
{
 public static function tables():array{return ['asaas_payment_origins','asaas_company_origin_meta','asaas_company_accounts','asaas_company_proofs','issuers_audit',CompanyOriginPolicy::TABLE];}
 private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 public static function member($db,string $p,int $issuer,int $origin):array
 {
  \IssuerTransaction::assertOwned($db,$p,self::tables());
  $rows=static fn(string $sql,array $args=[]):array=>\IssuerStorage::query($db,$sql,$args)->result_array();
  $fail=static function():never{throw new \DomainException('asaas_installment_identity_conflict');};
  $selected=$rows('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND provider=? AND channel=? FOR UPDATE',[$origin,$issuer,'asaas','installment'])[0]??null;if(!$selected)$fail();
  $initial=$rows("SELECT result_json FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='installment_bind' FOR UPDATE",[$origin]);if(count($initial)!==1)$fail();
  $initial=json_decode($initial[0]['result_json'],true,64,JSON_THROW_ON_ERROR);$rootId=$initial['group_origin']??null;if(!is_int($rootId)||$rootId<1)$fail();
  $root=$rows('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND provider=? AND channel=? FOR UPDATE',[$rootId,$issuer,'asaas','installments'])[0]??null;if(!$root)$fail();
  $all=$rows('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND account_id=? AND environment=? AND batch_id=? ORDER BY id FOR UPDATE',[$root['invoice_id'],$root['account_id'],$root['environment'],$root['batch_id']]);
  $rootMeta=null;$review=null;$members=[];$evidence=[];
  foreach($all as $row){
   foreach(['issuer_id','invoice_id','invoice_hash','invoice_total','account_id','environment','batch_id'] as $field)if((string)$row[$field]!== (string)$root[$field])$fail();
   if($row['provider']!=='asaas')$fail();
   $meta=$rows('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$row['id']])[0]??null;
   $proofs=$rows("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='installment_bind' FOR UPDATE",[$row['id']]);
   $audits=$rows("SELECT * FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_asaas_installment_bound' FOR UPDATE",[$issuer,$row['id']]);
   if(!$meta||count($proofs)!==1||count($audits)!==1)$fail();$proof=$proofs[0];$safe=json_decode($proof['result_json'],true,64,JSON_THROW_ON_ERROR);
   $audit=json_encode(['proof_id'=>(int)$proof['id'],'evidence_hash'=>$proof['evidence_hash']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
   if($proof['state']!=='installment_bound'||!hash_equals($proof['evidence_hash'],self::hash($safe))||($safe['group_origin']??null)!==$rootId||($safe['origin_id']??null)!==(int)$row['id']||($safe['issuer_id']??null)!==$issuer||($safe['account_row']??null)!==(int)$meta['account_row']||($safe['snapshot_hash']??null)!==$meta['snapshot_hash']||!hash_equals($meta['snapshot_hash'],hash('sha256',$meta['snapshot_json']))||(int)$proof['actor_id']!==(int)$meta['created_by']||(int)$audits[0]['actor_id']!==(int)$meta['created_by']||$audits[0]['metadata_json']!==$audit)$fail();
   CompanyOriginPolicy::assertManual($db,$p,(int)$row['id'],$issuer,$meta['request_key']);
   $evidence[]=['origin_id'=>(int)$row['id'],'proof_id'=>(int)$proof['id'],'proof_hash'=>$proof['evidence_hash'],'audit_id'=>(int)$audits[0]['id']];
   if((int)$row['id']===$rootId){$rootMeta=$meta;$review=$safe['review']??null;if(!is_array($review)||($review['review_hash']??null)!==$meta['request_key']||($review['group_id']??null)!==$row['batch_id']||$row['remote_id']!=='grp_'.substr(hash('sha256',$row['batch_id']),0,60))$fail();}
   else{$member=$safe['member']??null;if(!$rootMeta||$row['active_slot']!==null||$row['channel']!=='installment'||!is_array($member)||$member['id']!==$row['remote_id']||$member['due_date']!==$row['invoice_due_date']||$meta['request_key']!==self::hash(['installment-member',$rootMeta['request_key'],$row['remote_id']])||$meta['account_row']!==$rootMeta['account_row']||$meta['snapshot_json']!==$rootMeta['snapshot_json']||$meta['customer_id']!==$rootMeta['customer_id']||$meta['billing_type']!==$rootMeta['billing_type']||isset($members[$member['id']]))$fail();$members[$member['id']]=['origin'=>$row,'meta'=>$meta,'member'=>$member];}
  }
  if(!$review||count($members)!==count($review['members']??[])||$rootMeta['customer_id']!==($review['members'][0]['customer']??null)||$rootMeta['billing_type']!==($review['billing_type']??null))$fail();
  foreach($review['members'] as $member)if(($members[$member['id']]['member']??null)!==$member)$fail();
  $account=$rows('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$rootMeta['account_row'],$issuer])[0]??null;
  if(!$account||$account['account_id']!==$root['account_id']||$account['environment']!==$root['environment']||!isset($members[$selected['remote_id']])||(int)$members[$selected['remote_id']]['origin']['id']!==$origin)$fail();
  $snapshot=json_decode($rootMeta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
  if((string)($snapshot['id']??'')!==(string)$root['invoice_id']||(string)($snapshot['issuer_id']??'')!==(string)$issuer||($snapshot['hash']??null)!==$root['invoice_hash']||\CompanyInvoiceAmounts::minor((string)($snapshot['total']??''))!==\CompanyInvoiceAmounts::minor((string)$root['invoice_total'])||($snapshot['duedate']??null)!==$root['invoice_due_date'])$fail();
  [$review,$members,$editHistory]=CompanyInstallmentEditJournal::effective($db,$p,$root,$review,$members);
  $member=$members[$selected['remote_id']]['member'];
  $identity=['group_origin'=>$rootId,'group_id'=>$root['batch_id'],'member_origin'=>$origin,'member_id'=>$member['id'],'member_number'=>$member['number'],'member_value'=>$member['value'],'member_due_date'=>$member['due_date'],'issuer_id'=>$issuer,'account_row'=>(int)$account['id'],'account_id'=>$account['account_id'],'environment'=>$account['environment'],'invoice_id'=>(int)$root['invoice_id'],'invoice_hash'=>$root['invoice_hash'],'invoice_total'=>$root['invoice_total'],'snapshot_hash'=>$rootMeta['snapshot_hash'],'bindings'=>$evidence];
  if($member['external_reference']!==$root['invoice_hash'])$identity['remote_reference']=$member['external_reference'];
  if(isset($editHistory[$selected['remote_id']]))$identity['edits']=$editHistory[$selected['remote_id']];
  return ['review'=>$review,'members'=>$members,'root_meta'=>$rootMeta,'identity'=>$identity,'root'=>$root,'origin'=>$selected,'meta'=>$members[$selected['remote_id']]['meta'],'account'=>$account,'snapshot'=>$snapshot];
 }
}
