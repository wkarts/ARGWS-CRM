<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffInviteMail.php';
/** Frozen source checked before and after OAuth refresh; never an arbitrary authorization callback. */
final class NativeCompanyStaffInviteMailSource
{
    private object $email;
    public function __construct(private $db,private string $p,object $email,private array $headers,private string $secret){$this->email=clone$email;}
    public function assertFor(int $issuer):void
    {
        if((int)($this->email->issuer_id??0)!==$issuer)throw new DomainException('native_staff_invite_denied');
        (new NativeCompanyStaffInviteMail($this->db,$this->p,$this->secret))->state($this->email,$this->headers);
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_staff_invite_runtime_only');}
}
