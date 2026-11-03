<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffInviteMail.php';
/** Concrete creation participant; base URL and decryptor come from trusted bootstrap, never POST. */
final class NativeCompanyStaffWelcome
{
    private Closure $decrypt;
    private ?int $created=null;
    private ?array $mutation=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private string $key,private string $base,private string $secret,callable $decrypt)
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||strlen($secret)<32)throw new DomainException('native_staff_invite_denied');$this->decrypt=Closure::fromCallable($decrypt);
    }
    public function assertFor($db,string $p,int $i,int $a):void{if($db!==$this->db||$p!==$this->p||$i!==$this->issuer||$a!==$this->actor)throw new DomainException('native_staff_invite_denied');}
    public function input():array{return['request_key'=>$this->key,'trusted_base_hash'=>hash('sha256',$this->base)];}
    public function tables():array{return(new NativeCompanyStaffInviteMail($this->db,$this->p,$this->secret))->tables();}
    public function apply(int $staff,CompanyStaff $writer):void
    {
        $this->mutation=(new NativeCompanyStaffInviteMail($this->db,$this->p,$this->secret))->issueAndQueueInOwned($this->issuer,$this->actor,$staff,$this->key,$this->base,$this->decrypt,$writer);$this->created=$staff;
    }
    public function profileMutation(int $staff):array
    {
        if($staff!==$this->created||$this->mutation===null||array_keys($this->mutation)!==['new_pass_key','new_pass_key_requested'])throw new DomainException('native_staff_invite_denied');return$this->mutation;
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_staff_invite_runtime_only');}

}
