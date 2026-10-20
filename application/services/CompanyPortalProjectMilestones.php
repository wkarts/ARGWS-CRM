<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectMilestonesStorage.php';require_once __DIR__.'/CompanyPortal.php';
/** Permission6, published private project and native flag + available feature, before hydration. */
final class CompanyPortalProjectMilestones extends CompanyProjectMilestonesStorage
{
    private CompanyPortal $portal;
    public function __construct($db,string $p,string $secret){parent::__construct($db,$p);$this->portal=new CompanyPortal($db,$p,$secret);}
    private function context(array $session,int $issuer,int $project):array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_project_portal_unavailable');$parent=$this->parent($issuer,$project);if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');$rules=$this->milestoneRules($issuer,$project);if($rules['view_milestones']!==1||$rules['project_milestones']!==1)throw new DomainException('company_project_portal_unavailable');return ['who'=>$who,'parent'=>$parent,'rules'=>$rules,'task_flags'=>$this->flags($issuer,$project)];
    }
    private function seconds(int $issuer,int $project,int $milestone):int
    {
        $source=$this->milestoneSource($issuer,$project,$milestone);$seconds=0;$total=0;
        foreach($source['tasks'] as $task){$row=$this->task($issuer,$project,$task);$pub=$this->rows('SELECT task_revision,source_hash FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$pub||(int)$pub['task_revision']!==(int)$row['revision']||!hash_equals($pub['source_hash'],self::hash($row)))continue;
            $timers=$this->rows('SELECT child_key,source_hash FROM '.$this->p.'company_portal_task_children WHERE issuer_id=? AND project_id=? AND task_id=? AND kind=? AND visible=1 ORDER BY child_key LIMIT 501 FOR UPDATE',[$issuer,$project,$task,'timer']);$total+=count($timers);if(count($timers)>500||$total>5000)throw new DomainException('company_project_portal_unavailable');
            foreach($timers as $timer){$key=self::childKey('timer',$timer['child_key']);$r=$this->child($issuer,$project,$task,'timer',$key);if(!hash_equals($timer['source_hash'],self::hash($r)))throw new DomainException('company_project_portal_unavailable');if($seconds>PHP_INT_MAX-(int)$r['seconds'])throw new DomainException('company_project_portal_unavailable');$seconds+=(int)$r['seconds'];}
        }return $seconds;
    }
    private function items(int $issuer,int $project,array $ctx):array
    {
        $pubs=$this->rows('SELECT milestone_id,source_hash FROM '.$this->p.'company_portal_milestones WHERE issuer_id=? AND project_id=? AND visible=1 ORDER BY milestone_id LIMIT 101 FOR UPDATE',[$issuer,$project]);if(count($pubs)>100)throw new DomainException('company_project_portal_unavailable');$out=[];
        foreach($pubs as $pub){$s=$this->milestoneSource($issuer,$project,(int)$pub['milestone_id'],false);if(!$s['details']||!hash_equals($pub['source_hash'],self::hash($s)))continue;$this->validateDates($issuer,$project,$s);$r=$s['milestone'];$d=$s['details'];$row=['id'=>(int)$r['id'],'name'=>$r['name'],'start_date'=>$d['start_date'],'due_date'=>$r['due_date'],'done'=>(int)$r['done'],'sort_order'=>(int)$d['sort_order']];if((string)$d['description_visible']==='1')$row['description']=$d['description'];if($ctx['task_flags']['view_task_total_logged_time']===1)$row['published_logged_seconds']=$this->seconds($issuer,$project,(int)$r['id']);$out[]=$row;}
        usort($out,static fn($a,$b)=>[$a['sort_order'],$a['due_date'],$a['id']]<=>[$b['sort_order'],$b['due_date'],$b['id']]);return $out;
    }
    public function page(array $session,$issuer,$project):?array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);
        return $this->tx(function()use($session,$issuer,$project):?array{
            try{$ctx=$this->context($session,$issuer,$project);}catch(DomainException $e){return null;}$items=$this->items($issuer,$project,$ctx);$this->audit($issuer,0,$project,'company_portal_project_milestones_listed',['contact_id'=>$ctx['who']['contact_id']]);if($this->context($session,$issuer,$project)!==$ctx||$this->items($issuer,$project,$ctx)!==$items)throw new DomainException('company_project_portal_unavailable');return ['items'=>$items];
        });
    }
}
