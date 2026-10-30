<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeProtocol.php';
require_once __DIR__.'/../../../application/services/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../../../application/services/issuers/IssuerTransaction.php';

/** Per-integration credentials and explicit invoice actor; never reads session company. */
final class PigeBridgeConnections
{
    public function __construct(private $db,private $encryption,private string $prefix)
    { if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('invalid_prefix'); }
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','currencies','pige_bridge_connections'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function exact(array $value,array $keys):void{$actual=array_keys($value);sort($actual);sort($keys);if($actual!==$keys)throw new DomainException('pige_configuration_invalid');}
    public static function scope(array $value):array
    {
        self::exact($value,['school_id','issuer_id','account_id','environment']);
        $out=[];foreach(['school_id','issuer_id','account_id','environment'] as $field){if(!is_string($value[$field]))throw new DomainException('pige_scope_invalid');$out[$field]=$value[$field];}
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$out['school_id'])||!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$out['account_id'])||!in_array($out['environment'],['sandbox','production'],true))throw new DomainException('pige_scope_invalid');
        IssuerPolicy::id($out['issuer_id']);return $out;
    }
    public static function validateMapping(array $mapping):array
    {
        self::exact($mapping,['enabled','actor_id','currency_id']);
        if(!is_bool($mapping['enabled'])||!is_int($mapping['actor_id'])||!is_int($mapping['currency_id']))throw new DomainException('pige_mapping_invalid');
        IssuerPolicy::id($mapping['actor_id']);IssuerPolicy::id($mapping['currency_id']);
        return ['enabled'=>$mapping['enabled'],'actor_id'=>$mapping['actor_id'],'currency_id'=>$mapping['currency_id']];
    }
    private static function key(string $key):void{if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$key))throw new DomainException('pige_connection_invalid');}
    private static function secret(string $secret):void{if(!preg_match('/^[!-~]{32,512}$/D',$secret))throw new DomainException('pige_secret_invalid');}
    private static function json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function decoded(array $row):array
    {
        $scope=self::scope(json_decode($row['scope_json'],true,16,JSON_THROW_ON_ERROR));
        if(self::json($scope)!==$row['scope_json']||!hash_equals($row['scope_hash'],hash('sha256',$row['scope_json']))||(int)$row['revision']<1||!in_array((int)$row['enabled'],[0,1],true))throw new DomainException('pige_connection_corrupt');
        return $scope;
    }
    private function authorize(int $issuer,int $caller,bool $financial,?int $actor=null):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$caller);
        if(!$acl->can($issuer,$caller,'settings','edit'))throw new DomainException('pige_configuration_access_denied');
        if($actor!==null&&$actor!==$caller&&!$acl->isMaster($caller))throw new DomainException('pige_delegation_denied');
        if($financial){$this->authorizeActor($issuer,$caller);if($actor!==null)$this->authorizeActor($issuer,$actor);}
    }
    private function authorizeActor(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);
        foreach([['invoices','view'],['invoices','create'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('pige_invoice_actor_denied');
    }
    /** Returns plaintext only to trusted authentication code, never admin/UI. Missing table preserves deployment v1. */
    public function active(array $deployment=[]):array
    {
        $all=$deployment;
        if($this->db->table_exists($this->prefix.'pige_bridge_connections')){
            foreach($this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections ORDER BY connection_key') as $row){
                $key=$row['connection_key'];self::key($key);if(array_key_exists($key,$all))throw new DomainException('pige_duplicate_connection');
                $scope=self::decoded($row);$secret=$this->encryption->decrypt($row['secret_cipher']);
                if(!is_string($secret)||!hash_equals($row['secret_hash'],hash('sha256',$secret)))throw new DomainException('pige_secret_unavailable');self::secret($secret);
                $issuerActive=(bool)$this->rows('SELECT id FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1',[$scope['issuer_id']]);
                $all[$key]=['enabled'=>(int)$row['enabled']===1&&$issuerActive,'scope'=>$scope,'secret'=>$secret];
            }
        }
        $scopes=[];$secrets=[];$active=[];
        foreach($all as $key=>$connection){
            if(!is_string($key)||!is_array($connection))throw new DomainException('pige_connection_invalid');self::key($key);
            if(!is_bool($connection['enabled']??null)||!is_array($connection['scope']??null)||!is_string($connection['secret']??null))throw new DomainException('pige_connection_invalid');
            $scope=self::scope($connection['scope']);self::secret($connection['secret']);$sh=hash('sha256',self::json($scope));$kh=hash('sha256',$connection['secret']);
            if(isset($scopes[$sh])||isset($secrets[$kh]))throw new DomainException('pige_duplicate_scope_or_secret');$scopes[$sh]=true;$secrets[$kh]=true;
            if($connection['enabled'])$active[$key]=['enabled'=>true,'scope'=>$scope,'secret'=>$connection['secret']];
        }
        return $active;
    }
    public function save(string $key,array $scope,?string $secret,bool $enabled,int $revision,int $caller):void
    {
        self::key($key);$scope=self::scope($scope);$caller=IssuerPolicy::id($caller);if($revision<0||$revision===PHP_INT_MAX)throw new DomainException('pige_configuration_invalid');if($secret!==null)self::secret($secret);
        IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($key,$scope,$secret,$enabled,$revision,$caller):void{
            $old=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=? FOR UPDATE',[$key])[0]??null;
            if((int)($old['revision']??0)!==$revision)throw new DomainException('pige_configuration_changed_reload');
            $json=self::json($scope);if($old&&self::decoded($old)!==$scope)throw new DomainException('pige_scope_immutable');
            $mapping=$old&&$old['mapping_json']!==null?self::validateMapping(json_decode($old['mapping_json'],true,16,JSON_THROW_ON_ERROR)):null;
            $financial=$enabled||$secret!==null||!$old;$this->authorize((int)$scope['issuer_id'],$caller,$financial,$financial?($mapping['actor_id']??null):null);
            if(!$old&&$secret===null)throw new DomainException('pige_secret_required');
            $hash=$secret!==null?hash('sha256',$secret):$old['secret_hash'];$cipher=$old['secret_cipher']??null;
            if($secret!==null){$cipher=$this->encryption->encrypt($secret);if(!is_string($cipher)||$cipher===''||$cipher===$secret||$this->encryption->decrypt($cipher)!==$secret)throw new DomainException('pige_encryption_required');}
            if($this->rows('SELECT connection_key FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key<>? AND (scope_hash=? OR secret_hash=?) FOR UPDATE',[$key,hash('sha256',$json),$hash]))throw new DomainException('pige_duplicate_scope_or_secret');
            // Any connection change invalidates delegated invoice consent, while preserving the old actor/currency.
            if($mapping!==null)$mapping['enabled']=false;$mappingJson=$mapping===null?null:self::json($mapping);$when=gmdate('Y-m-d H:i:s');
            $values=[$json,hash('sha256',$json),$cipher,$hash,$enabled?1:0,$revision+1,$mappingJson,$caller,$when];
            if($old)IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'pige_bridge_connections SET scope_json=?,scope_hash=?,secret_cipher=?,secret_hash=?,enabled=?,revision=?,mapping_json=?,updated_by=?,updated_at=? WHERE connection_key=?',[...$values,$key]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'pige_bridge_connections(scope_json,scope_hash,secret_cipher,secret_hash,enabled,revision,mapping_json,updated_by,updated_at,connection_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[...$values,$key,$when]);
            $saved=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=?',[$key])[0]??[];
            if((int)($saved['revision']??0)!==$revision+1||($saved['scope_json']??null)!==$json||($saved['secret_hash']??null)!==$hash||($saved['secret_cipher']??null)!==$cipher||(int)$saved['enabled']!==($enabled?1:0)||$saved['mapping_json']!==$mappingJson)throw new RuntimeException('issuer_pige_connection_unconfirmed');
        });
    }
    public function saveMapping(string $key,int $revision,array $mapping,int $caller):void
    {
        self::key($key);$mapping=self::validateMapping($mapping);$caller=IssuerPolicy::id($caller);if($revision<1||$revision===PHP_INT_MAX)throw new DomainException('pige_configuration_invalid');
        IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($key,$revision,$mapping,$caller):void{
            $row=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=? FOR UPDATE',[$key])[0]??null;
            if(!$row||(int)$row['revision']!==$revision)throw new DomainException('pige_configuration_changed_reload');$scope=self::decoded($row);
            $old=$row['mapping_json']===null?null:self::validateMapping(json_decode($row['mapping_json'],true,16,JSON_THROW_ON_ERROR));$full=$mapping['enabled']||$old===null;
            $this->authorize((int)$scope['issuer_id'],$caller,$full,$full?$mapping['actor_id']:null);
            if(!$full){$mapping=$old;$mapping['enabled']=false;}
            else{if($mapping['enabled']&&(int)$row['enabled']!==1)throw new DomainException('pige_connection_disabled');$currency=$this->rows('SELECT id,name FROM '.$this->prefix.'currencies WHERE id=? FOR UPDATE',[$mapping['currency_id']])[0]??[];if(($currency['name']??null)!=='BRL')throw new DomainException('pige_currency_requires_brl');}
            $json=self::json($mapping);IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'pige_bridge_connections SET mapping_json=?,revision=?,updated_by=?,updated_at=? WHERE connection_key=?',[$json,$revision+1,$caller,gmdate('Y-m-d H:i:s'),$key]);
            $saved=$this->rows('SELECT revision,mapping_json FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=?',[$key])[0]??[];
            if((int)($saved['revision']??0)!==$revision+1||($saved['mapping_json']??null)!==$json)throw new RuntimeException('issuer_pige_mapping_unconfirmed');
        });
    }
    public function mapping(string $key):?array
    {
        self::key($key);$row=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=?',[$key])[0]??null;
        if(!$row)return null;$scope=self::decoded($row);return ['mapping'=>$row['mapping_json']===null?null:self::validateMapping(json_decode($row['mapping_json'],true,16,JSON_THROW_ON_ERROR)),'revision'=>(int)$row['revision'],'scope'=>$scope,'scope_hash'=>$row['scope_hash']];
    }
    public function assertMappingInOwned(string $key,array $scope,?int $revision=null):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables());self::key($key);$scope=self::scope($scope);
        $row=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=? FOR UPDATE',[$key])[0]??null;
        if(!$row||self::decoded($row)!==$scope||(int)$row['enabled']!==1||($revision!==null&&(int)$row['revision']!==$revision)||$row['mapping_json']===null)throw new DomainException('pige_invoice_mapping_denied');
        $mapping=self::validateMapping(json_decode($row['mapping_json'],true,16,JSON_THROW_ON_ERROR));if(!$mapping['enabled'])throw new DomainException('pige_invoice_mapping_disabled');$this->authorizeActor((int)$scope['issuer_id'],$mapping['actor_id']);
        $currency=$this->rows('SELECT name FROM '.$this->prefix.'currencies WHERE id=? FOR UPDATE',[$mapping['currency_id']])[0]??[];if(($currency['name']??null)!=='BRL')throw new DomainException('pige_currency_requires_brl');
        return ['mapping'=>$mapping,'revision'=>(int)$row['revision'],'scope'=>$scope,'scope_hash'=>$row['scope_hash'],'row'=>$row];
    }
    public function listed(int $caller):array
    {
        $caller=IssuerPolicy::id($caller);$acl=new IssuerResourceAccess($this->db,$this->prefix);$out=[];
        foreach($this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections ORDER BY connection_key') as $row){$scope=self::decoded($row);if(!$acl->can((int)$scope['issuer_id'],$caller,'settings','edit'))continue;$out[]=['connection_key'=>$row['connection_key'],'scope'=>$scope,'enabled'=>(int)$row['enabled']===1,'revision'=>(int)$row['revision'],'mapping'=>$row['mapping_json']===null?null:self::validateMapping(json_decode($row['mapping_json'],true,16,JSON_THROW_ON_ERROR)),'token_set'=>true,'updated_at'=>$row['updated_at']];}
        return $out;
    }
}
