<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsStorage.php';
require_once __DIR__.'/NativeCompanyBlankTimerSchema.php';
require_once __DIR__.'/NativeCompanyTimerPolicy.php';
require_once __DIR__.'/NativeCompanyTaskTimerCreationProof.php';
require_once __DIR__.'/NativeCompanyTaskTimerDetails.php';

/** Explicit native blank-timer operations; historical task APIs retain their single-timer contract. */
final class NativeCompanyBlankTimers extends CompanyProjectsStorage
{
    use NativeCompanyTaskTimerCreationProof;
    protected string $feature='tasks';
    private array $nativeImportTables=[];
    private bool $confirmingReplay=false;
    private array $nativeStoppedTargets=[];
    protected function authorize(int $issuer,int $actor,string $cap):void
    { $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'tasks','view')||!$acl->can($issuer,$actor,'tasks',$cap))throw new DomainException('native_company_task_denied'); }
    protected function receiptResourceTable(string $operation):string{return 'company_blank_task_timers';}
    protected function audit(int $issuer,int $actor,int $id,string $event):void
    {
        $expected=['actor_id'=>(string)$actor,'event'=>$event,'issuer_id'=>(string)$issuer,'subject_id'=>(string)$id,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>json_encode(['operation_nonce'=>bin2hex(random_bytes(16)),'stopped_targets'=>$this->nativeStoppedTargets],JSON_THROW_ON_ERROR)];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($expected));$audit=(int)$this->db->insert_id();
        $actual=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])[0]??[];
        if($audit<1||array_map('strval',$actual)!==$expected)throw new RuntimeException('issuer_native_timer_audit_unconfirmed');
    }

    protected function transaction(callable $work)
    {
        NativeCompanyBlankTimerSchema::assertValid($this->db,$this->p);CompanyProjectsSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge(NativeCompanyTimerPolicy::tables(),['issuers_audit','company_project_requests','company_blank_task_timers','company_task_timers','company_standalone_timers','taskstimers'],$this->nativeImportTables,(new NativeCompanyTaskTimerDetails($this->db,$this->p))->tables()))),$work);
    }
    private function row(int $issuer,int $actor,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_blank_task_timers WHERE issuer_id=? AND actor_id=? AND id=? FOR UPDATE',[$issuer,$actor,$id])[0]??null;
        if(!$row)throw new DomainException('native_company_task_denied');return $row;
    }
    protected function operationFrame(int $issuer,int $actor,int $id,string $operation):?array
    {
        $row=$this->row($issuer,$actor,$id);$frame=['source'=>$row,'details'=>(new NativeCompanyTaskTimerDetails($this->db,$this->p))->ownInOwned($issuer,$actor,'company_blank_task_timer',$id)];if(!$this->confirmingReplay&&$operation==='native_blank_started')$frame['start_rate']=(new NativeCompanyStaffRates($this->db,$this->p))->forOwnTimerInOwned($issuer,$actor);
        if($row['state']==='assigned'){
            $table=match($row['target_kind']){'company_task'=>'company_task_timers','company_standalone_task'=>'company_standalone_timers',default=>throw new RuntimeException('issuer_native_timer_write_unconfirmed')};
            $target=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND actor_id=? AND task_id=? AND id=? FOR UPDATE',[$issuer,$actor,$row['target_id'],$row['target_timer_id']])[0]??null;
            $zone=new DateTimeZone('UTC');$start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['started_at'],$zone);$stop=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$row['closed_at'],$zone);
            if(!$target||!$start||!$stop||$stop<$start||$target['stopped_at']===null||(int)$target['seconds']!==$stop->getTimestamp()-$start->getTimestamp())throw new RuntimeException('issuer_native_timer_write_unconfirmed');$frame['target']=$target;$frame['target_details']=(new NativeCompanyTaskTimerDetails($this->db,$this->p))->ownInOwned($issuer,$actor,$row['target_kind']==='company_task'?'company_task_timer':'company_standalone_timer',(int)$row['target_timer_id']);$writer=$row['target_kind']==='company_task'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);if(!$this->confirmingReplay)$frame['target_authority']=$writer->nativeBlankTimerTargetInOwned($issuer,$actor,(int)$row['target_id']);
        }
        if(!$this->confirmingReplay&&$this->nativeStoppedTargets){$frame['stopped']=[];foreach($this->nativeStoppedTargets as[$kind,$task]){$writer=$kind==='project'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$frame['stopped'][]=$writer->nativeBlankTimerTargetInOwned($issuer,$actor,$task);$table=$kind==='project'?'company_task_timers':'company_standalone_timers';$source=$kind==='project'?'company_task_timer':'company_standalone_timer';$timers=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$task]);$frame['stopped'][]=$timers;foreach($timers as$t)if((int)$t['actor_id']===$actor)$frame['stopped'][]=(new NativeCompanyTaskTimerDetails($this->db,$this->p))->ownInOwned($issuer,$actor,$source,(int)$t['id']);}}
        return $frame;
    }
    protected function operationReplay(int $issuer,int $actor,int $id,string $operation):void{$this->confirmingReplay=true;try{$this->operationFrame($issuer,$actor,$id,$operation);}finally{$this->confirmingReplay=false;}}
    public static function policyHash(array $policy):string{return hash('sha256',json_encode($policy,JSON_THROW_ON_ERROR));}
    public function start($issuer,$actor,string $expectedPolicy,string $key,string$note=''):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$note=self::text($note,4000,false);if(!preg_match('/^[a-f0-9]{64}$/D',$expectedPolicy))throw new DomainException('native_timer_policy_invalid');
        require_once __DIR__.'/CompanyTasks.php';require_once __DIR__.'/CompanyStandaloneTasks.php';$this->nativeImportTables=array_values(array_unique(array_merge((new CompanyTasks($this->db,$this->p))->nativeBlankTimerTables(),(new CompanyStandaloneTasks($this->db,$this->p))->nativeBlankTimerTables())));$this->nativeStoppedTargets=[];
        try{return $this->operation($issuer,$actor,'edit',$key,'native_blank_started',$note===''?[$expectedPolicy]:[$expectedPolicy,$note],function()use($issuer,$actor,$expectedPolicy,$note):int{
            $policy=(new NativeCompanyTimerPolicy($this->db,$this->p))->readInOwned($issuer,$actor);
            if(!hash_equals($expectedPolicy,self::policyHash($policy)))throw new DomainException('native_timer_policy_changed');
            // At most one unassigned timer per actor. Never stop or disclose another company's running timer.
            if($this->rows('SELECT id FROM '.$this->p.'company_blank_task_timers WHERE actor_id=? AND state=? FOR UPDATE',[$actor,'active']))throw new DomainException('native_timer_running');
            $owned=[];foreach(['company_task_timers'=>'project','company_standalone_timers'=>'standalone']as$table=>$kind)foreach($this->rows('SELECT * FROM '.$this->p.$table.' WHERE actor_id=? AND stopped_at IS NULL ORDER BY id LIMIT 501 FOR UPDATE',[$actor])as$row){if((int)$row['issuer_id']!==$issuer)throw new DomainException('native_timer_running');$owned[]=[$kind,$row];}if(count($owned)>500)throw new DomainException('company_project_limit');
            $legacy=$this->rows('SELECT id,task_id FROM '.$this->p.'taskstimers WHERE staff_id=? AND end_time IS NULL FOR UPDATE',[$actor]);
            foreach($legacy as $running)if((int)$running['task_id']===0)throw new DomainException('native_timer_running');
            if($legacy&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('native_timer_running');
            if($policy['auto_stop']&&$legacy)throw new DomainException('native_timer_running');
            if($policy['auto_stop'])foreach($owned as[$kind,$timer]){$writer=$kind==='project'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$target=$writer->nativeBlankTimerTargetInOwned($issuer,$actor,(int)$timer['task_id']);$source=$kind==='project'?'company_task_timer':'company_standalone_timer';$detail=(new NativeCompanyTaskTimerDetails($this->db,$this->p))->ownInOwned($issuer,$actor,$source,(int)$timer['id']);$writer->stopNativeTimerInOwned($issuer,$actor,(int)$timer['task_id'],(int)$target['task']['revision'],(int)$timer['id'],$detail['revision'],$note,date('Y-m-d H:i:s'));$this->nativeStoppedTargets[]=[$kind,(int)$timer['task_id']];}
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_blank_task_timers(issuer_id,actor_id,started_at,closed_at,state,revision,target_kind,target_id,target_timer_id,note) VALUES(?,?,?,NULL,?,1,NULL,NULL,NULL,?)',[$issuer,$actor,$now,'active',$note]);$id=(int)$this->db->insert_id();$row=$this->row($issuer,$actor,$id);
            if($id<1||$row['started_at']!==$now||$row['closed_at']!==null||$row['state']!=='active'||(int)$row['revision']!==1||$row['target_kind']!==null||$row['target_id']!==null||$row['target_timer_id']!==null||$row['note']!==$note)throw new RuntimeException('issuer_native_timer_write_unconfirmed');
            $proof=$this->nativeTimerCreationProof($issuer,$actor,'company_blank_task_timer',$id,$row);(new NativeCompanyTaskTimerDetails($this->db,$this->p))->captureNewInOwned($issuer,$actor,'company_blank_task_timer',$id,$proof,$note);
            if((new NativeCompanyTimerPolicy($this->db,$this->p))->readInOwned($issuer,$actor)!==$policy)throw new DomainException('native_timer_policy_changed');return $id;
        });}finally{$this->nativeImportTables=[];$this->nativeStoppedTargets=[];}
    }
    public function assign($issuer,$actor,$id,$revision,string $source,$task,$taskRevision,string $note,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$task=IssuerPolicy::id($task);$taskRevision=self::revision($taskRevision);$note=self::text($note,4000,false);
        require_once __DIR__.'/CompanyTasks.php';require_once __DIR__.'/CompanyStandaloneTasks.php';
        $target=match($source){'company_task'=>new CompanyTasks($this->db,$this->p),'company_standalone_task'=>new CompanyStandaloneTasks($this->db,$this->p),default=>throw new DomainException('native_timer_target_invalid')};
        $this->nativeImportTables=$target->nativeBlankTimerTables();
        try{return $this->operation($issuer,$actor,'edit',$key,'native_blank_assigned',[$id,$revision,$source,$task,$taskRevision,$note],function()use($issuer,$actor,$id,$revision,$source,$task,$taskRevision,$note,$target):int{
            $before=$this->row($issuer,$actor,$id);if((int)$before['revision']!==$revision)throw new DomainException('native_timer_stale');if($before['state']!=='active'||$before['closed_at']!==null)throw new DomainException('native_timer_closed');
            $now=gmdate('Y-m-d H:i:s');$timer=$target->attachNativeBlankTimerInOwnedTransaction($issuer,$actor,$task,$taskRevision,$id,$now,$note);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_blank_task_timers SET state=?,closed_at=?,revision=revision+1,target_kind=?,target_id=?,target_timer_id=?,note=? WHERE issuer_id=? AND actor_id=? AND id=? AND revision=? AND state=?',['assigned',$now,$source,$task,$timer,$note,$issuer,$actor,$id,$revision,'active']);
            $expected=$before;$expected['state']='assigned';$expected['closed_at']=$now;$expected['revision']=$revision+1;$expected['target_kind']=$source;$expected['target_id']=$task;$expected['target_timer_id']=$timer;$expected['note']=$note;
            $normalize=static fn($row)=>array_map(static fn($v)=>$v===null?null:(string)$v,$row);
            if($normalize($this->row($issuer,$actor,$id))!==$normalize($expected))throw new RuntimeException('issuer_native_timer_write_unconfirmed');return $id;
        });}finally{$this->nativeImportTables=[];}
    }
    public function discard($issuer,$actor,$id,$revision,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);
        return $this->operation($issuer,$actor,'edit',$key,'native_blank_discarded',[$id,$revision],function()use($issuer,$actor,$id,$revision):int{
            $row=$this->row($issuer,$actor,$id);if((int)$row['revision']!==$revision)throw new DomainException('native_timer_stale');if($row['state']!=='active'||$row['closed_at']!==null)throw new DomainException('native_timer_closed');
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_blank_task_timers SET state=?,closed_at=?,revision=revision+1 WHERE issuer_id=? AND actor_id=? AND id=? AND revision=? AND state=?',['discarded',$now,$issuer,$actor,$id,$revision,'active']);
            $expected=$row;$expected['state']='discarded';$expected['closed_at']=$now;$expected['revision']=$revision+1;
            if(array_map(static fn($v)=>$v===null?null:(string)$v,$this->row($issuer,$actor,$id))!==array_map(static fn($v)=>$v===null?null:(string)$v,$expected))throw new RuntimeException('issuer_native_timer_write_unconfirmed');return $id;
        });
    }
}
