<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyMicrosoftMailOAuthSchema.php';

/** Closed queue adapter: only the existing canonical invoice and sales receipt readers. */
final class CompanyMicrosoftMailOAuthDispatchSource
{
    private object $email;
    public function __construct(private $db,private string $p,object $email,private string $kind,private string $key)
    {
        if(!in_array($kind,['invoice','sales'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new DomainException('company_microsoft_mail_queue_invalid');$this->email=clone$email;
    }
    public function assertFor(int $issuer):void
    {
        if((int)($this->email->issuer_id??0)!==$issuer)throw new DomainException('company_microsoft_mail_queue_invalid');
        if($this->kind==='invoice'){
            require_once __DIR__.'/CompanyInvoiceDelivery.php';(new CompanyInvoiceDelivery($this->db,$this->p))->assertDispatch($this->email,$this->key);
            // A fixed typed recurrence extension may add a schedule guard; never arbitrary callbacks.
            if(is_file(__DIR__.'/CompanyExpenseRecurringDeliverySource.php')){require_once __DIR__.'/CompanyExpenseRecurringDeliverySource.php';$headers=@unserialize($this->email->headers,['allowed_classes'=>false]);$marker=is_array($headers)?($headers[CompanyExpenseRecurringDeliverySource::HEADER]??null):null;if($marker!==null&&!is_string($marker))throw new DomainException('company_microsoft_mail_queue_invalid');CompanyExpenseRecurringDeliverySource::assertDispatchIfOwned($this->db,$this->p,$this->email,$this->key,$marker);}
            if(is_file(__DIR__.'/CompanyInvoiceRecurringDeliverySource.php')){require_once __DIR__.'/CompanyInvoiceRecurringDeliverySource.php';$headers=@unserialize($this->email->headers,['allowed_classes'=>false]);$marker=is_array($headers)?($headers[CompanyInvoiceRecurringDeliverySource::HEADER]??null):null;if($marker!==null&&!is_string($marker))throw new DomainException('company_invoice_recurring_denied');CompanyInvoiceRecurringDeliverySource::assertDispatchIfOwned($this->db,$this->p,$this->email,$this->key,$marker);}
        }else{require_once __DIR__.'/CompanySalesDelivery.php';(new CompanySalesDelivery($this->db,$this->p))->assertDispatch($this->email,$this->key);}
    }
    public function __debugInfo():array{return ['kind'=>$this->kind,'issuer_id'=>(int)($this->email->issuer_id??0)];}
    public function __serialize():array{throw new LogicException('company_microsoft_mail_runtime_only');}
}
