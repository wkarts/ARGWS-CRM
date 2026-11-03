<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskCommentPolicy.php';

/** Finite original task commands composed inside the existing owned task transaction. */
trait NativeCompanyTaskOriginalActions
{
    private ?array $nativeOriginalContext=null;
    private ?array $nativeOriginalRequest=null;

    public function changeNativeOriginal($issuer,$actor,$id,$revision,$fieldsRevision,array $command,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        $revision=self::revision($revision);$fieldsRevision=$fieldsRevision===null?null:self::revision($fieldsRevision);
        $action=$command['action']??null;
        $keys=match($action){
            'description'=>['action','description'], 'public'=>['action','value'],
            'tags'=>['action','labels','revision'], 'milestone'=>['action','id','revision'],
            'assignee_add','assignee_remove'=>['action','staff_id','revision'],
            'follower_add','follower_remove'=>['action','staff_id'],
            'comment_edit'=>['action','comment_id','comment_revision','body'],
            'comment_remove'=>['action','comment_id','comment_revision'],
            'inline'=>['action','dates'],
            default=>throw new DomainException('native_task_original_invalid'),
        };
        if(array_diff(array_keys($command),$keys)||array_diff($keys,array_keys($command)))throw new DomainException('native_task_original_invalid');
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);
        $project=$this->taskKind()==='project';$op=$project?'task_native_original':'standalone_task_native_original';
        $this->nativeOriginalRequest=$command;
        try{return $this->operation($issuer,$actor,in_array($action,['comment_edit','comment_remove'],true)?'view':'edit',$key,$op,[$id,$revision,$fieldsRevision,$command],function()use($issuer,$actor,$id,$revision,$fieldsRevision,$command,$action,$project):int{
            $this->rememberNativeTaskParent($issuer,$actor,$id);$before=$this->row($issuer,$id);
            if((int)$before['revision']!==$revision||($before['native_fields_revision']===null?null:(int)$before['native_fields_revision'])!==$fieldsRevision)throw new DomainException('company_project_stale');
            if($project)$this->assertTaskUnclaimed($issuer,$actor,$id);else$this->assertBillingMutable($issuer,$actor,$id);
            $table=$project?'company_tasks':'company_standalone_tasks';$source=$project?'company_task':'company_standalone_task';
            $expected=$before;$auditBefore=[];$auditAfter=[];
            if($action==='description'){
                $expected['description']=self::text($command['description'],4000,false);
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET description=? WHERE issuer_id=? AND id=? AND revision=?',[$expected['description'],$issuer,$id,$revision]);
                $auditBefore=['description'=>$before['description']];$auditAfter=['description'=>$expected['description']];
            }elseif($action==='inline'){
                if(!is_array($command['dates'])||!$command['dates']||array_diff(array_keys($command['dates']),['startdate','duedate']))throw new DomainException('native_task_original_invalid');
                $fields=$before['native_fields_json']===null?null:json_decode($before['native_fields_json'],true,32,JSON_THROW_ON_ERROR);
                $auditBefore=['due_date'=>$before['due_date'],'fields'=>$fields];
                if(array_key_exists('duedate',$command['dates'])){
                    $expected['due_date']=$command['dates']['duedate']===null?null:NativeCompanyTaskFormValues::date($command['dates']['duedate']);
                    IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET due_date=? WHERE issuer_id=? AND id=? AND revision=?',[$expected['due_date'],$issuer,$id,$revision]);
                }
                if(array_key_exists('startdate',$command['dates'])){
                    if($fields===null)throw new DomainException('native_task_declaration_required');$fields['start_date']=NativeCompanyTaskFormValues::date($command['dates']['startdate']);
                    $status=match($before['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_task_original_invalid')};
                    NativeCompanyTaskFormValues::normalize($fields+['workflow_status'=>$status]);
                    $this->saveNativeTaskFields($issuer,$id,$fields,$fieldsRevision);$expected['native_fields_json']=json_encode($fields,JSON_THROW_ON_ERROR);$expected['native_fields_revision']=$fieldsRevision+1;
                }
                if($fields!==null&&$expected['due_date']!==null&&$expected['due_date']<$fields['start_date'])throw new DomainException('native_task_original_invalid');
                $this->references($issuer,$expected);
                $auditAfter=['due_date'=>$expected['due_date'],'fields'=>$fields];
            }elseif(in_array($action,['comment_edit','comment_remove'],true)){
                $comment=IssuerPolicy::id($command['comment_id']);$expectedComment=$command['comment_revision'];
                if(!is_string($expectedComment)||!preg_match('/^[a-f0-9]{64}$/D',$expectedComment))throw new DomainException('native_task_original_invalid');
                $commentTable=$project?'company_task_comments':'company_standalone_comments';
                $query='SELECT id,issuer_id,task_id,actor_id,body,created_at FROM '.$this->p.$commentTable.' WHERE issuer_id=? AND task_id=? AND id=? FOR UPDATE';$args=[$issuer,$id,$comment];
                $auditBefore=$this->rows($query,$args)[0]??null;
                if(!$auditBefore||$auditBefore['body']===''||!hash_equals($expectedComment,self::nativeCommentRevision($auditBefore)))throw new DomainException('native_task_comment_stale');
                NativeCompanyTaskCommentPolicy::assertAllowed($this->db,$this->p,$issuer,$actor,$auditBefore,$action);
                // Empty is a reserved hidden marker: new comments cannot be empty.
                // Preserve the source row/author/time and its original body in audit.
                $auditAfter=$auditBefore;$auditAfter['body']=$action==='comment_remove'?'':self::text($command['body'],4000);
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$commentTable.' SET body=? WHERE issuer_id=? AND task_id=? AND id=?',[$auditAfter['body'],$issuer,$id,$comment]);
                $rows=$this->rows($query,$args);if(count($rows)!==1||array_map('strval',$rows[0])!==array_map('strval',$auditAfter))throw new RuntimeException('native_task_original_unconfirmed');
                $this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>$args,'rows'=>$rows,'comment_authority'=>[$issuer,$actor,$auditBefore,$action]];
            }elseif($action==='public'){
                if(!in_array($command['value'],[0,1,'0','1'],true)||$before['native_fields_json']===null)throw new DomainException('native_task_original_invalid');
                $fields=json_decode($before['native_fields_json'],true,32,JSON_THROW_ON_ERROR);$auditBefore=['is_public'=>$fields['is_public']];$fields['is_public']=(int)$command['value'];
                $status=match($before['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_task_original_invalid')};
                NativeCompanyTaskFormValues::normalize($fields+['workflow_status'=>$status]);
                $this->saveNativeTaskFields($issuer,$id,$fields,$fieldsRevision);$expected['native_fields_json']=json_encode($fields,JSON_THROW_ON_ERROR);$expected['native_fields_revision']=$fieldsRevision+1;$auditAfter=['is_public'=>$fields['is_public']];
            }elseif($action==='tags'){
                require_once __DIR__.'/NativeCompanyResourceTags.php';$tags=new NativeCompanyResourceTags($this->db,$this->p);$canonical=NativeCompanyResourceId::encode($source,$id);
                if(!is_string($command['revision'])||!is_array($command['labels']))throw new DomainException('native_task_original_invalid');
                $auditBefore=$tags->read($issuer,$actor,'task',$canonical);$auditAfter=$tags->replaceInOwnedTransaction($issuer,$actor,'task',$canonical,$command['revision'],$command['labels']);
                $this->captureNativeTaskMetadata($issuer,$source,$id,['tags'=>true]);
            }elseif($action==='milestone'){
                $selection=['id'=>$command['id'],'revision'=>$command['revision']];$this->saveNativeTaskMilestone($issuer,$actor,$id,$selection);$auditAfter=$selection;
            }elseif(in_array($action,['assignee_add','assignee_remove'],true)){
                require_once __DIR__.'/NativeCompanyWorkAssignments.php';$members=new NativeCompanyWorkAssignments($this->db,$this->p);$kind=$project?'task':'standalone';$memberTable=$project?'company_task_assignees':'company_standalone_assignees';$staff=IssuerPolicy::id($command['staff_id']);
                if(!is_string($command['revision']))throw new DomainException('native_task_original_invalid');
                $query='SELECT * FROM '.$this->p.$memberTable.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$auditBefore=$this->rows($query,[$issuer,$id]);
                $ids=array_map('intval',array_column(array_filter($auditBefore,static fn($row)=>(int)$row['active']===1),'staff_id'));
                if($action==='assignee_add')$ids=array_values(array_unique([...$ids,$staff]));else$ids=array_values(array_filter($ids,static fn($candidate)=>$candidate!==$staff));
                $members->replaceInOwnedTransaction($issuer,$actor,$kind,$id,$command['revision'],$ids);$auditAfter=$this->rows($query,[$issuer,$id]);
                $this->nativeTaskRelatedFrames[]=['query'=>$query,'args'=>[$issuer,$id],'rows'=>$auditAfter];
            }else{
                $staff=IssuerPolicy::id($command['staff_id']);$followerTable=$project?'company_task_followers':'company_standalone_followers';IssuerTransaction::assertOwned($this->db,$this->p,[$followerTable]);
                $query='SELECT issuer_id,task_id,staff_id FROM '.$this->p.$followerTable.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$auditBefore=$this->rows($query,[$issuer,$id]);$ids=array_map('intval',array_column($auditBefore,'staff_id'));
                if($action==='follower_add'){
                    $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$staff);
                    if(!$acl->can($issuer,$staff,'tasks','view')||($project&&!$acl->can($issuer,$staff,'projects','view')))throw new DomainException('native_assignment_denied');
                    if(!in_array($staff,$ids,true)){if(count($ids)>=500)throw new DomainException('company_project_limit');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$followerTable.'(issuer_id,task_id,staff_id) VALUES(?,?,?)',[$issuer,$id,$staff]);$ids[]=$staff;}
                }else{IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$followerTable.' WHERE issuer_id=? AND task_id=? AND staff_id=?',[$issuer,$id,$staff]);$ids=array_values(array_filter($ids,static fn($candidate)=>$candidate!==$staff));}
                sort($ids,SORT_NUMERIC);$auditAfter=$this->rows($query,[$issuer,$id]);if(array_map('intval',array_column($auditAfter,'staff_id'))!==$ids)throw new RuntimeException('native_task_original_unconfirmed');
                $frame=['query'=>$query,'args'=>[$issuer,$id],'rows'=>$auditAfter];if($action==='follower_add')$frame['follower_authority']=[$issuer,[$staff],$project];$this->nativeTaskRelatedFrames[]=$frame;
            }
            $expected['revision']=$revision+1;$expected['updated_at']=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET revision=?,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$expected['revision'],$expected['updated_at'],$issuer,$id,$revision]);
            $normalize=static fn($row)=>array_map(static fn($value)=>$value===null?null:(string)$value,$row);
            if($normalize($this->row($issuer,$id))!==$normalize($expected))throw new RuntimeException('native_task_original_unconfirmed');
            $this->nativeOriginalContext=['action'=>$action,'before'=>$auditBefore,'after'=>$auditAfter,'revision'=>$revision,'result_revision'=>$revision+1];return$id;
        });}finally{$this->nativeOriginalContext=null;$this->nativeOriginalRequest=null;}
    }

    private function nativeOriginalReplay(int $issuer,int $actor,int $task):void{
        $request=$this->nativeOriginalRequest;
        if(!$request||!in_array($request['action'],['comment_edit','comment_remove'],true))return;
        $table=$this->taskKind()==='project'?'company_task_comments':'company_standalone_comments';
        $comment=IssuerPolicy::id($request['comment_id']);
        $rows=$this->rows('SELECT actor_id,created_at FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? AND id=? FOR UPDATE',[$issuer,$task,$comment]);
        if(count($rows)!==1)throw new DomainException('native_task_comment_denied');NativeCompanyTaskCommentPolicy::assertAllowed($this->db,$this->p,$issuer,$actor,$rows[0],$request['action']);
    }

    public static function nativeCommentRevision(array $row):string{
        $canonical=[];foreach(['id','issuer_id','task_id','actor_id','body','created_at']as$key){if(!array_key_exists($key,$row)||$row[$key]===null)throw new DomainException('native_task_comment_invalid');$canonical[$key]=(string)$row[$key];}
        return hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
}
