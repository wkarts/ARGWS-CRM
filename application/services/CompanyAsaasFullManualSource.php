<?php
declare(strict_types=1);
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyManualSettlements.php';
/** Only a confirmed durable full-manual command can cross the ordinary provider veto. */
final class CompanyAsaasFullManualSource
{
    private ?array $claim=null;
    public function __construct(private $db,private string $p,private string $key){}
    public function tables():array{return (new \Asaas\CompanyManualSettlements($this->db,$this->p))->tables();}
    public function claim($db,string $p,int $i,int $a,int $invoice,string $key,int $amount,string $hash):void
    {
        if($db!==$this->db||$p!==$this->p||$key!==$this->key)throw new DomainException('asaas_manual_command_denied');
        $this->claim=(new \Asaas\CompanyManualSettlements($db,$p))->claimInOwned($i,$a,$invoice,$key,$amount,$hash);
    }
    public function applied($db,string $p,int $i,int $a,int $invoice,string $key,int $payment,string $hash):void
    {
        if($db!==$this->db||$p!==$this->p||$key!==$this->key||$this->claim===null||(int)$this->claim['issuer_id']!==$i||(int)$this->claim['actor_id']!==$a)throw new DomainException('asaas_manual_command_denied');
        $input=json_decode($this->claim['input_json'],true,32,JSON_THROW_ON_ERROR);
        if($input['invoice']!==$invoice||$input['native_hash']!==$hash)throw new DomainException('asaas_manual_command_denied');
        (new \Asaas\CompanyManualSettlements($db,$p))->appliedInOwned($i,$a,$key,$payment);
    }
}
