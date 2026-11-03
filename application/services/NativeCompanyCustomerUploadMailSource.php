<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomerUploadMail.php';
final class NativeCompanyCustomerUploadMailSource
{
    private object $mail;
    public function __construct(private $db,private string $p,object $mail,private array $headers){$this->mail=clone$mail;}
    public function assertFor(int $issuer):void{if((int)($this->mail->issuer_id??0)!==$issuer)throw new DomainException('native_customer_upload_mail_denied');(new NativeCompanyCustomerUploadMail($this->db,$this->p))->state($this->mail,$this->headers);}
    public function __debugInfo():array{return['issuer_id'=>(int)($this->mail->issuer_id??0)];}
    public function __serialize():array{throw new LogicException('native_customer_upload_mail_runtime_only');}
}
