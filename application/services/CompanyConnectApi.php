<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyConnectApiSchema.php';require_once __DIR__.'/CompanyConnectApiClient.php';require_once __DIR__.'/CompanySettingsStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** One private instance profile per issuer; remote instance creation/QR/admin keys remain outside this profile. */
final class CompanyConnectApi
{
    public const PROTOCOL = 'evolution-v2-flat-instance-v1';
    private array$endpoints;
    public function __construct(private$db,private string$p,private?CompanyConnectApiClient$client=null,?array$endpoints=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->client??=new CompanyConnectApiClient();$this->endpoints=CompanyConnectApiClient::endpoints($endpoints);}
    public function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit'],array_keys(CompanyConnectApiSchema::definitions()));}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tx(callable$f){CompanyConnectApiSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$f);}
    public function authorize(int$issuer,int$actor,string$capability):void{$acl=new IssuerResourceAccess($this->db,$this->p);foreach([['settings',$capability==='configure'?'edit':'view'],['connect-api-connector',$capability]]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_connect_api_denied');}
    private function row(int$issuer):?array{return$this->rows('SELECT * FROM '.$this->p.'company_connect_api_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;}
    public function choices():array{return array_map(fn($e)=>$e['label'],$this->endpoints);}
    public function page(int$issuer,int$actor):array{$this->authorize($issuer,$actor,'view');CompanyConnectApiSchema::assertValid($this->db,$this->p);$r=$this->row($issuer);return['revision'=>(int)($r['revision']??0),'endpoint_id'=>$r['endpoint_id']??'','instance_name'=>$r['instance_name']??'','token_configured'=>($r['token_cipher']??'')!=='','status'=>$r['status']??'unconfigured','validated_at'=>$r['validated_at']??null,'provider_instance_id'=>$r['provider_instance_id']??'','provider_owner'=>empty($r['provider_owner_jid'])?'':'***'.substr(explode('@',$r['provider_owner_jid'])[0],-4)];}
    private static function confirmed(?array$row,array$fields):void{foreach($fields as$k=>$v)if((string)($row[$k]??null)!==(string)$v)throw new RuntimeException('issuer_connect_api_write_unconfirmed');}
    private function audit(int$issuer,int$actor,string$event):void{$stamp=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'company_connect_api_'.$event,$issuer,$stamp]);$id=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND subject_id IS NULL AND created_at=?',[$id,$actor,$issuer,'company_connect_api_'.$event,$stamp]))throw new RuntimeException('issuer_connect_api_write_unconfirmed');}
    public function save(int$issuer,int$actor,int$revision,string$endpoint,string$instance,string$token,callable$encrypt):void
    {
        $this->authorize($issuer,$actor,'configure');if(!isset($this->endpoints[$endpoint])||!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$instance)||strlen($token)>4096||preg_match('/[\r\n\x00]/',$token)||$revision<0||$revision>=2147483647)throw new DomainException('company_connect_api_invalid');$this->tx(function()use($issuer,$actor,$revision,$endpoint,$instance,$token,$encrypt){$this->authorize($issuer,$actor,'configure');$row=$this->row($issuer);if((int)($row['revision']??0)!==$revision)throw new DomainException('company_connect_api_stale');$changed=$row&&($row['endpoint_id']!==$endpoint||$row['instance_name']!==$instance);if($changed&&$token==='')throw new DomainException('company_connect_api_secret_required');$envelope=json_encode(['format'=>1,'issuer_id'=>$issuer,'endpoint_id'=>$endpoint,'endpoint_url_hash'=>hash('sha256',$this->endpoints[$endpoint]['url']),'instance_name'=>$instance,'token'=>$token],JSON_THROW_ON_ERROR);$cipher=$token===''?($row['token_cipher']??''):$encrypt($envelope);if(!is_string($cipher)||$cipher===''||($token!==''&&(hash_equals($token,$cipher)||hash_equals($envelope,$cipher))))throw new DomainException('company_connect_api_secret_required');if($this->rows('SELECT issuer_id FROM '.$this->p.'company_connect_api_accounts WHERE endpoint_id=? AND instance_name=? AND issuer_id<>?',[$endpoint,$instance,$issuer]))throw new DomainException('company_connect_api_conflict');$stamp=gmdate('Y-m-d H:i:s');$fields=['endpoint_id'=>$endpoint,'instance_name'=>$instance,'provider_instance_id'=>'','provider_owner_jid'=>'','token_cipher'=>$cipher,'revision'=>$revision+1,'status'=>'configured','configured_by'=>$actor,'validated_at'=>'1970-01-01 00:00:00','updated_at'=>$stamp];if(!$row){$fields=['issuer_id'=>$issuer]+$fields+['created_at'=>$stamp];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_connect_api_accounts (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));}else{IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_connect_api_accounts SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($fields))).' WHERE issuer_id=?',array_merge(array_values($fields),[$issuer]));}self::confirmed($this->row($issuer),$fields);$this->audit($issuer,$actor,'saved');$this->authorize($issuer,$actor,'configure');});
    }
    public function revoke(int$issuer,int$actor,int$revision):void{$this->authorize($issuer,$actor,'configure');$this->tx(function()use($issuer,$actor,$revision){$this->authorize($issuer,$actor,'configure');$r=$this->row($issuer);if(!$r||(int)$r['revision']!==$revision)throw new DomainException('company_connect_api_stale');IssuerStorage::query($this->db,"UPDATE ".$this->p."company_connect_api_accounts SET revision=revision+1,status='revoked',token_cipher='',validated_at='1970-01-01 00:00:00',updated_at=? WHERE issuer_id=?",[gmdate('Y-m-d H:i:s'),$issuer]);self::confirmed($this->row($issuer),['revision'=>$revision+1,'status'=>'revoked','token_cipher'=>'']);$this->audit($issuer,$actor,'revoked');});}
    /** Internal ciphertext-bearing state, only in a caller-owned transaction; never a UI DTO. */
    public function runtimeInOwned(int$issuer,bool$active=true):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$r=$this->row($issuer);if(!$r||$r['token_cipher']===''||($active&&($r['status']!=='active'||$r['provider_instance_id']===''||$r['provider_owner_jid']===''))||$r['status']==='revoked'||!isset($this->endpoints[$r['endpoint_id']]))throw new DomainException('company_connect_api_connection_required');$this->authorize($issuer,(int)$r['configured_by'],'configure');$r['endpoint']=$this->endpoints[$r['endpoint_id']];$r['account_hash']=hash('sha256',json_encode([self::PROTOCOL,$issuer,$r['endpoint_id'],$r['instance_name'],$r['provider_instance_id'],$r['provider_owner_jid'],(int)$r['revision'],(int)$r['configured_by'],$r['endpoint']['url']],JSON_THROW_ON_ERROR));return$r;
    }
    public static function state(array$response,string$instance):void{if(array_keys($response)!==['instance']||!is_array($response['instance'])||($response['instance']['instanceName']??null)!==$instance||($response['instance']['state']??null)!=='open')throw new DomainException('company_connect_api_instance_unconfirmed');}
    public static function open(callable$decrypt,string$cipher,array$account):string
    {
        $json=$decrypt($cipher);if(!is_string($json)||$json==='')throw new DomainException('company_connect_api_secret_required');try{$doc=json_decode($json,true,8,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new DomainException('company_connect_api_secret_required');}if(!is_array($doc)||array_keys($doc)!==['format','issuer_id','endpoint_id','endpoint_url_hash','instance_name','token']||$doc['format']!==1||$doc['issuer_id']!==(int)$account['issuer_id']||$doc['endpoint_id']!==$account['endpoint_id']||$doc['instance_name']!==$account['instance_name']||$doc['endpoint_url_hash']!==hash('sha256',$account['endpoint']['url'])||!is_string($doc['token'])||$doc['token']===''||strlen($doc['token'])>4096||preg_match('/[\r\n\x00]/',$doc['token']))throw new DomainException('company_connect_api_secret_required');return$doc['token'];
    }
    /** One explicit primary v2 profile. No wrapper/legacy shape or LID inference. */
    public static function identity(array$response,string$instance):array
    {
        $row=$response[0]??null;
        if(!array_is_list($response)||count($response)!==1||!is_array($row)||($row['name']??null)!==$instance||($row['connectionStatus']??null)!=='open'||!is_string($row['id']??null)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$row['id'])||!is_string($row['ownerJid']??null)||!preg_match('/^([1-9][0-9]{1,14})(?::[0-9]{1,5})?@s\.whatsapp\.net$/D',$row['ownerJid'],$owner))throw new DomainException('company_connect_api_identity_unconfirmed');
        return['provider_instance_id'=>strtolower($row['id']),'provider_owner_jid'=>$owner[1].'@s.whatsapp.net'];
    }
    private function review(array$row):void
    {
        $stamp=gmdate('Y-m-d H:i:s');
        IssuerStorage::query($this->db,"UPDATE ".$this->p."company_connect_api_accounts SET status='review',revision=revision+1,validated_at='1970-01-01 00:00:00',updated_at=? WHERE issuer_id=?",[$stamp,$row['issuer_id']]);
        self::confirmed($this->row((int)$row['issuer_id']),['status'=>'review','revision'=>(int)$row['revision']+1,'provider_instance_id'=>$row['provider_instance_id'],'provider_owner_jid'=>$row['provider_owner_jid']]);
        $this->audit((int)$row['issuer_id'],(int)$row['configured_by'],'identity_changed');
    }
    /** A mismatch must commit the review state before the caller throws; it never rebinds an old intent. */
    public function assertRemoteInOwned(int$issuer,string$expectedHash,array$identity):bool
    {
        $now=$this->runtimeInOwned($issuer);
        if(!hash_equals($expectedHash,$now['account_hash']))throw new DomainException('company_connect_api_stale');
        foreach(['provider_instance_id','provider_owner_jid']as$field)if(($identity[$field]??null)!==$now[$field]){$this->review($now);return false;}
        return true;
    }
    public function check(int$issuer,int$actor,int$revision,callable$decrypt):void
    {
        $this->authorize($issuer,$actor,'configure');
        $claim=$this->tx(function()use($issuer,$actor,$revision){$this->authorize($issuer,$actor,'configure');$r=$this->runtimeInOwned($issuer,false);if((int)$r['revision']!==$revision)throw new DomainException('company_connect_api_stale');return$r;});
        $token=self::open($decrypt,$claim['token_cipher'],$claim);
        self::state($this->client->request($claim['endpoint'],$claim['instance_name'],$token,'state'),$claim['instance_name']);
        // Revalidate before the second credential-bearing read as well as before persistence.
        $this->tx(function()use($issuer,$actor,$claim){$this->authorize($issuer,$actor,'configure');if(!hash_equals($this->runtimeInOwned($issuer,false)['account_hash'],$claim['account_hash']))throw new DomainException('company_connect_api_stale');});
        $identity=self::identity($this->client->request($claim['endpoint'],$claim['instance_name'],$token,'identity'),$claim['instance_name']);
        $matched=$this->tx(function()use($issuer,$actor,$claim,$identity){
            $this->authorize($issuer,$actor,'configure');$now=$this->runtimeInOwned($issuer,false);
            if(!hash_equals($now['account_hash'],$claim['account_hash']))throw new DomainException('company_connect_api_stale');
            if($now['status']==='review')return false;
            if($now['provider_instance_id']!==''||$now['provider_owner_jid']!=='')foreach(['provider_instance_id','provider_owner_jid']as$field)if($identity[$field]!==$now[$field]){$this->review($now);return false;}
            $stamp=gmdate('Y-m-d H:i:s');$fields=$identity+['status'=>'active','validated_at'=>$stamp,'updated_at'=>$stamp];
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_connect_api_accounts SET '.implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($fields))).' WHERE issuer_id=?',array_merge(array_values($fields),[$issuer]));
            self::confirmed($this->row($issuer),$fields);$this->audit($issuer,$actor,'state_checked');return true;
        });
        if(!$matched)throw new DomainException('company_connect_api_identity_changed');
    }
}
