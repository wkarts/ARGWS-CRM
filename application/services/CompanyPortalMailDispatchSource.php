<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalMailQueueConsumer.php';

/** Closed recovery source; no ambient staff/company and no arbitrary authorization callback. */
final class CompanyPortalMailDispatchSource
{
    private object$email;
    public function __construct(private$db,private string$p,object$email,private string$key,private string$secret,private string$purpose='recovery'){if(!in_array($purpose,['recovery','contact-message','contact-verification','contact-managed'],true))throw new DomainException('company_portal_mail_denied');$this->email=clone$email;}
    public function assertFor(int$issuer):void{if((int)($this->email->issuer_id??0)!==$issuer)throw new DomainException('company_portal_mail_denied');if($this->purpose==='contact-managed'){require_once __DIR__.'/CompanyNativeContactMail.php';set_error_handler(static function(){throw new DomainException('company_contact_mail_denied');});try{$headers=unserialize($this->email->headers,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($headers)||($headers[CompanyNativeContactMail::HEADER]['key']??null)!==$this->key)throw new DomainException('company_contact_mail_denied');(new CompanyNativeContactMail($this->db,$this->p,$this->secret))->managedState($this->email,$headers);}elseif($this->purpose==='contact-verification'){require_once __DIR__.'/NativePortalVerificationMail.php';set_error_handler(static function(){throw new DomainException('native_portal_verification_denied');});try{$headers=unserialize($this->email->headers,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($headers)||($headers[NativePortalVerificationMail::HEADER]['key']??null)!==$this->key)throw new DomainException('native_portal_verification_denied');(new NativePortalVerificationMail($this->db,$this->p,$this->secret))->state($this->email,$headers);}elseif($this->purpose==='contact-message'){require_once __DIR__.'/CompanyNativeContactMessage.php';set_error_handler(static function(){throw new DomainException('company_contact_mail_denied');});try{$headers=unserialize($this->email->headers,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($headers)||($headers[CompanyNativeContactMail::HEADER]['key']??null)!==$this->key)throw new DomainException('company_contact_mail_denied');(new CompanyNativeContactMessage($this->db,$this->p,$this->secret))->state($this->email,$headers);}else (new CompanyPortalMailQueueConsumer($this->db,$this->p,$this->secret))->assertDispatch($this->email,$this->key);}
    public function __debugInfo():array{return['issuer_id'=>(int)($this->email->issuer_id??0)];}
    public function __serialize():array{throw new LogicException('company_portal_mail_runtime_only');}
}
