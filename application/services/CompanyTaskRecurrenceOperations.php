<?php
declare(strict_types=1);

/** Explicit compositor: recurrence owns every participant, and uses the existing task inserter. */
trait CompanyTaskRecurrenceOperations
{
    public function validateRecurringTaskInOwnedTransaction(int $issuer,int $actor,array $input):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['company_task_schedules','company_task_schedule_runs','company_task_schedule_requests','company_task_schedule_cursor','issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),CompanyTaskPrioritySchema::tables($this->db,$this->p),$this->apiTables()));
        $data=self::input($input);if($data['status']!=='todo')throw new DomainException('company_task_recurring_invalid');
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$this->authorize($issuer,$actor,'edit');$this->references($issuer,$data);
    }
    public function createRecurringTaskInOwnedTransaction(int $issuer,int $actor,array $input):int
    {
        $this->validateRecurringTaskInOwnedTransaction($issuer,$actor,$input);
        return ($this->guardedWork(function()use($issuer,$actor,$input):int{
            $data=self::input($input);$id=$this->insertCreatedInOwned($issuer,$actor,$data);$this->audit($issuer,$actor,$id,'company_task_recurring_child_created');
            $this->validateRecurringTaskInOwnedTransaction($issuer,$actor,$input);return $id;
        }))();
    }
}
