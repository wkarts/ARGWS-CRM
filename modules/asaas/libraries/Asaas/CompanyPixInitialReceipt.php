<?php
declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/CompanyService.php';
/** Native writer accepts only a canonical persisted first-payment proof, never webhook amounts. */
final class CompanyPixInitialReceipt implements \CompanyReceiptSource
{
 private array $tables=[];private ?array $claim=null;
 public function __construct(private int $origin,private int $proof){}
 public function assertSchema($db,string $p):void{CompanySchema::ensure($db,$p,false);CompanyOriginPolicy::requireReady($db,$p);$this->tables=(new CompanyService($db,$p))->tablesForRun();}
 public function tables():array{return $this->tables;}
 public function claim($db,string $p,int $issuer,int $actor):array{$this->claim=(new CompanyService($db,$p))->pixInitialClaim($issuer,$actor,$this->origin,$this->proof);return array_intersect_key($this->claim,array_flip(['invoice','key','input']));}
 public function applied($db,string $p,int $issuer,int $actor,int $payment):void
 {
  if($this->claim===null)throw new \DomainException('asaas_company_claim_required');
  (new CompanyService($db,$p))->pixInitialApplied($issuer,$actor,$this->origin,$this->claim,$payment);
 }
}
