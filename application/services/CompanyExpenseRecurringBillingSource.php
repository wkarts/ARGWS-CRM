<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseRecurringStorage.php';

/** Closed source veto only. It never substitutes expense/invoice authority or accepts a caller callback. */
final class CompanyExpenseRecurringBillingSource extends CompanyExpenseRecurringStorage
{
    public function __construct($db,string $prefix,private int $issuer,private int $actor,private int $scheduleId,private string $date){parent::__construct($db,$prefix);}
    public function assertFor($db,string $prefix,int $issuer,int $actor,int $expense,int $origin):void
    {
        if($db!==$this->db||$prefix!==$this->prefix||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('company_expense_recurring_denied');IssuerTransaction::assertOwned($db,$prefix,$this->tables());CompanyExpenseRecurringSchema::assertValid($db,$prefix);$r=$this->schedule($issuer,$this->scheduleId);$run=$this->occurrence($issuer,$this->scheduleId,$this->date);if($run&&$run['state']==='completed'&&(int)$run['actor_id']===$actor&&(int)$run['expense_id']===$expense&&(int)$run['billing_origin_id']===$origin&&$run['invoice_id']!==null){$this->access($issuer,$actor,$this->decode($r['billing_json'])['config']);$link=$this->link($issuer,$expense);if(!$link||$link['state']!=='converted'||(int)$link['invoice_id']!==(int)$run['invoice_id'])throw new RuntimeException('issuer_expense_recurring_unconfirmed');$this->confirmedInvoice($issuer,$expense,$link);return;}if(!$run||$r['state']!=='processing'||$r['next_date']!==$this->date||(int)$run['actor_id']!==$actor||(int)$run['expense_id']!==$expense||(int)$run['billing_origin_id']!==$origin||!in_array($run['state'],['billing_prepared','invoiced','queued'],true)||!$this->decode($r['billing_json'])['config']['billable'])throw new DomainException('company_expense_recurring_paused');$this->validate($issuer,$actor,$r,$run);
    }
}
