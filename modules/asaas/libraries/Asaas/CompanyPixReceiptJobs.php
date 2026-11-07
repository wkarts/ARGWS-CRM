<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixReceiptJobSchema.php';
require_once __DIR__.'/CompanyPixInitialReceipt.php';
/** Financial posting of verified initial payments only, separately opted in from observation. */
final class CompanyPixReceiptJobs
{
 private CompanyService $service;
 public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 public function tables():array{return array_merge($this->service->tablesForRun(),array_keys(CompanyPixReceiptJobSchema::definitions()));}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
 private function ready():void{$this->service->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'1.9.6','<'))throw new \DomainException('asaas_module_196_required');CompanyPixReceiptJobSchema::ensure($this->db,$this->p,false);}
 private function account(int $issuer,int $actor,int $account):array
 {
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_pix_receipt_worker_denied');
  $this->service->authorize($issuer,$actor,'settle');$this->service->authorize($issuer,$actor,'issue');
  $a=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;
  if(!$a||(int)$a['enabled']!==1||$a['verified_at']===null||$a['token_hash']===null)throw new \DomainException('asaas_pix_receipt_worker_denied');return $a;
 }
 public function configure(int $issuer,int $actor,int $account,string $accountRevision,string $revision,bool $enabled):void
 {
  $this->ready();$this->run(function()use($issuer,$actor,$account,$accountRevision,$revision,$enabled){$this->service->authorize($issuer,$actor,'configure');$a=$this->account($issuer,$actor,$account);if($a['revision']!==$accountRevision)throw new \DomainException('asaas_company_stale');
   $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_receipt_workers WHERE account_row=? FOR UPDATE',[$account])[0]??null;if(($old['revision']??str_repeat('0',64))!==$revision)throw new \DomainException('asaas_company_stale');
   $cursor=(int)($this->rows('SELECT MAX(id) n FROM '.$this->p.'asaas_company_events WHERE account_row=?',[$account])[0]['n']??0);$next=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');
   if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_receipt_workers SET actor_id=?,account_revision=?,enabled=?,revision=?,cursor_event=?,updated_at=? WHERE account_row=? AND issuer_id=?',[$actor,$accountRevision,$enabled?1:0,$next,$cursor,$now,$account,$issuer]);
   else \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_pix_receipt_workers(account_row,issuer_id,actor_id,account_revision,enabled,revision,cursor_event,updated_at) VALUES(?,?,?,?,?,?,?,?)',[$account,$issuer,$actor,$accountRevision,$enabled?1:0,$next,$cursor,$now]);
   if(($this->rows('SELECT revision FROM '.$this->p.'asaas_company_pix_receipt_workers WHERE account_row=? AND issuer_id=?',[$account,$issuer])[0]['revision']??null)!==$next)throw new \RuntimeException('issuer_asaas_pix_worker_unconfirmed');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_pix_initial_worker_changed',$issuer,$account,json_encode(['enabled'=>$enabled,'revision'=>$next,'cutoff_event'=>$cursor],JSON_THROW_ON_ERROR),$now]);
  });
 }
 public function page(int $issuer,int $actor):array
 {
  $this->ready();return $this->run(function()use($issuer,$actor){$this->service->authorize($issuer,$actor,'configuration');$this->service->authorize($issuer,$actor,'query');return ['workers'=>$this->rows('SELECT account_row,enabled,revision,actor_id FROM '.$this->p.'asaas_company_pix_receipt_workers WHERE issuer_id=?',[$issuer]),'jobs'=>$this->rows('SELECT event_id,account_row,origin_id,state,attempts,last_code FROM '.$this->p.'asaas_company_pix_receipt_jobs WHERE issuer_id=? ORDER BY event_id DESC LIMIT 100',[$issuer])];});
 }
 /** Called again inside the native writer transaction, including after its insert. */
 public function assertLease(int $issuer,int $actor,int $event,string $lease,?int $origin=null):array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->ready();
  $j=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_receipt_jobs WHERE event_id=? AND issuer_id=? FOR UPDATE',[$event,$issuer])[0]??null;
  if(!$j||(int)$j['actor_id']!==$actor||$j['state']!=='processing'||$j['lease_key']!==$lease||$j['lease_until']<=gmdate('Y-m-d H:i:s')||$origin!==null&&(int)$j['origin_id']!==$origin)throw new \DomainException('asaas_pix_receipt_lease_lost');
  $a=$this->account($issuer,$actor,(int)$j['account_row']);$w=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_receipt_workers WHERE account_row=? AND issuer_id=? FOR UPDATE',[$j['account_row'],$issuer])[0]??null;
  $e=$this->rows('SELECT * FROM '.$this->p.'asaas_company_events WHERE id=? AND account_row=? FOR UPDATE',[$event,$j['account_row']])[0]??null;
  if(!$w||(int)$w['enabled']!==1||(int)$w['actor_id']!==$actor||$w['revision']!==$j['worker_revision']||$w['account_revision']!==$a['revision']||$j['account_revision']!==$a['revision']||!$e||$e['event_type']!=='PAYMENT_RECEIVED'||$e['state']!=='observed')throw new \DomainException('asaas_pix_receipt_worker_denied');
  return [$j,$a,$e];
 }
 private function enqueue():void
 {
  $this->run(function(){foreach($this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_receipt_workers WHERE enabled=1 ORDER BY updated_at,account_row LIMIT 20 FOR UPDATE') as $w){
   \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_receipt_workers SET updated_at=? WHERE account_row=?',[gmdate('Y-m-d H:i:s'),$w['account_row']]);
   try{$a=$this->account((int)$w['issuer_id'],(int)$w['actor_id'],(int)$w['account_row']);if($a['revision']!==$w['account_revision'])continue;}catch(\DomainException $denied){continue;}
   $events=$this->rows('SELECT * FROM '.$this->p.'asaas_company_events WHERE account_row=? AND id>? ORDER BY id LIMIT 100 FOR UPDATE',[$w['account_row'],$w['cursor_event']]);
   foreach($events as $e){if($e['event_type']==='PAYMENT_RECEIVED'&&$e['state']==='observed'&&!$this->rows('SELECT event_id FROM '.$this->p.'asaas_company_pix_receipt_jobs WHERE event_id=?',[$e['id']])){
    // Ordinary API charges keep their existing queue. Unknown IDs must be resolved canonically.
    $normal=$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE account_id=? AND environment=? AND remote_id=? AND channel<>? LIMIT 1',[$a['account_id'],$a['environment'],$e['remote_id'],'pix_mandate']);
    if(!$normal)\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_pix_receipt_jobs(event_id,issuer_id,account_row,actor_id,account_revision,worker_revision,origin_id,state,attempts,lease_key,lease_until,next_attempt,last_code,updated_at) VALUES(?,?,?,?,?,?,NULL,?,0,NULL,NULL,?,?,?)',[$e['id'],$w['issuer_id'],$w['account_row'],$w['actor_id'],$w['account_revision'],$w['revision'],'queued',gmdate('Y-m-d H:i:s'),'awaiting_canonical_identity',gmdate('Y-m-d H:i:s')]);
   }\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_receipt_workers SET cursor_event=? WHERE account_row=? AND revision=?',[$e['id'],$w['account_row'],$w['revision']]);}
  }});
 }
 public function tick(callable $decrypt,?callable $transport=null,int $limit=5):array
 {
  $this->ready();$this->enqueue();$out=[];$deadline=microtime(true)+45;
  for($i=0;$i<max(1,min(20,$limit))&&microtime(true)<$deadline;$i++){
   $job=$this->run(function(){return $this->rows("SELECT * FROM {$this->p}asaas_company_pix_receipt_jobs WHERE (state IN ('queued','retry') AND next_attempt<=?) OR (state='processing' AND lease_until<=?) ORDER BY event_id LIMIT 1 FOR UPDATE",[gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')])[0]??null;});if(!$job)break;
   $event=(int)$job['event_id'];$issuer=(int)$job['issuer_id'];$actor=(int)$job['actor_id'];$lease=bin2hex(random_bytes(32));
   $claimed=$this->run(function()use($event,$lease){$j=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_receipt_jobs WHERE event_id=? FOR UPDATE',[$event])[0]??null;if(!$j||($j['state']==='processing'&&$j['lease_until']>gmdate('Y-m-d H:i:s'))||!in_array($j['state'],['queued','retry','processing'],true)||$j['next_attempt']>gmdate('Y-m-d H:i:s'))return false;\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_pix_receipt_jobs SET state='processing',attempts=attempts+1,lease_key=?,lease_until=? WHERE event_id=?",[$lease,gmdate('Y-m-d H:i:s',time()+300),$event]);return true;});if(!$claimed)continue;
   $guard=fn()=>$this->assertLease($issuer,$actor,$event,$lease);
   try{
    [$j,$a,$e]=$this->run($guard);if((int)$j['attempts']>5)throw new \DomainException('asaas_pix_receipt_attempts_exhausted');$secret=$decrypt($a['secret_cipher']);if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');$client=new CompanyClient($a['environment'],$secret,$transport);
    $wallet=$client->request('GET','wallets/');$this->run($guard);if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['hasMore']??null)!==false||($wallet['data'][0]['id']??null)!==$a['account_id'])throw new \DomainException('asaas_company_account_unverified');
    $payment=$client->request('GET','payments/'.$e['remote_id']);$this->run($guard);$tx=$payment['pixTransaction']??null;if(($payment['id']??null)!==$e['remote_id']||!is_string($tx)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$tx))throw new \DomainException('asaas_pix_payment_conflict');
    $transaction=$client->request('GET','pix/transactions/'.$tx);$this->run($guard);
    $origin=$this->run(function()use($guard,$issuer,$actor,$a,$payment,$transaction){$guard();$candidates=$this->rows('SELECT o.id FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.issuer_id=? AND m.account_row=? AND o.channel=? AND o.active_slot=1 ORDER BY o.id LIMIT 101 FOR UPDATE',[$issuer,$a['id'],'pix_mandate']);if(count($candidates)>100)throw new \DomainException('asaas_pix_receipt_review_limit');$matches=[];foreach($candidates as $candidate){try{$source=$this->service->pixInitialIdentityInOwned($issuer,$actor,(int)$candidate['id']);CompanyPixAuthorizationContract::initialPayment($source,$payment,$transaction);$matches[]=(int)$candidate['id'];}catch(\DomainException $mismatch){}}if(count($matches)!==1)throw new \DomainException('asaas_pix_payment_conflict');return $matches[0];});
    $this->run(function()use($guard,$origin,$event){$guard();\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_receipt_jobs SET origin_id=? WHERE event_id=?',[$origin,$event]);});
    $proof=$this->service->observePixInitialPayment($issuer,$actor,$origin,$e['remote_id'],$decrypt,$transport);$this->run($guard);
    $receipt=CompanyPixInitialReceipt::forJob($origin,$proof,$event,$lease);$paymentId=(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,$receipt);
    $state='applied';$code='native_receipt_confirmed';
   }catch(\Throwable $error){$state=$error instanceof \DomainException||(int)$job['attempts']>=4?'review_required':'retry';$code=$state==='retry'?'read_or_storage_unconfirmed':'authority_or_identity_review';}
   $this->run(function()use($event,$lease,$state,$code){\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_receipt_jobs SET state=?,last_code=?,lease_key=NULL,lease_until=NULL,next_attempt=?,updated_at=? WHERE event_id=? AND lease_key=?',[$state,$code,gmdate('Y-m-d H:i:s',time()+60),gmdate('Y-m-d H:i:s'),$event,$lease]);});$out[]=['event_id'=>$event,'state'=>$state];
  }return $out;
 }
}
