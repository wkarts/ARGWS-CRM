<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesStorage.php';require_once __DIR__.'/CompanyPortal.php';
/** Native permission6 + private owner + published project + flags + individual publication. */
final class CompanyPortalProjectTasks extends CompanyProjectPortalRulesStorage
{
    private CompanyPortal $portal;
    public function __construct($db,string $p,string $portalSecret){parent::__construct($db,$p);$this->portal=new CompanyPortal($db,$p,$portalSecret);}
    private function context(array $session,int $issuer,int $project):array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_project_portal_unavailable');$parent=$this->parent($issuer,$project);
        if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');$flags=$this->flags($issuer,$project);if($flags['view_tasks']!==1)throw new DomainException('company_project_portal_unavailable');return ['who'=>$who,'parent'=>$parent,'flags'=>$flags];
    }
    private function visibleTask(int $issuer,int $project,int $task):array
    {
        $r=$this->task($issuer,$project,$task);$v=$this->rows('SELECT task_revision,source_hash FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$v||(int)$v['task_revision']!==(int)$r['revision']||!hash_equals($v['source_hash'],self::hash($r)))throw new DomainException('company_project_portal_unavailable');return $r;
    }
    private function summary(int $issuer,array $r,array $flags):array
    {
        $out=['id'=>(int)$r['id'],'name'=>$r['name'],'description'=>$r['description'],'status'=>$r['status'],'due_date'=>$r['due_date'],'revision'=>(int)$r['revision']];
        if($flags['view_team_members']===1){$assignee=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'company_tasks t JOIN '.$this->p.'staff s ON s.staffid=t.assignee_id AND s.active=1 WHERE t.issuer_id=? AND t.project_id=? AND t.id=?',[$issuer,$r['project_id'],$r['id']])[0]??null;$acl=new IssuerResourceAccess($this->db,$this->p);if($assignee&&$acl->can($issuer,(int)$assignee['staffid'],'projects','view')&&$acl->can($issuer,(int)$assignee['staffid'],'tasks','view'))$out['assignee_name']=trim($assignee['firstname'].' '.$assignee['lastname']);}
        return $out;
    }
    private function children(int $issuer,int $project,int $task,string $kind):array
    {
        $join=$kind==='file'?' JOIN '.$this->p.'company_project_files f ON f.issuer_id=v.issuer_id AND f.project_id=v.project_id AND f.task_id=v.task_id AND f.file_key=v.child_key AND f.active=1':'';$rows=$this->rows('SELECT v.child_key,v.source_hash FROM '.$this->p.'company_portal_task_children v'.$join.' WHERE v.issuer_id=? AND v.project_id=? AND v.task_id=? AND v.kind=? AND v.visible=1 ORDER BY v.child_key LIMIT 501 FOR UPDATE',[$issuer,$project,$task,$kind]);if(count($rows)>500)throw new DomainException('company_project_portal_unavailable');$out=[];
        foreach($rows as $publication){$key=self::childKey($kind,$publication['child_key']);$source=$this->child($issuer,$project,$task,$kind,$key);if(!hash_equals($publication['source_hash'],self::hash($source)))throw new DomainException('company_project_portal_unavailable');$out[]=$source;}return $out;
    }
    private function confirm(array $session,int $issuer,int $project,array $context,?array $task=null):void
    {
        if($this->context($session,$issuer,$project)!==$context)throw new DomainException('company_project_portal_unavailable');if($task!==null&&$this->visibleTask($issuer,$project,(int)$task['id'])!==$task)throw new DomainException('company_project_portal_unavailable');
    }
    public function page(array $session,$issuer,$project,$after=0):?array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);
        return $this->tx(function()use($session,$issuer,$project,$after):?array{
            try{$ctx=$this->context($session,$issuer,$project);}catch(DomainException $e){return null;}
            $ids=$this->rows('SELECT t.id FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_portal_project_tasks v ON v.issuer_id=t.issuer_id AND v.project_id=t.project_id AND v.task_id=t.id AND v.visible=1 AND v.task_revision=t.revision WHERE t.issuer_id=? AND t.project_id=? AND t.id>? ORDER BY t.id LIMIT 51',[$issuer,$project,$after]);$next=count($ids)>50?(int)$ids[49]['id']:null;$items=[];$proof=[];foreach(array_slice($ids,0,50) as $id){$r=$this->visibleTask($issuer,$project,(int)$id['id']);$items[]=$this->summary($issuer,$r,$ctx['flags']);$proof[]=$r;}
            $this->audit($issuer,0,$project,'company_portal_project_tasks_listed',['contact_id'=>$ctx['who']['contact_id'],'after'=>$after]);$this->confirm($session,$issuer,$project,$ctx);foreach($proof as $i=>$r){if($this->visibleTask($issuer,$project,(int)$r['id'])!==$r||$this->summary($issuer,$r,$ctx['flags'])!==$items[$i])throw new DomainException('company_project_portal_unavailable');}return ['items'=>$items,'next'=>$next];
        });
    }
    public function detail(array $session,$issuer,$project,$task):array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);$task=IssuerPolicy::id($task);
        return $this->tx(function()use($session,$issuer,$project,$task):array{
            $ctx=$this->context($session,$issuer,$project);$r=$this->visibleTask($issuer,$project,$task);$out=$this->summary($issuer,$r,$ctx['flags']);$summary=$out;$proof=[];foreach(['comment'=>'view_task_comments','checklist'=>'view_task_checklist_items','file'=>'view_task_attachments','timer'=>'view_task_total_logged_time'] as $kind=>$flag)if($ctx['flags'][$flag]===1)$proof[$kind]=$this->children($issuer,$project,$task,$kind);
            if($ctx['flags']['view_task_comments']===1)$out['comments']=array_map(static fn($r)=>['id'=>(int)$r['id'],'body'=>$r['body'],'created_at'=>$r['created_at']],$proof['comment']);
            if($ctx['flags']['view_task_checklist_items']===1)$out['checklist']=array_map(static fn($r)=>['text'=>$r['text'],'done'=>$r['done']],$proof['checklist']);
            if($ctx['flags']['view_task_attachments']===1)$out['files']=array_map(static fn($r)=>['file_key'=>$r['file_key'],'filename'=>$r['filename'],'mime'=>$r['mime'],'byte_size'=>(int)$r['byte_size']],$proof['file']);
            if($ctx['flags']['view_task_total_logged_time']===1){$seconds=0;foreach($proof['timer'] as $timer){if($seconds>PHP_INT_MAX-(int)$timer['seconds'])throw new DomainException('company_project_portal_unavailable');$seconds+=(int)$timer['seconds'];}$out['total_logged_seconds']=$seconds;}
            $this->audit($issuer,0,$project,'company_portal_project_task_viewed',['contact_id'=>$ctx['who']['contact_id'],'task_id'=>$task,'task_revision'=>(int)$r['revision']]);$this->confirm($session,$issuer,$project,$ctx,$r);if($this->summary($issuer,$r,$ctx['flags'])!==$summary)throw new DomainException('company_project_portal_unavailable');foreach($proof as $kind=>$source)if($this->children($issuer,$project,$task,$kind)!==$source)throw new DomainException('company_project_portal_unavailable');return $out;
        });
    }
    private function visibleFile(int $issuer,int $project,int $task,string $key):array
    {
        $source=$this->child($issuer,$project,$task,'file',$key);$pub=$this->rows('SELECT source_hash FROM '.$this->p.'company_portal_task_children WHERE issuer_id=? AND project_id=? AND task_id=? AND kind=? AND child_key=? AND visible=1 FOR UPDATE',[$issuer,$project,$task,'file',$key])[0]??null;if(!$pub||!hash_equals($pub['source_hash'],self::hash($source)))throw new DomainException('company_project_portal_unavailable');return $source;
    }
    public function file(array $session,$issuer,$project,$task,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);$task=IssuerPolicy::id($task);$key=self::childKey('file',$key);
        return $this->tx(function()use($session,$issuer,$project,$task,$key):array{
            $ctx=$this->context($session,$issuer,$project);if($ctx['flags']['view_task_attachments']!==1)throw new DomainException('company_project_portal_unavailable');$r=$this->visibleTask($issuer,$project,$task);$source=$this->visibleFile($issuer,$project,$task,$key);
            $file=$this->rows('SELECT filename,mime,byte_size,content_hash,content FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND file_key=? AND active=1 FOR UPDATE',[$issuer,$project,$task,$key])[0]??null;
            if(!$file||!in_array($file['mime'],['application/pdf','image/png'],true)||(int)$file['byte_size']<8||(int)$file['byte_size']>2097152||strlen($file['content'])!==(int)$file['byte_size']||!hash_equals($file['content_hash'],hash('sha256',$file['content'])))throw new RuntimeException('issuer_project_portal_file_corrupt');
            $this->audit($issuer,0,$project,'company_portal_project_task_file_downloaded',['contact_id'=>$ctx['who']['contact_id'],'task_id'=>$task,'file_key'=>$key,'sha256'=>$file['content_hash']]);$this->confirm($session,$issuer,$project,$ctx,$r);if($this->visibleFile($issuer,$project,$task,$key)!==$source)throw new DomainException('company_project_portal_unavailable');return $file;
        });
    }
    public function confirmFile(array $session,$issuer,$project,$task,string $key,string $contentHash):void
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);$task=IssuerPolicy::id($task);$key=self::childKey('file',$key);if(!preg_match('/^[a-f0-9]{64}$/D',$contentHash))throw new DomainException('company_project_portal_unavailable');
        $this->tx(function()use($session,$issuer,$project,$task,$key,$contentHash):void{$ctx=$this->context($session,$issuer,$project);if($ctx['flags']['view_task_attachments']!==1)throw new DomainException('company_project_portal_unavailable');$r=$this->visibleTask($issuer,$project,$task);$source=$this->visibleFile($issuer,$project,$task,$key);if(!hash_equals($contentHash,$source['content_hash']))throw new DomainException('company_project_portal_unavailable');$this->confirm($session,$issuer,$project,$ctx,$r);});
    }
}
