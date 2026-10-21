<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesStorage.php';require_once __DIR__.'/CompanyProjectTimesheetsSchema.php';
abstract class CompanyProjectTimesheetsStorage extends CompanyProjectPortalRulesStorage
{
    public function tables():array{return array_merge(parent::tables(),array_keys(CompanyProjectTimesheetsSchema::definitions($this->p)));}
    protected function tx(callable $work){CompanyProjectTimesheetsSchema::assertValid($this->db,$this->p);return parent::tx($work);}
    protected static function rulesRevision($v):int{if((!is_int($v)&&!is_string($v))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$v)||(int)$v>2147483646)throw new DomainException('company_project_portal_invalid');return (int)$v;}
    protected function timesheetRules(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT view_timesheets,project_timesheets,revision FROM '.$this->p.'company_project_timesheet_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??['view_timesheets'=>0,'project_timesheets'=>0,'revision'=>0];foreach(['view_timesheets','project_timesheets'] as $f){if(!in_array((string)$r[$f],['0','1'],true))throw new DomainException('company_project_portal_unavailable');$r[$f]=(int)$r[$f];}$r['revision']=self::rulesRevision($r['revision']);return $r;
    }
}
