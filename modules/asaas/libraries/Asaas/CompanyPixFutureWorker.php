<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixFutureWorkerSchema.php';
require_once __DIR__.'/CompanyPixFutureCycle.php';
/** Bounded per-series polling. A prepared binding alone never starts financial automation. */
final class CompanyPixFutureWorker
{
 private CompanyService $service;
 private CompanyPixFutureSchedules $bindings;
 public function __construct(private $db,private string $p,private $clock=null){$this->service=new CompanyService($db,$p);$this->bindings=new CompanyPixFutureSchedules($db,$p);}
 public function tables():array{return array_merge($this->bindings->tables(),array_keys(CompanyPixFutureWorkerSchema::definitions()));}
 private function run(callable $work){return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
 private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function ready():void{$this->service->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'1.9.9','<'))throw new \DomainException('asaas_module_199_required');CompanyPixFutureWorkerSchema::ensure($this->db,$this->p,false);}
 private function authority(int $issuer,int $actor):void
 {
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_pix_future_worker_denied');
  foreach(['configure','issue','settle'] as $action)$this->service->authorize($issuer,$actor,$action);
 }
 public function page(int $issuer,int $actor,int $root):?array
 {
  $this->ready();return $this->run(function()use($issuer,$actor,$root){$this->service->authorize($issuer,$actor,'configuration');$this->service->authorize($issuer,$actor,'query');return $this->rows('SELECT root_origin,actor_id,enabled,revision,binding_hash,last_code,next_attempt FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=?',[$root,$issuer])[0]??null;});
 }
 public function configure(int $issuer,int $actor,int $root,string $bindingHash,string $revision,bool $enabled,bool $confirmed):void
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');$this->ready();
  $this->run(function()use($issuer,$actor,$root,$bindingHash,$revision,$enabled){
   $this->authority($issuer,$actor);$old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=? FOR UPDATE',[$root,$issuer])[0]??null;
   if(($old['revision']??str_repeat('0',64))!==$revision)throw new \DomainException('asaas_company_stale');
   if($enabled){[$proof]=$this->bindings->bindingInOwnedTransaction($issuer,$actor,$root);if($proof['evidence_hash']!==$bindingHash)throw new \DomainException('asaas_pix_schedule_stale');}
   elseif(!$old||$old['binding_hash']!==$bindingHash)throw new \DomainException('asaas_pix_schedule_stale');
   $next=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');
   if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_future_workers SET actor_id=?,binding_hash=?,enabled=?,revision=?,lease_key=NULL,lease_until=NULL,next_attempt=?,last_code=?,updated_at=? WHERE root_origin=? AND issuer_id=?',[$actor,$bindingHash,$enabled?1:0,$next,$now,'policy_changed',$now,$root,$issuer]);
   else \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_pix_future_workers(root_origin,issuer_id,actor_id,binding_hash,enabled,revision,cursor_invoice,lease_key,lease_until,next_attempt,last_code,updated_at) VALUES(?,?,?,?,?,?,0,NULL,NULL,?,?,?)',[$root,$issuer,$actor,$bindingHash,$enabled?1:0,$next,$now,'policy_changed',$now]);
   if(($this->rows('SELECT revision FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=?',[$root,$issuer])[0]['revision']??null)!==$next)throw new \RuntimeException('issuer_asaas_pix_worker_unconfirmed');
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_pix_future_worker_changed',$issuer,$root,json_encode(['enabled'=>$enabled,'revision'=>$next,'binding_hash'=>$bindingHash],JSON_THROW_ON_ERROR),$now]);
  });
 }
 /** Also executed inside the native receipt writer before and after its financial insert. */
 public function assertLease(array $claim):void
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->ready();$this->authority((int)$claim['issuer_id'],(int)$claim['actor_id']);
  $row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=? AND issuer_id=? FOR UPDATE',[$claim['root_origin'],$claim['issuer_id']])[0]??null;
  if(!$row||(int)$row['enabled']!==1||$row['revision']!==$claim['revision']||$row['binding_hash']!==$claim['binding_hash']||(int)$row['actor_id']!==(int)$claim['actor_id']||$row['lease_key']!==$claim['lease_key']||$row['lease_until']<=gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_pix_future_worker_lease_lost');
 }
 private function candidate(array $claim):?int
 {
  $this->assertLease($claim);[$proof,$context]=$this->bindings->bindingInOwnedTransaction((int)$claim['issuer_id'],(int)$claim['actor_id'],(int)$claim['root_origin']);
  if($proof['evidence_hash']!==$claim['binding_hash'])throw new \DomainException('asaas_pix_schedule_stale');$binding=$proof['value'];$ids=[];
  foreach($context[5]['runs'] as $run){$id=(int)$run['canonical_invoice_id'];if($run['state']!=='completed'||$id<=$binding['cutoff_invoice_id'])continue;
   $row=$this->rows('SELECT i.duedate,i.status,o.id origin_id,r.payment_id FROM '.$this->p.'invoices i LEFT JOIN '.$this->p.'asaas_payment_origins o ON o.invoice_id=i.id AND o.issuer_id=i.issuer_id AND o.channel=? AND o.active_slot=1 LEFT JOIN '.$this->p.'asaas_company_receipts r ON r.origin_id=o.id AND r.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=?', ['api',$id,$claim['issuer_id']])[0]??null;
   if(!$row||$row['payment_id']!==null||(int)$row['status']===5)continue;
   if($row['origin_id']===null){$today=$this->clock?($this->clock)():(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$days=(new CompanyPixBankCalendar($binding['holidays']))->businessDays($today,$row['duedate']);if($days<2||$days>10)continue;}
   else{$meta=$this->rows('SELECT result_json FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$row['origin_id'],'pix_future']);if(count($meta)!==1||(json_decode($meta[0]['result_json'],true,32,JSON_THROW_ON_ERROR)['root_origin']??null)!==(int)$claim['root_origin'])continue;}
   $ids[]=$id;
  }
  sort($ids,SORT_NUMERIC);foreach($ids as $id)if($id>(int)$claim['cursor_invoice'])return $id;return $ids[0]??null;
 }
 public function tick(callable $decrypt,?callable $transport=null,int $limit=5):array
 {
  $this->ready();$out=[];$deadline=microtime(true)+45;
  for($n=0;$n<max(1,min(20,$limit))&&microtime(true)<$deadline;$n++){
   $claim=$this->run(function(){ $now=gmdate('Y-m-d H:i:s');$row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_pix_future_workers WHERE enabled=1 AND next_attempt<=? AND (lease_until IS NULL OR lease_until<=?) ORDER BY next_attempt,root_origin LIMIT 1 FOR UPDATE',[$now,$now])[0]??null;if(!$row)return null;$lease=bin2hex(random_bytes(32));$until=gmdate('Y-m-d H:i:s',time()+300);\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_future_workers SET lease_key=?,lease_until=?,next_attempt=? WHERE root_origin=?',[$lease,$until,gmdate('Y-m-d H:i:s',time()+60),$row['root_origin']]);$written=$this->rows('SELECT lease_key FROM '.$this->p.'asaas_company_pix_future_workers WHERE root_origin=?',[$row['root_origin']])[0]??null;if(($written['lease_key']??null)!==$lease)throw new \RuntimeException('issuer_asaas_pix_worker_unconfirmed');return array_replace($row,['lease_key'=>$lease,'lease_until'=>$until]);});
   if(!$claim)break;$invoice=null;$code='idle';
   try{$invoice=$this->run(fn()=>$this->candidate($claim));if($invoice!==null){
    $guard=fn()=>$this->assertLease($claim);$cycle=new CompanyPixFutureCycle($this->db,$this->p,(int)$claim['issuer_id'],(int)$claim['actor_id'],(int)$claim['root_origin'],$invoice,$this->clock,$guard,$this->tables());
    $existing=$this->run(function()use($guard,$invoice,$claim){$guard();return $this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE issuer_id=? AND invoice_id=? AND channel=? AND active_slot=1',[$claim['issuer_id'],$invoice,'api'])[0]??null;});
    if(!$existing)$cycle->dispatch($decrypt,$transport);
    $proof=$cycle->observe($decrypt,$transport);$observed=$this->run(function()use($guard,$proof){$guard();return $this->rows('SELECT state FROM '.$this->p.'asaas_company_proofs WHERE id=?',[$proof])[0]??null;});
    if(($observed['state']??null)==='received_observed'){$cycle->settle($proof);$code='native_receipt_confirmed';}else $code='payment_observed';
   }}catch(\DomainException $error){$code='authority_or_identity_review';}catch(\Throwable $error){$code='result_unconfirmed';}
   $this->run(function()use($claim,$invoice,$code){\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_pix_future_workers SET cursor_invoice=?,lease_key=NULL,lease_until=NULL,next_attempt=?,last_code=?,updated_at=? WHERE root_origin=? AND revision=? AND lease_key=?',[$invoice??$claim['cursor_invoice'],gmdate('Y-m-d H:i:s',time()+60),$code,gmdate('Y-m-d H:i:s'),$claim['root_origin'],$claim['revision'],$claim['lease_key']]);});
   $out[]=['root_origin'=>(int)$claim['root_origin'],'invoice_id'=>$invoice,'state'=>$code];
  }return $out;
 }
}
