<?php
declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/CompanyService.php';
/** Native writer accepts only a canonical persisted first-payment proof, never webhook amounts. */
final class CompanyPixInitialReceipt implements \CompanyReceiptSource
{
 private array $tables=[];private ?array $claim=null;private ?int $event=null;private ?string $lease=null;
 public static function forJob(int $origin,int $proof,int $event,string $lease):self{if($event<1||!preg_match('/^[a-f0-9]{64}$/D',$lease))throw new \DomainException('asaas_pix_receipt_lease_lost');$out=new self($origin,$proof);$out->event=$event;$out->lease=$lease;return $out;}
 private function guard($db,string $p,int $issuer,int $actor):void{if($this->event!==null)(new CompanyPixReceiptJobs($db,$p))->assertLease($issuer,$actor,$this->event,$this->lease,$this->origin);}

 public function __construct(private int $origin,private int $proof){}
 public function assertSchema($db,string $p):void{CompanySchema::ensure($db,$p,false);CompanyOriginPolicy::requireReady($db,$p);$this->tables=(new CompanyService($db,$p))->tablesForRun();if($this->event!==null){require_once __DIR__.'/CompanyPixReceiptJobs.php';CompanyPixReceiptJobSchema::ensure($db,$p,false);$this->tables=array_merge($this->tables,array_keys(CompanyPixReceiptJobSchema::definitions()));}}
 public function tables():array{return $this->tables;}
 public function claim($db,string $p,int $issuer,int $actor):array{$this->guard($db,$p,$issuer,$actor);$this->claim=(new CompanyService($db,$p))->pixInitialClaim($issuer,$actor,$this->origin,$this->proof);return array_intersect_key($this->claim,array_flip(['invoice','key','input']));}
 public function applied($db,string $p,int $issuer,int $actor,int $payment):void
 {
  $this->guard($db,$p,$issuer,$actor);
  if($this->claim===null)throw new \DomainException('asaas_company_claim_required');
  (new CompanyService($db,$p))->pixInitialApplied($issuer,$actor,$this->origin,$this->claim,$payment);$this->guard($db,$p,$issuer,$actor);
 }
}
