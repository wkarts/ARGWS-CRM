<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTasks.php';
require_once __DIR__.'/CompanyStandaloneTasks.php';
require_once __DIR__.'/NativeCompanyTaskTimerDetails.php';
require_once __DIR__.'/NativeCompanyTimerPolicy.php';
require_once __DIR__.'/NativeCompanyBlankTimers.php';
/** Optional action projection; staff-report authority never grants task mutation authority. */
final class NativeCompanyStaffTimerActions
{
    public function __construct(private $db,private string $p){}
    public function forRow(int $i,int $a,array $row):?array
    {
        if(empty($row['not_finished'])||(int)$row['staff_id']!==$a)return null;
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($i,$a,'tasks','view')||!$acl->can($i,$a,'tasks','edit'))return null;
        $kind=$row['source_kind'];$id=IssuerPolicy::id($row['source_id']);
        $table=match($kind){'company_task_timer'=>'company_task_timers','company_standalone_timer'=>'company_standalone_timers','company_blank_task_timer'=>'company_blank_task_timers',default=>throw new DomainException('native_staff_timesheet_denied')};
        $read=fn()=>IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND actor_id=? AND id=?',[$i,$a,$id])->row_array();$timer=$read();
        if(!$timer||($kind==='company_blank_task_timer'?($timer['state']!=='active'||$timer['closed_at']!==null):$timer['stopped_at']!==null))throw new DomainException('native_staff_timesheet_stale');
        $writer=null;$target=null;
        if($kind!=='company_blank_task_timer'){
            $ref=NativeCompanyResourceId::decode('task',$row['task_id']);$expected=$kind==='company_task_timer'?'company_task':'company_standalone_task';
            if($ref['source_kind']!==$expected||(int)$ref['source_id']!==(int)$timer['task_id'])throw new DomainException('native_staff_timesheet_denied');
            $writer=$kind==='company_task_timer'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);
            try{$target=$writer->nativeBlankTimerCandidate($i,$a,(int)$timer['task_id']);}
            catch(DomainException $e){if(in_array($e->getMessage(),['company_project_access_denied','company_project_closed','native_timer_assignee_required','company_task_billing_locked','company_project_billing_locked','company_standalone_billing_locked'],true))return null;throw $e;}
        }
        $policyReader=new NativeCompanyTimerPolicy($this->db,$this->p);$policy=$policyReader->read($i,$a);$details=new NativeCompanyTaskTimerDetails($this->db,$this->p);$note=$details->forOwnTimesheet($i,$a,$kind,$id);
        if($read()!==$timer||($writer&&$writer->nativeBlankTimerCandidate($i,$a,(int)$timer['task_id'])!==$target)||$policyReader->read($i,$a)!==$policy||$details->forOwnTimesheet($i,$a,$kind,$id)!==$note)throw new DomainException('native_staff_timesheet_stale');
        if(!$acl->can($i,$a,'tasks','view')||!$acl->can($i,$a,'tasks','edit'))throw new DomainException('native_staff_timesheet_denied');
        return ['timer_revision'=>$target?(int)$target['task']['revision']:(int)$timer['revision'],'note_revision'=>$note['revision'],'policy_hash'=>NativeCompanyBlankTimers::policyHash($policy)];
    }
}
