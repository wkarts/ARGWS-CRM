<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectMilestonesStorage.php';
/** Staff-only native milestone exposure. Never changes task timers, billing or assignees. */
final class CompanyProjectMilestones extends CompanyProjectMilestonesStorage
{
    private function authorize(int $issuer,int $actor,string $cap='edit',bool $tasks=false):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'projects',$cap)||($tasks&&!$acl->can($issuer,$actor,'tasks','view')))throw new DomainException('company_project_portal_access_denied');
    }
    private function operation(int $issuer,int $actor,int $project,int $revision,string $key,string $op,array $input,bool $tasks,callable $work):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_portal_invalid');$hash=self::hash([$revision,$input]);
        $this->tx(function()use($issuer,$actor,$project,$revision,$key,$op,$hash,$tasks,$work):void{
            $this->authorize($issuer,$actor,'edit',$tasks);$parent=$this->parent($issuer,$project);$old=$this->rows('SELECT * FROM '.$this->p.'company_milestone_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||(int)$old['project_id']!==$project||$old['operation']!==$op||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_project_portal_request_conflict');return;}
            if((int)$parent['revision']!==$revision)throw new DomainException('company_project_portal_stale');$confirm=$work($parent);$this->audit($issuer,$actor,$project,'company_project_milestones_'.$op,['request_key'=>$key,'input_hash'=>$hash]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_milestone_requests(request_key,issuer_id,project_id,actor_id,operation,input_hash,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$project,$actor,$op,$hash,gmdate('Y-m-d H:i:s')]);
            if(!$this->rows('SELECT request_key FROM '.$this->p.'company_milestone_requests WHERE request_key=? AND issuer_id=? AND project_id=? AND actor_id=? AND operation=? AND input_hash=?',[$key,$issuer,$project,$actor,$op,$hash]))throw new RuntimeException('issuer_project_milestones_unconfirmed');
            $this->authorize($issuer,$actor,'edit',$tasks);if($this->parent($issuer,$project)!==$parent)throw new DomainException('company_project_portal_stale');$confirm();
        });
    }
    public function get($issuer,$actor,$project,$milestone=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$milestone=$milestone===null?null:IssuerPolicy::id($milestone);
        return $this->tx(function()use($issuer,$actor,$project,$milestone):array{
            $this->authorize($issuer,$actor,'view');$parent=$this->parent($issuer,$project);$rules=$this->milestoneRules($issuer,$project);$acl=new IssuerResourceAccess($this->db,$this->p);$canTasks=$acl->can($issuer,$actor,'tasks','view');$ids=$this->rows('SELECT id FROM '.$this->p.'company_project_milestones WHERE issuer_id=? AND project_id=? ORDER BY due_date,id LIMIT 101',[$issuer,$project]);if(count($ids)>100)throw new DomainException('company_project_portal_unavailable');$items=[];
            foreach($ids as $id){$s=$this->milestoneSource($issuer,$project,(int)$id['id'],false);$v=$this->rows('SELECT visible,source_hash FROM '.$this->p.'company_portal_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$issuer,$project,$id['id']])[0]??null;$items[]=['source'=>$s,'visible'=>$v&&(string)$v['visible']==='1'&&hash_equals($v['source_hash'],self::hash($s))];}
            $selected=$milestone===null?null:$this->milestoneSource($issuer,$project,$milestone,$canTasks);$tasks=$canTasks?$this->rows('SELECT id,name FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 1001',[$issuer,$project]):[];if(count($tasks)>1000)throw new DomainException('company_project_portal_unavailable');$this->authorize($issuer,$actor,'view');return ['project'=>$parent,'rules'=>$rules,'items'=>$items,'selected'=>$selected,'tasks'=>$tasks,'can_tasks'=>$canTasks,'can_edit'=>$acl->can($issuer,$actor,'projects','edit')];
        });
    }
    public function saveRules($issuer,$actor,$project,$revision,$rulesRevision,array $flags,array $features,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);$rulesRevision=self::zeroRevision($rulesRevision);
        if(array_keys($flags)!==['view_milestones']||array_keys($features)!==['project_milestones']||!in_array($flags['view_milestones'],[0,1,'0','1'],true)||!in_array($features['project_milestones'],[0,1,'0','1'],true))throw new DomainException('company_project_portal_invalid');$data=['view_milestones'=>(int)$flags['view_milestones'],'project_milestones'=>(int)$features['project_milestones']];
        $this->operation($issuer,$actor,$project,$revision,$key,'rules',[$rulesRevision,$data],false,function()use($issuer,$actor,$project,$rulesRevision,$data):callable{
            if($this->milestoneRules($issuer,$project)['revision']!==$rulesRevision)throw new DomainException('company_project_portal_stale');$this->put('company_milestone_portal_rules',['issuer_id'=>$issuer,'project_id'=>$project],$data+['revision'=>$rulesRevision+1,'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')]);return function()use($issuer,$project,$rulesRevision,$data):void{if($this->milestoneRules($issuer,$project)!==($data+['revision'=>$rulesRevision+1]))throw new RuntimeException('issuer_project_milestones_unconfirmed');};
        });
    }
    public function details($issuer,$actor,$project,$revision,$milestone,$detailsRevision,array $input,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);$milestone=IssuerPolicy::id($milestone);$detailsRevision=self::zeroRevision($detailsRevision);
        if(array_diff(array_keys($input),['start_date','description','description_visible','sort_order'])||count($input)!==4||!is_string($input['description']??null)||strlen($input['description'])>16000||preg_match_all('/./us',$input['description'])>4000||preg_match('//u',$input['description'])!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$input['description'])||!in_array($input['description_visible']??null,[0,1,'0','1'],true))throw new DomainException('company_project_portal_invalid');$order=self::zeroRevision($input['sort_order']);if($order>10000)throw new DomainException('company_project_portal_invalid');$data=['start_date'=>self::date($input['start_date']),'description'=>$input['description'],'description_visible'=>(int)$input['description_visible'],'sort_order'=>$order];
        $this->operation($issuer,$actor,$project,$revision,$key,'details',[$milestone,$detailsRevision,$data],false,function()use($issuer,$actor,$project,$milestone,$detailsRevision,$data):callable{
            $s=$this->milestoneSource($issuer,$project,$milestone,false);if((int)($s['details']['revision']??0)!==$detailsRevision)throw new DomainException('company_project_portal_stale');$dates=$this->rows('SELECT start_date,due_date FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=?',[$issuer,$project])[0];if($data['start_date']<$dates['start_date']||$data['start_date']>$s['milestone']['due_date']||($dates['due_date']!==null&&$s['milestone']['due_date']>$dates['due_date']))throw new DomainException('company_project_portal_invalid');
            $this->put('company_milestone_details',['issuer_id'=>$issuer,'project_id'=>$project,'milestone_id'=>$milestone],$data+['revision'=>$detailsRevision+1,'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')]);$after=$this->milestoneSource($issuer,$project,$milestone,false);return function()use($issuer,$project,$milestone,$after):void{if($this->milestoneSource($issuer,$project,$milestone,false)!==$after)throw new DomainException('company_project_portal_stale');};
        });
    }
    public function tasks($issuer,$actor,$project,$revision,$milestone,$detailsRevision,array $tasks,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);$milestone=IssuerPolicy::id($milestone);$detailsRevision=IssuerPolicy::id($detailsRevision);if(count($tasks)>1000)throw new DomainException('company_project_portal_invalid');$tasks=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$tasks)));sort($tasks,SORT_NUMERIC);
        $this->operation($issuer,$actor,$project,$revision,$key,'tasks',[$milestone,$detailsRevision,$tasks],true,function()use($issuer,$actor,$project,$milestone,$detailsRevision,$tasks):callable{
            $source=$this->milestoneSource($issuer,$project,$milestone);if(!$source['details']||(int)$source['details']['revision']!==$detailsRevision)throw new DomainException('company_project_portal_stale');foreach($tasks as $task){$this->task($issuer,$project,$task);if($this->rows('SELECT task_id FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND task_id=? AND milestone_id<>? FOR UPDATE',[$issuer,$project,$task,$milestone]))throw new DomainException('company_project_portal_invalid');}
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$issuer,$project,$milestone]);foreach($tasks as $task)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_milestones(issuer_id,project_id,task_id,milestone_id) VALUES(?,?,?,?)',[$issuer,$project,$task,$milestone]);
            $details=$source['details'];$details['revision']=$detailsRevision+1;$details['actor_id']=$actor;$details['updated_at']=gmdate('Y-m-d H:i:s');$this->put('company_milestone_details',['issuer_id'=>$issuer,'project_id'=>$project,'milestone_id'=>$milestone],$details);$after=$this->milestoneSource($issuer,$project,$milestone);if($after['tasks']!==$tasks)throw new RuntimeException('issuer_project_milestones_unconfirmed');return function()use($issuer,$project,$milestone,$after):void{if($this->milestoneSource($issuer,$project,$milestone)!==$after)throw new DomainException('company_project_portal_stale');};
        });
    }
    public function publish($issuer,$actor,$project,$revision,$milestone,$detailsRevision,bool $visible,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);$milestone=IssuerPolicy::id($milestone);$detailsRevision=IssuerPolicy::id($detailsRevision);
        $this->operation($issuer,$actor,$project,$revision,$key,'publication',[$milestone,$detailsRevision,$visible],false,function($parent)use($issuer,$actor,$project,$milestone,$detailsRevision,$visible):callable{
            $source=$this->milestoneSource($issuer,$project,$milestone,false);if(!$source['details']||(int)$source['details']['revision']!==$detailsRevision)throw new DomainException('company_project_portal_stale');if($visible){$this->canPublish($issuer,$project,$parent);$this->validateDates($issuer,$project,$source);}$fields=['visible'=>(int)$visible,'source_hash'=>self::hash($source),'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')];$this->put('company_portal_milestones',['issuer_id'=>$issuer,'project_id'=>$project,'milestone_id'=>$milestone],$fields);
            return function()use($issuer,$project,$milestone,$source,$fields,$visible,$parent):void{if($visible){$this->canPublish($issuer,$project,$parent);$this->validateDates($issuer,$project,$source);}$saved=$this->rows('SELECT visible,source_hash,actor_id,updated_at FROM '.$this->p.'company_portal_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$issuer,$project,$milestone])[0]??[];foreach($fields as $f=>$v)if((string)($saved[$f]??'')!==(string)$v)throw new RuntimeException('issuer_project_milestones_unconfirmed');if($this->milestoneSource($issuer,$project,$milestone,false)!==$source)throw new DomainException('company_project_portal_stale');};
        });
    }
    private function canPublish(int $issuer,int $project,array $parent):void
    {
        $r=$this->milestoneRules($issuer,$project);if($r['view_milestones']!==1||$r['project_milestones']!==1||!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');
    }
}
