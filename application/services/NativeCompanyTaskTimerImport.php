<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyBlankTimerSchema.php';
require_once __DIR__.'/NativeCompanyWorkAssignmentsSchema.php';
require_once __DIR__.'/NativeCompanyTaskTimerCreationProof.php';
require_once __DIR__.'/NativeCompanyTaskTimerDetails.php';
require_once __DIR__.'/NativeCompanyTaskTimerOperations.php';

/** Internal native blank-timer compositor. Never grants an arbitrary caller transaction authority. */
trait NativeCompanyTaskTimerImport
{
    use NativeCompanyTaskTimerCreationProof;
    use NativeCompanyTaskTimerOperations;
    public function nativeBlankTimerTables():array
    {
        return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit','company_blank_task_timers'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),array_keys(NativeCompanyWorkAssignmentsSchema::definitions()),CompanyTaskPrioritySchema::tables($this->db,$this->p),$this->apiTables(),(new NativeCompanyTaskTimerDetails($this->db,$this->p))->tables())));
    }
    /** Read-only eligibility used by the original timer picker and rechecked by the same assignment writer. */
    public function nativeBlankTimerCandidate(int$issuer,int$actor,int$task):array
    {
        return $this->transaction(function()use($issuer,$actor,$task){return$this->nativeBlankTimerTargetInOwned($issuer,$actor,$task);});
    }
    public function nativeBlankTimerTargetInOwned(int$issuer,int$actor,int$task):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$task=IssuerPolicy::id($task);$kind=$this->taskKind();$table=$kind==='project'?'company_task_assignees':'company_standalone_assignees';
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit',$table],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),CompanyTaskPrioritySchema::tables($this->db,$this->p),$this->apiTables()));NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');
        $this->rememberNativeTaskParent($issuer,$actor,$task);$row=$this->row($issuer,$task);if(!in_array($row['status'],['todo','awaiting_feedback','testing','in_progress'],true))throw new DomainException('company_project_closed');$parent=null;
        if($kind==='project'){$parent=$this->parent($issuer,(int)$row['project_id'],true);$this->assertTaskUnclaimed($issuer,$actor,$task);}else$this->assertBillingMutable($issuer,$actor,$task);
        $member=$this->rows('SELECT staff_id,active,revision FROM '.$this->p.$table.' WHERE issuer_id=? AND task_id=? AND staff_id=? FOR UPDATE',[$issuer,$task,$actor])[0]??null;
        if($member!==null){if((int)$member['active']!==1)throw new DomainException('native_timer_assignee_required');$assignment=['basis'=>'explicit_membership','row'=>$member];}
        elseif($row['assignee_id']!==null&&(int)$row['assignee_id']===$actor)$assignment=['basis'=>'canonical_historical_assignee','staff_id'=>$actor];
        else throw new DomainException('native_timer_assignee_required');
        return['task'=>$row,'assignment'=>$assignment,'parent'=>$parent];
    }
    public function attachNativeBlankTimerInOwnedTransaction(int $issuer,int $actor,int $task,int $revision,int $blank,string $closedUtc,string$note=''):int
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->nativeBlankTimerTables());CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);NativeCompanyBlankTimerSchema::assertValid($this->db,$this->p);NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');
        $kind=$this->taskKind();$timerTable=$kind==='project'?'company_task_timers':'company_standalone_timers';$assignment=$kind==='project'?'company_task_assignees':'company_standalone_assignees';
        $target=$this->nativeBlankTimerTargetInOwned($issuer,$actor,$task);$before=$target['task'];if((int)$before['revision']!==$revision)throw new DomainException('company_project_stale');
        $source=$this->rows('SELECT * FROM '.$this->p.'company_blank_task_timers WHERE issuer_id=? AND actor_id=? AND id=? FOR UPDATE',[$issuer,$actor,$blank])[0]??null;
        if(!$source||$source['state']!=='active'||$source['closed_at']!==null||$source['target_kind']!==null||$source['target_id']!==null||$source['target_timer_id']!==null)throw new DomainException('native_timer_closed');
        $utc=new DateTimeZone('UTC');$start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$source['started_at'],$utc);$stop=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$closedUtc,$utc);
        if(!$start||!$stop||$start->format('Y-m-d H:i:s')!==$source['started_at']||$stop->format('Y-m-d H:i:s')!==$closedUtc||$stop<$start)throw new DomainException('native_timer_time_invalid');
        $seconds=$stop->getTimestamp()-$start->getTimestamp();if($seconds>2147483647)throw new DomainException('native_timer_time_invalid');
        // Existing owned timers persist the runtime datetime basis; convert the explicit UTC source once.
        $zone=new DateTimeZone(date_default_timezone_get());$started=$start->setTimezone($zone)->format('Y-m-d H:i:s');$stopped=$stop->setTimezone($zone)->format('Y-m-d H:i:s');
        $history=$this->rows('SELECT * FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$task]);if(count($history)>=500)throw new DomainException('company_project_limit');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$timerTable.'(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,?,?)',[$issuer,$task,$actor,$started,$stopped,$seconds]);$id=(int)$this->db->insert_id();
        $expected=['id'=>(string)$id,'issuer_id'=>(string)$issuer,'task_id'=>(string)$task,'actor_id'=>(string)$actor,'started_at'=>$started,'stopped_at'=>$stopped,'seconds'=>(string)$seconds];
        $after=$this->rows('SELECT * FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND task_id=? ORDER BY id',[$issuer,$task]);$history[]=$expected;
        $normalize=static fn($rows)=>array_map(static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r),$rows);
        if($id<1||$normalize($after)!==$normalize($history)||$this->row($issuer,$task)!==$before)throw new RuntimeException('issuer_native_timer_write_unconfirmed');
        $sourceKind=$kind==='project'?'company_task_timer':'company_standalone_timer';$actual=$this->rows('SELECT * FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND id=?',[$issuer,$id])[0];$proof=$this->nativeTimerCreationProof($issuer,$actor,$sourceKind,$id,$actual,$blank);(new NativeCompanyTaskTimerDetails($this->db,$this->p))->captureTransferInOwned($issuer,$actor,$sourceKind,$id,$proof,$blank,$note);
        if($kind==='project')$this->assertTaskUnclaimed($issuer,$actor,$task);else$this->assertBillingMutable($issuer,$actor,$task);
        if($this->nativeBlankTimerTargetInOwned($issuer,$actor,$task)!==$target)throw new DomainException('native_timer_assignee_required');$this->authorize($issuer,$actor,'edit');return $id;
    }
}
