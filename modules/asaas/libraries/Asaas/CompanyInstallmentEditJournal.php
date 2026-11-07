<?php
declare(strict_types=1);
namespace Asaas;
/** Append-only plans, dispatch claims and canonical acknowledgements. Never rewrites bindings. */
final class CompanyInstallmentEditJournal
{
 public static function hash(array $v):string{return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 public static function records($db,string $p,int $issuer,int $root,string $operation):array
 {
  \IssuerTransaction::assertOwned($db,$p,['asaas_company_proofs','issuers_audit']);
  $out=[];foreach(\IssuerStorage::query($db,'SELECT * FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id FOR UPDATE',[$root,$operation])->result_array() as $row){
   $value=json_decode($row['result_json'],true,64,JSON_THROW_ON_ERROR);
   $audit=json_encode(['proof_id'=>(int)$row['id'],'evidence_hash'=>$row['evidence_hash']],JSON_THROW_ON_ERROR);
   if(!hash_equals($row['evidence_hash'],self::hash($value))||($value['issuer_id']??null)!==$issuer||$row['state']!==$operation||count(\IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$issuer,$row['actor_id'],$root,'company_asaas_'.$operation,$audit])->result_array())!==1)throw new \DomainException('asaas_installment_edit_history_conflict');
   $out[]=$row+['value'=>$value];
  }
  $audits=\IssuerStorage::query($db,'SELECT metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id FOR UPDATE',[$issuer,$root,'company_asaas_'.$operation])->result_array();
  if(count($audits)!==count($out))throw new \DomainException('asaas_installment_edit_history_conflict');
  return $out;
 }
 public static function append($db,string $p,int $issuer,int $actor,int $root,string $revision,string $operation,array $value):array
 {
  \IssuerTransaction::assertOwned($db,$p,['asaas_company_proofs','issuers_audit']);$hash=self::hash($value);$now=gmdate('Y-m-d H:i:s');
  \IssuerStorage::query($db,'INSERT INTO '.$p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$root,$revision,$actor,$operation,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,$operation,$now]);$id=(int)$db->insert_id();
  \IssuerStorage::query($db,'INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_'.$operation,$issuer,$root,json_encode(['proof_id'=>$id,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR),$now]);
  $records=self::records($db,$p,$issuer,$root,$operation);$last=end($records);if(!$last||(int)$last['id']!==$id)throw new \RuntimeException('issuer_asaas_installment_edit_unconfirmed');return $last;
 }
 /** Identity validation is independent of present-day actor/account authority. */
 public static function effective($db,string $p,array $root,array $review,array $members,?string $ownKey=null):array
 {
  $issuer=(int)$root['issuer_id'];$id=(int)$root['id'];$plans=self::records($db,$p,$issuer,$id,'parcel_edit');$applied=self::records($db,$p,$issuer,$id,'parcel_edit_done');$seen=[];$history=[];
  foreach($plans as $plan){$v=$plan['value'];$current=array_map(static fn($m)=>$m['member'],$members);
   if(($v['group_origin']??null)!==$id||($v['before']??null)!==$current||!is_array($v['after']??null)||array_keys($v['after'])!==array_keys($current)||($v['key']??null)!==self::hash(['installment-edit',$issuer,$id,$v['actor_id']??null,$v['account_revision']??null,$current,$v['after']]))throw new \DomainException('asaas_installment_edit_history_conflict');
   $beforeSum=0;$afterSum=0;$changed=[];
   foreach($current as $remote=>$member){$next=$v['after'][$remote];if(array_diff_key($member,['value'=>1,'due_date'=>1])!==array_diff_key($next,['value'=>1,'due_date'=>1])||array_keys($member)!==array_keys($next))throw new \DomainException('asaas_installment_edit_history_conflict');$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$next['due_date']);$amount=\CompanyInvoiceAmounts::minor($next['value']);if(!$date||$date->format('Y-m-d')!==$next['due_date']||$amount<=0)throw new \DomainException('asaas_installment_edit_history_conflict');$beforeSum+=\CompanyInvoiceAmounts::minor($member['value']);$afterSum+=$amount;if($next!==$member)$changed[]=$remote;}
   if(!$changed||$beforeSum!==$afterSum)throw new \DomainException('asaas_installment_edit_history_conflict');
   $done=array_values(array_filter($applied,static fn($r)=>($r['value']['plan_id']??null)===(int)$plan['id']));
   if(count($done)>1)throw new \DomainException('asaas_installment_edit_history_conflict');
   if(!$done){if($ownKey!==$v['key']||$plan!==end($plans))throw new \DomainException('asaas_installment_edit_pending');continue;}
   $done=$done[0];$seen[]=(int)$done['id'];$results=self::records($db,$p,$issuer,$id,'parcel_edit_seen');$sends=self::records($db,$p,$issuer,$id,'parcel_edit_send');
   if(array_keys($done['value']['results']??[])!==$changed)throw new \DomainException('asaas_installment_edit_history_conflict');
   foreach($changed as $remote){$acks=array_values(array_filter($results,static fn($r)=>(int)$r['id']===($done['value']['results'][$remote]??null)));$claims=array_values(array_filter($sends,static fn($r)=>($r['value']['plan_id']??null)===(int)$plan['id']&&($r['value']['remote_id']??null)===$remote));
    if(count($acks)!==1||count($claims)!==1||($acks[0]['value']['plan_id']??null)!==(int)$plan['id']||($acks[0]['value']['remote_id']??null)!==$remote||($acks[0]['value']['target']??null)!==$v['after'][$remote])throw new \DomainException('asaas_installment_edit_history_conflict');
    $history[$remote][]=['plan_id'=>(int)$plan['id'],'plan_hash'=>$plan['evidence_hash'],'done_id'=>(int)$done['id'],'done_hash'=>$done['evidence_hash']];
   }
   foreach($v['after'] as $remote=>$member)$members[$remote]['member']=$member;
  }
  if($seen!==array_map(static fn($r)=>(int)$r['id'],$applied))throw new \DomainException('asaas_installment_edit_history_conflict');
  $review['members']=array_values(array_map(static fn($m)=>$m['member'],$members));return [$review,$members,$history];
 }
}
