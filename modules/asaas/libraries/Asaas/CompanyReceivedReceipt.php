<?php

declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/CompanyService.php';

/** No public payload or callback can directly supply a receipt; source selects persisted canonical proof. */
final class CompanyReceivedReceipt implements \CompanyReceiptSource
{
    private ?array $claim=null;
    private bool $hasBb=false;
    private bool $hasRemoteOperations=false;
    private bool $hasRefunds=false;
    public function __construct(private int $origin,private int $proof,private ?int $jobEvent=null,private ?string $leaseToken=null){\IssuerPolicy::id($origin);\IssuerPolicy::id($proof);if(($jobEvent===null)!==($leaseToken===null)||($jobEvent!==null&&($jobEvent<1||!preg_match('/^[a-f0-9]{64}$/D',$leaseToken))))throw new \DomainException('asaas_company_worker_lease_lost');}
    public function assertSchema($db,string $p):void{CompanySchema::ensure($db,$p,false);$this->hasBb=$db->table_exists($p.'bb_origins');$this->hasRemoteOperations=$db->table_exists($p.'asaas_company_remote_operations');if($this->hasRemoteOperations)CompanyRemoteOperationsSchema::ensure($db,$p,false);if($db->table_exists($p.'asaas_company_refund_requests')){require_once __DIR__.'/CompanyRefundSchema.php';CompanyRefundSchema::ensure($db,$p,false);$this->hasRefunds=true;}}
    public function tables():array{return array_merge(CompanyService::tables(),$this->hasBb?['bb_origins']:[],$this->hasRemoteOperations?['asaas_company_remote_operations']:[],$this->hasRefunds?array_keys(CompanyRefundSchema::definitions()):[]);}
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        if($this->hasRemoteOperations&&\IssuerStorage::query($db,'SELECT origin_id FROM '.$p.'asaas_company_remote_operations WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$this->origin,$issuer])->row_array())throw new \DomainException('asaas_company_remote_operation_pending');
        if($this->jobEvent!==null){
            \IssuerTransaction::assertOwned($db,$p,CompanyService::tables());
            $job=\IssuerStorage::query($db,'SELECT j.state,j.lease_token,j.lease_until,a.enabled,a.automation_actor FROM '.$p.'asaas_company_jobs j JOIN '.$p.'asaas_company_origin_meta m ON m.origin_id=j.origin_id JOIN '.$p.'asaas_company_accounts a ON a.id=m.account_row AND a.issuer_id=j.issuer_id JOIN '.$p.'asaas_payment_origins o ON o.id=j.origin_id AND o.issuer_id=j.issuer_id JOIN '.$p.'asaas_company_events e ON e.id=j.event_id AND e.account_row=a.id AND e.remote_id=o.remote_id WHERE e.event_type=\'PAYMENT_RECEIVED\' AND e.state=\'observed\' AND j.event_id=? AND j.issuer_id=? AND j.origin_id=? AND j.actor_id=? FOR UPDATE',[$this->jobEvent,$issuer,$this->origin,$actor])->row_array();
            if(!$job||$job['state']!=='processing'||!hash_equals((string)$job['lease_token'],$this->leaseToken)||$job['lease_until']<=gmdate('Y-m-d H:i:s')||(int)$job['enabled']!==1||(int)$job['automation_actor']!==$actor)throw new \DomainException('asaas_company_worker_lease_lost');
        }
        $this->claim=(new CompanyService($db,$p))->receiptClaim($issuer,$actor,$this->origin,$this->proof);
        return array_intersect_key($this->claim,array_flip(['invoice','key','input']));
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        if($this->claim===null)throw new \DomainException('asaas_company_claim_required');(new CompanyService($db,$p))->receiptApplied($issuer,$actor,$this->origin,$this->claim,$payment);
    }
}
