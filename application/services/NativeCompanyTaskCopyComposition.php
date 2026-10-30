<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';
/** Original copy choices composed inside the existing reviewed task-copy transaction. */
trait NativeCompanyTaskCopyComposition
{
    private static function nativeCopyOptions($input):array
    {
        $keys=['assignees','followers','checklist','attachments','status','start_date'];
        if(!is_array($input)||array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input)))throw new DomainException('company_task_copy_invalid');
        $out=[];foreach(['assignees','followers','checklist','attachments']as$key){if(!in_array($input[$key],[0,1,'0','1'],true))throw new DomainException('company_task_copy_invalid');$out[$key]=(int)$input[$key];}
        if(!in_array($input['status'],[1,2,3,4,5,'1','2','3','4','5'],true))throw new DomainException('company_task_copy_invalid');
        $out['status']=(int)$input['status'];$out['start_date']=self::date($input['start_date']);return$out;
    }
    private function nativeCopySource(int $issuer,int $actor,string $kind,array $task):array
    {
        $canonical=NativeCompanyResourceId::encode($kind==='project'?'company_task':'company_standalone_task',$task['id']);
        $members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($issuer,$actor,$kind==='project'?'task':'standalone',$task['id']);
        $followers=$this->rows('SELECT staff_id FROM '.$this->p.($kind==='project'?'company_task_followers':'company_standalone_followers').' WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE',[$issuer,$task['id']]);
        $files=$kind==='project'?$this->rows('SELECT file_key,filename,mime,byte_size,content_hash FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND active=1 ORDER BY file_key FOR UPDATE',[$issuer,$task['project_id'],$task['id']]):[];
        if(count($files)>20)throw new DomainException('company_task_copy_invalid');
        return ['fields'=>json_decode($task['native_fields_json'],true,32,JSON_THROW_ON_ERROR),'assignees'=>$members,'followers'=>array_map('intval',array_column($followers,'staff_id')),
            'tags'=>array_column((new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'task',$canonical)['tags'],'name'),
            'custom_fields'=>(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($issuer,$actor,'tasks',$canonical),'files'=>$files];
    }
    private function nativeCopyTarget(string $kind,array $source,array $review):array
    {
        $options=$review['native'];$native=$source['native'];$task=$source['task'];$fields=$native['fields'];
        $fields['start_date']=$options['start_date'];$fields['workflow_status']=$options['status'];$fields['date_finished']=$options['status']===5?date('Y-m-d H:i:s'):null;
        if($kind==='project'){$fields['relation_type']='project';$fields['relation_source_kind']='company_project';$fields['relation_source_id']=$review['target_project_id'];}
        $assignees=$options['assignees']?array_map('intval',array_column($native['assignees']['members'],'staffid')):[];
        $related=['assignees'=>$assignees,'followers'=>$options['followers']?$native['followers']:[],'tags'=>$native['tags'],'custom_fields'=>$native['custom_fields']];
        $input=['assignee_id'=>$assignees[0]??null,'name'=>$review['name'],'description'=>$task['description'],'due_date'=>$review['due_date'],'status'=>NativeCompanyTaskFormValues::lifecycle($options['status']),
            'checklist'=>$options['checklist']?array_map(static fn($item)=>['text'=>$item['text'],'done'=>$options['status']===5?1:0],json_decode($task['checklist_json'],true,32,JSON_THROW_ON_ERROR)):[],'priority'=>$task['priority']];
        if($kind==='project')$input=['project_id'=>$review['target_project_id']]+$input;
        return[$input,$fields,$related];
    }
    private function nativeCopyFiles(int $issuer,int $actor,string $kind,array $source,int $target,int $project,string $request):void
    {
        if($kind!=='project'){if($source['native']['files'])throw new DomainException('company_task_copy_invalid');return;}
        IssuerTransaction::assertOwned($this->db,$this->p,['company_project_files','company_task_copy_requests']);$total=0;
        foreach($source['native']['files']as$file){
            $row=$this->rows('SELECT * FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND file_key=? AND active=1 FOR UPDATE',[$issuer,$source['task']['project_id'],$source['task']['id'],$file['file_key']])[0]??throw new DomainException('company_task_copy_stale');
            foreach($file as$key=>$value)if((string)$row[$key]!== (string)$value)throw new DomainException('company_task_copy_stale');
            $total+=(int)$row['byte_size'];if($total>20971520||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_project_file_corrupt');
            $key=hash('sha256',$request.':task-file:'.$row['file_key']);$row['file_key']=$key;$row['project_id']=$project;$row['task_id']=$target;$row['created_at']=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_files (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
            $saved=$this->rows('SELECT * FROM '.$this->p.'company_project_files WHERE issuer_id=? AND file_key=?',[$issuer,$key])[0]??[];
            if(array_map(static fn($v)=>$v===null?null:(string)$v,$saved)!==array_map(static fn($v)=>$v===null?null:(string)$v,$row))throw new RuntimeException('issuer_task_copy_unconfirmed');
        }
    }
}
