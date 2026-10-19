<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once __DIR__.'/CompanyRemoteOperationsSchema.php';

/** Unpaid remote-charge removal only. Each origin has at most one outbound DELETE. */
final class CompanyRemoteOperations
{
    private CompanyService $service;
    public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tables():array{return array_merge(CompanyService::tables(),[CompanyRemoteOperationsSchema::TABLE],$this->db->table_exists($this->p.'bb_origins')?['bb_origins']:[]);}
    private function run(int $issuer,int $actor,callable $fn)
    {
        CompanySchema::ensure($this->db,$this->p,false);CompanyRemoteOperationsSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$fn){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->service->authorize($issuer,$actor,'remote_remove');$m=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];if(version_compare($m['installed_version']??'0','1.5.0','<'))throw new \DomainException('asaas_company_remote_module_required');return $fn();});
    }
    private function source(int $issuer,int $actor,int $origin):array{return $this->service->remoteSourceInOwnedTransaction($issuer,$actor,$origin);}
    private function ledger(int $issuer,int $origin):?array{return $this->rows('SELECT * FROM '.$this->p.CompanyRemoteOperationsSchema::TABLE.' WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;}
    private function publicState(array $row):array{return array_intersect_key($row,array_flip(['origin_id','state','last_code','updated_at']));}
    private function proof(array $origin,array $account,bool $deleted=false):array
    {
        $proof=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin['id']])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['account_revision']!==$account['revision']||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_company_remote_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))||!in_array($safe['status']??'',['PENDING','OVERDUE'],true)||($safe['deleted']??null)!==$deleted||($safe['adverse']??true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!=='')throw new \DomainException('asaas_company_remote_unpaid_required');
        return $proof;
    }
    private function eligible(array $origin,array $meta,array $account,bool $confirmation=false):array
    {
        $id=(int)$origin['id'];$invoice=(int)$origin['invoice_id'];
        if(!$origin['remote_id']||(!$confirmation&&$origin['state']==='review_required'))throw new \DomainException('asaas_company_remote_review_required');
        if($this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$invoice])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$invoice])||$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$id]))throw new \DomainException('asaas_company_remote_financial_history');
        if($this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? AND id<>? FOR UPDATE',[$invoice,$id])||($this->db->table_exists($this->p.'bb_origins')&&$this->rows('SELECT id FROM '.$this->p.'bb_origins WHERE invoice_id=? FOR UPDATE',[$invoice])))throw new \DomainException('asaas_company_multiple_providers');
        if($this->rows("SELECT event_id FROM {$this->p}asaas_company_jobs WHERE origin_id=? AND state IN ('pending','processing') FOR UPDATE",[$id]))throw new \DomainException('asaas_company_remote_job_pending');
        $events=$this->rows('SELECT event_type,state FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$origin['remote_id']]);
        foreach($events as $event)if(($event['state']==='conflict'||($event['state']==='review_required'&&!($confirmation&&$event['event_type']==='PAYMENT_DELETED')))||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'],true))throw new \DomainException('asaas_company_adverse_history');
        return $this->proof($origin,$account,$confirmation);
    }
    private function state(int $issuer,int $actor,int $origin,string $state,string $code):void
    {
        $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyRemoteOperationsSchema::TABLE.' SET state=?,last_code=?,updated_at=? WHERE origin_id=? AND issuer_id=?',[$state,$code,$now,$origin,$issuer]);
        \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET state=?,last_code=?,updated_at=? WHERE id=? AND issuer_id=?',[$state==='deleted'?'remote_removed':'remote_removal_pending',$code,$now,$origin,$issuer]);
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_asaas_remote_'.$state,$issuer,$origin,$now]);
        if(($this->ledger($issuer,$origin)['state']??'')!==$state)throw new \RuntimeException('asaas_company_remote_state_unconfirmed');
    }
    public function remove($issuer,$actor,$origin,string $key,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_company_invalid_input');
        $old=$this->run($issuer,$actor,function()use($issuer,$actor,$origin){$this->source($issuer,$actor,$origin);return $this->ledger($issuer,$origin);});
        if($old){if(!hash_equals($old['request_key'],$key))throw new \DomainException('asaas_company_remote_request_conflict');return $this->publicState($old);}
        $this->service->query($issuer,$actor,$origin,$decrypt,$transport);
        $new=$this->run($issuer,$actor,function()use($issuer,$actor,$origin,$key){[$row,$meta,$account]=$this->source($issuer,$actor,$origin);$old=$this->ledger($issuer,$origin);if($old){if(!hash_equals($old['request_key'],$key))throw new \DomainException('asaas_company_remote_request_conflict');return false;}$proof=$this->eligible($row,$meta,$account);$now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.CompanyRemoteOperationsSchema::TABLE.'(origin_id,issuer_id,actor_id,account_revision,request_key,proof_id,state,last_code,created_at,updated_at,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,NULL)',[$origin,$issuer,$actor,$account['revision'],$key,$proof['id'],'reserved','intent_reserved',$now,$now]);$this->state($issuer,$actor,$origin,'reserved','intent_reserved');return true;});
        return $new?$this->dispatch($issuer,$actor,$origin,$decrypt,$transport):$this->inspect($issuer,$actor,$origin);
    }
    public function inspect($issuer,$actor,$origin):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);return $this->run($issuer,$actor,function()use($issuer,$actor,$origin){$this->source($issuer,$actor,$origin);$row=$this->ledger($issuer,$origin);if(!$row)throw new \DomainException('asaas_company_remote_intent_required');return $this->publicState($row);});
    }
    public function dispatch($issuer,$actor,$origin,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        $prepared=$this->run($issuer,$actor,function()use($issuer,$actor,$origin){[$row,$meta,$account]=$this->source($issuer,$actor,$origin);$op=$this->ledger($issuer,$origin);if(!$op)throw new \DomainException('asaas_company_remote_intent_required');if($op['state']!=='reserved')return null;if((int)$op['actor_id']!==$actor||!hash_equals($op['account_revision'],$account['revision']))throw new \DomainException('asaas_company_stale');$this->eligible($row,$meta,$account);$this->state($issuer,$actor,$origin,'sending','delete_started');\IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyRemoteOperationsSchema::TABLE.' SET sent_at=? WHERE origin_id=?',[gmdate('Y-m-d H:i:s'),$origin]);return [$row,$account];});
        if($prepared===null)return $this->inspect($issuer,$actor,$origin);
        [$row,$account]=$prepared;
        try{$secret=$decrypt($account['secret_cipher']);if(!is_string($secret)||$secret==='')throw new \RuntimeException();$response=(new CompanyClient($account['environment'],$secret,$transport))->request('DELETE','payments/'.$row['remote_id']);if(($response['id']??null)!==$row['remote_id']||($response['deleted']??null)!==true)throw new \RuntimeException();}
        catch(\Throwable $e){$this->recordObservation($issuer,$actor,$origin,'uncertain','delete_unconfirmed');return ['origin_id'=>$origin,'state'=>'uncertain','last_code'=>'delete_unconfirmed'];}
        $this->recordObservation($issuer,$actor,$origin,'awaiting_confirmation','delete_response_observed');
        return $this->refresh($issuer,$actor,$origin,$decrypt,$transport);
    }
    /** The originating intent authorizes durable observation even if ACL is revoked while HTTP is in flight. */
    private function recordObservation(int $issuer,int $actor,int $origin,string $state,string $code):void
    {
        \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$origin,$state,$code){$op=$this->ledger($issuer,$origin);if(!$op||(int)$op['actor_id']!==$actor||$op['state']!=='sending')throw new \DomainException('asaas_company_remote_intent_conflict');$this->state($issuer,$actor,$origin,$state,$code);});
    }
    public function refresh($issuer,$actor,$origin,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);$old=$this->inspect($issuer,$actor,$origin);if($old['state']==='deleted')return $old;
        $this->service->query($issuer,$actor,$origin,$decrypt,$transport);
        return $this->run($issuer,$actor,function()use($issuer,$actor,$origin){[$row,$meta,$account]=$this->source($issuer,$actor,$origin);$op=$this->ledger($issuer,$origin);if(!$op||$op['sent_at']===null)throw new \DomainException('asaas_company_remote_intent_unsent');try{$this->eligible($row,$meta,$account,true);$this->state($issuer,$actor,$origin,'deleted','canonical_deleted_confirmed');}catch(\DomainException $e){$this->state($issuer,$actor,$origin,'review_required','canonical_deletion_not_confirmed');}return $this->publicState($this->ledger($issuer,$origin));});
    }
}
