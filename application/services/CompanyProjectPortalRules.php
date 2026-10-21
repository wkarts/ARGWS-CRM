<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesStorage.php';
/** Staff decisions use existing projects/edit. No flags for contact writes are accepted here. */
final class CompanyProjectPortalRules extends CompanyProjectPortalRulesStorage
{
    private function authorize(int $issuer,int $actor,string $cap='edit',bool $task=false):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'projects',$cap)||($task&&!$acl->can($issuer,$actor,'tasks','view')))throw new DomainException('company_project_portal_access_denied');
    }
    private function operation(int $issuer,int $actor,int $project,string $key,string $op,array $input,callable $work):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_portal_invalid');$hash=self::hash($input);
        $this->tx(function()use($issuer,$actor,$project,$key,$op,$hash,$work):void{
            $this->authorize($issuer,$actor,'edit',$op==='publication');$parent=$this->parent($issuer,$project);$old=$this->rows('SELECT * FROM '.$this->p.'company_project_portal_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||(int)$old['project_id']!==$project||$old['operation']!==$op||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_project_portal_request_conflict');return;}
            $confirm=$work($parent);$this->audit($issuer,$actor,$project,'company_project_portal_'.$op,['request_key'=>$key,'input_hash'=>$hash]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_portal_requests(request_key,issuer_id,project_id,actor_id,operation,input_hash,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$project,$actor,$op,$hash,gmdate('Y-m-d H:i:s')]);
            if(!$this->rows('SELECT request_key FROM '.$this->p.'company_project_portal_requests WHERE request_key=? AND issuer_id=? AND project_id=? AND actor_id=? AND operation=? AND input_hash=?',[$key,$issuer,$project,$actor,$op,$hash]))throw new RuntimeException('issuer_project_portal_unconfirmed');
            $this->authorize($issuer,$actor,'edit',$op==='publication');if(self::hash($this->parent($issuer,$project))!==self::hash($parent))throw new DomainException('company_project_portal_stale');$confirm();
        });
    }
    public function get($issuer,$actor,$project,$task=null,$after=0):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$task=$task===null?null:IssuerPolicy::id($task);$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);
        return $this->tx(function()use($issuer,$actor,$project,$task,$after):array{
            $this->authorize($issuer,$actor,'view');$parent=$this->parent($issuer,$project);$rules=$this->flags($issuer,$project);$acl=new IssuerResourceAccess($this->db,$this->p);$tasks=[];$selected=null;$selectedPublication=null;$next=null;$children=[];
            if($acl->can($issuer,$actor,'tasks','view')){
                $tasks=$this->rows('SELECT t.id,t.name,t.revision,COALESCE(v.visible,0) visible,v.task_revision FROM '.$this->p.'company_tasks t LEFT JOIN '.$this->p.'company_portal_project_tasks v ON v.issuer_id=t.issuer_id AND v.project_id=t.project_id AND v.task_id=t.id WHERE t.issuer_id=? AND t.project_id=? AND t.id>? ORDER BY t.id LIMIT 51',[$issuer,$project,$after]);$next=count($tasks)>50?(int)$tasks[49]['id']:null;$tasks=array_slice($tasks,0,50);
                if($task!==null){$selected=$this->task($issuer,$project,$task);$selectedPublication=$this->rows('SELECT visible,task_revision FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=?',[$issuer,$project,$task])[0]??null;foreach(['comment','timer','file','checklist'] as $kind){
                    if($kind==='comment')$keys=$this->rows('SELECT id child_key FROM '.$this->p.'company_task_comments WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 501',[$issuer,$task]);
                    elseif($kind==='timer')$keys=$this->rows('SELECT id child_key FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? AND stopped_at IS NOT NULL ORDER BY id LIMIT 501',[$issuer,$task]);
                    elseif($kind==='file')$keys=$this->rows('SELECT file_key child_key FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND active=1 ORDER BY file_key LIMIT 501',[$issuer,$project,$task]);
                    else{$raw=$this->rows('SELECT checklist_json FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id=?',[$issuer,$project,$task])[0];$list=json_decode($raw['checklist_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($list)||!array_is_list($list)||count($list)>30)throw new DomainException('company_project_portal_unavailable');$keys=array_map(static fn($i)=>['child_key'=>(string)$i],array_keys($list));}
                    if(count($keys)>500)throw new DomainException('company_project_portal_limit');foreach($keys as $key){$ck=(string)$key['child_key'];$source=$this->child($issuer,$project,$task,$kind,$ck);$v=$this->rows('SELECT visible,source_hash FROM '.$this->p.'company_portal_task_children WHERE issuer_id=? AND project_id=? AND task_id=? AND kind=? AND child_key=?',[$issuer,$project,$task,$kind,$ck])[0]??[];$children[]=['kind'=>$kind,'key'=>$ck,'source'=>$source,'visible'=>(int)($v['visible']??0)===1&&hash_equals($v['source_hash'],self::hash($source))];}
                }}
            }elseif($task!==null)throw new DomainException('company_project_portal_access_denied');
            $this->authorize($issuer,$actor,'view',$task!==null);return ['project'=>$parent,'rules'=>$rules,'tasks'=>$tasks,'next'=>$next,'task'=>$selected,'task_publication'=>$selectedPublication,'children'=>$children,'can_edit'=>$acl->can($issuer,$actor,'projects','edit'),'can_tasks'=>$acl->can($issuer,$actor,'tasks','view')];
        });
    }
    public function save($issuer,$actor,$project,$projectRevision,$rulesRevision,array $flags,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$projectRevision=IssuerPolicy::id($projectRevision);if((!is_int($rulesRevision)&&!is_string($rulesRevision))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$rulesRevision)||(int)$rulesRevision>2147483646||array_diff(array_keys($flags),CompanyProjectPortalRulesSchema::FLAGS)||array_diff(CompanyProjectPortalRulesSchema::FLAGS,array_keys($flags)))throw new DomainException('company_project_portal_invalid');$rulesRevision=(int)$rulesRevision;
        $data=[];foreach(CompanyProjectPortalRulesSchema::FLAGS as $f){if(!in_array($flags[$f],[0,1,'0','1'],true))throw new DomainException('company_project_portal_invalid');$data[$f]=(int)$flags[$f];}if($data['view_tasks']===0&&array_sum($data)!==0)throw new DomainException('company_project_portal_invalid');
        $this->operation($issuer,$actor,$project,$key,'rules',[$projectRevision,$rulesRevision,$data],function($parent)use($issuer,$actor,$project,$projectRevision,$rulesRevision,$data):callable{
            if((int)$parent['revision']!==$projectRevision||$this->flags($issuer,$project)['revision']!==$rulesRevision)throw new DomainException('company_project_portal_stale');$fields=$data+['revision'=>$rulesRevision+1,'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')];
            $this->put('company_project_portal_rules',['issuer_id'=>$issuer,'project_id'=>$project],$fields);
            return function()use($issuer,$project,$data,$rulesRevision):void{if($this->flags($issuer,$project)!==($data+['revision'=>$rulesRevision+1]))throw new RuntimeException('issuer_project_portal_unconfirmed');};
        });
    }
    private function put(string $table,array $identity,array $fields):void
    {
        $where=implode(' AND ',array_map(static fn($f)=>$f.'=?',array_keys($identity)));$args=array_values($identity);
        if($this->rows('SELECT issuer_id FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args))IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($fields))).' WHERE '.$where,array_merge(array_values($fields),$args));
        else{$all=$identity+$fields;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' (`'.implode('`,`',array_keys($all)).'`) VALUES ('.implode(',',array_fill(0,count($all),'?')).')',array_values($all));}
        $saved=$this->rows('SELECT '.implode(',',array_keys($fields)).' FROM '.$this->p.$table.' WHERE '.$where,$args)[0]??[];foreach($fields as $f=>$v)if((string)($saved[$f]??'')!==(string)$v)throw new RuntimeException('issuer_project_portal_unconfirmed');
    }
    public function publish($issuer,$actor,$project,$projectRevision,$task,$taskRevision,string $kind,$child,bool $visible,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$projectRevision=IssuerPolicy::id($projectRevision);$task=IssuerPolicy::id($task);$taskRevision=IssuerPolicy::id($taskRevision);$child=self::childKey($kind,$child);
        $this->operation($issuer,$actor,$project,$key,'publication',[$projectRevision,$task,$taskRevision,$kind,$child,$visible],function($parent)use($issuer,$actor,$project,$projectRevision,$task,$taskRevision,$kind,$child,$visible):callable{
            $r=$this->task($issuer,$project,$task);if((int)$parent['revision']!==$projectRevision||(int)$r['revision']!==$taskRevision)throw new DomainException('company_project_portal_stale');
            if($visible&&(!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project])||$this->flags($issuer,$project)['view_tasks']!==1))throw new DomainException('company_project_portal_unavailable');
            $source=$kind==='task'?$r:$this->child($issuer,$project,$task,$kind,$child);$sourceHash=self::hash($source);$identity=['issuer_id'=>$issuer,'project_id'=>$project,'task_id'=>$task];$table='company_portal_project_tasks';$fields=['task_revision'=>$taskRevision,'visible'=>(int)$visible,'source_hash'=>$sourceHash,'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')];
            if($kind!=='task'){if(!$this->rows('SELECT task_id FROM '.$this->p.$table.' WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task]))throw new DomainException('company_project_portal_unavailable');$table='company_portal_task_children';$identity+=['kind'=>$kind,'child_key'=>$child];unset($fields['task_revision']);}
            $this->put($table,$identity,$fields);
            return function()use($issuer,$project,$task,$r,$kind,$child,$sourceHash,$identity,$table,$fields,$visible,$parent):void{if($visible&&(!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project])||$this->flags($issuer,$project)['view_tasks']!==1))throw new DomainException('company_project_portal_unavailable');$where=implode(' AND ',array_map(static fn($f)=>$f.'=?',array_keys($identity)));$saved=$this->rows('SELECT '.implode(',',array_keys($fields)).' FROM '.$this->p.$table.' WHERE '.$where,array_values($identity))[0]??[];foreach($fields as $f=>$v)if((string)($saved[$f]??'')!==(string)$v)throw new RuntimeException('issuer_project_portal_unconfirmed');if(self::hash($this->task($issuer,$project,$task))!==self::hash($r)||self::hash($kind==='task'?$this->task($issuer,$project,$task):$this->child($issuer,$project,$task,$kind,$child))!==$sourceHash)throw new DomainException('company_project_portal_stale');};
        });
    }
}
