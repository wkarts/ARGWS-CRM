<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once __DIR__.'/CompanyPixInboxSchema.php';
require_once __DIR__.'/PixEvent.php';
/** Observational queue only. Neither authenticated events nor canonical ACTIVE/DONE authorize money movement. */
final class CompanyPixInbox
{
 private CompanyService $service;
 public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_pix_prefix_invalid');$this->service=new CompanyService($db,$p);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function ready():void{$this->service->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'1.9.0','<'))throw new \DomainException('asaas_module_190_required');CompanyPixInboxSchema::ensure($this->db,$this->p,false);}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,array_merge(CompanyPreferences::tables(),array_keys(CompanyPixInboxSchema::definitions())),$work);}
 private function insert(string $table,array $row):void{\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));}
 private function account(int $issuer,int $account):array{$r=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;if(!$r)throw new \DomainException('asaas_company_access_denied');return $r;}
 private function authorize(int $issuer,int $actor):void
 {
  $this->service->authorize($issuer,$actor,'configuration');$this->service->authorize($issuer,$actor,'query');
  if(!(new \IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'asaas_reports','view'))throw new \DomainException('asaas_report_access_denied');
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
 }
 public function receive(string $public,array $headers,string $body):array
 {
  $this->ready();if(!preg_match('/^[a-f0-9]{32}$/D',$public))return ['status'=>404];
  return $this->run(function()use($public,$headers,$body){
   $a=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE webhook_public_id=? FOR UPDATE',[$public])[0]??null;
   if(!$a||(int)$a['enabled']!==1||$a['token_hash']===null||!$this->rows('SELECT id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1',[$a['issuer_id']]))return ['status'=>401];
   $verified=WebhookSecurity::validateDigest($a['token_hash'],$headers,$body);if(!$verified['ok'])return ['status'=>$verified['status']];$pix=$verified['payload']['pix']??null;if(!$pix)return ['status'=>422];
   $digest=hash('sha256',$body);$old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_events WHERE account_row=? AND event_id=? FOR UPDATE',[$a['id'],$verified['event_id']])[0]??null;
   if($old){if($old['payload_hash']===$digest)return ['status'=>$old['state']==='conflict'?409:200,'duplicate'=>true];\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_events SET state='conflict' WHERE id=?",[$old['id']]);\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_event_jobs SET state='conflict',lease_key=NULL,lease_until=NULL,last_code='event_conflict' WHERE event_id=?",[$old['id']]);return ['status'=>409];}
   $now=gmdate('Y-m-d H:i:s');$this->insert('asaas_company_pix_events',['issuer_id'=>$a['issuer_id'],'account_row'=>$a['id'],'environment'=>$a['environment'],'wallet'=>$a['account_id'],'event_id'=>$verified['event_id'],'event_type'=>$verified['event'],'resource_kind'=>$pix['kind'],'remote_id'=>$pix['remote_id'],'authorization_id'=>$pix['authorization_id'],'payment_id'=>$pix['payment_id'],'remote_status'=>$pix['status'],'payload_hash'=>$digest,'state'=>'received','created_at'=>$now]);$id=(int)$this->db->insert_id();
   $this->insert('asaas_company_pix_event_jobs',['event_id'=>$id,'issuer_id'=>$a['issuer_id'],'account_row'=>$a['id'],'state'=>$pix['kind']==='eligibility'?'informational':'queued','attempts'=>0,'lease_key'=>null,'lease_until'=>null,'next_attempt'=>$now,'last_code'=>'no_financial_effect','updated_at'=>$now]);
   $saved=$this->rows('SELECT payload_hash FROM '.$this->p.'asaas_company_pix_events WHERE id=? AND account_row=?',[$id,$a['id']])[0]??[];if(($saved['payload_hash']??null)!==$digest||!$this->rows('SELECT event_id FROM '.$this->p.'asaas_company_pix_event_jobs WHERE event_id=?',[$id]))throw new \RuntimeException('issuer_asaas_pix_inbox_unconfirmed');
   return ['status'=>200,'duplicate'=>false];
  });
 }
 private function source(int $issuer,int $actor,int $event,string $revision):array
 {
  $this->authorize($issuer,$actor);$r=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_events WHERE id=? AND issuer_id=? FOR UPDATE',[$event,$issuer])[0]??null;if(!$r)throw new \DomainException('asaas_company_access_denied');
  $a=$this->account($issuer,(int)$r['account_row']);$j=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_event_jobs WHERE event_id=? FOR UPDATE',[$event])[0]??null;
  if(!$j||(int)$j['issuer_id']!==$issuer||(int)$j['account_row']!==(int)$a['id']||$r['wallet']!==$a['account_id']||$r['environment']!==$a['environment'])throw new \DomainException('asaas_pix_scope_conflict');
  if($r['state']==='conflict')throw new \DomainException('asaas_pix_event_conflict');
  if((int)$a['enabled']!==1||$a['verified_at']===null||!hash_equals($a['revision'],$revision))throw new \DomainException('asaas_company_stale');
  return [$r,$a,$j];
 }
 public function process($issuer,$actor,$event,string $revision,callable $decrypt,?callable $transport=null,?string $workerRevision=null):array
 {
  $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$event=\IssuerPolicy::id($event);$this->ready();
  $read=function()use($issuer,$actor,$event,$revision,$workerRevision){$source=$this->source($issuer,$actor,$event,$revision);if($workerRevision!==null){$w=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_workers WHERE account_row=? AND issuer_id=? FOR UPDATE',[$source[1]['id'],$issuer])[0]??null;if(!$w||(int)$w['enabled']!==1||(int)$w['actor_id']!==$actor||$w['revision']!==$workerRevision)throw new \DomainException('asaas_company_access_denied');}return $source;};
  $claim=$this->run(function()use($read,$event){[$r,$a,$j]=$read();$now=gmdate('Y-m-d H:i:s');if(in_array($j['state'],['observed','informational','review_required','conflict'],true))return ['state'=>$j['state']];if($j['state']==='processing'&&$j['lease_until']>$now||$j['next_attempt']>$now)return ['state'=>'busy'];if((int)$j['attempts']>=5){\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_event_jobs SET state='review_required',lease_key=NULL,lease_until=NULL WHERE event_id=?",[$event]);return ['state'=>'review_required'];}$lease=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_event_jobs SET state='processing',attempts=attempts+1,lease_key=?,lease_until=?,updated_at=? WHERE event_id=?",[$lease,gmdate('Y-m-d H:i:s',time()+120),$now,$event]);$written=$this->rows('SELECT lease_key FROM '.$this->p.'asaas_company_pix_event_jobs WHERE event_id=?',[$event])[0]??[];if(($written['lease_key']??null)!==$lease)throw new \RuntimeException('issuer_asaas_pix_claim_unconfirmed');return ['event'=>$r,'account'=>$a,'lease'=>$lease];});
  if(!isset($claim['lease']))return $claim;
  $guard=function()use($read,$claim){$s=$read();if($s[2]['state']!=='processing'||$s[2]['lease_key']!==$claim['lease']||$s[2]['lease_until']<gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_pix_lease_lost');return $s;};
  try{
   $secret=$decrypt($claim['account']['secret_cipher']);if(!is_string($secret))throw new \RuntimeException('asaas_company_credentials_required');$client=new CompanyClient($claim['account']['environment'],$secret,$transport);
   $this->run($guard);$wallet=$client->request('GET','wallets/');if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['hasMore']??false)!==false||($wallet['data'][0]['id']??null)!==$claim['account']['account_id'])throw new \DomainException('asaas_company_account_unverified');$this->run($guard);
   $e=$claim['event'];$path=$e['resource_kind']==='authorization'?'pix/automatic/authorizations/':'pix/automatic/paymentInstructions/';$response=$client->request('GET',$path.$e['remote_id']);$safe=PixEvent::canonical($e,$response);
   $this->run(function()use($guard,$safe,$event,$issuer,$actor,$claim){$guard();$json=json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);$this->insert('asaas_company_pix_observations',['event_id'=>$event,'issuer_id'=>$issuer,'account_row'=>$claim['account']['id'],'actor_id'=>$actor,'account_revision'=>$claim['account']['revision'],'result_json'=>$json,'evidence_hash'=>$hash,'created_at'=>gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();if(($this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_pix_observations WHERE id=?',[$id])[0]['evidence_hash']??null)!==$hash)throw new \RuntimeException('issuer_asaas_pix_observation_unconfirmed');\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_event_jobs SET state='observed',lease_key=NULL,lease_until=NULL,last_code='canonical_observation_only',updated_at=? WHERE event_id=?",[gmdate('Y-m-d H:i:s'),$event]);});
   return ['state'=>'observed'];
  }catch(\Throwable $error){
   $review=$error instanceof \DomainException;$state=$this->run(function()use($event,$issuer,$claim,$review){$j=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_event_jobs WHERE event_id=? AND issuer_id=? FOR UPDATE',[$event,$issuer])[0]??null;if(!$j||$j['lease_key']!==$claim['lease'])return $j['state']??'review_required';$state=$review||(int)$j['attempts']>=5?'review_required':'retry';\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_event_jobs SET state=?,lease_key=NULL,lease_until=NULL,next_attempt=?,last_code=?,updated_at=? WHERE event_id=?',[$state,gmdate('Y-m-d H:i:s',time()+60),$review?'scope_or_identity_review':'read_unconfirmed',gmdate('Y-m-d H:i:s'),$event]);return $state;});
   return ['state'=>$state];
  }
 }
 public function configure($issuer,$actor,$account,string $accountRevision,string $workerRevision,bool $enabled):void
 {
  $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);$this->ready();
  $this->run(function()use($issuer,$actor,$account,$accountRevision,$workerRevision,$enabled){$this->authorize($issuer,$actor);$this->service->authorize($issuer,$actor,'configure');$a=$this->account($issuer,$account);if($a['revision']!==$accountRevision||(int)$a['enabled']!==1||$a['verified_at']===null)throw new \DomainException('asaas_company_stale');$old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_workers WHERE account_row=? FOR UPDATE',[$account])[0]??null;if(($old['revision']??str_repeat('0',64))!==$workerRevision)throw new \DomainException('asaas_company_stale');$now=gmdate('Y-m-d H:i:s');$next=bin2hex(random_bytes(32));if($old){\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_workers SET actor_id=?,enabled=?,revision=?,next_check=?,updated_at=? WHERE account_row=? AND issuer_id=?',[$actor,$enabled?1:0,$next,$now,$now,$account,$issuer]);}else{$this->insert('asaas_company_pix_workers',['account_row'=>$account,'issuer_id'=>$issuer,'actor_id'=>$actor,'enabled'=>$enabled?1:0,'revision'=>$next,'next_check'=>$now,'updated_at'=>$now]);}$saved=$this->rows('SELECT revision FROM '.$this->p.'asaas_company_pix_workers WHERE account_row=? AND issuer_id=?',[$account,$issuer])[0]??[];if(($saved['revision']??null)!==$next)throw new \RuntimeException('issuer_asaas_pix_worker_unconfirmed');$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$account,'event'=>'company_asaas_pix_observation_worker_changed','created_at'=>$now]);});
 }
 public function page(int $issuer,int $actor):array
 {
  $this->ready();return $this->run(function()use($issuer,$actor){$this->authorize($issuer,$actor);return ['workers'=>$this->rows('SELECT account_row,actor_id,enabled,revision FROM '.$this->p.'asaas_company_pix_workers WHERE issuer_id=?',[$issuer]),'events'=>$this->rows('SELECT e.id,e.account_row,e.environment,e.event_type,e.remote_id,e.resource_kind,e.created_at,j.state,j.attempts,j.last_code FROM '.$this->p.'asaas_company_pix_events e JOIN '.$this->p.'asaas_company_pix_event_jobs j ON j.event_id=e.id AND j.issuer_id=e.issuer_id AND j.account_row=e.account_row WHERE e.issuer_id=? ORDER BY e.id DESC LIMIT 100',[$issuer])];});
 }
 public function tick(callable $decrypt,?callable $transport=null,int $limit=5):array
 {
  $this->ready();$now=gmdate('Y-m-d H:i:s');$workers=$this->rows('SELECT account_row,issuer_id FROM '.$this->p.'asaas_company_pix_workers WHERE enabled=1 AND next_check<=? ORDER BY next_check,account_row LIMIT '.max(1,min(20,$limit)),[$now]);$out=[];$deadline=microtime(true)+45;
  foreach($workers as $candidate){if(microtime(true)>=$deadline)break;
   try{$work=$this->run(function()use($candidate){$w=$this->rows('SELECT w.*,a.revision account_revision FROM '.$this->p.'asaas_company_pix_workers w JOIN '.$this->p.'asaas_company_accounts a ON a.id=w.account_row AND a.issuer_id=w.issuer_id WHERE w.account_row=? AND w.issuer_id=? FOR UPDATE',[$candidate['account_row'],$candidate['issuer_id']])[0]??null;if(!$w||(int)$w['enabled']!==1||$w['next_check']>gmdate('Y-m-d H:i:s'))return null;\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_workers SET next_check=? WHERE account_row=?',[gmdate('Y-m-d H:i:s',time()+60),$w['account_row']]);$w['event']=$this->rows("SELECT event_id FROM {$this->p}asaas_company_pix_event_jobs WHERE account_row=? AND issuer_id=? AND (state IN ('queued','retry') OR (state='processing' AND lease_until<=?)) AND next_attempt<=? ORDER BY next_attempt,event_id LIMIT 1",[$w['account_row'],$w['issuer_id'],gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')])[0]['event_id']??null;return $w;});if($work&&$work['event'])$out[]=$this->process((int)$work['issuer_id'],(int)$work['actor_id'],(int)$work['event'],$work['account_revision'],$decrypt,$transport,$work['revision']);}
   catch(\Throwable $ignored){$out[]=['state'=>'review_required'];}
  }
  return $out;
 }
}
