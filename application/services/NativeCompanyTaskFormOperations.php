<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskFormSchema.php';
require_once __DIR__.'/NativeCompanyTaskFormValues.php';
/** Explicit native form entrypoints on the existing task writers, with the same transaction and billing fences. */
trait NativeCompanyTaskFormOperations
{
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
    private function nativeTaskTables():array
    {
        $kind=$this->taskKind()==='project'?'task':'standalone';$table=$kind==='task'?'company_task_assignees':'company_standalone_assignees';
        require_once __DIR__.'/NativeCompanyWorkAssignments.php';$tables=$this->db->table_exists($this->p.$table)?(new NativeCompanyWorkAssignments($this->db,$this->p))->tableNames($kind):[];
        if($this->db->table_exists($this->p.'company_task_checklist_templates')){require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';$tables=array_merge($tables,(new NativeCompanyTaskChecklistTemplates($this->db,$this->p))->tableNames());}
        if($this->db->table_exists($this->p.'company_work_tags')){require_once __DIR__.'/NativeCompanyResourceTags.php';$tables=array_merge($tables,NativeCompanyResourceTags::tableNames());}
        if($this->db->table_exists($this->p.'company_custom_fields')){require_once __DIR__.'/NativeCompanyCustomFields.php';$tables=array_merge($tables,(new NativeCompanyCustomFields($this->db,$this->p))->tableNames());}if($this->db->table_exists($this->p.'company_standalone_followers'))$tables[]='company_standalone_followers';return array_values(array_unique($tables));
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
        if(array_diff(array_keys($related),['assignees','tags','custom_fields','template_sources','followers'])||!isset($related['assignees'])||!is_array($related['assignees']))throw new DomainException('native_task_form_invalid');
        require_once __DIR__.'/NativeCompanyWorkAssignments.php';$kind=$this->taskKind()==='project'?'task':'standalone';$table=$kind==='task'?'company_tasks':'company_standalone_tasks';
        $proof=new stdClass();self::$nativeTaskCreationProofs??=new WeakMap();$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        self::$nativeTaskCreationProofs[$proof]=['db'=>$this->db,'prefix'=>$this->p,'issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'table'=>$table,'row'=>$row];
        try{(new NativeCompanyWorkAssignments($this->db,$this->p))->createTaskMembersInOwnedTransaction($issuer,$actor,$kind,$id,$related['assignees'],$proof);
            $memberTable=$kind==='task'?'company_task_assignees':'company_standalone_assignees';$memberQuery='SELECT * FROM '.$this->p.$memberTable.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE';$this->nativeTaskRelatedFrames[]=['query'=>$memberQuery,'args'=>[$issuer,$id],'rows'=>$this->rows($memberQuery,[$issuer,$id])];
            if(array_key_exists('followers',$related)&&!is_array($related['followers']))throw new DomainException('native_task_form_invalid');if(array_key_exists('followers',$related))$this->createNativeTaskFollowers($issuer,$actor,$id,$related['followers'],$proof);
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
        if($related===null)return;if(array_diff(array_keys($related),['tags','tags_revision','custom_fields'])||array_diff(['tags','tags_revision','custom_fields'],array_keys($related)))throw new DomainException('native_task_form_invalid');
        require_once __DIR__.'/NativeCompanyResourceTags.php';require_once __DIR__.'/NativeCompanyCustomFields.php';
        $source=$this->taskKind()==='project'?'company_task':'company_standalone_task';$canonical=NativeCompanyResourceId::encode($source,$id);
        (new NativeCompanyResourceTags($this->db,$this->p))->replaceInOwnedTransaction($issuer,$actor,'task',$canonical,$related['tags_revision'],$related['tags']);$this->captureNativeTaskMetadata($issuer,$source,$id,['tags'=>true]);
        (new NativeCompanyCustomFields($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,'tasks',$canonical,$related['custom_fields']);
        $this->captureNativeTaskMetadata($issuer,$source,$id,$related);
    }
    private function confirmNativeTaskRelatedFrames():void
    {
        foreach($this->nativeTaskRelatedFrames as$f){if($this->rows($f['query'],$f['args'])!==$f['rows'])throw new RuntimeException('issuer_project_write_unconfirmed');if(isset($f['follower_authority'])){[$i,$members,$project]=$f['follower_authority'];$acl=new IssuerResourceAccess($this->db,$this->p);foreach($members as$member)if(!$acl->can($i,$member,'tasks','view')||($project&&!$acl->can($i,$member,'projects','view')))throw new DomainException('native_assignment_denied');}}
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
            // Other native relation writers must join their own guarded tables before admission.
            throw new DomainException('native_task_relation_writer_required');
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
            $this->confirmNativeTaskTemplateInput($issuer,$actor,$data,$related);$stored=$this->nativeTaskFields($issuer,$actor,$data,$fields);$this->nativeExplicitFollowers=is_array($related)&&array_key_exists('followers',$related);try{$id=$this->insertCreatedInOwned($issuer,$actor,$data);}finally{$this->nativeExplicitFollowers=false;}$this->saveNativeTaskFields($issuer,$id,$stored,null);$this->nativeTaskRelated($issuer,$actor,$id,$related);$this->references($issuer,$data);return$id;
        });
    }
    public function updateNative($issuer,$actor,$id,$revision,$fieldsRevision,array$input,array$fields,string$key,?array$related=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$fieldsRevision=$fieldsRevision===null?null:self::revision($fieldsRevision);$data=self::input($input,true);$fields=NativeCompanyTaskFormValues::normalize($fields);
        NativeCompanyTaskFormSchema::assertValid($this->db,$this->p);$op=$this->taskKind()==='project'?'task_native_updated':'standalone_task_native_updated';
        return$this->operation($issuer,$actor,'edit',$key,$op,[$id,$revision,$fieldsRevision,$data,$fields,$related],function()use($issuer,$actor,$id,$revision,$fieldsRevision,$data,$fields,$related):int{
            $old=$this->row($issuer,$id);$actual=$old['native_fields_revision']===null?null:(int)$old['native_fields_revision'];if($actual!==$fieldsRevision)throw new DomainException('company_project_stale');
            $stored=$this->nativeTaskFields($issuer,$actor,$data,$fields);$this->updateCanonicalInOwned($issuer,$actor,$id,$revision,$data);$this->saveNativeTaskFields($issuer,$id,$stored,$fieldsRevision);$this->updateNativeTaskRelated($issuer,$actor,$id,$related);$this->references($issuer,$data);return$id;
        });
    }
}
