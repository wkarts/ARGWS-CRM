<?php
declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/CompanyService.php';
/** Explicit per-member receipt from a persisted reviewed group and canonical query proof. */
final class CompanyInstallmentReceipt implements \CompanyReceiptSource
{
    private ?array $claim=null;private array $tables=[];
    public function __construct(private int $groupOrigin,private string $remote,private int $proof){}
    public function assertSchema($db,string $p):void{CompanySchema::ensure($db,$p,false);CompanyOriginPolicy::requireReady($db,$p);$this->tables=(new CompanyService($db,$p))->tablesForRun();}
    public function tables():array{return $this->tables;}
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        $this->claim=(new CompanyService($db,$p))->installmentReceiptClaim($issuer,$actor,$this->groupOrigin,$this->remote,$this->proof);
        return array_intersect_key($this->claim,array_flip(['invoice','key','input']));
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        if($this->claim===null)throw new \DomainException('asaas_company_claim_required');$service=new CompanyService($db,$p);
        if($service->installmentReceiptClaim($issuer,$actor,$this->groupOrigin,$this->remote,$this->proof)!==$this->claim)throw new \DomainException('asaas_company_receipt_conflict');
        $service->installmentReceiptApplied($issuer,$actor,$this->claim,$payment);
    }
}
