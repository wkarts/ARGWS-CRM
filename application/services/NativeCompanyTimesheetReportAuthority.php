<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Existing reports.view-timesheets does not grant staff identity, wages or task access. */
final class NativeCompanyTimesheetReportAuthority
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function assertTarget(int $issuer,int $actor,int $subject):void
    {
        foreach([$issuer,$actor,$subject]as$id)IssuerPolicy::id($id);
        $acl=new IssuerResourceAccess($this->db,$this->p);
        $guard=function()use($acl,$issuer,$actor,$subject):void{
            if(!$acl->member($issuer,$actor)||!$acl->can($issuer,$actor,'reports','view-timesheets'))throw new DomainException('native_timesheet_report_denied');
            $rows=IssuerStorage::query($this->db,'SELECT staffid FROM '.$this->p.'staff WHERE staffid=?',[$subject])->result_array();
            $links=IssuerStorage::query($this->db,'SELECT staff_id FROM '.$this->p.'issuers_staff WHERE issuer_id=? AND staff_id=? AND can_view=1',[$issuer,$subject])->result_array();
            if(count($rows)!==1||(!$acl->isInstallationMaster($subject)&&count($links)!==1))throw new DomainException('native_timesheet_report_denied');
        };
        $guard();$guard();
    }
    /** Only staff who actually have owned timer sources; identity is hydrated after target authority. */
    public function subjects(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->member($issuer,$actor)||!$acl->can($issuer,$actor,'reports','view-timesheets'))throw new DomainException('native_timesheet_report_denied');
        $sql='SELECT actor_id FROM '.$this->p.'company_task_timers WHERE issuer_id=? UNION SELECT actor_id FROM '.$this->p.'company_standalone_timers WHERE issuer_id=? ORDER BY actor_id';$args=[$issuer,$issuer];$ids=IssuerStorage::query($this->db,$sql,$args)->result_array();$result=[];
        foreach($ids as$row){$id=IssuerPolicy::id($row['actor_id']);try{$this->assertTarget($issuer,$actor,$id);}catch(DomainException $error){if($error->getMessage()==='native_timesheet_report_denied'&&$acl->member($issuer,$actor)&&$acl->can($issuer,$actor,'reports','view-timesheets'))continue;throw $error;}
            $q='SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE staffid=?';$name=IssuerStorage::query($this->db,$q,[$id])->row_array();if(!$name)throw new DomainException('native_timesheet_report_denied');$this->assertTarget($issuer,$actor,$id);if(IssuerStorage::query($this->db,$q,[$id])->row_array()!==$name)throw new DomainException('native_staff_timesheet_stale');$result[]=['staff_id'=>$id,'name'=>$name['firstname'].' '.$name['lastname']];
        }
        if(IssuerStorage::query($this->db,$sql,$args)->result_array()!==$ids||!$acl->member($issuer,$actor)||!$acl->can($issuer,$actor,'reports','view-timesheets'))throw new DomainException('native_staff_timesheet_stale');foreach($result as$entry){$this->assertTarget($issuer,$actor,$entry['staff_id']);$name=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$entry['staff_id']])->row_array();if(!$name||$name['firstname'].' '.$name['lastname']!==$entry['name'])throw new DomainException('native_staff_timesheet_stale');}return $result;
    }

}
