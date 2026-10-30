<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyConnectApi.php';
require_once __DIR__.'/CompanyConnectInstanceOperationsSchema.php';

/** Explicit own-instance operations; an observation never proves the cause of a remote state or message delivery. */
final class CompanyConnectInstanceOperations
{
    private CompanyConnectApi $config;
    public function __construct(private $db,private string $p,private ?CompanyConnectApiClient $client=null,?array $endpoints=null)
    {
        $this->client??=new CompanyConnectApiClient();$this->config=new CompanyConnectApi($db,$p,$this->client,$endpoints);
    }
    public function tables():array{return array_merge($this->config->tables(),array_keys(CompanyConnectInstanceOperationsSchema::definitions()));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tx(callable $f){CompanyConnectApiSchema::assertValid($this->db,$this->p);CompanyConnectInstanceOperationsSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$f);}
    private function auth(int $issuer,int $actor):void
    {
        $this->config->authorize($issuer,$actor,'reconnect');if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings','edit'))throw new DomainException('company_connect_api_denied');
    }
    private function row(int $issuer,string $key):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_connect_instance_operations WHERE issuer_id=? AND request_key=? FOR UPDATE',[$issuer,$key])[0]??null;
        if(!$row)throw new DomainException('company_connect_api_denied');return $row;
    }
    private static function confirm(array $row,array $expected):void{foreach($expected as $field=>$value)if((string)($row[$field]??null)!==(string)$value)throw new RuntimeException('issuer_connect_api_write_unconfirmed');}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_connect_api_invalid');}
    private function audit(int $issuer,int $actor,string $key,string $event,array $details=[]):void
    {
        $stamp=gmdate('Y-m-d H:i:s');$meta=json_encode(['request_key'=>$key]+$details,JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,NULL,?,?)',[$actor,'company_connect_instance_'.$event,$issuer,$stamp,$meta]);$id=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND subject_id IS NULL AND metadata_json=?',[$id,$actor,$issuer,'company_connect_instance_'.$event,$meta]))throw new RuntimeException('issuer_connect_api_write_unconfirmed');
    }
    private function validate(array $claim):array
    {
        $issuer=(int)$claim['issuer_id'];$actor=(int)$claim['actor_id'];$this->auth($issuer,$actor);
        $row=$this->row($issuer,$claim['request_key']);foreach(array_intersect_key($claim,array_flip(['issuer_id','actor_id','account_hash','account_revision','kind','status','claim_key','observation_hash','observed_at']))as$field=>$value)if((string)($row[$field]??null)!==(string)$value)throw new DomainException('company_connect_api_stale');
        $account=$this->config->runtimeInOwned($issuer);if(!hash_equals($claim['account_hash'],$account['account_hash']))throw new DomainException('company_connect_api_stale');return $account;
    }
    public function page(int $issuer,int $actor):array
    {
        return $this->tx(function()use($issuer,$actor){$this->auth($issuer,$actor);return $this->rows('SELECT request_key,status,observed_state,observed_at,created_at,updated_at FROM '.$this->p.'company_connect_instance_operations WHERE issuer_id=? AND actor_id=? ORDER BY created_at DESC,request_key LIMIT 30',[$issuer,$actor]);});
    }
    /** The durable reservation and its audit commit before any provider request. */
    public function reserve(int $issuer,int $actor,int $revision,string $key):string
    {
        self::key($key);$this->auth($issuer,$actor);
        return $this->tx(function()use($issuer,$actor,$revision,$key){
            $this->auth($issuer,$actor);$old=$this->rows('SELECT * FROM '.$this->p.'company_connect_instance_operations WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||$old['kind']!=='restart'||(int)$old['account_revision']!==$revision)throw new DomainException('company_connect_api_conflict');return $key;}
            else{
                $account=$this->config->runtimeInOwned($issuer);if((int)$account['revision']!==$revision)throw new DomainException('company_connect_api_stale');
                if($this->rows("SELECT request_key FROM ".$this->p."company_connect_instance_operations WHERE issuer_id=? AND account_hash=? AND (status IN ('pending','submitting') OR (status='uncertain' AND observed_at='1970-01-01 00:00:00')) FOR UPDATE",[$issuer,$account['account_hash']]))throw new DomainException('company_connect_api_conflict');
                $stamp=gmdate('Y-m-d H:i:s');$row=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'account_hash'=>$account['account_hash'],'account_revision'=>$revision,'kind'=>'restart','status'=>'pending','claim_key'=>'','response_hash'=>'','observed_state'=>'','observation_hash'=>'','observed_at'=>'1970-01-01 00:00:00','created_at'=>$stamp,'updated_at'=>$stamp];
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_connect_instance_operations (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));self::confirm($this->row($issuer,$key),$row);$this->audit($issuer,$actor,$key,'reserved');
            }
            return $key;
        });
    }
    public function restart(int $issuer,int $actor,int $revision,string $key,callable $decrypt):string
    {
        $this->reserve($issuer,$actor,$revision,$key);
        $claim=$this->tx(function()use($issuer,$actor,$key){
            $this->auth($issuer,$actor);$row=$this->row($issuer,$key);if((int)$row['actor_id']!==$actor)throw new DomainException('company_connect_api_denied');
            if($row['status']!=='pending')return ['done'=>$row['status']];$account=$this->validate($row);
            // Any unreleased/old preview belongs before this claim, never to its later outcome.
            if($row['observed_at']!=='1970-01-01 00:00:00'||$row['observation_hash']!==''||$row['observed_state']!=='')$this->audit($issuer,$actor,$key,'pre_attempt_observation_archived',array_intersect_key($row,array_flip(['observed_state','observation_hash','observed_at'])));
            $row['observed_at']='1970-01-01 00:00:00';$row['observation_hash']='';$row['observed_state']='';
            $lease=bin2hex(random_bytes(32));$row['updated_at']=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,"UPDATE ".$this->p."company_connect_instance_operations SET status='submitting',claim_key=?,updated_at=?,observed_at='1970-01-01 00:00:00',observation_hash='',observed_state='' WHERE issuer_id=? AND request_key=?",[$lease,$row['updated_at'],$issuer,$key]);$row['status']='submitting';$row['claim_key']=$lease;self::confirm($this->row($issuer,$key),$row);$this->validate($row);return ['row'=>$row,'account'=>$account];
        });
        if(isset($claim['done']))return $claim['done'];$row=$claim['row'];$account=$claim['account'];
        try{
            $token=CompanyConnectApi::open($decrypt,$account['token_cipher'],$account);
            CompanyConnectApi::state($this->client->request($account['endpoint'],$account['instance_name'],$token,'state'),$account['instance_name']);$this->tx(fn()=>$this->validate($row));
            $identity=CompanyConnectApi::identity($this->client->request($account['endpoint'],$account['instance_name'],$token,'identity'),$account['instance_name']);
            $same=$this->tx(function()use($row,$issuer,$identity){$this->validate($row);return $this->config->assertRemoteInOwned($issuer,$row['account_hash'],$identity);});if(!$same)throw new DomainException('company_connect_api_identity_changed');
            $response=$this->client->request($account['endpoint'],$account['instance_name'],$token,'restart');$instance=$response['instance']??null;
            // Both shapes come from the pinned primary controller's two restart branches.
            if(array_keys($response)!==['instance']||!is_array($instance)||($instance['instanceName']??null)!==$account['instance_name']||!in_array(array_keys($instance),[['instanceName','status'],['instanceName','state']],true)||!in_array($instance['status']??$instance['state']??null,['open','connecting'],true))throw new DomainException('company_connect_api_response_unconfirmed');
            return $this->tx(function()use($row,$issuer,$actor,$key,$response){$this->validate($row);$fields=['status'=>'accepted','response_hash'=>hash('sha256',json_encode($response,JSON_THROW_ON_ERROR)),'updated_at'=>gmdate('Y-m-d H:i:s')];IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_connect_instance_operations SET status=?,response_hash=?,updated_at=? WHERE issuer_id=? AND request_key=?',array_merge(array_values($fields),[$issuer,$key]));self::confirm($this->row($issuer,$key),$fields);$this->audit($issuer,$actor,$key,'accepted');$this->auth($issuer,$actor);return 'accepted';});
        }catch(Throwable $e){
            try{$this->tx(function()use($issuer,$key,$row){IssuerStorage::query($this->db,"UPDATE ".$this->p."company_connect_instance_operations SET status='uncertain',updated_at=? WHERE issuer_id=? AND request_key=? AND status='submitting' AND claim_key=?",[gmdate('Y-m-d H:i:s'),$issuer,$key,$row['claim_key']]);});}catch(Throwable $ignored){}
            if($e instanceof DomainException)throw $e;throw new RuntimeException('company_connect_api_unavailable');
        }
    }
    /** Authenticated current-state observation. Never resends restart and never proves a lost response succeeded. */
    public function observe(int $issuer,int $actor,string $key,callable $decrypt):string
    {
        self::key($key);$claim=$this->tx(function()use($issuer,$actor,$key){$this->auth($issuer,$actor);$row=$this->row($issuer,$key);if((int)$row['actor_id']!==$actor)throw new DomainException('company_connect_api_denied');if(!in_array($row['status'],['accepted','uncertain','submitting'],true)||($row['status']==='submitting'&&strtotime($row['updated_at'].' UTC')>time()-120))throw new DomainException('company_connect_api_conflict');return ['row'=>$row,'account'=>$this->validate($row)];});$account=$claim['account'];$row=$claim['row'];$token=CompanyConnectApi::open($decrypt,$account['token_cipher'],$account);
        $response=$this->client->request($account['endpoint'],$account['instance_name'],$token,'state');$state=$response['instance']['state']??null;
        if(array_keys($response)!==['instance']||($response['instance']['instanceName']??null)!==$account['instance_name']||!in_array($state,['open','connecting','close'],true))throw new DomainException('company_connect_api_instance_unconfirmed');$this->tx(fn()=>$this->validate($row));
        $raw=$this->client->request($account['endpoint'],$account['instance_name'],$token,'identity');
        // Reuse the exact ID/owner parser while allowing only the known observed transition state.
        if(!array_is_list($raw)||count($raw)!==1||!is_array($raw[0])||($raw[0]['connectionStatus']??null)!==$state)throw new DomainException('company_connect_api_identity_unconfirmed');$identityInput=$raw;$identityInput[0]['connectionStatus']='open';$identity=CompanyConnectApi::identity($identityInput,$account['instance_name']);
        $matched=$this->tx(function()use($row,$issuer,$actor,$key,$identity,$response,$state){$this->validate($row);if(!$this->config->assertRemoteInOwned($issuer,$row['account_hash'],$identity))return false;
            $status=$row['status'];if($status==='submitting'&&strtotime($row['updated_at'].' UTC')<=time()-120)$status='uncertain';$fields=['status'=>$status,'observed_state'=>$state,'observation_hash'=>hash('sha256',json_encode([$response,$identity],JSON_THROW_ON_ERROR)),'observed_at'=>gmdate('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_connect_instance_operations SET status=?,observed_state=?,observation_hash=?,observed_at=? WHERE issuer_id=? AND request_key=?',array_merge(array_values($fields),[$issuer,$key]));self::confirm($this->row($issuer,$key),$fields);$this->audit($issuer,$actor,$key,'observed');$this->auth($issuer,$actor);return true;
        });if(!$matched)throw new DomainException('company_connect_api_identity_changed');return $state;
    }
}
