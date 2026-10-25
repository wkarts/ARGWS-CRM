<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectMilestonesStorage.php';require_once __DIR__.'/CompanyProjectGanttSchema.php';
abstract class CompanyProjectGanttStorage extends CompanyProjectMilestonesStorage
{
    public function tables():array{return array_merge(parent::tables(),array_keys(CompanyProjectGanttSchema::definitions($this->p)));}
    protected function tx(callable $work){CompanyProjectGanttSchema::assertValid($this->db,$this->p);return parent::tx($work);}
    protected function ganttRules(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT view_gantt,project_gantt,revision FROM '.$this->p.'company_project_gantt_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??['view_gantt'=>0,'project_gantt'=>0,'revision'=>0];foreach(['view_gantt','project_gantt'] as $f){if(!in_array((string)$r[$f],['0','1'],true))throw new DomainException('company_project_portal_unavailable');$r[$f]=(int)$r[$f];}$r['revision']=self::zeroRevision($r['revision']);return $r;
    }
    protected function dates(int $issuer,int $project,int $task):?array
    {
        $r=$this->rows('SELECT start_date,visible,revision,source_hash FROM '.$this->p.'company_task_gantt_dates WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;if($r){if(!in_array((string)$r['visible'],['0','1'],true))throw new DomainException('company_project_portal_unavailable');IssuerPolicy::id($r['revision']);self::date($r['start_date']);}return $r;
    }
    protected static function datesHash(int $issuer,int $project,int $task,string $date,int $revision):string{return self::hash([$issuer,$project,$task,$date,$revision]);}
    protected function interval(int $issuer,int $project,array $task,string $start):array
    {
        $r=$this->rows('SELECT start_date,due_date FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$r)throw new DomainException('company_project_portal_unavailable');self::date($r['start_date']);if($r['due_date']!==null)self::date($r['due_date']);self::date($task['due_date']);if($start<$r['start_date']||$start>$task['due_date']||($r['due_date']!==null&&$task['due_date']>$r['due_date']))throw new DomainException('company_project_portal_invalid');return $r;
    }
}
