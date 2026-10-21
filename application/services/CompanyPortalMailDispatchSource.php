<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalMailQueueConsumer.php';

/** Closed recovery source; no ambient staff/company and no arbitrary authorization callback. */
final class CompanyPortalMailDispatchSource
{
    private object$email;
    public function __construct(private$db,private string$p,object$email,private string$key,private string$secret){$this->email=clone$email;}
    public function assertFor(int$issuer):void{if((int)($this->email->issuer_id??0)!==$issuer)throw new DomainException('company_portal_mail_denied');(new CompanyPortalMailQueueConsumer($this->db,$this->p,$this->secret))->assertDispatch($this->email,$this->key);}
    public function __debugInfo():array{return['issuer_id'=>(int)($this->email->issuer_id??0)];}
    public function __serialize():array{throw new LogicException('company_portal_mail_runtime_only');}
}
