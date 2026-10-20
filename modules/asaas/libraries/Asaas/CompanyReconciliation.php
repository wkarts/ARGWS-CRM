<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyReceivedReceipt.php';

/** Persisted origin/actor, leased retries and canonical writer; never an interactive company session. */
final class CompanyReconciliation
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function transaction(callable $work)
    {
        CompanySchema::ensure($this->db,$this->p,false);return \IssuerTransaction::run($this->db,$this->p,CompanyService::tables(),$work);
    }
    private function policy(array $job):array
    {
        $service=new CompanyService($this->db,$this->p);$service->authorize((int)$job['issuer_id'],(int)$job['actor_id'],'settle');
        $account=$this->rows('SELECT a.* FROM '.$this->p.'asaas_company_accounts a JOIN '.$this->p.'asaas_company_origin_meta m ON m.account_row=a.id WHERE m.origin_id=? AND a.issuer_id=? FOR UPDATE',[$job['origin_id'],$job['issuer_id']])[0]??null;
        if(!$account||(int)$account['enabled']!==1||$account['verified_at']===null||(int)$account['automation_actor']!==(int)$job['actor_id'])throw new \DomainException('asaas_company_automation_denied');return $account;
    }
    private function claim():?array
    {
        return $this->transaction(function():?array{
            (new CompanyService($this->db,$this->p))->module();$now=gmdate('Y-m-d H:i:s');
            $job=$this->rows("SELECT * FROM {$this->p}asaas_company_jobs WHERE (state='pending' AND next_attempt_at<=?) OR (state='processing' AND lease_until<=?) ORDER BY event_id LIMIT 1 FOR UPDATE",[$now,$now])[0]??null;if(!$job)return null;
            try{$this->policy($job);if((int)$job['attempts']>=10)throw new \DomainException('asaas_company_attempts_exhausted');}
            catch(\DomainException $e){\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_jobs SET state='review_required',last_code='authorization_or_attempts_review',lease_token=NULL,lease_until=NULL,updated_at=? WHERE event_id=?",[$now,$job['event_id']]);return ['skipped'=>true];}
            $token=bin2hex(random_bytes(32));$until=gmdate('Y-m-d H:i:s',time()+300);
            \IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_jobs SET state='processing',attempts=attempts+1,lease_token=?,lease_until=?,updated_at=? WHERE event_id=?",[$token,$until,$now,$job['event_id']]);
            $claimed=$this->rows('SELECT * FROM '.$this->p.'asaas_company_jobs WHERE event_id=?',[$job['event_id']])[0]??null;
            if(!$claimed||$claimed['lease_token']!==$token)throw new \RuntimeException('issuer_asaas_job_unconfirmed');return $claimed;
        });
    }
    private function finish(array $job,string $state,string $code):void
    {
        $this->transaction(function()use($job,$state,$code):void{
            $row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_jobs WHERE event_id=? FOR UPDATE',[$job['event_id']])[0]??null;
            if(!$row||$row['state']!=='processing'||!hash_equals((string)$row['lease_token'],$job['lease_token']))return;
            $when=gmdate('Y-m-d H:i:s',time()+min(3600,30*(2**min(7,(int)$job['attempts']))));
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_jobs SET state=?,last_code=?,lease_token=NULL,lease_until=NULL,next_attempt_at=?,updated_at=? WHERE event_id=?',[$state,$code,$when,gmdate('Y-m-d H:i:s'),$job['event_id']]);
            $saved=$this->rows('SELECT state,last_code FROM '.$this->p.'asaas_company_jobs WHERE event_id=?',[$job['event_id']])[0]??[];if(($saved['state']??null)!==$state||($saved['last_code']??null)!==$code)throw new \RuntimeException('issuer_asaas_job_unconfirmed');
        });
    }
    public function runOnce(callable $decrypt,?callable $transport=null,int $limit=25):array
    {
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active())throw new \DomainException('asaas_company_worker_context_denied');
        if($limit<1||$limit>100)throw new \InvalidArgumentException('asaas_company_worker_limit');$result=['completed'=>0,'retry'=>0,'review'=>0];
        // Repair old435 observed events before claiming; every job freezes persisted ownership and automation actor.
        $this->transaction(fn()=>(new CompanyService($this->db,$this->p))->enqueueObservedJobsInOwnedTransaction($limit));
        for($i=0;$i<$limit;$i++){
            $job=$this->claim();if($job===null)break;if(isset($job['skipped'])){$result['review']++;continue;}
            try{
                // Every call uses persisted ownership; no network is held under a database transaction.
                $service=new CompanyService($this->db,$this->p);$proof=$service->query((int)$job['issuer_id'],(int)$job['actor_id'],(int)$job['origin_id'],$decrypt,$transport);
                $this->transaction(function()use($job):void{$this->policy($job);$current=$this->rows('SELECT state,lease_token FROM '.$this->p.'asaas_company_jobs WHERE event_id=? FOR UPDATE',[$job['event_id']])[0]??[];if(($current['state']??null)!=='processing'||!hash_equals((string)($current['lease_token']??''),$job['lease_token']))throw new \DomainException('asaas_company_worker_lease_lost');});
                (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt((int)$job['issuer_id'],(int)$job['actor_id'],new CompanyReceivedReceipt((int)$job['origin_id'],$proof,(int)$job['event_id'],$job['lease_token']));
                $this->finish($job,'completed','canonical_receipt_applied');$result['completed']++;
            }catch(\DomainException $e){$this->finish($job,'review_required','canonical_or_authorization_review');$result['review']++;}
            catch(\Throwable $e){$state=(int)$job['attempts']>=10?'review_required':'pending';$this->finish($job,$state,'temporary_unconfirmed');$result[$state==='pending'?'retry':'review']++;}
        }
        return $result;
    }
}
