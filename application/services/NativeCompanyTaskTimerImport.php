<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyBlankTimerSchema.php';
require_once __DIR__.'/NativeCompanyWorkAssignmentsSchema.php';

/** Internal native blank-timer compositor. Never grants an arbitrary caller transaction authority. */
trait NativeCompanyTaskTimerImport
{
    public function nativeBlankTimerTables():array
    {
        return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit','company_blank_task_timers'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),array_keys(NativeCompanyWorkAssignmentsSchema::definitions()),CompanyTaskPrioritySchema::tables($this->db,$this->p),$this->apiTables())));
    }
    public function attachNativeBlankTimerInOwnedTransaction(int $issuer,int $actor,int $task,int $revision,int $blank,string $closedUtc):int
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->nativeBlankTimerTables());CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);NativeCompanyBlankTimerSchema::assertValid($this->db,$this->p);NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');
        $kind=$this->taskKind();$timerTable=$kind==='project'?'company_task_timers':'company_standalone_timers';$assignment=$kind==='project'?'company_task_assignees':'company_standalone_assignees';
        $before=$this->row($issuer,$task);if((int)$before['revision']!==$revision)throw new DomainException('company_project_stale');
        if($kind==='project'){$this->parent($issuer,(int)$before['project_id'],true);$this->assertTaskUnclaimed($issuer,$actor,$task);}else{$this->assertBillingMutable($issuer,$actor,$task);}
        if(!in_array($before['status'],['todo','in_progress'],true))throw new DomainException('company_project_closed');
        $member=$this->rows('SELECT staff_id,active,revision FROM '.$this->p.$assignment.' WHERE issuer_id=? AND task_id=? AND staff_id=? FOR UPDATE',[$issuer,$task,$actor])[0]??null;
        if(!$member||(int)$member['active']!==1)throw new DomainException('native_timer_assignee_required');
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
        if($kind==='project')$this->assertTaskUnclaimed($issuer,$actor,$task);else$this->assertBillingMutable($issuer,$actor,$task);
        if($this->rows('SELECT staff_id,active,revision FROM '.$this->p.$assignment.' WHERE issuer_id=? AND task_id=? AND staff_id=? FOR UPDATE',[$issuer,$task,$actor])[0]!==$member)throw new DomainException('native_timer_assignee_required');$this->authorize($issuer,$actor,'edit');return $id;
    }
}
