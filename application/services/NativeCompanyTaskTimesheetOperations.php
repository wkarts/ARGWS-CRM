<?php
declare(strict_types=1);
/** Manual original timesheets join the canonical task transaction and billing fences. */
trait NativeCompanyTaskTimesheetOperations
{
    private function nativeManualAuthority(int $issuer,int $actor,int $task,int $subject,string $action):array
    {
        $this->authorize($issuer,$actor,'view');$this->rememberNativeTaskParent($issuer,$actor,$task);$row=$this->row($issuer,$task);
        $acl=new IssuerResourceAccess($this->db,$this->p);$own=$actor===$subject;
        if(!$acl->can($issuer,$subject,'tasks','view'))throw new DomainException('native_timesheet_denied');
        $allowed=match($action){'add'=>$own||$acl->can($issuer,$actor,'tasks','edit_timesheet'), 'edit'=>$acl->can($issuer,$actor,'tasks','edit_timesheet')||($own&&$acl->can($issuer,$actor,'tasks','edit_own_timesheet')), 'delete'=>$acl->can($issuer,$actor,'tasks','delete_timesheet')||($own&&$acl->can($issuer,$actor,'tasks','delete_own_timesheet')), default=>false};
        if(!$allowed||$row['status']==='deleted')throw new DomainException('native_timesheet_denied');
        if($this->taskKind()==='project'){$this->assertTaskUnclaimed($issuer,$actor,$task);$parent=$this->parent($issuer,(int)$row['project_id']);if($parent['status']==='cancelled')throw new DomainException('native_timesheet_denied');}
        else $this->assertBillingMutable($issuer,$actor,$task);
        if(!$own)(new CompanyStaff($this->db,$this->p))->assertAdministrativeTarget($issuer,$actor,$subject);
        return $row;
    }
    public static function nativeTimesheetRevision(array $timer,?array $details):string
    {
        $normalize=static fn($row)=>$row===null?null:array_map(static fn($v)=>$v===null?null:(string)$v,$row);
        return hash('sha256',json_encode([$normalize($timer),$normalize($details)],JSON_THROW_ON_ERROR));
    }
    private function nativeManualFrame(int $issuer,int $actor):array
    {
        $context=$this->nativeTimerContext;$id=$context['targets'][0][1];$subject=$context['subject'];$action=substr($context['action'],7);
        $task=$this->nativeManualAuthority($issuer,$actor,$id,$subject,$action);$kind=$this->taskKind();$table=$kind==='project'?'company_task_timers':'company_standalone_timers';$source=$kind==='project'?'company_task_timer':'company_standalone_timer';
        $timers=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$id]);$details=[];
        foreach($timers as$timer)$details[]=$this->rows('SELECT * FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=? AND issuer_id=?',[$source,$timer['id'],$issuer]);
        return ['task'=>$task,'timers'=>$timers,'details'=>$details];
    }
    public function changeNativeTimesheet(int $issuer,int $actor,int $task,int $revision,string $action,?int $timer,?string $expected,int $subject,?string $start,?string $end,string $note,string $key):int
    {
        foreach([$issuer,$actor,$task,$subject]as$value)IssuerPolicy::id($value);self::revision($revision);
        if(!in_array($action,['add','edit','delete'],true)||($action==='add'?($timer!==null||$expected!==null):($timer===null||$timer<1||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))))throw new DomainException('native_timesheet_invalid');
        $note=self::text($note,4000,false);$seconds=null;
        if($action!=='delete'){
            $from=is_string($start)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start):false;$to=is_string($end)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$end):false;
            if(!$from||!$to||$from->format('Y-m-d H:i:s')!==$start||$to->format('Y-m-d H:i:s')!==$end||$to<$from||$to->getTimestamp()>time())throw new DomainException('native_timesheet_invalid');
            $seconds=$to->getTimestamp()-$from->getTimestamp();if($seconds>2147483646)throw new DomainException('native_timesheet_invalid');
        }elseif($start!==null||$end!==null||$note!=='')throw new DomainException('native_timesheet_invalid');
        if($this->nativeTimerContext!==null)throw new LogicException('Native timer operation already active');
        $this->nativeTimerContext=['action'=>'manual_'.$action,'targets'=>[[$this->taskKind(),$task]],'subject'=>$subject];
        try{return $this->operation($issuer,$actor,'view',$key,($this->taskKind()==='project'?'task':'standalone_task').'_manual_'.$action,[$task,$revision,$action,$timer,$expected,$subject,$start,$end,$note],function()use($issuer,$actor,$task,$revision,$action,$timer,$expected,$subject,$start,$end,$note,$seconds):int{
            $before=$this->nativeManualAuthority($issuer,$actor,$task,$subject,$action);if((int)$before['revision']!==$revision)throw new DomainException('company_project_stale');
            $kind=$this->taskKind();$table=$kind==='project'?'company_task_timers':'company_standalone_timers';$tasks=$kind==='project'?'company_tasks':'company_standalone_tasks';$source=$kind==='project'?'company_task_timer':'company_standalone_timer';
            $history=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$task]);$afterHistory=$history;$old=null;$oldDetails=null;$details=new NativeCompanyTaskTimerDetails($this->db,$this->p);
            if($timer!==null){
                foreach($history as$row)if((int)$row['id']===$timer)$old=$row;
                if(!$old||(int)$old['actor_id']!==$subject||$old['stopped_at']===null)throw new DomainException('native_timesheet_denied');
                $oldDetails=$this->rows('SELECT * FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=? AND issuer_id=?',[$source,$timer,$issuer])[0]??null;
                if(!hash_equals(self::nativeTimesheetRevision($old,$oldDetails),$expected))throw new DomainException('native_timesheet_stale');
                if($this->rows('SELECT id FROM '.$this->p.'company_blank_task_timers WHERE issuer_id=? AND target_kind=? AND target_timer_id=?',[$issuer,$kind==='project'?'company_task':'company_standalone_task',$timer]))throw new DomainException('native_timesheet_transfer_immutable');
            }
            if($action==='add'){
                if(count($history)>=500)throw new DomainException('company_project_limit');
                foreach($history as$running)if((int)$running['actor_id']===$subject&&$running['stopped_at']===null)throw new DomainException('native_timer_running');
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,?,?)',[$issuer,$task,$subject,$start,$end,$seconds]);$timer=(int)$this->db->insert_id();
                $new=['id'=>$timer,'issuer_id'=>$issuer,'task_id'=>$task,'actor_id'=>$subject,'started_at'=>$start,'stopped_at'=>$end,'seconds'=>$seconds];$afterHistory[]=$new;
                $actual=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$issuer,$timer])[0]??throw new RuntimeException('native_timesheet_unconfirmed');
                $proof=$this->nativeTimerCreationProof($issuer,$subject,$source,$timer,$actual);$details->captureForStaffInOwned($issuer,$actor,$subject,$source,$timer,$proof,$note);
            }elseif($action==='edit'){
                $proof=$this->nativeTimerEditProof($issuer,$subject,$source,$timer,$old);$details->saveNoteInOwned($issuer,$subject,$source,$timer,(int)($oldDetails['revision']??0),$note,$proof);
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET started_at=?,stopped_at=?,seconds=? WHERE issuer_id=? AND task_id=? AND actor_id=? AND id=?',[$start,$end,$seconds,$issuer,$task,$subject,$timer]);
                foreach($afterHistory as&$row)if((int)$row['id']===$timer){$row['started_at']=$start;$row['stopped_at']=$end;$row['seconds']=$seconds;}unset($row);
            }else{
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=? AND issuer_id=? AND actor_id=?',[$source,$timer,$issuer,$subject]);
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? AND actor_id=? AND id=?',[$issuer,$task,$subject,$timer]);
                $afterHistory=array_values(array_filter($history,static fn($row)=>(int)$row['id']!==$timer));
            }
            $normalize=static fn($rows)=>array_map(static fn($row)=>array_map(static fn($v)=>$v===null?null:(string)$v,$row),$rows);
            if($normalize($this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$task]))!==$normalize($afterHistory))throw new RuntimeException('native_timesheet_unconfirmed');
            $now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'UPDATE '.$this->p.$tasks.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$task,$revision]);
            $expectedTask=$before;$expectedTask['revision']=$revision+1;$expectedTask['updated_at']=$now;$actualTask=$this->nativeManualAuthority($issuer,$actor,$task,$subject,$action);
            if($normalize([$actualTask])!==$normalize([$expectedTask]))throw new RuntimeException('native_timesheet_unconfirmed');
            $this->nativeOriginalContext=['action'=>'timesheet_'.$action,'source_kind'=>$source,'timer_id'=>$timer,'before'=>['timer'=>$old,'details'=>$oldDetails],'after'=>['timer'=>$action==='delete'?null:$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$issuer,$timer])[0],'details'=>$this->rows('SELECT * FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=? AND issuer_id=?',[$source,$timer,$issuer])[0]??null]];
            return $task;
        });}finally{$this->nativeTimerContext=null;$this->nativeOriginalContext=null;}
    }
}
