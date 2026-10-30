<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';

/** Private legal identity only. One creation intention per client/account; no inherited contacts or notifications. */
final class CompanyPayers
{
    private CompanyService $service;
    public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_merge($this->service->tablesForRun(),CompanyCheckoutSchema::tables(),$this->db->table_exists($this->p.'asaas_company_remote_operations')?['asaas_company_remote_operations']:[],$this->db->table_exists($this->p.'bb_origins')?['bb_origins']:[]);}
    private function run(int $issuer,int $actor,callable $fn)
    {
        $this->service->checkoutModule();return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$fn){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->service->authorize($issuer,$actor,'payer');$this->service->checkoutModule();return $fn();});
    }
    private function client(int $issuer,int $client):array
    {
        $row=$this->rows('SELECT c.userid,c.company,c.vat FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=? AND c.active=1 FOR UPDATE',[$client,$issuer])[0]??null;
        if(!$row)throw new \DomainException('asaas_company_payer_denied');$name=trim((string)$row['company']);if($name===''||strlen($name)>191||preg_match('/[\x00-\x1f\x7f]/',$name)||preg_match('//u',$name)!==1)throw new \DomainException('asaas_company_payer_identity_invalid');
        return ['client_id'=>$client,'name'=>$name,'cpfCnpj'=>\IssuerPolicy::taxId($row['vat'])];
    }
    private static function hash(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
    private function payer(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_payers WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$row)throw new \DomainException('asaas_company_payer_denied');return $row;
    }
    private function source(int $issuer,int $actor,int $id,bool $dispatch=false):array
    {
        $payer=$this->payer($issuer,$id);$account=$this->service->payerAccountInOwnedTransaction($issuer,$actor,(int)$payer['account_row']);$client=$this->client($issuer,(int)$payer['client_id']);
        if(!hash_equals($payer['snapshot_hash'],self::hash($client)))throw new \DomainException('asaas_company_payer_identity_changed');
        if($dispatch&&((int)$payer['actor_id']!==$actor||!hash_equals($payer['account_revision'],$account['revision'])))throw new \DomainException('asaas_company_stale');return [$payer,$account,$client];
    }
    private static function transport(array $account,callable $decrypt,?callable $transport):CompanyClient
    {
        try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $e){throw new \DomainException('asaas_company_credentials_required');}if(!is_string($secret)||$secret==='')throw new \DomainException('asaas_company_credentials_required');return new CompanyClient($account['environment'],$secret,$transport);
    }
    private static function wallet(CompanyClient $client,array $account):void
    {
        $response=$client->request('GET','wallets/');if(!is_array($response['data']??null)||count($response['data'])!==1||($response['data'][0]['id']??null)!==$account['account_id']||($response['hasMore']??null)!==false)throw new \DomainException('asaas_company_account_unverified');
    }
    private static function id($id):string{if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id))throw new \DomainException('asaas_company_payer_response_conflict');return $id;}
    private static function canonical(CompanyClient $client,array $payer,array $local,string $remote):array
    {
        $response=$client->request('GET','customers/'.$remote);try{$tax=\IssuerPolicy::taxId($response['cpfCnpj']??null);}catch(\Throwable $error){throw new \DomainException('asaas_company_payer_response_conflict');}
        if(($response['id']??null)!==$remote||($response['deleted']??null)!==false||($response['notificationDisabled']??null)!==true||($response['externalReference']??null)!==$payer['external_reference']||($response['name']??null)!==$local['name']||$tax!==$local['cpfCnpj'])throw new \DomainException('asaas_company_payer_response_conflict');
        return ['id'=>$remote,'external_reference'=>$payer['external_reference'],'identity_hash'=>self::hash($local),'notifications_disabled'=>true];
    }
    private static function lookup(CompanyClient $client,array $payer):?string
    {
        $ids=[];
        for($page=0;$page<5;$page++){$response=$client->request('GET','customers?externalReference='.$payer['external_reference'].'&limit=100&offset='.($page*100));
            if(!is_array($response['data']??null)||!array_is_list($response['data'])||count($response['data'])>100||!is_bool($response['hasMore']??null))throw new \DomainException('asaas_company_payer_lookup_incomplete');
            foreach($response['data'] as $row){if(!is_array($row)||($row['externalReference']??null)!==$payer['external_reference'])throw new \DomainException('asaas_company_payer_response_conflict');$ids[]=self::id($row['id']??null);if(count($ids)>1)throw new \DomainException('asaas_company_payer_duplicate_review');}
            if($response['hasMore']===false)return $ids[0]??null;
        }
        throw new \DomainException('asaas_company_payer_lookup_incomplete');
    }
    private function state(int $issuer,int $actor,int $id,string $state,string $code):void
    {
        $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_payers SET state=?,last_code=?,updated_at=? WHERE id=? AND issuer_id=?',[$state,$code,$now,$id,$issuer]);
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_asaas_payer_'.$state,$issuer,$id,$now]);
        if(($this->payer($issuer,$id)['state']??'')!==$state)throw new \RuntimeException('issuer_asaas_payer_unconfirmed');
    }
    private function bind(int $issuer,int $actor,int $id,array $config,string $remote,array $proof,array $expected):void
    {
        $this->run($issuer,$actor,function()use($issuer,$actor,$id,$config,$remote,$proof,$expected){[$payer,$account,$client]=$this->source($issuer,$actor,$id);
            foreach(['state','proof_hash','customer_id'] as $field)if($payer[$field]!==$expected[$field])throw new \DomainException('asaas_company_stale');
            if(!hash_equals($account['revision'],$config['revision'])||$proof['identity_hash']!==self::hash($client)||($payer['customer_id']!==null&&$payer['customer_id']!==$remote))throw new \DomainException('asaas_company_stale');
            if($this->rows('SELECT id FROM '.$this->p.'asaas_company_payers WHERE account_row=? AND customer_id=? AND id<>? FOR UPDATE',[$account['id'],$remote,$id]))throw new \DomainException('asaas_company_payer_response_conflict');
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_payers SET customer_id=?,proof_hash=? WHERE id=? AND issuer_id=?',[$remote,self::hash($proof),$id,$issuer]);$this->state($issuer,$actor,$id,'ready','canonical_customer_confirmed');
            if($this->payer($issuer,$id)['customer_id']!==$remote||$this->payer($issuer,$id)['proof_hash']!==self::hash($proof))throw new \RuntimeException('issuer_asaas_payer_unconfirmed');
        });
    }
    private function conflict(int $issuer,int $actor,int $id,\DomainException $error):void
    {
        if(!in_array($error->getMessage(),['asaas_company_payer_response_conflict','asaas_company_payer_duplicate_review','asaas_company_payer_lookup_incomplete','asaas_company_account_unverified'],true))return;
        $this->run($issuer,$actor,function()use($issuer,$actor,$id){$this->payer($issuer,$id);$this->state($issuer,$actor,$id,'review_required','canonical_customer_conflict');});
    }
    public function register($issuer,$actor,$account,$client,string $revision,string $key,callable $decrypt,?callable $transport=null):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);$client=\IssuerPolicy::id($client);if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_company_invalid_input');
        [$id,$created]=$this->run($issuer,$actor,function()use($issuer,$actor,$account,$client,$revision,$key){$config=$this->service->payerAccountInOwnedTransaction($issuer,$actor,$account);$local=$this->client($issuer,$client);if(!hash_equals($config['revision'],$revision))throw new \DomainException('asaas_company_stale');
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_payers WHERE account_row=? AND client_id=? FOR UPDATE',[$account,$client])[0]??null;
            if($old){if($old['snapshot_hash']!==self::hash($local))throw new \DomainException('asaas_company_payer_identity_changed');return [(int)$old['id'],false];}
            $ref=self::hash(['company-asaas-payer-v1',$issuer,$config['account_id'],$config['environment'],$client]);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_payers(issuer_id,account_row,client_id,actor_id,account_revision,request_key,external_reference,snapshot_hash,customer_id,state,last_code,proof_hash,sent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NULL,?,?,NULL,NULL,?,?)',[$issuer,$account,$client,$actor,$revision,$key,$ref,self::hash($local),'reserved','intent_reserved',$now,$now]);$id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('issuer_asaas_payer_unconfirmed');$this->state($issuer,$actor,$id,'reserved','intent_reserved');return [$id,true];});
        if($created)$this->dispatch($issuer,$actor,$id,$decrypt,$transport);return $id;
    }
    public function dispatch($issuer,$actor,$id,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$id=\IssuerPolicy::id($id);
        $prepared=$this->run($issuer,$actor,function()use($issuer,$actor,$id){[$payer,$account,$local]=$this->source($issuer,$actor,$id,true);return $payer['state']==='reserved'?[$payer,$account,$local]:null;});if($prepared===null)return $this->inspect($issuer,$actor,$id);
        [$payer,$account,$local]=$prepared;$client=self::transport($account,$decrypt,$transport);try{self::wallet($client,$account);$existing=self::lookup($client,$payer);}catch(\DomainException $error){$this->conflict($issuer,$actor,$id,$error);throw $error;}
        if($existing!==null){$proof=self::canonical($client,$payer,$local,$existing);$this->bind($issuer,$actor,$id,$account,$existing,$proof,$payer);return $this->inspect($issuer,$actor,$id);}
        $send=$this->run($issuer,$actor,function()use($issuer,$actor,$id,$account){[$payer,$current,$local]=$this->source($issuer,$actor,$id,true);if(!hash_equals($current['revision'],$account['revision']))throw new \DomainException('asaas_company_stale');if($payer['state']!=='reserved')return false;$this->state($issuer,$actor,$id,'sending','customer_post_started');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_payers SET sent_at=? WHERE id=? AND issuer_id=?',[gmdate('Y-m-d H:i:s'),$id,$issuer]);return true;});if(!$send)return $this->inspect($issuer,$actor,$id);
        try{$response=$client->request('POST','customers',['name'=>$local['name'],'cpfCnpj'=>$local['cpfCnpj'],'externalReference'=>$payer['external_reference'],'notificationDisabled'=>true]);$remote=self::id($response['id']??null);$proof=self::canonical($client,$payer,$local,$remote);$this->bind($issuer,$actor,$id,$account,$remote,$proof,array_replace($payer,['state'=>'sending']));}
        catch(\Throwable $error){$this->observeUnconfirmed($issuer,$actor,$id);}
        return $this->inspect($issuer,$actor,$id);
    }
    /** Authorized dispatch can record an ambiguous observation despite revocation while HTTP was in flight. */
    private function observeUnconfirmed(int $issuer,int $actor,int $id):void
    {
        \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$id){$payer=$this->payer($issuer,$id);if((int)$payer['actor_id']!==$actor||$payer['state']!=='sending')throw new \DomainException('asaas_company_payer_intent_conflict');$this->state($issuer,$actor,$id,'uncertain','customer_post_unconfirmed');});
    }
    public function recover($issuer,$actor,$id,callable $decrypt,?callable $transport=null,string $remoteId=''):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$id=\IssuerPolicy::id($id);[$payer,$account,$local]=$this->run($issuer,$actor,fn()=>$this->source($issuer,$actor,$id));$client=self::transport($account,$decrypt,$transport);try{self::wallet($client,$account);
        $remote=$remoteId!==''?self::id($remoteId):($payer['customer_id']??self::lookup($client,$payer));
        if($remote===null)return $this->inspect($issuer,$actor,$id);$proof=self::canonical($client,$payer,$local,$remote);$this->bind($issuer,$actor,$id,$account,$remote,$proof,$payer);return $this->inspect($issuer,$actor,$id);
        }catch(\DomainException $error){$this->conflict($issuer,$actor,$id,$error);throw $error;}
    }
    public function inspect($issuer,$actor,$id):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$id=\IssuerPolicy::id($id);return $this->run($issuer,$actor,function()use($issuer,$actor,$id){[$row]=$this->source($issuer,$actor,$id);return array_intersect_key($row,array_flip(['id','client_id','account_row','customer_id','state','last_code','updated_at']));});
    }
    public function page($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);return $this->run($issuer,$actor,function()use($issuer){return $this->rows('SELECT p.id,p.client_id,p.account_row,p.customer_id,p.state,p.last_code,p.updated_at,a.account_id,a.environment FROM '.$this->p.'asaas_company_payers p JOIN '.$this->p.'asaas_company_accounts a ON a.id=p.account_row AND a.issuer_id=p.issuer_id JOIN '.$this->p.'issuers_clients o ON o.client_id=p.client_id AND o.owner_issuer_id=p.issuer_id WHERE p.issuer_id=? ORDER BY p.id DESC LIMIT 100',[$issuer]);});
    }
}
