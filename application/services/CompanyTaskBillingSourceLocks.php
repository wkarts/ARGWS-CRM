<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTaskBillingSchema.php';require_once __DIR__.'/CompanyProjectFixedBillingFence.php';
/** Adds conditional participants to the existing storage participant hook; no API authority. */
trait CompanyTaskBillingSourceLocks
{
    protected function apiTables():array
    {
        $t=array_merge(parent::apiTables(),CompanyProjectFixedBillingFence::tables($this->db,$this->p));if(!$this->db->table_exists($this->p.'company_task_billing_links'))return $t;CompanyTaskBillingSchema::assertValid($this->db,$this->p);return array_values(array_unique(array_merge($t,['company_task_billing_links','company_task_billing_origins'])));
    }
    private function assertTaskUnclaimed(int $issuer,int $actor,int $task):void
    {
        $parent=$this->rows('SELECT project_id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND id=?',[$issuer,$task])[0]??null;if(!$parent)throw new DomainException('company_project_access_denied');CompanyProjectFixedBillingFence::assertAvailableInOwned($this->db,$this->p,$issuer,$actor,(int)$parent['project_id']);
        if($this->db->table_exists($this->p.'company_task_billing_links')&&$this->rows('SELECT task_id FROM '.$this->p.'company_task_billing_links WHERE issuer_id=? AND task_id=? AND state IN (?,?) FOR UPDATE',[$issuer,$task,'converting','converted']))throw new DomainException('company_task_billing_immutable');
    }
    private function assertProjectNoPendingBilling(int $issuer,int $project):void
    {
        if($this->db->table_exists($this->p.'company_task_billing_links')&&$this->rows('SELECT l.task_id FROM '.$this->p.'company_task_billing_links l JOIN '.$this->p.'company_tasks t ON t.id=l.task_id AND t.issuer_id=l.issuer_id WHERE l.issuer_id=? AND t.project_id=? AND l.state=? LIMIT 1 FOR UPDATE',[$issuer,$project,'converting']))throw new DomainException('company_task_billing_immutable');
    }
}
