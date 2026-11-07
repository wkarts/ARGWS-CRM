<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInstallmentEditJournal.php';
require_once __DIR__.'/CompanyInstallmentIdentity.php';
/** An active group may coexist only with explicitly retired, canonically deleted predecessors. */
final class CompanyInstallmentGenerations
{
 public static function currentMembers($db,string $p,array $active,array $all):array
 {
  $rows=static fn($sql,$args=[])=>\IssuerStorage::query($db,$sql,$args)->result_array();$issuer=(int)$active['issuer_id'];$roots=[];$seen=[];
  foreach($all as $o){if((int)$o['issuer_id']!==$issuer||$o['provider']!=='asaas'||!in_array($o['channel'],['installments','installment'],true))throw new \DomainException('asaas_installment_generation_conflict');if($o['channel']==='installments')$roots[(int)$o['id']]=$o;}
  if(!isset($roots[(int)$active['id']]))throw new \DomainException('asaas_installment_generation_conflict');
  $cursor=(int)$active['id'];$visited=[];
  while(true){if(isset($visited[$cursor]))throw new \DomainException('asaas_installment_generation_conflict');$visited[$cursor]=true;$root=$roots[$cursor]??null;if(!$root)throw new \DomainException('asaas_installment_generation_conflict');
   $members=array_values(array_filter($all,static fn($o)=>$o['batch_id']===$root['batch_id']&&$o['account_id']===$root['account_id']&&$o['environment']===$root['environment']));foreach($members as $m)$seen[(int)$m['id']]=true;
   $previous=CompanyInstallmentEditJournal::records($db,$p,$issuer,$cursor,'group_prev');if(!$previous)break;if(count($previous)!==1)throw new \DomainException('asaas_installment_generation_conflict');
   $link=$previous[0]['value'];$oldId=$link['previous_root']??null;$old=$roots[$oldId]??null;if(!$old||$old['state']!=='installment_bound'||$old['active_slot']!==null||($link['successor_root']??null)!==$cursor)throw new \DomainException('asaas_installment_generation_conflict');
   foreach(['invoice_id','invoice_hash','invoice_total','account_id','environment'] as $field)if($old[$field]!==$root[$field])throw new \DomainException('asaas_installment_generation_conflict');
   $next=CompanyInstallmentEditJournal::records($db,$p,$issuer,$oldId,'group_next');if(count($next)!==1||$next[0]['value']!==$link)throw new \DomainException('asaas_installment_generation_conflict');
   $plans=CompanyInstallmentEditJournal::records($db,$p,$issuer,$oldId,'group_recreate');$sends=CompanyInstallmentEditJournal::records($db,$p,$issuer,$oldId,'group_create_s');$results=CompanyInstallmentEditJournal::records($db,$p,$issuer,$oldId,'group_created');
   if(count($plans)!==1||count($sends)!==1||count($results)!==1||($sends[0]['value']['plan_id']??null)!==(int)$plans[0]['id']||($results[0]['value']['plan_id']??null)!==(int)$plans[0]['id']||($link['result_proof']??null)!==(int)$results[0]['id']||($link['result_hash']??null)!==$results[0]['evidence_hash']||($results[0]['value']['review']['group_id']??null)!==$root['batch_id'])throw new \DomainException('asaas_installment_generation_conflict');
   if(($plans[0]['value']['account_revision']??null)!==$plans[0]['account_revision']||($plans[0]['value']['previous_root']??null)!==$oldId||($plans[0]['value']['actor_id']??null)!==(int)$plans[0]['actor_id'])throw new \DomainException('asaas_installment_generation_conflict');
   foreach([$sends[0],$results[0],$next[0],$previous[0]] as $record)if($record['account_revision']!==$plans[0]['account_revision']||(int)$record['actor_id']!==(int)$plans[0]['actor_id'])throw new \DomainException('asaas_installment_generation_conflict');
   foreach($results[0]['value']['review']['members'] as $member)if(($member['external_reference']??null)!==hash('sha256','installment-successor:'.$plans[0]['value']['key']))throw new \DomainException('asaas_installment_generation_conflict');
   $bound=$rows("SELECT result_json FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='installment_bind'",[$cursor]);if(count($bound)!==1||(json_decode($bound[0]['result_json'],true,64,JSON_THROW_ON_ERROR)['review']??null)!==$results[0]['value']['review'])throw new \DomainException('asaas_installment_generation_conflict');
   $oldMembers=array_values(array_filter($all,static fn($o)=>$o['batch_id']===$old['batch_id']&&$o['channel']==='installment'));if(!$oldMembers)throw new \DomainException('asaas_installment_generation_conflict');$historical=CompanyInstallmentIdentity::member($db,$p,$issuer,(int)$oldMembers[0]['id']);
   if(count($link['retired']??[])!==count($historical['members']))throw new \DomainException('asaas_installment_generation_conflict');
   foreach($historical['members'] as $remote=>$member){$origin=(int)$member['origin']['id'];$e=$link['retired'][$remote]??[];$proof=$rows("SELECT * FROM {$p}asaas_company_proofs WHERE id=? AND origin_id=? AND operation='parcel_query'",[$e['proof_id']??0,$origin])[0]??null;$safe=$proof?json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR):[];
    if(!$proof||$proof['state']!=='installment_observed'||$proof['account_revision']!==$plans[0]['account_revision']||($safe['customer']??null)!==$member['member']['customer']||($safe['billing_type']??null)!==$member['member']['billing_type']||!in_array($member['origin']['state'],['installment_bound','review_required'],true)||($member['origin']['state']==='review_required'&&$member['origin']['last_code']!=='company_adverse_event')||($e['proof_hash']??null)!==$proof['evidence_hash']||!hash_equals($proof['evidence_hash'],CompanyInstallmentEditJournal::hash($safe))||($safe['id']??null)!==$remote||($safe['deleted']??null)!==true||!in_array($safe['status']??null,['PENDING','OVERDUE'],true)||($safe['value']??null)!==$member['member']['value']||($safe['due_date']??null)!==$member['member']['due_date']||($safe['external_reference']??null)!==$member['member']['external_reference']||($safe['adverse']??true)||($safe['payment_date']??null)!==''||($safe['credit_date']??null)!==''||$rows('SELECT origin_id FROM '.$p.'asaas_company_receipts WHERE origin_id=?',[$origin])||($rows('SELECT state FROM '.$p.'asaas_company_remote_operations WHERE origin_id=?',[$origin])[0]['state']??null)!=='deleted')throw new \DomainException('asaas_installment_generation_conflict');
    foreach($rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=?',[$historical['account']['id'],$remote]) as $event)if(!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'],true)||$event['state']==='conflict'||$event['created_at']>$proof['created_at'])throw new \DomainException('asaas_installment_generation_conflict');
   }
   $cursor=$oldId;
  }
  if(count($visited)!==count($roots)||count($seen)!==count($all))throw new \DomainException('asaas_installment_generation_conflict');
  return array_values(array_filter($all,static fn($o)=>$o['batch_id']===$active['batch_id']&&$o['account_id']===$active['account_id']&&$o['environment']===$active['environment']));
 }
}
