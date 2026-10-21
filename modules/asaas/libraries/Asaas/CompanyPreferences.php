<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPreferencesSchema.php';
require_once __DIR__.'/ModuleReadiness.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';

/** Company-owned presentation/issuance preferences. Never reads legacy options or changes provider accounts. */
final class CompanyPreferences
{
    public const DEFAULTS=['label'=>'Asaas','description'=>'Fatura {invoice_number}','allow_pix'=>'1','allow_boleto'=>'1','allow_card'=>'1'];
    public const ZERO='0000000000000000000000000000000000000000000000000000000000000000';
    public function __construct(private $db,private string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('asaas_company_prefix_invalid');}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','modules','issuers_audit','asaas_company_accounts'],CompanyPreferencesSchema::tables());}
    public function ready():bool
    {
        if(!ModuleReadiness::ready155($this->db,$this->prefix))return false;
        try{CompanyPreferencesSchema::ensure($this->db,$this->prefix,false);return true;}catch(\Throwable $error){return false;}
    }
    public function requireReady():void{ModuleReadiness::require155($this->db,$this->prefix);CompanyPreferencesSchema::ensure($this->db,$this->prefix,false);}
    private function auth(int $issuer,int $actor,bool $write):void
    {
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        $acl=new \IssuerResourceAccess($this->db,$this->prefix);
        if(!$acl->can($issuer,$actor,'settings',$write?'edit':'view')&&($write||!$acl->can($issuer,$actor,'invoices','view')))throw new \DomainException('asaas_company_access_denied');
        $row=$this->rows('SELECT active FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])[0]??[];
        if((int)($row['active']??0)!==1)throw new \DomainException('asaas_company_module_required');
    }
    private static function environment(string $value):string{if(!in_array($value,['production','sandbox'],true))throw new \DomainException('asaas_company_invalid_input');return $value;}
    private static function revision(string $value):string{if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('asaas_company_preferences_stale');return $value;}
    private function selection(int $issuer):array{return $this->rows('SELECT active_environment,revision FROM '.$this->prefix.'asaas_company_preferences WHERE issuer_id=?',[$issuer])[0]??['active_environment'=>null,'revision'=>self::ZERO];}
    private function profile(int $issuer,string $environment):array
    {
        $row=$this->rows('SELECT label,description,allow_pix,allow_boleto,allow_card,revision FROM '.$this->prefix.'asaas_company_profiles WHERE issuer_id=? AND environment=?',[$issuer,$environment])[0]??self::DEFAULTS+['revision'=>self::ZERO];
        foreach(self::DEFAULTS as $field=>$unused)$row[$field]=(string)$row[$field];return $row;
    }
    public function page($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$this->auth($issuer,$actor,false);
        $data=['ready'=>$this->ready(),'active_environment'=>null,'revision'=>self::ZERO,'profiles'=>[]];if(!$data['ready'])return $data;
        $data=array_replace($data,$this->selection($issuer));
        foreach(['production','sandbox'] as $environment)$data['profiles'][$environment]=$this->profile($issuer,$environment);
        return $data;
    }
    public static function validate(array $input):array
    {
        if(array_diff(array_keys($input),array_keys(self::DEFAULTS))||array_diff(array_keys(self::DEFAULTS),array_keys($input)))throw new \DomainException('asaas_company_profile_invalid');
        foreach(self::DEFAULTS as $name=>$unused){$value=$input[$name];if(!is_string($value))throw new \DomainException('asaas_company_profile_invalid');
            if(str_starts_with($name,'allow_')){if(!in_array($value,['0','1'],true))throw new \DomainException('asaas_company_profile_invalid');}
            elseif(!mb_check_encoding($value,'UTF-8')||mb_strlen($value,'UTF-8')>($name==='label'?191:500)||preg_match('/[\x00-\x1F\x7F]/',$value)||($name==='label'&&trim($value)===''))throw new \DomainException('asaas_company_profile_invalid');
        }
        // The only supported placeholder is stable invoice numbering; unknown templates fail visibly.
        if(preg_match('/[{}]/',str_replace('{invoice_number}','',$input['description'])))throw new \DomainException('asaas_company_profile_invalid');
        return array_replace(self::DEFAULTS,$input);
    }
    private function account(int $issuer,string $environment,string $expected):?array
    {
        self::revision($expected);$account=$this->rows('SELECT id,issuer_id,environment,revision,enabled,verified_at FROM '.$this->prefix.'asaas_company_accounts WHERE issuer_id=? AND environment=? FOR UPDATE',[$issuer,$environment])[0]??null;
        if(!hash_equals($account['revision']??self::ZERO,$expected))throw new \DomainException('asaas_company_preferences_stale');return $account;
    }
    private function transaction(int $issuer,int $actor,callable $operation)
    {
        $this->auth($issuer,$actor,true);$this->requireReady();
        return \IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($issuer,$actor,$operation){$this->auth($issuer,$actor,true);$this->requireReady();return $operation();});
    }
    private function audit(int $issuer,int $actor,string $event):void
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$issuer,gmdate('Y-m-d H:i:s')]);
        $id=(int)$this->db->insert_id();if($id<1||!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND event=?',[$id,$issuer,$event]))throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');
    }
    public function saveProfile($issuer,$actor,string $environment,array $values,string $revision,string $accountRevision):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::environment($environment);self::revision($revision);$values=self::validate($values);
        $this->transaction($issuer,$actor,function()use($issuer,$actor,$environment,$values,$revision,$accountRevision){
            $this->account($issuer,$environment,$accountRevision);$old=$this->profile($issuer,$environment);if(!hash_equals($old['revision'],$revision))throw new \DomainException('asaas_company_preferences_stale');$next=bin2hex(random_bytes(32));
            if($old['revision']===self::ZERO)\IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'asaas_company_profiles(issuer_id,environment,label,description,allow_pix,allow_boleto,allow_card,revision) VALUES(?,?,?,?,?,?,?,?)',[$issuer,$environment,...array_values($values),$next]);
            else \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'asaas_company_profiles SET label=?,description=?,allow_pix=?,allow_boleto=?,allow_card=?,revision=? WHERE issuer_id=? AND environment=?',[...array_values($values),$next,$issuer,$environment]);
            if($this->profile($issuer,$environment)!==$values+['revision'=>$next])throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');$this->audit($issuer,$actor,'company_asaas_profile_saved');
        });
    }
    public function selectEnvironment($issuer,$actor,string $environment,string $revision,string $accountRevision):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::environment($environment);self::revision($revision);
        $this->transaction($issuer,$actor,function()use($issuer,$actor,$environment,$revision,$accountRevision){
            $account=$this->account($issuer,$environment,$accountRevision);if(!$account||(int)$account['enabled']!==1||$account['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');
            $old=$this->selection($issuer);if(!hash_equals($old['revision'],$revision))throw new \DomainException('asaas_company_preferences_stale');$next=bin2hex(random_bytes(32));
            if($old['revision']===self::ZERO)\IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'asaas_company_preferences(issuer_id,active_environment,revision) VALUES(?,?,?)',[$issuer,$environment,$next]);
            else \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'asaas_company_preferences SET active_environment=?,revision=? WHERE issuer_id=?',[$environment,$next,$issuer]);
            if($this->selection($issuer)!==['active_environment'=>$environment,'revision'=>$next])throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');$this->audit($issuer,$actor,'company_asaas_environment_selected');
        });
    }
    /** Both preparation and reservation re-read live policy; caller freezes this result before its one POST. */
    public function snapshotInOwned(int $issuer,int $actor,array $account,string $method,string $number,?array $expected=null):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->prefix,CompanyPreferencesSchema::tables());$this->auth($issuer,$actor,false);$this->requireReady();
        if((int)$account['issuer_id']!==$issuer)throw new \DomainException('asaas_company_access_denied');
        if((int)$account['enabled']!==1||$account['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');
        $environment=self::environment($account['environment']);$selection=$this->selection($issuer);$profile=$this->profile($issuer,$environment);
        if($selection['active_environment']!==$environment)throw new \DomainException('asaas_company_active_environment_required');
        $flag=['PIX'=>'allow_pix','BOLETO'=>'allow_boleto','CREDIT_CARD'=>'allow_card'][$method]??null;if($flag===null||$profile[$flag]!=='1')throw new \DomainException('asaas_company_method_disabled');
        if($expected!==null){if(array_keys($expected)!==['selection_revision','profile_revision','account_revision'])throw new \DomainException('asaas_company_preferences_stale');foreach(['selection_revision'=>$selection['revision'],'profile_revision'=>$profile['revision'],'account_revision'=>$account['revision']] as $key=>$value)if(!is_string($expected[$key])||!hash_equals($value,$expected[$key]))throw new \DomainException('asaas_company_preferences_stale');}
        $description=str_replace('{invoice_number}',$number,$profile['description']);if(mb_strlen($description,'UTF-8')>500)throw new \DomainException('asaas_company_profile_invalid');
        return ['contract'=>'company_profile_v1','issuer_id'=>$issuer,'account_row'=>(int)$account['id'],'environment'=>$environment,'account_revision'=>$account['revision'],'selection_revision'=>$selection['revision'],'profile_revision'=>$profile['revision'],'label'=>$profile['label'],'description'=>$description,'billing_type'=>$method,'allowed_methods'=>array_values(array_filter(['PIX','BOLETO','CREDIT_CARD'],fn($value)=>$profile[['PIX'=>'allow_pix','BOLETO'=>'allow_boleto','CREDIT_CARD'=>'allow_card'][$value]]==='1'))];
    }
    public function persistInOwned(int $origin,array $snapshot):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->prefix,CompanyPreferencesSchema::tables());$json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'asaas_company_origin_profiles(origin_id,issuer_id,account_row,snapshot_json,snapshot_hash,created_at) VALUES(?,?,?,?,?,?)',[$origin,$snapshot['issuer_id'],$snapshot['account_row'],$json,$hash,gmdate('Y-m-d H:i:s')]);
        $row=$this->rows('SELECT snapshot_json,snapshot_hash FROM '.$this->prefix.'asaas_company_origin_profiles WHERE origin_id=?',[$origin])[0]??[];if(($row['snapshot_json']??null)!==$json||($row['snapshot_hash']??null)!==$hash)throw new \RuntimeException('issuer_asaas_preferences_unconfirmed');
    }
    /** Historical origins have no155 row. Existing snapshots never follow current selector/profile revisions. */
    public function originSnapshot(array $origin,array $meta):?array
    {
        $invoiceSnapshot=json_decode($meta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        $required=($invoiceSnapshot['asaas_profile_contract']??null)==='company_profile_v1';
        if(!$this->db->table_exists($this->prefix.'asaas_company_origin_profiles')){if($required)throw new \DomainException('asaas_company_profile_snapshot_divergence');return null;}
        $row=$this->rows('SELECT * FROM '.$this->prefix.'asaas_company_origin_profiles WHERE origin_id=?',[$origin['id']])[0]??null;if($row===null){if($required)throw new \DomainException('asaas_company_profile_snapshot_divergence');return null;}
        if(!$required)throw new \DomainException('asaas_company_profile_snapshot_divergence');
        $value=json_decode($row['snapshot_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($value)||!hash_equals($row['snapshot_hash'],hash('sha256',$row['snapshot_json']))||($value['contract']??null)!=='company_profile_v1'
            ||(int)$row['issuer_id']!==(int)$origin['issuer_id']||(int)$row['account_row']!==(int)$meta['account_row']
            ||($value['issuer_id']??null)!==(int)$origin['issuer_id']||($value['account_row']??null)!==(int)$meta['account_row']
            ||($value['environment']??null)!==$origin['environment']||($value['billing_type']??null)!==$meta['billing_type']
            ||!is_string($value['description']??null)||!is_string($value['label']??null))throw new \DomainException('asaas_company_profile_snapshot_divergence');
        return $value;
    }
}
