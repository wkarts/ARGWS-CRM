<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyMicrosoftMailOAuth.php';
require_once __DIR__.'/CompanyMicrosoftMailOAuthDispatchSource.php';

/** PHPMailer receives a short-lived credential only after the canonical job and connector are revalidated. */
final class CompanyMicrosoftMailOAuthTokenProvider implements \PHPMailer\PHPMailer\OAuthTokenProvider
{
    public function __construct(private CompanyMicrosoftMailOAuth $oauth,private int $issuer,private int $epoch,private string $email,private $encrypt,private $decrypt,private CompanyMicrosoftMailOAuthDispatchSource|CompanyPortalMailDispatchSource|NativeCompanyFileMailDispatchSource|NativeCompanyMailTestSource|NativeCompanyEventMailSource|null $source){}
    public function assertReady():void
    {
        if(!$this->source)throw new DomainException('company_microsoft_mail_queue_required');$this->source->assertFor($this->issuer);$this->oauth->assertReady($this->issuer,$this->epoch);$profile=$this->oauth->transportProfile($this->issuer);if(!$profile||!hash_equals($profile['sender_email'],$this->email))throw new DomainException('company_microsoft_mail_queue_invalid');
    }
    public function getOauth64():string
    {
        $this->assertReady();if(!is_callable($this->encrypt)||!is_callable($this->decrypt))throw new DomainException('company_microsoft_mail_secret_unavailable');
        $access=$this->oauth->accessToken($this->issuer,$this->epoch,$this->encrypt,$this->decrypt);
        // A refresh can yield while role, recipient, invoice or schedule authority changes.
        $this->assertReady();return base64_encode('user='.$this->email."\x01auth=Bearer ".$access."\x01\x01");
    }
    public function __debugInfo():array{return ['issuer_id'=>$this->issuer,'epoch'=>$this->epoch];}
    public function __serialize():array{throw new LogicException('company_microsoft_mail_runtime_only');}
}
