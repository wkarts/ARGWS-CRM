<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskFormSchema.php';
require_once __DIR__.'/NativeCompanyTaskFormValues.php';
require_once __DIR__.'/NativeCompanyTaskRelationAuthority.php';
require_once __DIR__.'/NativeCompanyTaskOriginalActions.php';
/** Explicit native form entrypoints on the existing task writers, with the same transaction and billing fences. */
trait NativeCompanyTaskFormOperations
{
    use NativeCompanyTaskOriginalActions;
    private bool $nativeChecklistActive=false;
    private ?array $nativeChecklistContext=null;
    private ?array $nativeStatusContext=null;
    private function nativeStatusExtraTables():array
    {
        if($this->nativeStatusContext===null)return[];require_once __DIR__.'/NativeCompanyTaskStatusNotice.php';require_once __DIR__.'/NativeCompanyNotifications.php';return array_values(array_unique(array_merge(NativeCompanyTaskStatusNotice::tables($this->db,$this->p),NativeCompanyNotifications::tables('task_status'))));
    }
    private function finalizeNativeStatusNotices():void
    {
        if($this->nativeStatusContext===null||!isset($this->nativeStatusContext['source_id']))return;
        $audit=null;foreach($this->taskPriorityAuditFrames as$frame){$metadata=json_decode($frame['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(($metadata['native_task_status']??null)===$this->nativeStatusContext){if($audit!==null)throw new RuntimeException('native_task_status_unconfirmed');$audit=$frame;}}
        if($audit===null)throw new RuntimeException('native_task_status_unconfirmed');(new NativeCompanyTaskStatusNotice($this->db,$this->p))->queueInOwned((int)$audit['issuer_id'],(int)$audit['actor_id'],(int)$audit['id']);
    }

    /** Native creator/assignee/edit authority is specific to status, never a general edit grant. */
    private function nativeStatusAuthority(int$i,int$a,int$id):array
    {
        $this->authorize($i,$a,'view');$this->rememberNativeTaskParent($i,$a,$id);$row=$this->row($i,$id);$kind=$this->taskKind();
        if($kind==='project'){$this->parent($i,(int)$row['project_id']);$this->assertTaskUnclaimed($i,$a,$id);}else$this->assertBillingMutable($i,$a,$id);
        $table=$kind==='project'?'company_task_assignees':'company_standalone_assignees';
        $assigned=$this->rows('SELECT staff_id,active,revision FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? AND staff_id=? FOR UPDATE',[$i,$id,$a]);
        $isAssigned=$assigned?count($assigned)===1&&(int)$assigned[0]['active']===1:($row['assignee_id']!==null&&(int)$row['assignee_id']===$a);
        $operations=$kind==='project'?['task_created','task_native_created']:['standalone_task_created','standalone_task_native_created'];
        $creators=$this->rows('SELECT actor_id,operation,input_hash,request_key FROM '.$this->p.'company_project_requests WHERE issuer_id=? AND result_id=? AND operation IN (?,?) ORDER BY request_key FOR UPDATE',[$i,$id,...$operations]);
        $actors=array_unique(array_map('intval',array_column($creators,'actor_id')));if(count($actors)>1)throw new DomainException('native_task_creation_ambiguous');
        $edit=(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'tasks','edit');$creator=$actors!==[]&&reset($actors)===$a;
        if(!$edit&&!$isAssigned&&!$creator)throw new DomainException('native_task_status_denied');
        return['edit'=>$edit,'assignment'=>$assigned,'historical_assignee'=>$row['assignee_id'],'creation'=>$creators];
    }
    private function nativeStatusFrame(int$i,int$a,int$id):array
    {
        $authority=$this->nativeStatusAuthority($i,$a,$id);$table=$this->taskKind()==='project'?'company_task_timers':'company_standalone_timers';
        return['authority'=>$authority,'timers'=>$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$i,$id])];
    }
    /** Original status mutation on canonical storage. External delivery is not performed here. */
    public function markNativeStatus($issuer,$actor,$id,$revision,$fieldsRevision,$status,string$key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$fieldsRevision=$fieldsRevision===null?null:self::revision($fieldsRevision);
        if(!in_array($status,[1,2,3,4,5,'1','2','3','4','5'],true))throw new DomainException('native_task_status_invalid');$status=(int)$status;
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);$payload=[$id,$revision,$fieldsRevision,$status];$op=$this->taskKind()==='project'?'task_native_status':'standalone_task_native_status';
        $this->nativeStatusContext=['request_key'=>$key,'operation'=>$op,'input_hash'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))];
        try{return$this->operation($issuer,$actor,'view',$key,$op,$payload,function()use($issuer,$actor,$id,$revision,$fieldsRevision,$status){
            $authority=$this->nativeStatusAuthority($issuer,$actor,$id);$before=$this->row($issuer,$id);if((int)$before['revision']!==$revision||($before['native_fields_revision']===null?null:(int)$before['native_fields_revision'])!==$fieldsRevision)throw new DomainException('company_project_stale');
            $from=$before['status'];$to=NativeCompanyTaskFormValues::lifecycle($status);$now=date('Y-m-d H:i:s');$table=$this->taskKind()==='project'?'company_tasks':'company_standalone_tasks';$timerTable=$this->taskKind()==='project'?'company_task_timers':'company_standalone_timers';
            $timers=$this->rows('SELECT * FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$id]);$expectedTimers=$timers;$closed=[];
            if($to==='done'&&$from!=='done')foreach($expectedTimers as&$timer)if($timer['stopped_at']===null){
                $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$timer['started_at'],new DateTimeZone(date_default_timezone_get()));$stop=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$now,new DateTimeZone(date_default_timezone_get()));
                if(!$start||$start->format('Y-m-d H:i:s')!==$timer['started_at']||!$stop||$stop<$start||$stop->getTimestamp()-$start->getTimestamp()>2147483647)throw new DomainException('native_timer_time_invalid');
                $seconds=$stop->getTimestamp()-$start->getTimestamp();IssuerStorage::query($this->db,'UPDATE '.$this->p.$timerTable.' SET stopped_at=?,seconds=? WHERE issuer_id=? AND task_id=? AND id=? AND stopped_at IS NULL',[$now,$seconds,$issuer,$id,$timer['id']]);$timer['stopped_at']=$now;$timer['seconds']=$seconds;$closed[]=(int)$timer['id'];
            }unset($timer);
            $expected=$before;$fields=$before['native_fields_json']===null?null:json_decode($before['native_fields_json'],true,32,JSON_THROW_ON_ERROR);
            if($from!==$to){$expected['status']=$to;$expected['revision']=$revision+1;$expected['updated_at']=$now;
                if($fields!==null){$fields['date_finished']=$to==='done'?$now:null;NativeCompanyTaskFormValues::normalize($fields+['workflow_status'=>$status]);$expected['native_fields_json']=json_encode($fields,JSON_THROW_ON_ERROR);$expected['native_fields_revision']=$fieldsRevision+1;}
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET status=?,revision=?,updated_at=?,native_fields_json=?,native_fields_revision=? WHERE issuer_id=? AND id=? AND revision=?',[$to,$expected['revision'],$now,$expected['native_fields_json'],$expected['native_fields_revision'],$issuer,$id,$revision]);
            }
            $norm=static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r);$after=$this->row($issuer,$id);$actualTimers=$this->rows('SELECT * FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$id]);
            if($norm($after)!==$norm($expected)||array_map($norm,$actualTimers)!==array_map($norm,$expectedTimers)||$this->nativeStatusAuthority($issuer,$actor,$id)!==$authority)throw new RuntimeException('native_task_status_unconfirmed');
            $this->nativeStatusContext+=['source_kind'=>$this->taskKind()==='project'?'company_task':'company_standalone_task','source_id'=>$id,'from'=>$from,'to'=>$to,'result_revision'=>(int)$after['revision'],'date_finished'=>$to==='done'?($fields['date_finished']??($from!==$to?$now:null)):null,'closed_timer_ids'=>$closed];return$id;
        });}finally{$this->nativeStatusContext=null;}
    }
    /** Priority changes retain the original edit permission, canonical identity and billing fence. */
    public function changeNativePriority($issuer,$actor,$id,$revision,$fieldsRevision,$priority,string$key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$fieldsRevision=$fieldsRevision===null?null:self::revision($fieldsRevision);$priority=CompanyTaskPriority::value($priority);
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);$kind=$this->taskKind();$op=$kind==='project'?'task_native_priority':'standalone_task_native_priority';
        return $this->operation($issuer,$actor,'edit',$key,$op,[$id,$revision,$fieldsRevision,$priority],function()use($issuer,$actor,$id,$revision,$fieldsRevision,$priority,$kind):int{
            $this->rememberNativeTaskParent($issuer,$actor,$id);$before=$this->row($issuer,$id);
            if((int)$before['revision']!==$revision||($before['native_fields_revision']===null?null:(int)$before['native_fields_revision'])!==$fieldsRevision)throw new DomainException('company_project_stale');
            if($kind==='project')$this->assertTaskUnclaimed($issuer,$actor,$id);else$this->assertBillingMutable($issuer,$actor,$id);
            $expected=$before;
            if((int)$before['priority']!==$priority){
                $table=$kind==='project'?'company_tasks':'company_standalone_tasks';$expected['revision']=$revision+1;$expected['updated_at']=date('Y-m-d H:i:s');$expected['priority']=$priority;
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET revision=?,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$expected['revision'],$expected['updated_at'],$issuer,$id,$revision]);
                CompanyTaskPriority::save($this->db,$this->p,$kind,$issuer,$id,$priority,false);
            }
            $normalize=static fn($row)=>array_map(static fn($v)=>$v===null?null:(string)$v,$row);
            if($normalize($this->row($issuer,$id))!==$normalize($expected))throw new RuntimeException('issuer_task_priority_unconfirmed');
            return$id;
        });
    }
    /** Assign stable IDs to the current historical JSON exactly once, without rewriting it. */
    public function initializeNativeChecklist($issuer,$actor,$id,$revision,string$key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);require_once __DIR__.'/NativeCompanyTaskItems.php';NativeCompanyTaskItemSchema::assertValid($this->db,$this->p);
        $kind=$this->taskKind();$op=$kind==='project'?'task_native_checklist_init':'standalone_task_native_checklist_init';$this->nativeChecklistActive=true;
        try{return$this->operation($issuer,$actor,'edit',$key,$op,[$id,$revision],function()use($issuer,$actor,$id,$revision,$kind):int{
            $this->rememberNativeTaskParent($issuer,$actor,$id);$row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_project_stale');
            if($kind==='project')$this->assertTaskUnclaimed($issuer,$actor,$id);else$this->assertBillingMutable($issuer,$actor,$id);
            (new NativeCompanyTaskItems($this->db,$this->p))->initializeInOwned($issuer,$id,$kind,$row['checklist_json']);
            foreach(NativeCompanyTaskItems::tables($kind)as$table){$query='SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY '.(str_ends_with($table,'_items')?'position,id':'issuer_id').' FOR UPDATE';$args=[$issuer,$id];$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$this->rows($query,$args)];}
            return$id;
        });}finally{$this->nativeChecklistActive=false;}
    }
    /** A checklist command changes its item projection and task JSON in one audited transaction. */
    public function changeNativeChecklist($issuer,$actor,$id,$revision,$checklistRevision,array$command,string$key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$checklistRevision=self::revision($checklistRevision);require_once __DIR__.'/NativeCompanyTaskItems.php';NativeCompanyTaskItemSchema::assertValid($this->db,$this->p);
        $kind=$this->taskKind();$op=$kind==='project'?'task_native_checklist_change':'standalone_task_native_checklist_change';$this->nativeChecklistActive=true;
        try{return$this->operation($issuer,$actor,'edit',$key,$op,[$id,$revision,$checklistRevision,$command],function()use($issuer,$actor,$id,$revision,$checklistRevision,$command,$kind):int{
            $this->rememberNativeTaskParent($issuer,$actor,$id);$row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_project_stale');
            if($kind==='project')$this->assertTaskUnclaimed($issuer,$actor,$id);else$this->assertBillingMutable($issuer,$actor,$id);
            if(($command['action']??null)==='assign'&&($command['assigned_id']??null)!==null){
                $target=IssuerPolicy::id($command['assigned_id']);require_once __DIR__.'/NativeCompanyWorkAssignments.php';$members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($issuer,$actor,$kind==='project'?'task':'standalone',$id);$ids=array_map('intval',array_column($members['members'],'staffid'));
                if(!in_array($target,$ids,true)&&($row['assignee_id']===null||(int)$row['assignee_id']!==$target))throw new DomainException('native_assignment_denied');
                if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$target,'tasks','view'))throw new DomainException('native_assignment_denied');
                $assignTable=$kind==='project'?'company_task_assignees':'company_standalone_assignees';$query='SELECT * FROM '.$this->p.$assignTable.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$args=[$issuer,$id];$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$this->rows($query,$args),'follower_authority'=>[$issuer,[$target],$kind==='project']];
            }
            $change=(new NativeCompanyTaskItems($this->db,$this->p))->mutateInOwned($issuer,$actor,$id,$kind,$row['checklist_json'],$checklistRevision,$command);
            $table=$kind==='project'?'company_tasks':'company_standalone_tasks';$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$id,$revision]);
            $expected=$row;$expected['checklist_json']=$change['json'];$expected['revision']=$revision+1;$expected['updated_at']=$now;$norm=static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r);if($norm($this->row($issuer,$id))!==$norm($expected))throw new RuntimeException('native_task_checklist_unconfirmed');
            $this->nativeChecklistContext=['kind'=>$kind,'task_id'=>$id,'action'=>$change['action'],'before'=>$change['before'],'after'=>$change['after']];
            foreach(NativeCompanyTaskItems::tables($kind)as$t){$query='SELECT * FROM '.$this->p.$t.' WHERE issuer_id=? AND task_id=? ORDER BY '.(str_ends_with($t,'_items')?'id':'issuer_id').' FOR UPDATE';$args=[$issuer,$id];$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$this->rows($query,$args)];}return$id;
        });}finally{$this->nativeChecklistActive=false;$this->nativeChecklistContext=null;}
    }
    private static ?WeakMap $nativeTaskCreationProofs=null;
    private array $nativeTaskRelatedFrames=[];
    private bool $nativeExplicitFollowers=false;
    public static function assertNativeTaskCreationProof(object$proof,$db,string$p,int$issuer,int$actor,int$id):void
    {
        $record=self::$nativeTaskCreationProofs[$proof]??null;
        if(!$record||$record['db']!==$db||$record['prefix']!==$p||$record['issuer']!==$issuer||$record['actor']!==$actor||$record['id']!==$id)throw new DomainException('native_task_creation_proof_denied');
        IssuerTransaction::assertOwned($db,$p,[$record['table'],'company_project_requests']);
        $acl=new IssuerResourceAccess($db,$p);if(!$acl->can($issuer,$actor,'tasks','create')||!$acl->can($issuer,$actor,'tasks','view')||($record['table']==='company_tasks'&&!$acl->can($issuer,$actor,'projects','view')))throw new DomainException('native_task_creation_proof_denied');
        $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.$record['table'].' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])->result_array();
        if(count($rows)!==1||$rows[0]!==$record['row'])throw new DomainException('native_task_creation_proof_denied');
    }
    /** Original task selector reads the existing milestone lineage, without publishing or inventing dates. */
    public function nativeMilestoneSelection(int $issuer,int $actor,int $project):array
    {
        if($this->taskKind()!=='project')throw new DomainException('native_task_form_invalid');
        return $this->transaction(function()use($issuer,$actor,$project):array{
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');
            if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('company_project_access_denied');$this->parent($issuer,$project);
            $snapshot=$this->nativeMilestoneSnapshot($issuer,$project);$this->authorize($issuer,$actor,'view');
            return ['revision'=>hash('sha256',json_encode([$issuer,$project,$snapshot],JSON_THROW_ON_ERROR)),'items'=>$snapshot['company_project_milestones']];
        });
    }
    private function nativeMilestoneSnapshot(int $issuer,int $project):array
    {
        require_once __DIR__.'/CompanyProjectMilestonesSchema.php';CompanyProjectMilestonesSchema::assertValid($this->db,$this->p);
        $tables=['company_project_milestones','company_milestone_details','company_task_milestones'];IssuerTransaction::assertOwned($this->db,$this->p,$tables);$out=[];
        foreach($tables as$table){$order=$table==='company_project_milestones'?'id':($table==='company_milestone_details'?'milestone_id':'task_id');$rows=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND project_id=? ORDER BY '.$order.' FOR UPDATE',[$issuer,$project]);if(count($rows)>10000)throw new DomainException('company_project_limit');$out[$table]=$rows;}
        return $out;
    }
    private function saveNativeTaskMilestone(int $issuer,int $actor,int $id,array $selection):void
    {
        if($this->taskKind()!=='project'||array_keys($selection)!==['id','revision']||!is_string($selection['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$selection['revision']))throw new DomainException('native_task_form_invalid');
        $row=$this->row($issuer,$id);$project=(int)$row['project_id'];$snapshot=$this->nativeMilestoneSnapshot($issuer,$project);
        if(!hash_equals(hash('sha256',json_encode([$issuer,$project,$snapshot],JSON_THROW_ON_ERROR)),$selection['revision']))throw new DomainException('native_task_milestone_stale');
        $selected=$selection['id']===null?null:IssuerPolicy::id($selection['id']);$available=array_map('intval',array_column($snapshot['company_project_milestones'],'id'));
        if($selected!==null&&!in_array($selected,$available,true))throw new DomainException('company_project_reference_denied');
        $current=null;foreach($snapshot['company_task_milestones']as$mapping)if((int)$mapping['task_id']===$id)$current=(int)$mapping['milestone_id'];
        $changed=$current!==$selected;$now=gmdate('Y-m-d H:i:s');
        if($changed){
            // Existing milestone assignment authority remains projects.edit; selecting a task does not grant it.
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'projects','edit'))throw new DomainException('company_project_portal_access_denied');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND task_id=?',[$issuer,$project,$id]);
            if($selected!==null)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_milestones(issuer_id,project_id,task_id,milestone_id) VALUES(?,?,?,?)',[$issuer,$project,$id,$selected]);
            // Preserve undeclared details as absent. Existing details gain a real revision; no synthetic start date.
            foreach(array_unique(array_filter([$current,$selected],static fn($v)=>$v!==null))as$milestone)IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_milestone_details SET revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$actor,$now,$issuer,$project,$milestone]);
        }
        $after=$this->nativeMilestoneSnapshot($issuer,$project);$mappings=array_values(array_filter($after['company_task_milestones'],static fn($m)=>(int)$m['task_id']===$id));
        if(($selected===null&&$mappings)||($selected!==null&&(count($mappings)!==1||(int)$mappings[0]['milestone_id']!==$selected)))throw new RuntimeException('issuer_project_write_unconfirmed');
        // Compare the untouched lineage too, so a trigger cannot change another task or milestone as a side effect.
        $expected=$snapshot;$expected['company_task_milestones']=array_values(array_filter($expected['company_task_milestones'],static fn($m)=>(int)$m['task_id']!==$id));
        if($selected!==null)$expected['company_task_milestones'][]=['issuer_id'=>$issuer,'project_id'=>$project,'task_id'=>$id,'milestone_id'=>$selected];usort($expected['company_task_milestones'],static fn($a,$b)=>(int)$a['task_id']<=>(int)$b['task_id']);
        foreach($expected['company_milestone_details']as&$details)if($changed&&in_array((int)$details['milestone_id'],[$current,$selected],true)){$details['revision']=(int)$details['revision']+1;$details['actor_id']=$actor;$details['updated_at']=$now;}unset($details);
        $normalize=static fn($s)=>array_map(static fn($rows)=>array_map(static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r),$rows),$s);
        if($normalize($expected)!==$normalize($after))throw new RuntimeException('issuer_project_write_unconfirmed');
        foreach($after as$table=>$rows){$order=$table==='company_project_milestones'?'id':($table==='company_milestone_details'?'milestone_id':'task_id');$this->nativeTaskRelatedFrames[]=['query'=>'SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND project_id=? ORDER BY '.$order.' FOR UPDATE','args'=>[$issuer,$project],'rows'=>$rows,'milestone_authority'=>$changed?[$issuer,$actor]:null];}
    }
    private function nativeTaskTables():array
    {
        $kind=$this->taskKind()==='project'?'task':'standalone';$table=$kind==='task'?'company_task_assignees':'company_standalone_assignees';
        require_once __DIR__.'/NativeCompanyWorkAssignments.php';$tables=$this->db->table_exists($this->p.$table)?(new NativeCompanyWorkAssignments($this->db,$this->p))->tableNames($kind):[];if($this->db->table_exists($this->p.'options'))$tables[]='options';
        require_once __DIR__.'/NativeCompanyTaskItems.php';$checklistTables=NativeCompanyTaskItems::tables($this->taskKind());$present=array_map(fn($name)=>$this->db->table_exists($this->p.$name),$checklistTables);
        if($this->nativeChecklistActive||in_array(true,$present,true)){if(in_array(false,$present,true))throw new RuntimeException('native_task_item_schema_invalid');$tables=array_merge($tables,$checklistTables);}
        if($this->db->table_exists($this->p.'company_task_checklist_templates')){require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';$tables=array_merge($tables,(new NativeCompanyTaskChecklistTemplates($this->db,$this->p))->tableNames());}
        if($this->db->table_exists($this->p.'company_work_tags')){require_once __DIR__.'/NativeCompanyResourceTags.php';$tables=array_merge($tables,NativeCompanyResourceTags::tableNames());}
        if($this->db->table_exists($this->p.'company_custom_fields')){require_once __DIR__.'/NativeCompanyCustomFields.php';$tables=array_merge($tables,(new NativeCompanyCustomFields($this->db,$this->p))->tableNames());}if($this->db->table_exists($this->p.'company_standalone_followers'))$tables[]='company_standalone_followers';if($this->taskKind()==='project'&&$this->db->table_exists($this->p.'company_task_milestones'))$tables=array_merge($tables,['company_project_milestones','company_milestone_details','company_task_milestones']);if($kind==='standalone'&&$this->db->table_exists($this->p.'company_ticket_catalog'))$tables=array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,'ticket'));if($kind==='standalone'&&$this->db->table_exists($this->p.'invoices'))$tables=array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,'invoice'));return array_values(array_unique($tables));
    }
    private function confirmNativeTaskTemplateInput(int$i,int$a,array$data,?array$related):void
    {
        $templates=$related['template_sources']??[];if(!is_array($templates)||!array_is_list($templates)||count($templates)>30)throw new DomainException('native_task_form_invalid');if(!$templates)return;
        require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';$service=new NativeCompanyTaskChecklistTemplates($this->db,$this->p);IssuerTransaction::assertOwned($this->db,$this->p,$service->tableNames());$checks=[];$seen=[];
        foreach($templates as$t){if(!is_array($t)||array_keys($t)!==['id','revision']||!is_string($t['revision']))throw new DomainException('native_task_form_invalid');$current=$service->get($i,$a,$t['id']);if(isset($seen[$current['id']])||!hash_equals($current['revision'],$t['revision']))throw new DomainException('native_task_template_stale');$seen[$current['id']]=true;$checks[]=['text'=>$current['description'],'done'=>0];
            $legacy=$current['source_kind']==='task_checklist_template';$query='SELECT * FROM '.$this->p.($legacy?'tasks_checklist_templates':'company_task_checklist_templates').' WHERE '.($legacy?'id=?':'issuer_id=? AND id=?').' FOR UPDATE';$args=$legacy?[$current['source_id']]:[$i,$current['source_id']];$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$this->rows($query,$args)];
        }
        if(json_decode($data['checklist_json'],true,32,JSON_THROW_ON_ERROR)!==$checks)throw new DomainException('native_task_template_stale');
    }
    private function nativeTaskRelated(int$issuer,int$actor,int$id,?array$related):void
    {
        if($related===null)return;
        if(array_diff(array_keys($related),['assignees','tags','custom_fields','template_sources','followers','milestone','creation_preferences'])||!isset($related['assignees'])||!is_array($related['assignees']))throw new DomainException('native_task_form_invalid');
        require_once __DIR__.'/NativeCompanyWorkAssignments.php';$kind=$this->taskKind()==='project'?'task':'standalone';$table=$kind==='task'?'company_tasks':'company_standalone_tasks';
        $proof=new stdClass();self::$nativeTaskCreationProofs??=new WeakMap();$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        self::$nativeTaskCreationProofs[$proof]=['db'=>$this->db,'prefix'=>$this->p,'issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'table'=>$table,'row'=>$row];
        try{(new NativeCompanyWorkAssignments($this->db,$this->p))->createTaskMembersInOwnedTransaction($issuer,$actor,$kind,$id,$related['assignees'],$proof);
            $memberTable=$kind==='task'?'company_task_assignees':'company_standalone_assignees';$memberQuery='SELECT * FROM '.$this->p.$memberTable.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$this->nativeTaskRelatedFrames[]=['query'=>$memberQuery,'args'=>[$issuer,$id],'rows'=>$this->rows($memberQuery,[$issuer,$id])];
            if(array_key_exists('followers',$related)&&!is_array($related['followers']))throw new DomainException('native_task_form_invalid');if(array_key_exists('followers',$related))$this->createNativeTaskFollowers($issuer,$actor,$id,$related['followers'],$proof);
            if(array_key_exists('milestone',$related))$this->saveNativeTaskMilestone($issuer,$actor,$id,$related['milestone']);
            $source=$kind==='task'?'company_task':'company_standalone_task';
            if(array_key_exists('tags',$related)){require_once __DIR__.'/NativeCompanyResourceTags.php';(new NativeCompanyResourceTags($this->db,$this->p))->createTaskTagsInOwnedTransaction($issuer,$actor,$source,$id,$related['tags'],$proof);$this->captureNativeTaskMetadata($issuer,$source,$id,['tags'=>true]);}
            if(array_key_exists('custom_fields',$related)){require_once __DIR__.'/NativeCompanyCustomFields.php';(new NativeCompanyCustomFields($this->db,$this->p))->saveCreatedTaskInOwnedTransaction($issuer,$actor,$source,$id,$related['custom_fields'],$proof);$this->captureNativeTaskMetadata($issuer,$source,$id,['custom_fields'=>true]);}
        }finally{unset(self::$nativeTaskCreationProofs[$proof]);}
        $members=$kind==='task'?'company_task_assignees':'company_standalone_assignees';
        $query='SELECT * FROM '.$this->p.$members.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>[$issuer,$id],'rows'=>$this->rows($query,[$issuer,$id])];
        $this->captureNativeTaskMetadata($issuer,$source,$id,$related);
    }
    private function createNativeTaskFollowers(int$i,int$a,int$id,array$followers,object$proof):void
    {
        self::assertNativeTaskCreationProof($proof,$this->db,$this->p,$i,$a,$id);if(!array_is_list($followers)||count($followers)>500)throw new DomainException('native_task_form_invalid');$followers=array_map([IssuerPolicy::class,'id'],$followers);if(count(array_unique($followers))!==count($followers))throw new DomainException('native_task_form_invalid');sort($followers,SORT_NUMERIC);
        $project=$this->taskKind()==='project';$table=$project?'company_task_followers':'company_standalone_followers';if(!$project){require_once __DIR__.'/NativeCompanyStandaloneFollowersSchema.php';NativeCompanyStandaloneFollowersSchema::assertValid($this->db,$this->p);}IssuerTransaction::assertOwned($this->db,$this->p,[$table]);$acl=new IssuerResourceAccess($this->db,$this->p);
        foreach($followers as$staff){$acl->lockFinancialIdentity($i,$staff);if(!$acl->can($i,$staff,'tasks','view')||($project&&!$acl->can($i,$staff,'projects','view')))throw new DomainException('native_assignment_denied');}
        $sql='SELECT issuer_id,task_id,staff_id FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';if($this->rows($sql,[$i,$id]))throw new DomainException('native_task_form_invalid');
        foreach($followers as$staff)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(issuer_id,task_id,staff_id) VALUES(?,?,?)',[$i,$id,$staff]);$rows=$this->rows($sql,[$i,$id]);if(array_map('intval',array_column($rows,'staff_id'))!==$followers)throw new RuntimeException('issuer_project_write_unconfirmed');
        $this->nativeTaskRelatedFrames[]=['query'=>$sql,'args'=>[$i,$id],'rows'=>$rows,'follower_authority'=>[$i,$followers,$project]];
        if($project)foreach($this->taskPriorityFrames as&$frame)if($frame['issuer']===$i&&$frame['id']===$id)$frame['frame']['followers']=array_map(static fn($r)=>array_map('strval',$r),$rows);unset($frame);
    }
    private function captureNativeTaskMetadata(int$issuer,string$source,int$id,array$related):void
    {
        foreach(['tags'=>'company_work_tag_links','custom_fields'=>'company_custom_field_values']as$key=>$metaTable)if(array_key_exists($key,$related)){$query='SELECT * FROM '.$this->p.$metaTable.' WHERE issuer_id=? AND source_kind=? AND source_id=? ORDER BY '.($key==='tags'?'tag_id':'field_source_kind,field_id').' FOR UPDATE';if($key==='tags')$query='SELECT l.*,t.label,t.label_hash,t.feature,t.revision AS tag_revision FROM '.$this->p.'company_work_tag_links l JOIN '.$this->p.'company_work_tags t ON t.issuer_id=l.issuer_id AND t.id=l.tag_id WHERE l.issuer_id=? AND l.source_kind=? AND l.source_id=? ORDER BY l.tag_id FOR UPDATE';$args=[$issuer,$source,$id];$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$this->rows($query,$args)];}
    }
    private function updateNativeTaskRelated(int$issuer,int$actor,int$id,?array$related):void
    {
        if($related===null)return;if(array_diff(array_keys($related),['tags','tags_revision','custom_fields','milestone'])||array_diff(['tags','tags_revision','custom_fields'],array_keys($related)))throw new DomainException('native_task_form_invalid');
        require_once __DIR__.'/NativeCompanyResourceTags.php';require_once __DIR__.'/NativeCompanyCustomFields.php';
        if(array_key_exists('milestone',$related))$this->saveNativeTaskMilestone($issuer,$actor,$id,$related['milestone']);
        $source=$this->taskKind()==='project'?'company_task':'company_standalone_task';$canonical=NativeCompanyResourceId::encode($source,$id);
        (new NativeCompanyResourceTags($this->db,$this->p))->replaceInOwnedTransaction($issuer,$actor,'task',$canonical,$related['tags_revision'],$related['tags']);$this->captureNativeTaskMetadata($issuer,$source,$id,['tags'=>true]);
        (new NativeCompanyCustomFields($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,'tasks',$canonical,$related['custom_fields']);
        $this->captureNativeTaskMetadata($issuer,$source,$id,$related);
    }
    private function confirmNativeTaskRelatedFrames():void
    {
        foreach($this->nativeTaskRelatedFrames as$f){if(isset($f['task_relation'])){[$i,$a,$fields,$proof]=$f['task_relation'];if(NativeCompanyTaskRelationAuthority::fields($this->db,$this->p,$i,$a,$fields)!==$proof)throw new DomainException('native_task_relation_stale');continue;}if(isset($f['milestone_authority'])){[$i,$a]=$f['milestone_authority'];if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'projects','edit'))throw new DomainException('company_project_portal_access_denied');}if($this->rows($f['query'],$f['args'])!==$f['rows'])throw new RuntimeException('issuer_project_write_unconfirmed');if(isset($f['comment_authority'])){[$i,$a,$comment,$action]=$f['comment_authority'];NativeCompanyTaskCommentPolicy::assertAllowed($this->db,$this->p,$i,$a,$comment,$action);}if(isset($f['follower_authority'])){[$i,$members,$project]=$f['follower_authority'];$acl=new IssuerResourceAccess($this->db,$this->p);foreach($members as$member)if(!$acl->can($i,$member,'tasks','view')||($project&&!$acl->can($i,$member,'projects','view')))throw new DomainException('native_assignment_denied');}}
    }
    private function rememberNativeTaskParent(int$i,int$a,int$id):void
    {
        if($this->taskKind()!=='standalone')return;
        $frame=NativeCompanyTaskRelationAuthority::capture($this->db,$this->p,$i,$a,$id);
        if(($frame['parent']??null)!==null){$fields=json_decode($frame['row']['native_fields_json'],true,32,JSON_THROW_ON_ERROR);$this->nativeTaskRelatedFrames[]=['task_relation'=>[$i,$a,$fields,$frame['parent']]];}
    }
    private function guardNativeChecklistWrite(int$i,int$id,string$old,string$new):void
    {
        $tables=NativeCompanyTaskItems::tables($this->taskKind());if(!$this->db->table_exists($this->p.$tables[0]))return;
        NativeCompanyTaskItemSchema::assertValid($this->db,$this->p);(new NativeCompanyTaskItems($this->db,$this->p))->guardLegacyWriteInOwned($i,$id,$this->taskKind(),$old,$new);
    }
    private function nativeTaskFields(int$issuer,int$actor,array$data,array$fields):array
    {
        $fields=NativeCompanyTaskFormValues::normalize($fields);
        if($data['status']!==NativeCompanyTaskFormValues::lifecycle($fields['workflow_status']))throw new DomainException('native_task_form_invalid');
        if($data['due_date']!==null&&$data['due_date']<$fields['start_date'])throw new DomainException('native_task_form_invalid');
        if($this->taskKind()==='project'){
            if($fields['relation_type']!=='project'||$fields['relation_source_kind']!=='company_project'||$fields['relation_source_id']!==$data['project_id'])throw new DomainException('company_project_reference_denied');
            $this->parent($issuer,$data['project_id'],true);
        }elseif($fields['relation_type']!==null){
            $tables=NativeCompanyResourceReferences::transactionTables($this->db,$this->p,$fields['relation_type']);IssuerTransaction::assertOwned($this->db,$this->p,$tables);
            $proof=NativeCompanyTaskRelationAuthority::fields($this->db,$this->p,$issuer,$actor,$fields);
            $this->nativeTaskRelatedFrames[]=['task_relation'=>[$issuer,$actor,$fields,$proof]];
        }
        // Status is stored once, in the canonical row, so native timer transitions cannot leave a duplicate stale value.
        unset($fields['workflow_status']);return$fields;
    }
    private function saveNativeTaskFields(int$issuer,int$id,array$fields,?int$revision):void
    {
        $table=$this->taskKind()==='project'?'company_tasks':'company_standalone_tasks';$json=json_encode($fields,JSON_THROW_ON_ERROR);$next=($revision??0)+1;
        $where=$revision===null?'native_fields_revision IS NULL':'native_fields_revision=?';$args=[$json,$next,$issuer,$id];if($revision!==null)$args[]=$revision;
        IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET native_fields_json=?,native_fields_revision=? WHERE issuer_id=? AND id=? AND '.$where,$args);
        if(method_exists($this->db,'affected_rows')&&(int)$this->db->affected_rows()!==1)throw new RuntimeException('issuer_project_write_unconfirmed');
        $row=$this->row($issuer,$id);if(($row['native_fields_json']??null)!==$json||(int)($row['native_fields_revision']??0)!==$next)throw new RuntimeException('issuer_project_write_unconfirmed');
        // Advance only the two exact columns just written in a pending creation frame. All other captured fields remain immutable.
        foreach($this->taskPriorityFrames as&$frame)if($frame['issuer']===$issuer&&$frame['id']===$id){$frame['frame']['native']['native_fields_json']=$json;$frame['frame']['native']['native_fields_revision']=(string)$next;}unset($frame);
    }
    public function createNative($issuer,$actor,array$input,array$fields,string$key,?array$related=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input,true);$fields=NativeCompanyTaskFormValues::normalize($fields);
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);
        $op=$this->taskKind()==='project'?'task_native_created':'standalone_task_native_created';
        return$this->operation($issuer,$actor,'create',$key,$op,[$data,$fields,$related],function()use($issuer,$actor,$data,$fields,$related):int{
            if(isset($related['creation_preferences'])){$current=CompanyTaskPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);$selection=['revision'=>$current['revision'],CompanyTaskPreferences::STATUS=>$current[CompanyTaskPreferences::STATUS],CompanyTaskPreferences::ASSIGN=>$current[CompanyTaskPreferences::ASSIGN]];if($selection!==$related['creation_preferences']||CompanyTaskPreferences::forStartDate($current[CompanyTaskPreferences::STATUS],$fields['start_date'])!==$data['status'])throw new DomainException('company_task_preferences_stale');$query='SELECT id,active,settings_json FROM '.$this->p.'issuers_registry WHERE id=? FOR UPDATE';$this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>[$issuer],'rows'=>$this->rows($query,[$issuer])];}
            $this->confirmNativeTaskTemplateInput($issuer,$actor,$data,$related);$stored=$this->nativeTaskFields($issuer,$actor,$data,$fields);$this->nativeExplicitFollowers=is_array($related)&&array_key_exists('followers',$related);try{$id=$this->insertCreatedInOwned($issuer,$actor,$data);}finally{$this->nativeExplicitFollowers=false;}$this->saveNativeTaskFields($issuer,$id,$stored,null);$this->nativeTaskRelated($issuer,$actor,$id,$related);$this->references($issuer,$data);return$id;
        });
    }
    public function updateNative($issuer,$actor,$id,$revision,$fieldsRevision,array$input,array$fields,string$key,?array$related=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$fieldsRevision=$fieldsRevision===null?null:self::revision($fieldsRevision);$data=self::input($input,true);$fields=NativeCompanyTaskFormValues::normalize($fields);
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);$op=$this->taskKind()==='project'?'task_native_updated':'standalone_task_native_updated';
        return$this->operation($issuer,$actor,'edit',$key,$op,[$id,$revision,$fieldsRevision,$data,$fields,$related],function()use($issuer,$actor,$id,$revision,$fieldsRevision,$data,$fields,$related):int{
            $this->rememberNativeTaskParent($issuer,$actor,$id);$old=$this->row($issuer,$id);$actual=$old['native_fields_revision']===null?null:(int)$old['native_fields_revision'];if($actual!==$fieldsRevision)throw new DomainException('company_project_stale');
            $stored=$this->nativeTaskFields($issuer,$actor,$data,$fields);$this->updateCanonicalInOwned($issuer,$actor,$id,$revision,$data);$this->saveNativeTaskFields($issuer,$id,$stored,$fieldsRevision);$this->updateNativeTaskRelated($issuer,$actor,$id,$related);$this->references($issuer,$data);return$id;
        });
    }
}
