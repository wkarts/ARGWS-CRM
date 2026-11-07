<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixFutureWorker.php';
/** Atomically replaces a cancelled mandate's future-series binding; no bank mutation or reused consent. */
final class CompanyPixReplacement
{
 private CompanyService $service;
 private CompanyPixFutureSchedules $series;
 public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);$this->series=new CompanyPixFutureSchedules($db,$p);}
 public function tables():array{return array_merge($this->series->tables(),array_keys(CompanyPixFutureWorkerSchema::definitions()));}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private static function hash(array $v):string{return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
 private function authority(int $issuer,int $actor):void
 {
  $v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'2.0.0','<'))throw new \DomainException('asaas_module_200_required');CompanyPixFutureWorkerSchema::ensure($this->db,$this->p,false);
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_pix_replacement_denied');
  foreach(['configure','remote_remove','issue'] as $action)$this->service->authorize($issuer,$actor,$action);
 }
 private function proof(int $root,string $operation):?array
 {
  $rows=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$root,$operation]);if(!$rows)return null;if(count($rows)!==1)throw new \DomainException('asaas_pix_replacement_conflict');$r=$rows[0];$value=json_decode($r['result_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($value)||self::hash($value)!==$r['evidence_hash'])throw new \DomainException('asaas_pix_replacement_conflict');return $r+['value'=>$value];
 }
 private function previous(int $issuer,int $actor,int $old,int $new,int $schedule):array
 {
  $this->authority($issuer,$actor);if($old===$new)throw new \DomainException('asaas_pix_replacement_denied');
  foreach([$old,$new] as $root)if(!$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=?',[$root,$issuer,'pix_mandate']))throw new \DomainException('asaas_pix_replacement_denied');
  $binding=$this->proof($old,'pix_series');$cancel=$this->proof($old,'pix_cancelled');$revoke=$this->proof($old,'pix_revoke');
  if(!$binding||($binding['value']['issuer_id']??null)!==$issuer||($binding['value']['schedule_id']??null)!==$schedule||!$cancel||!$revoke||($cancel['value']['status']??null)!=='CANCELLED'||($cancel['value']['remote_id']??null)!==$binding['value']['authorization_id']||$cancel['account_revision']!==$binding['account_revision'])throw new \DomainException('asaas_pix_replacement_cancellation_required');
  $worker=$this->rows('SELECT revision FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=? FOR UPDATE',[$old,$issuer])[0]??null;
  return ['issuer_id'=>$issuer,'actor_id'=>$actor,'old_origin'=>$old,'new_origin'=>$new,'schedule_id'=>$schedule,'old_binding_hash'=>$binding['evidence_hash'],'cancellation_hash'=>$cancel['evidence_hash'],'old_worker_revision'=>$worker['revision']??null,'old_binding'=>$binding['value']];
 }
 public function review(int $issuer,int $actor,int $old,int $new,int $schedule,array $holidays,callable $decrypt,?callable $transport=null):array
 {
  $this->run(fn()=>$this->previous($issuer,$actor,$old,$new,$schedule));
  $review=$this->series->review($issuer,$actor,$new,$schedule,$holidays,$decrypt,$transport);
  return $this->run(function()use($issuer,$actor,$old,$new,$schedule,$review){$value=$this->previous($issuer,$actor,$old,$new,$schedule);foreach(['client_id','currency','frequency'] as $key)if($value['old_binding'][$key]!==$review['binding'][$key])throw new \DomainException('asaas_pix_replacement_conflict');if($value['old_binding']['account_row']===$review['binding']['account_row']&&$value['old_binding']['authorization_id']===$review['binding']['authorization_id'])throw new \DomainException('asaas_pix_replacement_conflict');$value['new_binding_hash']=$review['revision'];return $review+['replacement'=>$value,'replacement_revision'=>self::hash($value)];});
 }
 public function confirm(int $issuer,int $actor,int $old,int $new,int $schedule,array $holidays,string $newRevision,string $revision,bool $confirmed):int
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');if(!array_is_list($holidays)||count($holidays)>100)throw new \DomainException('asaas_pix_calendar_invalid');foreach($holidays as $day){if(!is_string($day))throw new \DomainException('asaas_pix_calendar_invalid');\CompanyInvoiceRecurringDates::date($day);}sort($holidays,SORT_STRING);$holidays=array_values(array_unique($holidays));
  return $this->run(function()use($issuer,$actor,$old,$new,$schedule,$holidays,$newRevision,$revision){
   $this->authority($issuer,$actor);$existing=$this->proof($new,'pix_replaces');
   if($existing){$v=$existing['value'];if(($v['issuer_id']??null)!==$issuer||($v['actor_id']??null)!==$actor||($v['old_origin']??null)!==$old||($v['new_origin']??null)!==$new||($v['schedule_id']??null)!==$schedule||($v['new_binding_hash']??null)!==$newRevision||$existing['evidence_hash']!==$revision)throw new \DomainException('asaas_pix_replacement_conflict');$peer=$this->proof($old,'pix_replaced_by');$bound=$this->proof($new,'pix_series');if(!$peer||$peer['evidence_hash']!==$revision||!$bound||$bound['evidence_hash']!==$newRevision||$bound['value']['holidays']!==$holidays)throw new \DomainException('asaas_pix_replacement_conflict');return (int)$bound['id'];}
   $value=$this->previous($issuer,$actor,$old,$new,$schedule);$value['new_binding_hash']=$newRevision;if(self::hash($value)!==$revision)throw new \DomainException('asaas_pix_replacement_stale');
   if($this->proof($old,'pix_replaced_by')||$this->proof($new,'pix_series'))throw new \DomainException('asaas_pix_replacement_conflict');
   // Both mutations share the existing financial mutex and complete owned transaction.
   $this->series->stopInOwnedTransaction($issuer,$actor,$old,$value['old_binding_hash'],true);
   $id=$this->series->confirmInOwnedTransaction($issuer,$actor,$new,$schedule,$holidays,$newRevision,true);
   $bound=$this->proof($new,'pix_series');foreach(['client_id','currency','frequency'] as $key)if($value['old_binding'][$key]!==$bound['value'][$key])throw new \DomainException('asaas_pix_replacement_conflict');
   if($value['old_binding']['account_row']===$bound['value']['account_row']&&$value['old_binding']['authorization_id']===$bound['value']['authorization_id'])throw new \DomainException('asaas_pix_replacement_conflict');
   if($value['old_worker_revision']!==null){$next=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_future_workers SET enabled=0,revision=?,lease_key=NULL,lease_until=NULL,last_code=?,updated_at=? WHERE root_origin=? AND issuer_id=? AND revision=?',[$next,'mandate_replaced',gmdate('Y-m-d H:i:s'),$old,$issuer,$value['old_worker_revision']]);$worker=$this->rows('SELECT enabled,revision FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=?',[$old,$issuer])[0]??null;if(!$worker||(int)$worker['enabled']!==0||$worker['revision']!==$next)throw new \RuntimeException('issuer_asaas_pix_worker_unconfirmed');}
   foreach([[$old,'pix_replaced_by',$value['old_binding']['account_revision']],[$new,'pix_replaces',$bound['account_revision']]] as [$root,$operation,$accountRevision])\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$root,$accountRevision,$actor,$operation,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$revision,'replaced',gmdate('Y-m-d H:i:s')]);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_pix_replaced',$issuer,$new,json_encode(['previous_origin'=>$old,'evidence_hash'=>$revision],JSON_THROW_ON_ERROR),gmdate('Y-m-d H:i:s')]);
   if(($this->proof($new,'pix_replaces')['evidence_hash']??null)!==$revision||($this->proof($old,'pix_replaced_by')['evidence_hash']??null)!==$revision)throw new \RuntimeException('issuer_asaas_pix_replacement_unconfirmed');return $id;
  });
 }
}
