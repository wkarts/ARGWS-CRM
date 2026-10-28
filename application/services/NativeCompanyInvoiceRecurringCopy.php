<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceDrafts.php';
require_once __DIR__.'/CompanyInvoiceRecurring.php';

/** Recoverable handoff: a committed invoice and each schedule have independent idempotent receipts. */
final class NativeCompanyInvoiceRecurringCopy
{
    private ?array $authority=null;
    public function __construct(private $db,private string $prefix){}
    public function assertFor(int $issuer,int $actor,int $draft,array $row):void
    {
        if(!$this->authority||$this->authority['issuer']!==$issuer||$this->authority['actor']!==$actor||$this->authority['draft']!==$draft||!hash_equals($this->authority['source_hash'],(string)($row['source_hash']??'')))throw new DomainException('company_invoice_draft_recurring_required');
    }
    private function plans(array $draft):array
    {
        $plans=$draft['source']['native_metadata']['recurring']??null;if(!is_array($plans)||!array_is_list($plans)||!$plans)throw new DomainException('company_invoice_draft_recurring_required');
        foreach($plans as$plan)if(!is_array($plan)||!isset($plan['id'],$plan['fingerprint'],$plan['mode'],$plan['config'])||!is_int($plan['id'])||$plan['id']<1||!is_string($plan['fingerprint'])||!preg_match('/^[a-f0-9]{64}$/D',$plan['fingerprint'])||!in_array($plan['mode'],['issue','draft'],true)||!is_array($plan['config'])||CompanyInvoiceRecurringDates::configuration($plan['config'])!==$plan['config'])throw new DomainException('company_invoice_draft_recurring_required');
        return$plans;
    }
    public function issue(int $issuer,int $actor,int $draft,int $revision,string $key,?callable $decrypt=null):array
    {
        $drafts=new CompanyInvoiceDrafts($this->db,$this->prefix);$before=$drafts->document($issuer,$actor,$draft);$this->plans($before);$this->schedulePayload($before['payload'],$before['source']['native_metadata']['recurring'][0]['config']);
        $row=IssuerStorage::query($this->db,'SELECT source_hash FROM '.$this->prefix.'company_invoice_drafts WHERE id=? AND issuer_id=?',[$draft,$issuer])->row_array();if(!$row||!is_string($row['source_hash']))throw new DomainException('company_invoice_draft_recurring_required');
        $this->authority=['issuer'=>$issuer,'actor'=>$actor,'draft'=>$draft,'source_hash'=>$row['source_hash']];
        try{$invoice=$drafts->issue($issuer,$actor,$draft,$revision,$key,null,[],null,$this);}finally{$this->authority=null;}
        $result=$this->resume($issuer,$actor,$draft,$decrypt);return ['invoice_id'=>$invoice]+$result;
    }
    private function schedulePayload(array $payload,array $config):array
    {
        if(!is_string($payload['due_date']??null))throw new DomainException('company_invoice_recurring_invalid');
        $first=CompanyInvoiceRecurringDates::occurrence($payload['date'],$config,1);if($first===null)throw new DomainException('company_invoice_recurring_invalid');
        $from=new DateTimeImmutable($payload['date'],new DateTimeZone('UTC'));$due=new DateTimeImmutable($payload['due_date'],new DateTimeZone('UTC'));$days=(int)$from->diff($due)->format('%r%a');
        $payload['date']=$first;$payload['due_date']=(new DateTimeImmutable($first,new DateTimeZone('UTC')))->modify(($days>=0?'+':'').$days.' days')->format('Y-m-d');CompanyInvoiceRecurringDates::date($payload['due_date']);return$payload;
    }
    public function resume(int $issuer,int $actor,int $draft,?callable $decrypt=null):array
    {
        $drafts=new CompanyInvoiceDrafts($this->db,$this->prefix);$state=$drafts->document($issuer,$actor,$draft);$plans=$this->plans($state);if($state['state']!=='issued'||!is_int($state['invoice_id']))throw new DomainException('company_invoice_draft_recurring_required');
        $service=new CompanyInvoiceRecurring($this->db,$this->prefix);$completed=[];$pending=[];
        foreach($plans as$plan){$request=hash('sha256','native-draft-recurring:'.$issuer.':'.$draft.':'.$plan['id']);$spec=$plan['config']+['mode'=>$plan['mode'],'principal_invoice_id'=>$state['invoice_id']];
            try{$schedule=$service->create($issuer,$actor,$this->schedulePayload($state['payload'],$plan['config']),$spec,$request,$decrypt);$completed[]=['source_schedule_id'=>$plan['id'],'schedule_id'=>$schedule];}
            catch(Throwable $error){$pending[]=$plan['id'];}
        }
        return ['recurring_status'=>$pending?'pending':'scheduled','schedules'=>$completed,'pending_source_ids'=>$pending];
    }
}
