<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyRefundStorage.php';

/** Explicit private refund outbox. A sent intent is never automatically resent or replaced. */
final class CompanyRefunds extends CompanyRefundStorage
{
    public function setGrant($issuer,$actor,$account,$target,string $revision,bool $enabled):string
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);$target=\IssuerPolicy::id($target);self::key($revision);
        return $this->run($issuer,$actor,function()use($issuer,$actor,$account,$target,$revision,$enabled){$this->account($issuer,$account);if($enabled){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$target);$this->auth($issuer,$target,true);}
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_refund_grants WHERE account_row=? AND actor_id=? FOR UPDATE',[$account,$target])[0]??null;
            if($old&&((int)$old['issuer_id']!==$issuer||!hash_equals($old['revision'],$revision))||!$old&&$revision!==str_repeat('0',64))throw new \DomainException('asaas_refund_grant_stale');$next=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');
            if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_refund_grants SET enabled=?,revision=?,changed_by=?,updated_at=? WHERE account_row=? AND actor_id=? AND issuer_id=?',[(int)$enabled,$next,$actor,$now,$account,$target,$issuer]);
            else $this->insert('asaas_company_refund_grants',['account_row'=>$account,'actor_id'=>$target,'issuer_id'=>$issuer,'enabled'=>(int)$enabled,'revision'=>$next,'changed_by'=>$actor,'created_at'=>$now,'updated_at'=>$now]);
            $this->audit($issuer,$actor,$account,'company_asaas_refund_grant_changed',['account_row'=>$account,'target_actor'=>$target,'enabled'=>$enabled,'revision'=>$next]);$saved=$this->rows('SELECT issuer_id,enabled,revision FROM '.$this->p.'asaas_company_refund_grants WHERE account_row=? AND actor_id=?',[$account,$target])[0]??[];if((int)($saved['issuer_id']??0)!==$issuer||(int)($saved['enabled']??-1)!==(int)$enabled||($saved['revision']??null)!==$next)throw new \RuntimeException('issuer_asaas_refund_grant_unconfirmed');if($enabled)$this->auth($issuer,$target,true);return $next;});
    }
    public function grantForActor($issuer,$actor,$account):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);
        return $this->run($issuer,$actor,function()use($issuer,$actor,$account){$this->account($issuer,$account);$row=$this->rows('SELECT enabled,revision,updated_at FROM '.$this->p.'asaas_company_refund_grants WHERE account_row=? AND actor_id=? AND issuer_id=?',[$account,$actor,$issuer])[0]??null;return $row??['enabled'=>0,'revision'=>str_repeat('0',64),'updated_at'=>null];});
    }
    public function page($issuer,$actor,$origin):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        return $this->run($issuer,$actor,function()use($issuer,$actor,$origin){[$source]=$this->source($issuer,$actor,$origin,false);$requests=[];foreach($this->rows('SELECT request_key FROM '.$this->p.'asaas_company_refund_requests WHERE origin_id=? AND issuer_id=? ORDER BY created_at DESC,request_key LIMIT 101',[$origin,$issuer]) as $r)$requests[]=$this->publicRequest($this->requestRow($issuer,$r['request_key']));if(count($requests)>100)throw new \DomainException('asaas_refund_limit');return ['source'=>$source,'requests'=>$requests];},false);
    }
    public function inspect($issuer,$actor,string $key):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::key($key);return $this->run($issuer,$actor,function()use($issuer,$actor,$key){$r=$this->requestRow($issuer,$key);$this->source($issuer,$actor,(int)$r['origin_id'],false);return $this->publicRequest($r);},false);
    }
    private function client(array $account,callable $decrypt,?callable $transport):CompanyClient
    {
        try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $e){throw new \DomainException('asaas_refund_credentials_required');}
        if(!is_string($secret)||$secret==='')throw new \DomainException('asaas_refund_credentials_required');return new CompanyClient($account['environment'],$secret,$transport);
    }
    private function identity(CompanyClient $client,array $source):void
    {
        $wallets=$client->request('GET','wallets/');if(($wallets['hasMore']??true)!==false||!is_array($wallets['data']??null)||count($wallets['data'])!==1||($wallets['data'][0]['id']??null)!==$source['account_id'])throw new \DomainException('asaas_refund_account_unverified');
    }
    private static function input(int $actor,string $key,string $amount,string $reason,string $accountRevision,string $grantRevision):array
    {
        if(strlen($reason)<3||strlen($reason)>160||trim($reason)!==$reason||preg_match('//u',$reason)!==1||preg_match('/[\x00-\x1f\x7f]/',$reason))throw new \DomainException('asaas_refund_invalid_input');
        return ['actor_id'=>$actor,'request_key'=>$key,'amount'=>$amount,'reason'=>$reason,'description'=>'CRM '.$key.' '.$reason,'account_revision'=>$accountRevision,'grant_revision'=>$grantRevision];
    }
    public function prepare($issuer,$actor,$origin,string $amount,string $reason,string $key,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);self::key($key);$amount=CompanyRefundContract::money($amount);if(\CompanyInvoiceAmounts::minor($amount)<1)throw new \DomainException('asaas_refund_invalid_input');
        $first=$this->run($issuer,$actor,function()use($issuer,$actor,$origin,$amount,$reason,$key){[$source,$account]=$this->source($issuer,$actor,$origin);$grant=$this->grant($issuer,$actor,(int)$account['id']);$input=self::input($actor,$key,$amount,$reason,$account['revision'],$grant['revision']);$old=$this->rows('SELECT issuer_id FROM '.$this->p.'asaas_company_refund_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){$r=$this->requestRow($issuer,$key);if((int)$r['origin_id']!==$origin||!hash_equals($r['input_hash'],CompanyRefundContract::hash($input)))throw new \DomainException('asaas_refund_request_conflict');return ['old'=>$this->publicRequest($r)];}
            if(!in_array($source['billing_type'],['PIX','CREDIT_CARD'],true))throw new \DomainException('asaas_refund_payment_method_contract_required');if(\CompanyInvoiceAmounts::minor($amount)>\CompanyInvoiceAmounts::minor($source['nominal']))throw new \DomainException('asaas_refund_amount_requires_review');$this->safeToInitiate($issuer,$source);$this->available($issuer,$origin);return compact('source','account','input');});
        if(isset($first['old']))return $first['old'];$client=$this->client($first['account'],$decrypt,$transport);$this->identity($client,$first['source']);$baseline=CompanyRefundContract::normalize($client->request('GET','payments/'.$first['source']['remote_id']),$first['source']);
        if($baseline['deleted']||!$baseline['not_deleted_confirmed']||$baseline['unsupported']||$baseline['done_total']!=='0.00'||$baseline['pending_total']!=='0.00'||!in_array($baseline['status'],$first['source']['billing_type']==='PIX'?['RECEIVED']:['CONFIRMED','RECEIVED'],true))throw new \DomainException('asaas_refund_existing_history_requires_review');
        return $this->run($issuer,$actor,function()use($issuer,$actor,$origin,$amount,$key,$first,$baseline){[$source,$account]=$this->source($issuer,$actor,$origin);$this->grant($issuer,$actor,(int)$account['id'],$first['input']['grant_revision']);if(!hash_equals($account['revision'],$first['account']['revision'])||CompanyRefundContract::hash($source)!==CompanyRefundContract::hash($first['source']))throw new \DomainException('asaas_refund_source_changed');
            if($this->rows('SELECT request_key FROM '.$this->p.'asaas_company_refund_requests WHERE request_key=? FOR UPDATE',[$key])){$old=$this->requestRow($issuer,$key);if(!hash_equals($old['input_hash'],CompanyRefundContract::hash($first['input'])))throw new \DomainException('asaas_refund_request_conflict');return $this->publicRequest($old);}$this->safeToInitiate($issuer,$source);$this->available($issuer,$origin);$now=gmdate('Y-m-d H:i:s');
            $this->insert('asaas_company_refund_requests',['request_key'=>$key,'issuer_id'=>$issuer,'origin_id'=>$origin,'actor_id'=>$actor,'account_row'=>(int)$account['id'],'account_revision'=>$account['revision'],'grant_revision'=>$first['input']['grant_revision'],'source_json'=>CompanyRefundContract::encode($source),'source_hash'=>CompanyRefundContract::hash($source),'input_json'=>CompanyRefundContract::encode($first['input']),'input_hash'=>CompanyRefundContract::hash($first['input']),'baseline_json'=>CompanyRefundContract::encode($baseline),'baseline_hash'=>CompanyRefundContract::hash($baseline),'amount'=>$amount,'state'=>'reserved','last_code'=>'refund_intent_reserved','latest_observation'=>null,'sent_at'=>null,'created_at'=>$now,'updated_at'=>$now]);
            $saved=$this->requestRow($issuer,$key);$this->audit($issuer,$actor,$origin,'company_asaas_refund_reserved',['request_key'=>$key,'amount'=>$amount]);$this->assertIntentCurrent($issuer,$actor,$saved);return $this->publicRequest($saved);});
    }
    /** Observation of a sent refund accepts only its known refund followup events; it grants no new POST. */
    private function safeToInitiate(int $issuer,array $source,bool $observeSent=false):void
    {
        $row=$this->rows('SELECT state,last_code FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$source['origin_id'],$issuer])[0]??null;
        if(!$row||in_array($row['state'],['remote_removed','remote_removal_pending'],true))throw new \DomainException('asaas_refund_adverse_history');
        $refundFollowup=false;
        foreach($this->rows('SELECT event_type,state FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$source['account_row'],$source['remote_id']]) as $event)if(in_array($event['state'],['review_required','conflict'],true)){
            if(!$observeSent||$event['state']!=='review_required'||!in_array($event['event_type'],['PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS'],true))throw new \DomainException('asaas_refund_adverse_history');$refundFollowup=true;
        }
        if($row['state']==='review_required'&&(!$observeSent||!$refundFollowup||$row['last_code']!=='company_adverse_event'))throw new \DomainException('asaas_refund_adverse_history');
        if($this->db->table_exists($this->p.'asaas_company_remote_operations')&&$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? FOR UPDATE',[$source['origin_id']]))throw new \DomainException('asaas_refund_adverse_history');
    }
    private function available(int $issuer,int $origin):void
    {
        $rows=$this->rows('SELECT issuer_id,state,sent_at FROM '.$this->p.'asaas_company_refund_requests WHERE origin_id=? FOR UPDATE',[$origin]);if(count($rows)>=100)throw new \DomainException('asaas_refund_limit');
        foreach($rows as $r)if((int)$r['issuer_id']!==$issuer||$r['sent_at']!==null||$r['state']!=='cancelled_local')throw new \DomainException('asaas_refund_origin_intent_exists');
    }
    public function cancelReserved($issuer,$actor,string $key):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::key($key);$this->run($issuer,$actor,function()use($issuer,$actor,$key){$r=$this->requestRow($issuer,$key);$this->source($issuer,$actor,(int)$r['origin_id'],false);if((int)$r['actor_id']!==$actor)throw new \DomainException('asaas_refund_access_denied');if($r['state']==='cancelled_local'&&$r['sent_at']===null)return;if($r['state']!=='reserved'||$r['sent_at']!==null)throw new \DomainException('asaas_refund_already_sent');$this->setState($issuer,$key,'cancelled_local','unsent_intent_cancelled');$this->audit($issuer,$actor,(int)$r['origin_id'],'company_asaas_refund_cancelled_local',['request_key'=>$key]);});
    }
    private function setState(int $issuer,string $key,string $state,string $code,?int $observation=null):void
    {
        \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_refund_requests SET state=?,last_code=?,latest_observation=COALESCE(?,latest_observation),updated_at=? WHERE request_key=? AND issuer_id=?',[$state,$code,$observation,gmdate('Y-m-d H:i:s'),$key,$issuer]);$r=$this->requestRow($issuer,$key);if($r['state']!==$state||$r['last_code']!==$code||$observation!==null&&(int)$r['latest_observation']!==$observation)throw new \RuntimeException('issuer_asaas_refund_state_unconfirmed');
    }
    /** Persists only sanitized facts of a previously authorized outbound operation, even after revocation. */
    private function observe(int $issuer,int $actor,string $key,string $sourceHash,string $accountRevision,?array $safe,bool $dispatch):void
    {
        \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$key,$sourceHash,$accountRevision,$safe,$dispatch){$r=$this->requestRow($issuer,$key);if($r['sent_at']===null||$r['source_hash']!==$sourceHash||$r['account_revision']!==$accountRevision||$dispatch&&($r['state']!=='sending'||(int)$r['actor_id']!==$actor))throw new \DomainException('asaas_refund_observation_conflict');$id=null;
            if($safe!==null){$this->insert('asaas_company_refund_observations',['request_key'=>$key,'issuer_id'=>$issuer,'origin_id'=>(int)$r['origin_id'],'actor_id'=>$actor,'account_revision'=>$accountRevision,'source_hash'=>$sourceHash,'operation'=>$dispatch?'post':'query','result_json'=>CompanyRefundContract::encode($safe),'evidence_hash'=>CompanyRefundContract::hash($safe),'created_at'=>gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();[$state,$code]=CompanyRefundContract::state($safe);}else [$state,$code]=['uncertain','remote_refund_unconfirmed'];
            $this->setState($issuer,$key,$state,$code,$id);$this->audit($issuer,$actor,(int)$r['origin_id'],'company_asaas_refund_observed',['request_key'=>$key,'observation_id'=>$id,'state'=>$state]);if($id!==null)$this->publicRequest($this->requestRow($issuer,$key));});
    }
    public function dispatch($issuer,$actor,string $key,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::key($key);
        $candidate=$this->run($issuer,$actor,function()use($issuer,$actor,$key){$r=$this->requestRow($issuer,$key);if($r['state']!=='reserved')return null;if($r['sent_at']!==null)throw new \DomainException('asaas_refund_already_sent');if((int)$r['actor_id']!==$actor)throw new \DomainException('asaas_refund_access_denied');[$source,$account]=$this->assertIntentCurrent($issuer,$actor,$r);$this->safeToInitiate($issuer,$source);return compact('r','source','account');});
        if($candidate===null)return $this->inspect($issuer,$actor,$key);$client=$this->client($candidate['account'],$decrypt,$transport);$this->identity($client,$candidate['source']);$fresh=CompanyRefundContract::normalize($client->request('GET','payments/'.$candidate['source']['remote_id']),$candidate['source']);if(!hash_equals($candidate['r']['baseline_hash'],CompanyRefundContract::hash($fresh)))throw new \DomainException('asaas_refund_remote_changed');
        $prepared=$this->run($issuer,$actor,function()use($issuer,$actor,$key){$r=$this->requestRow($issuer,$key);if($r['state']!=='reserved')return null;if($r['sent_at']!==null)throw new \DomainException('asaas_refund_already_sent');if((int)$r['actor_id']!==$actor)throw new \DomainException('asaas_refund_access_denied');[$source,$account]=$this->assertIntentCurrent($issuer,$actor,$r);$this->safeToInitiate($issuer,$source);$this->setState($issuer,$key,'sending','refund_post_started');$sent=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_refund_requests SET sent_at=? WHERE request_key=? AND issuer_id=?',[$sent,$key,$issuer]);$saved=$this->requestRow($issuer,$key);if($saved['sent_at']!==$sent)throw new \RuntimeException('issuer_asaas_refund_state_unconfirmed');$this->audit($issuer,$actor,(int)$r['origin_id'],'company_asaas_refund_post_started',['request_key'=>$key]);$this->assertIntentCurrent($issuer,$actor,$saved);return compact('saved','source','account');});
        if($prepared===null)return $this->inspect($issuer,$actor,$key);$input=json_decode($prepared['saved']['input_json'],true,16,JSON_THROW_ON_ERROR);
        try{$reply=$client->request('POST','payments/'.$prepared['source']['remote_id'].'/refund',['value'=>$input['amount'],'description'=>$input['description']]);$safe=CompanyRefundContract::normalize($reply,$prepared['source']);}catch(\Throwable $error){$safe=null;}
        $this->observe($issuer,$actor,$key,$prepared['saved']['source_hash'],$prepared['saved']['account_revision'],$safe,true);return $this->inspect($issuer,$actor,$key);
    }
    public function refresh($issuer,$actor,string $key,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::key($key);
        $prepared=$this->run($issuer,$actor,function()use($issuer,$actor,$key){$r=$this->requestRow($issuer,$key);if($r['sent_at']===null)throw new \DomainException('asaas_refund_unsent');[$source,$account]=$this->assertIntentCurrent($issuer,$actor,$r,false);$this->safeToInitiate($issuer,$source,true);return compact('r','source','account');});
        $client=$this->client($prepared['account'],$decrypt,$transport);$this->identity($client,$prepared['source']);$safe=CompanyRefundContract::normalize($client->request('GET','payments/'.$prepared['source']['remote_id']),$prepared['source']);
        $this->observe($issuer,$actor,$key,$prepared['r']['source_hash'],$prepared['r']['account_revision'],$safe,false);return $this->inspect($issuer,$actor,$key);
    }
}
