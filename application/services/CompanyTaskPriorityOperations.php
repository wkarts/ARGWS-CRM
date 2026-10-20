<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskPriority.php';
/** Confirm the complete task/priority/follower frame after the last native/API write. */
trait CompanyTaskPriorityOperations
{
 private array $taskPriorityFrames=[];
 private array $taskPriorityAuditFrames=[];
 public function priorityDefaults($issuer,$actor):array
 {
  $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'create');return $this->transaction(function()use($issuer,$actor){$this->authorize($issuer,$actor,'create');if(!CompanyTaskPrioritySchema::tables($this->db,$this->p))return ['available'=>false,'default_task_priority'=>'2','new_task_auto_follower_current_member'=>'0'];$v=CompanyTaskOperationPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);CompanyTaskOperationPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$v);$this->authorize($issuer,$actor,'create');return ['available'=>true,'default_task_priority'=>$v[CompanyTaskOperationPreferences::PRIORITY],'new_task_auto_follower_current_member'=>$this->taskKind()==='project'?$v[CompanyTaskOperationPreferences::FOLLOW]:'0'];});
 }

 private function sealCreationRequest(array &$request):?string
 {
  $row=CompanyTaskPriority::raw($this->db,$this->p,$this->taskKind(),$request['issuer_id'],$request['result_id']);if($row===null)return null;
  $birth=CompanyTaskPriority::birthHash($this->db,$this->p,$this->taskKind(),$request['issuer_id'],$request['result_id']);
  $payload=['kind'=>$this->taskKind(),'task_id'=>$request['result_id'],'priority'=>CompanyTaskPriority::value($row['priority']),'contract'=>'own_task_priority_creation_v1','request_key'=>$request['request_key'],'issuer_id'=>$request['issuer_id'],'actor_id'=>$request['actor_id'],'operation'=>$request['operation'],'request_created_at'=>$request['created_at'],'request_input_hash'=>$request['input_hash'],'birth_hash'=>$birth];
  $json=json_encode(['task_priority_v1'=>$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$request['input_hash']=hash('sha256',$json);return $json;
 }
 private function sealedCreationReplay(array $receipt,string $incoming):bool
 {
  $matches=[];foreach(CompanyTaskPriority::auditMarkers($this->db,$this->p,$this->taskKind(),(int)$receipt['issuer_id'],(int)$receipt['result_id'])as$audit){
   if($audit['event']!=='company_'.$receipt['operation']||$audit['actor_id']!==(string)$receipt['actor_id'])continue;
   try{$decoded=json_decode($audit['metadata_json'],true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){continue;}$v=$decoded['task_priority_v1']??null;
   if(!is_array($v)||($v['request_key']??null)!==$receipt['request_key'])continue;
   if(array_keys($decoded)!==['task_priority_v1']||array_keys($v)!==['kind','task_id','priority','contract','request_key','issuer_id','actor_id','operation','request_created_at','request_input_hash','birth_hash'])return false;
   if($v['kind']!==$this->taskKind()||$v['task_id']!==(int)$receipt['result_id']||$v['issuer_id']!==(int)$receipt['issuer_id']||$v['actor_id']!==(int)$receipt['actor_id']||$v['operation']!==$receipt['operation']||$v['request_created_at']!==$receipt['created_at']||$v['contract']!=='own_task_priority_creation_v1'||!in_array($v['priority'],[1,2,3,4],true)||!is_string($v['birth_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$v['birth_hash'])||!is_string($v['request_input_hash'])||!hash_equals($incoming,$v['request_input_hash'])||!hash_equals((string)$receipt['input_hash'],hash('sha256',$audit['metadata_json'])))return false;
   $matches[]=$audit;
  }return count($matches)===1;
 }
 protected function priorityAuditMetadata(int $issuer,int $id):?string
 {
  $row=CompanyTaskPriority::raw($this->db,$this->p,$this->taskKind(),$issuer,$id);return$row===null?null:json_encode(['task_priority_v1'=>['kind'=>$this->taskKind(),'task_id'=>$id,'priority'=>CompanyTaskPriority::value($row['priority'])]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
 }
 protected function audit(int $issuer,int $actor,int $id,string $event):void
 {
  $metadata=$this->priorityAuditMetadata($issuer,$id);if($metadata===null){parent::audit($issuer,$actor,$id,$event);return;}$fields=['actor_id'=>$actor,'event'=>$event,'issuer_id'=>$issuer,'subject_id'=>$id,'created_at'=>date('Y-m-d H:i:s'),'metadata_json'=>$metadata];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($fields));$audit=(int)$this->db->insert_id();$expected=['id'=>(string)$audit]+array_map('strval',$fields);$actual=IssuerStorage::query($this->db,'SELECT id,actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])->row_array();if($audit<1||!$actual||array_map('strval',$actual)!==$expected)throw new RuntimeException('issuer_task_priority_unconfirmed');$this->taskPriorityAuditFrames[]=$expected;
 }
 protected function operationReplay(int $issuer,int $actor,int $id,string $operation):void{CompanyTaskPriority::read($this->db,$this->p,$this->taskKind(),$issuer,$id);}
 private function priorityReferences(int $issuer,array $row):void{$row['assignee_id']=IssuerPolicy::id($row['assignee_id']);if(isset($row['project_id']))$row['project_id']=IssuerPolicy::id($row['project_id']);$this->references($issuer,$row);}
 protected function operationFrame(int $issuer,int $actor,int $id,string $operation):?array{$frame=CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$issuer,$id,false);$this->priorityReferences($issuer,$frame['native']);if($this->taskKind()==='project')$this->assertTaskUnclaimed($issuer,$actor,$id);else $this->assertBillingMutable($issuer,$actor,$id);return $frame;}
 protected function guardedWork(callable $work):callable
 {
  $guarded=parent::guardedWork($work);return function()use($guarded){$this->taskPriorityFrames=[];$this->taskPriorityAuditFrames=[];try{$result=$guarded();foreach($this->taskPriorityFrames as$expected){if(CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$expected['issuer'],$expected['id'],false)!==$expected['frame'])throw new RuntimeException('issuer_task_priority_unconfirmed');if($expected['preferences']!==null)CompanyTaskOperationPreferences::confirmInOwnedTransaction($this->db,$this->p,$expected['issuer'],$expected['preferences']);$this->priorityReferences($expected['issuer'],$expected['frame']['native']);$this->authorize($expected['issuer'],$expected['actor'],'create');}foreach($this->taskPriorityAuditFrames as$expectedAudit){$actual=IssuerStorage::query($this->db,'SELECT id,actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$expectedAudit['id']])->row_array();if(!$actual||array_map('strval',$actual)!==$expectedAudit)throw new RuntimeException('issuer_task_priority_unconfirmed');}return$result;}finally{$this->taskPriorityFrames=[];$this->taskPriorityAuditFrames=[];}};
 }
 private function createPriorityInOwned(int $issuer,int $actor,int $id,array $data,string $now):void
 {
  $enabled=$this->db->table_exists($this->p.CompanyTaskPriority::table($this->taskKind()));$explicit=array_key_exists('priority',$data);$preferences=null;$value=2;
  if(!$enabled&&$explicit)throw new DomainException('company_task_priority_unavailable');
  if($enabled){if(!$explicit||$this->taskKind()==='project')$preferences=CompanyTaskOperationPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);$value=$explicit?$data['priority']:CompanyTaskPriority::value($preferences[CompanyTaskOperationPreferences::PRIORITY]);CompanyTaskPriority::save($this->db,$this->p,$this->taskKind(),$issuer,$id,$value,true);if($this->taskKind()==='project'&&$preferences[CompanyTaskOperationPreferences::FOLLOW]==='1'){require_once __DIR__.'/CompanyProjectExtras.php';CompanyProjectExtras::followCreatedTaskInOwned($this->db,$this->p,$issuer,$actor,$id);}if($preferences!==null)CompanyTaskOperationPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$preferences);}
  $frame=CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$issuer,$id,false);$native=['id'=>$id,'issuer_id'=>$issuer];if($this->taskKind()==='project')$native['project_id']=$data['project_id'];foreach(['assignee_id','name','description','status','due_date','checklist_json']as$f)$native[$f]=$data[$f];$native+=['revision'=>1,'created_at'=>$now,'updated_at'=>$now];$native=array_map('strval',$native);$actual=$frame['native'];ksort($native);ksort($actual);if($native!==$actual)throw new RuntimeException('issuer_task_priority_unconfirmed');
  if($enabled&&$frame['priority']!==['issuer_id'=>(string)$issuer,'task_id'=>(string)$id,'priority'=>(string)$value])throw new RuntimeException('issuer_task_priority_unconfirmed');$this->taskPriorityFrames[]=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'frame'=>$frame,'preferences'=>$preferences];
 }
}
