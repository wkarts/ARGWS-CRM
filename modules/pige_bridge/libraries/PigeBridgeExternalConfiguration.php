<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeConnections.php';
/** Separate, revocable external consent. Never creates accounts, payers or financial effects. */
final class PigeBridgeExternalConfiguration
{
    public function __construct(private $db,private $encryption,private string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('invalid_prefix');}
    public static function tables():array{return array_merge(PigeBridgeConnections::tables(),['pige_bridge_external_config','modules']);}
    public static function providerTables(string $provider):array{return $provider==='banco_brasil'?['bb_connections','bb_api_profiles','bb_api_credentials']:['asaas_company_accounts'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public static function validate(array $value):array
    {
        $keys=array_keys($value);sort($keys);$expected=['provider','account_row','actor_id','enabled','allow_reconcile'];if(($value['provider']??null)==='banco_brasil')$expected[]='profile_row';sort($expected);
        if($keys!==$expected||!in_array($value['provider'],['asaas','banco_brasil'],true)||!is_bool($value['enabled'])||!is_bool($value['allow_reconcile']))throw new DomainException('pige_external_mapping_invalid');
        foreach($value['provider']==='banco_brasil'?['account_row','actor_id','profile_row']:['account_row','actor_id'] as $key)if(!is_int($value[$key])||$value[$key]<1||$value[$key]>2147483647)throw new DomainException('pige_external_mapping_invalid');
        return ['provider'=>$value['provider'],'account_row'=>$value['account_row'],'actor_id'=>$value['actor_id'],'enabled'=>$value['enabled'],'allow_reconcile'=>$value['allow_reconcile']]+($value['provider']==='banco_brasil'?['profile_row'=>$value['profile_row']]:[]);
    }
    private function financial(array $scope,array $mapping,?string $expectedAccount=null):array
    {
        $issuer=(int)$scope['issuer_id'];$actor=$mapping['actor_id'];$acl=new IssuerResourceAccess($this->db,$this->prefix);
        foreach([['invoices','view'],['invoices','edit'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('pige_external_actor_denied');
        if($mapping['allow_reconcile']&&!$acl->can($issuer,$actor,'payments','create'))throw new DomainException('pige_external_actor_denied');
        if($mapping['provider']==='banco_brasil')return $this->bbFinancial($scope,$mapping,$expectedAccount);
        $module=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])[0]??[];
        if((int)($module['active']??0)!==1||version_compare($module['installed_version']??'0',$mapping['allow_reconcile']?'1.5.8':'1.5.7','<'))throw new DomainException('pige_external_module_required');
        $account=$this->rows('SELECT id,issuer_id,account_id,environment,enabled,verified_at,revision FROM '.$this->prefix.'asaas_company_accounts WHERE id=? FOR UPDATE',[$mapping['account_row']])[0]??null;
        if(!$account||(int)$account['issuer_id']!==$issuer||$account['account_id']!==$scope['account_id']||$account['environment']!==$scope['environment']||(int)$account['enabled']!==1||$account['verified_at']===null||($expectedAccount!==null&&!hash_equals($expectedAccount,$account['revision'])))throw new DomainException('pige_external_account_denied');return $account;
    }
    private function bbFinancial(array $scope,array $mapping,?string $expected):array
    {
        $issuer=(int)$scope['issuer_id'];$actor=$mapping['actor_id'];$acl=new IssuerResourceAccess($this->db,$this->prefix);
        foreach(array_merge(['view','api_issue','api_query'],$mapping['allow_reconcile']?['reconcile']:[]) as $cap)if(!$acl->can($issuer,$actor,'banco_brasil',$cap))throw new DomainException('pige_external_actor_denied');
        $module=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['banco_brasil'])[0]??[];
        if((int)($module['active']??0)!==1||($module['installed_version']??null)!=='1.2.5')throw new DomainException('pige_external_module_required');
        $account=$this->rows('SELECT * FROM '.$this->prefix.'bb_connections WHERE id=? FOR UPDATE',[$mapping['account_row']])[0]??null;
        $profile=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_profiles WHERE id=? FOR UPDATE',[$mapping['profile_row']])[0]??null;
        $credential=$this->rows('SELECT id FROM '.$this->prefix.'bb_api_credentials WHERE profile_id=? AND issuer_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$mapping['profile_row'],$issuer])[0]??null;
        if(!$account||!$profile||!$credential||(int)$account['issuer_id']!==$issuer||$account['provider']!=='banco_brasil'||$account['account_id']!==$scope['account_id']||$account['environment']!==$scope['environment']||(int)$profile['issuer_id']!==$issuer||(int)$profile['connection_id']!==$mapping['account_row']||$profile['configuration_hash']!==$account['configuration_hash']||!hash_equals($profile['profile_hash'],hash('sha256',$profile['definition_json'])))throw new DomainException('pige_external_account_denied');
        $definition=json_decode($profile['definition_json'],true,32,JSON_THROW_ON_ERROR);require_once __DIR__.'/../../banco_brasil/libraries/BancoBrasil/ApiClient.php';
        if(($definition['spec_sha256']??null)!==BancoBrasil\ApiClient::SPEC_SHA256||($definition['api_stage']??null)!==$profile['api_stage']||($definition['configuration_hash']??null)!==$account['configuration_hash']||(int)($definition['issuer_id']??0)!==$issuer||(int)($definition['connection_id']??0)!==$mapping['account_row'])throw new DomainException('pige_external_account_denied');
        foreach(['interest_policy','discount_policy','receipt_window_policy','discount_assignment_policy'] as $field)if(isset($definition[$field]))throw new DomainException('pige_bb_policy_requires_review');
        if(($profile['api_stage']==='production')!==($scope['environment']==='production'))throw new DomainException('pige_external_account_denied');
        $identity=hash('sha256',json_encode([$issuer,$account['account_id'],$account['environment'],$account['agreement_number'],$account['wallet'],$account['variation']],JSON_THROW_ON_ERROR));if(!hash_equals($identity,$account['identity_hash'])||!hash_equals(hash('sha256',json_encode([$identity,$account['issuer_tax_id'],$account['issuer_name']],JSON_THROW_ON_ERROR)),$account['configuration_hash']))throw new DomainException('pige_external_account_denied');
        $revision=hash('sha256',self::json([$profile['profile_hash'],$account['configuration_hash'],(int)$credential['id']]));
        if($expected!==null&&!hash_equals($expected,$revision))throw new DomainException('pige_external_account_denied');
        return ['id'=>(int)$account['id'],'revision'=>$revision,'profile_row'=>$mapping['profile_row']];
    }
    public function get(string $connection):?array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$connection))throw new DomainException('pige_connection_invalid');
        $row=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_external_config WHERE connection_key=?',[$connection])[0]??null;
        if(!$row)return null;$row['mapping']=self::validate(json_decode($row['mapping_json'],true,16,JSON_THROW_ON_ERROR));return $row;
    }
    /** Advisory read only: no transaction helper, writes, crypto or provider. Writer repeats all checks. */
    public function ready(string $connection,array $scope):bool
    {
        try{$row=$this->get($connection);$base=(new PigeBridgeConnections($this->db,$this->encryption,$this->prefix))->mapping($connection);
            if(!$row||!$base||!$row['mapping']['enabled']||!($base['mapping']['enabled']??false)||$base['scope']!==PigeBridgeConnections::scope($scope)||(int)$row['connection_revision']!==$base['revision']||$row['scope_hash']!==$base['scope_hash']||$row['mapping']['actor_id']!==$base['mapping']['actor_id'])return false;
            $this->financial($scope,$row['mapping'],$row['account_revision']);return true;
        }catch(Throwable $e){return false;}
    }
    public function save(string $connection,int $connectionRevision,int $revision,array $mapping,int $caller):void
    {
        $mapping=self::validate($mapping);if($revision<0||$connectionRevision<1)throw new DomainException('pige_external_revision_invalid');
        IssuerTransaction::run($this->db,$this->prefix,array_merge(self::tables(),self::providerTables($mapping['provider'])),function()use($connection,$connectionRevision,$revision,$mapping,$caller):void{
            $config=(new PigeBridgeConnections($this->db,$this->encryption,$this->prefix))->mapping($connection);if(!$config||$config['revision']!==$connectionRevision)throw new DomainException('pige_configuration_changed');
            $scope=$config['scope'];$issuer=(int)$scope['issuer_id'];$acl=new IssuerResourceAccess($this->db,$this->prefix);
            if(!$acl->can($issuer,$caller,'settings','edit'))throw new DomainException('pige_external_configuration_denied');
            $old=$this->get($connection);if(($old===null&&$revision!==0)||($old!==null&&(int)$old['revision']!==$revision))throw new DomainException('pige_external_configuration_changed');
            $accountRevision=$old['account_revision']??str_repeat('0',64);
            if($mapping['enabled']){
                $current=(new PigeBridgeConnections($this->db,$this->encryption,$this->prefix))->assertMappingInOwned($connection,$scope,$connectionRevision);
                if($mapping['actor_id']!==$current['mapping']['actor_id']||($mapping['actor_id']!==$caller&&!$acl->isMaster($caller)))throw new DomainException('pige_external_delegation_denied');
                $this->financial($scope,array_replace($mapping,['actor_id'=>$caller]));$account=$this->financial($scope,$mapping);$accountRevision=$account['revision'];
            }
            $row=['connection_revision'=>$connectionRevision,'revision'=>$revision+1,'mapping_json'=>self::json($mapping),'account_revision'=>$accountRevision,'scope_hash'=>$config['scope_hash'],'updated_by'=>$caller,'updated_at'=>gmdate('Y-m-d H:i:s')];
            if($old){$set=[];foreach(array_keys($row) as $key)$set[]='`'.$key.'`=?';IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'pige_bridge_external_config SET '.implode(',',$set).' WHERE connection_key=?',array_merge(array_values($row),[$connection]));}
            else{$row=['connection_key'=>$connection]+$row+['created_at'=>gmdate('Y-m-d H:i:s')];IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'pige_bridge_external_config (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));}
            $saved=$this->get($connection);if(!$saved||(int)$saved['revision']!==$revision+1||$saved['mapping']!==$mapping)throw new RuntimeException('pige_external_configuration_unconfirmed');
        });
    }
    public function assertInOwned(string $connection,array $scope,string $secretHash,?int $revision=null,bool $reconcile=false):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables());$row=$this->get($connection);
        if(!$row||!$row['mapping']['enabled']||($revision!==null&&(int)$row['revision']!==$revision)||($reconcile&&!$row['mapping']['allow_reconcile']))throw new DomainException('pige_external_mapping_denied');
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::providerTables($row['mapping']['provider']));
        $current=(new PigeBridgeConnections($this->db,$this->encryption,$this->prefix))->assertMappingInOwned($connection,$scope,(int)$row['connection_revision']);
        if(!hash_equals($current['row']['secret_hash'],$secretHash)||!hash_equals($current['scope_hash'],$row['scope_hash'])||$current['mapping']['actor_id']!==$row['mapping']['actor_id'])throw new DomainException('pige_external_mapping_denied');
        $row['account']=$this->financial($scope,$row['mapping'],$row['account_revision']);return $row;
    }
}
