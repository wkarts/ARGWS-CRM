<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurringStorage.php';

/** Internal final veto only. Construction never grants invoice authority. */
final class CompanyInvoiceRecurringOrigin extends CompanyInvoiceRecurringStorage
{
    public function __construct($db,string$p,private int$i,private int$a,private int$id,private string$date,private string$contextHash){parent::__construct($db,$p);}
    public function assertCurrent():void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$this->access($this->i,$this->a);$r=$this->schedule($this->i,$this->id);$run=$this->occurrence($this->i,$this->id,$this->date);
        if(!$run||(int)$r['actor_id']!==$this->a||(int)$run['actor_id']!==$this->a||$run['request_key']!==self::runKey($this->i,$this->id,$this->date)||!hash_equals($r['context_hash'],$this->contextHash))throw new DomainException('company_invoice_recurring_conflict');
        if($run['state']==='completed'){$this->validate($this->i,$this->a,$r,$run,false);return;}
        if($r['state']!=='processing'||$r['next_date']!==$this->date||!in_array($run['state'],['processing','invoiced','queued'],true))throw new DomainException('company_invoice_recurring_paused');$this->validate($this->i,$this->a,$r,$run);
    }
}
