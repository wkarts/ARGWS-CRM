<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectGanttStorage.php';require_once __DIR__.'/CompanyPortal.php';
/** Read-only milestone-grouped native Gantt; no inferred task start or private temporal data. */
final class CompanyPortalProjectGantt extends CompanyProjectGanttStorage
{
    private CompanyPortal $portal;
    public function __construct($db,string $p,string $secret){parent::__construct($db,$p);$this->portal=new CompanyPortal($db,$p,$secret);}
    private function context(array $session,int $issuer,int $project):array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_project_portal_unavailable');$parent=$this->parent($issuer,$project);if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');$rules=$this->ganttRules($issuer,$project);$flags=$this->flags($issuer,$project);if($rules['view_gantt']!==1||$rules['project_gantt']!==1||$flags['view_tasks']!==1)throw new DomainException('company_project_portal_unavailable');return ['who'=>$who,'parent'=>$parent,'rules'=>$rules,'flags'=>$flags,'milestone_rules'=>$this->milestoneRules($issuer,$project)];
    }
    private function milestone(int $issuer,int $project,int $id,array $ctx):?array
    {
        if($ctx['milestone_rules']['view_milestones']!==1||$ctx['milestone_rules']['project_milestones']!==1)return null;$pub=$this->rows('SELECT source_hash FROM '.$this->p.'company_portal_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$id])[0]??null;if(!$pub)return null;$s=$this->milestoneSource($issuer,$project,$id,false);if(!$s['details']||!hash_equals($pub['source_hash'],self::hash($s)))return null;$this->validateDates($issuer,$project,$s);return ['id'=>'milestone_'.$id,'name'=>$s['milestone']['name'],'start_date'=>$s['details']['start_date'],'due_date'=>$s['milestone']['due_date'],'sort_order'=>(int)$s['details']['sort_order'],'tasks'=>[]];
    }
    private function seconds(int $issuer,int $project,int $task,int &$read):int
    {
        $pub=$this->rows('SELECT child_key,source_hash FROM '.$this->p.'company_portal_task_children WHERE issuer_id=? AND project_id=? AND task_id=? AND kind=? AND visible=1 ORDER BY child_key LIMIT 501 FOR UPDATE',[$issuer,$project,$task,'timer']);$read+=count($pub);if(count($pub)>500||$read>5000)throw new DomainException('company_project_portal_unavailable');$total=0;foreach($pub as $v){$r=$this->child($issuer,$project,$task,'timer',self::childKey('timer',$v['child_key']));if(!hash_equals($v['source_hash'],self::hash($r))||$total>PHP_INT_MAX-(int)$r['seconds'])throw new DomainException('company_project_portal_unavailable');$total+=(int)$r['seconds'];}return $total;
    }
    private function projection(int $issuer,int $project,array $ctx):array
    {
        $projectDates=$this->rows('SELECT start_date,due_date FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0];self::date($projectDates['start_date']);self::date($projectDates['due_date']);
        $ids=$this->rows('SELECT t.id FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_portal_project_tasks v ON v.issuer_id=t.issuer_id AND v.project_id=t.project_id AND v.task_id=t.id AND v.visible=1 AND v.task_revision=t.revision JOIN '.$this->p.'company_task_gantt_dates d ON d.issuer_id=t.issuer_id AND d.project_id=t.project_id AND d.task_id=t.id AND d.visible=1 WHERE t.issuer_id=? AND t.project_id=? ORDER BY t.id LIMIT 1001 FOR UPDATE',[$issuer,$project]);if(count($ids)>1000)throw new DomainException('company_project_portal_unavailable');$groups=[];$milestones=[];$timerRead=0;
        foreach($ids as $id){$task=(int)$id['id'];$map=$this->rows('SELECT milestone_id FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;$group='milestone_0';
            if($map){$mid=(int)$map['milestone_id'];if(!array_key_exists($mid,$milestones))$milestones[$mid]=$this->milestone($issuer,$project,$mid,$ctx);if($milestones[$mid]===null)continue;$group='milestone_'.$mid;if(!isset($groups[$group]))$groups[$group]=$milestones[$mid];}
            $r=$this->task($issuer,$project,$task);if(!in_array($r['status'],['todo','in_progress','done'],true))throw new DomainException('company_project_portal_unavailable');$v=$this->rows('SELECT task_revision,source_hash FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$v||(int)$v['task_revision']!==(int)$r['revision']||!hash_equals($v['source_hash'],self::hash($r)))throw new DomainException('company_project_portal_unavailable');$dates=$this->dates($issuer,$project,$task);if(!$dates||(string)$dates['visible']!=='1'||!hash_equals($dates['source_hash'],self::datesHash($issuer,$project,$task,$dates['start_date'],(int)$dates['revision'])))throw new DomainException('company_project_portal_unavailable');try{$this->interval($issuer,$project,$r,$dates['start_date']);}catch(DomainException $invalid){if($invalid->getMessage()==='company_project_portal_invalid')continue;throw $invalid;}
            if(!isset($groups[$group]))$groups[$group]=['id'=>$group,'name'=>'Sem marco','start_date'=>$projectDates['start_date'],'due_date'=>$projectDates['due_date'],'sort_order'=>10001,'tasks'=>[]];$out=['id'=>$task,'name'=>$r['name'],'start_date'=>$dates['start_date'],'due_date'=>$r['due_date'],'status'=>$r['status']];if($ctx['flags']['view_task_total_logged_time']===1)$out['published_logged_seconds']=$this->seconds($issuer,$project,$task,$timerRead);$groups[$group]['tasks'][]=$out;
        }
        $groups=array_values(array_filter($groups,static fn($g)=>$g['tasks']!==[]));usort($groups,static fn($a,$b)=>[$a['sort_order'],$a['due_date'],$a['id']]<=>[$b['sort_order'],$b['due_date'],$b['id']]);foreach($groups as &$g)unset($g['sort_order']);unset($g);return ['start_date'=>$projectDates['start_date'],'due_date'=>$projectDates['due_date'],'groups'=>$groups];
    }
    public function page(array $session,$issuer,$project):?array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project):?array{try{$ctx=$this->context($session,$issuer,$project);}catch(DomainException $e){return null;}$out=$this->projection($issuer,$project,$ctx);$this->audit($issuer,0,$project,'company_portal_project_gantt_viewed',['contact_id'=>$ctx['who']['contact_id']]);if($this->context($session,$issuer,$project)!==$ctx||$this->projection($issuer,$project,$ctx)!==$out)throw new DomainException('company_project_portal_unavailable');return $out;});
    }
}
