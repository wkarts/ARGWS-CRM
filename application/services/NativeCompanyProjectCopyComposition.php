<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyProjectMetadata.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyProjectFormState.php';
/** Optional original copy form composition, inside the existing durable project-copy transaction. */
trait NativeCompanyProjectCopyComposition
{
    private static function nativeOptions(array $v):array
    {
        $keys=['client_id','checklist','assignees','followers','members','task_status'];
        if(array_diff(array_keys($v),$keys)||array_diff($keys,array_keys($v)))throw new DomainException('company_project_copy_invalid');
        $out=['client_id'=>IssuerPolicy::id($v['client_id'])];
        foreach(['checklist','assignees','followers','members']as$key){if(!in_array($v[$key],[0,1,'0','1'],true))throw new DomainException('company_project_copy_invalid');$out[$key]=(int)$v[$key];}
        if(!in_array($v['task_status'],[1,2,3,4,5,'1','2','3','4','5'],true))throw new DomainException('company_project_copy_invalid');$out['task_status']=(int)$v['task_status'];return$out;
    }
    private function nativeSource(int $issuer,int $actor,int $source,array $tasks):array
    {
        $canonical=NativeCompanyResourceId::encode('company_project',$source);
        $metadata=$this->db->table_exists($this->p.'company_project_native_metadata')?(new NativeCompanyProjectMetadata($this->db,$this->p))->readInOwnedTransaction($issuer,$actor,$source):null;
        $members=$this->db->table_exists($this->p.'company_project_members')?$this->rows('SELECT staff_id,active,revision,origin FROM '.$this->p.'company_project_members WHERE issuer_id=? AND project_id=? ORDER BY staff_id FOR UPDATE',[$issuer,$source]):[];
        $decorations=null;if($this->db->table_exists($this->p.'company_work_tags')&&$this->db->table_exists($this->p.'company_custom_fields'))$decorations=['tags'=>array_column((new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'project',$canonical)['tags'],'name'),'custom_fields'=>(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($issuer,$actor,'projects',$canonical)];
        $form=$this->db->table_exists($this->p.'company_project_native_form_state')?(new NativeCompanyProjectFormState($this->db,$this->p))->readInOwnedTransaction($issuer,$actor,$source):null;
        $children=[];foreach($tasks as$t){$id=$t['id'];$row=$this->rows('SELECT * FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$source,$id])[0]??throw new DomainException('company_project_copy_denied');$canonicalTask=NativeCompanyResourceId::encode('company_task',$id);
            $children[$id]=['fields'=>$row['native_fields_json']??null,'fields_revision'=>$row['native_fields_revision']??null,
                'assignees'=>$this->db->table_exists($this->p.'company_task_assignees')?$this->rows('SELECT staff_id,active,revision FROM '.$this->p.'company_task_assignees WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE',[$issuer,$id]):[],
                'followers'=>$this->rows('SELECT staff_id FROM '.$this->p.'company_task_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE',[$issuer,$id]),
                'tags'=>$decorations===null?null:array_column((new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'task',$canonicalTask)['tags'],'name'),
                'custom_fields'=>$decorations===null?null:(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($issuer,$actor,'tasks',$canonicalTask)];
        }
        return['metadata'=>$metadata,'members'=>$members,'decorations'=>$decorations,'form_state'=>$form,'tasks'=>$children];
    }
    private function nativeProjectTarget(array $source,array $options):array
    {
        $native=$source['native'];$metadata=$native['metadata'];if($metadata!==null){$metadata=array_intersect_key($metadata,array_flip(NativeCompanyProjectMetadataInput::FIELDS));$metadata['workflow_status']=1;$metadata['progress']=0;}
        $members=$options['members']?array_map('intval',array_column(array_filter($native['members'],static fn($m)=>(int)$m['active']===1),'staff_id')):[];
        $form=$native['form_state']['values']??null;if($form!==null){$form['date_finished']=null;if($options['client_id']!==$source['header']['client_id']){$form['notify_contacts']=[];$form['contact_notification']=0;}}
        return['metadata'=>$metadata,'members'=>$members,'decorations'=>$native['decorations'],'form_state'=>$form];
    }
    private function nativeTaskTarget(array $source,array $task,array $review,int $project):array
    {
        $options=$review['native'];$native=$source['native']['tasks'][$task['id']];$fields=$native['fields']===null?null:json_decode($native['fields'],true,32,JSON_THROW_ON_ERROR);
        // Historical tasks keep absent native metadata absent; copying does not invent a billable rate.
        if($fields!==null){$fields['start_date']=self::shifted($fields['start_date'],$source['header']['start_date'],$review['start_date']);$fields['workflow_status']=$options['task_status'];$fields['date_finished']=$options['task_status']===5?gmdate('Y-m-d H:i:s'):null;$fields['relation_type']='project';$fields['relation_source_kind']='company_project';$fields['relation_source_id']=$project;}
        $assignees=$options['assignees']?array_map('intval',array_column(array_filter($native['assignees'],static fn($m)=>(int)$m['active']===1),'staff_id')):[];
        if($options['assignees']&&$native['assignees']===[]&&$task['assignee_id'])$assignees=[$task['assignee_id']];
        $related=['assignees'=>$assignees,'followers'=>$options['followers']?array_map('intval',array_column($native['followers'],'staff_id')):[]];
        if($native['tags']!==null)$related+=['tags'=>$native['tags'],'custom_fields'=>$native['custom_fields']];
        $items=$options['checklist']?array_map(static fn($v)=>['text'=>$v['text'],'done'=>$options['task_status']===5?1:0],$task['checklist']):[];
        $input=['project_id'=>$project,'assignee_id'=>$assignees[0]??null,'name'=>$task['name'],'description'=>$task['description'],'due_date'=>$task['due_date']===null?null:self::shifted($task['due_date'],$source['header']['start_date'],$review['start_date']),'status'=>NativeCompanyTaskFormValues::lifecycle($options['task_status']),'checklist'=>$items,'priority'=>$task['priority']];
        return[$input,$fields,$related];
    }
}
