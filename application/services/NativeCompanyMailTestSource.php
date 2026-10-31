<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyMailTest.php';
final class NativeCompanyMailTestSource
{
    private object $email;
    public function __construct(private $db,private string $p,object $email,private array $headers){$this->email=clone$email;}
    public function assertFor(int $issuer):void{if((int)($this->email->issuer_id??0)!==$issuer)throw new DomainException('native_mail_test_denied');(new NativeCompanyMailTest($this->db,$this->p))->state($this->email,$this->headers);}
    public function __debugInfo():array{return ['issuer_id'=>(int)($this->email->issuer_id??0)];}
    public function __serialize():array{throw new LogicException('native_mail_test_runtime_only');}
}
