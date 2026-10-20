<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesStorage.php';require_once __DIR__.'/CompanyProjectMilestonesSchema.php';
abstract class CompanyProjectMilestonesStorage extends CompanyProjectPortalRulesStorage
{
    public function tables():array{return array_merge(parent::tables(),['company_project_milestones'],array_keys(CompanyProjectMilestonesSchema::definitions($this->p)));}
    protected function tx(callable $work){CompanyProjectMilestonesSchema::assertValid($this->db,$this->p);return parent::tx($work);}
    protected function milestoneRules(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT view_milestones,project_milestones,revision FROM '.$this->p.'company_milestone_portal_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??['view_milestones'=>0,'project_milestones'=>0,'revision'=>0];
        foreach(['view_milestones','project_milestones'] as $f){if(!in_array((string)$r[$f],['0','1'],true))throw new DomainException('company_project_portal_unavailable');$r[$f]=(int)$r[$f];}$r['revision']=self::zeroRevision($r['revision']);return $r;
    }
    protected static function zeroRevision($v):int{if((!is_int($v)&&!is_string($v))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$v)||(int)$v>2147483646)throw new DomainException('company_project_portal_invalid');return (int)$v;}
    protected function milestoneSource(int $issuer,int $project,int $milestone,bool $mapping=true):array
    {
        $r=$this->rows('SELECT id,issuer_id,project_id,name,due_date,done FROM '.$this->p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$milestone])[0]??null;
        if(!$r||!in_array((string)$r['done'],['0','1'],true))throw new DomainException('company_project_portal_unavailable');
        $d=$this->rows('SELECT start_date,description,description_visible,sort_order,revision FROM '.$this->p.'company_milestone_details WHERE issuer_id=? AND project_id=? AND milestone_id=? FOR UPDATE',[$issuer,$project,$milestone])[0]??null;
        if($d&&(!in_array((string)$d['description_visible'],['0','1'],true)||!preg_match('/^[0-9]+$/D',(string)$d['sort_order'])||(int)$d['sort_order']>10000||!is_string($d['description'])||strlen($d['description'])>16000||preg_match_all('/./us',$d['description'])>4000||preg_match('//u',$d['description'])!==1))throw new DomainException('company_project_portal_unavailable');
        if($d)IssuerPolicy::id($d['revision']);
        $out=['milestone'=>$r,'details'=>$d];if($mapping){$ids=$this->rows('SELECT task_id FROM '.$this->p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=? ORDER BY task_id LIMIT 1001 FOR UPDATE',[$issuer,$project,$milestone]);if(count($ids)>1000)throw new DomainException('company_project_portal_unavailable');$out['tasks']=array_map('intval',array_column($ids,'task_id'));}return $out;
    }
    protected function validateDates(int $issuer,int $project,array $source):void
    {
        $d=$source['details'];$r=$source['milestone'];if(!$d)throw new DomainException('company_project_portal_unavailable');$dates=$this->rows('SELECT start_date,due_date FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??[];self::date($d['start_date']);self::date($r['due_date']);if($d['start_date']<($dates['start_date']??'9999')||$d['start_date']>$r['due_date']||$r['due_date']>($dates['due_date']??''))throw new DomainException('company_project_portal_unavailable');
    }
    protected static function date($v):string{if(!is_string($v)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))throw new DomainException('company_project_portal_invalid');return $v;}
    protected function put(string $table,array $identity,array $fields):void
    {
        $where=implode(' AND ',array_map(static fn($f)=>$f.'=?',array_keys($identity)));$args=array_values($identity);
        if($this->rows('SELECT issuer_id FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args))IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($fields))).' WHERE '.$where,array_merge(array_values($fields),$args));
        else{$all=$identity+$fields;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' (`'.implode('`,`',array_keys($all)).'`) VALUES ('.implode(',',array_fill(0,count($all),'?')).')',array_values($all));}
        $saved=$this->rows('SELECT '.implode(',',array_keys($fields)).' FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args)[0]??[];foreach($fields as $f=>$v)if((string)($saved[$f]??'')!==(string)$v)throw new RuntimeException('issuer_project_milestones_unconfirmed');
    }
}
