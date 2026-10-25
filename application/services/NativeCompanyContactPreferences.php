<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanyCustomerAccess.php';
/** Native factory defaults, never inherited tenant options or arbitrary plugin hooks. */
final class NativeCompanyContactPreferences
{
    public const PERMISSIONS='default_contact_permissions';
    public const CALLING='automatic_calling_codes';
    public const DEFAULT_PERMISSIONS='a:6:{i:0;s:1:"1";i:1;s:1:"2";i:2;s:1:"3";i:3;s:1:"4";i:4;s:1:"5";i:5;s:1:"6";}';
    public static function fields():array{return[self::PERMISSIONS,self::CALLING];}
    public static function defaultValue(string $field):string{return match($field){self::PERMISSIONS=>self::DEFAULT_PERMISSIONS,self::CALLING=>'1',default=>throw new DomainException('company_contact_preferences_invalid')};}
    public static function permissions(string $encoded):array
    {
        if(strlen($encoded)>200||!preg_match('/^a:[0-6]:\{(?:i:[0-5];(?:i:[1-6];|s:1:"[1-6]";))*\}$/D',$encoded))throw new DomainException('company_contact_preferences_invalid');
        preg_match('/^a:([0-6]):/',$encoded,$count);preg_match_all('/i:[0-5];(?:i:[1-6];|s:1:"[1-6]";)/',$encoded,$entries);
        if((int)$count[1]!==count($entries[0]))throw new DomainException('company_contact_preferences_invalid');
        set_error_handler(static function(){throw new DomainException('company_contact_preferences_invalid');});
        try{$values=unserialize($encoded,['allowed_classes'=>false,'max_depth'=>2]);}finally{restore_error_handler();}
        if(!is_array($values)||!array_is_list($values)||count($values)>6)throw new DomainException('company_contact_preferences_invalid');
        $out=[];foreach($values as$value){if(!in_array($value,[1,2,3,4,5,6,'1','2','3','4','5','6'],true))throw new DomainException('company_contact_preferences_invalid');$out[]=(int)$value;}
        if(count(array_unique($out))!==count($out))throw new DomainException('company_contact_preferences_invalid');return$out;
    }
    public static function prepare(array $values):array
    {
        if(array_diff(array_keys($values),self::fields()))throw new DomainException('company_contact_preferences_invalid');
        foreach($values as$field=>$value){if(!is_string($value))throw new DomainException('company_contact_preferences_invalid');if($field===self::PERMISSIONS)self::permissions($value);elseif(!in_array($value,['0','1'],true))throw new DomainException('company_contact_preferences_invalid');}return$values;
    }
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function authorize(int $issuer,int $actor,bool $write=false,?int $client=null):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$store=new CompanySettingsStorage($this->db,$this->p);
        if(($write?!$acl->can($issuer,$actor,'settings','edit'):!($acl->can($issuer,$actor,'settings','view')||$acl->can($issuer,$actor,'customers','view')||$acl->can($issuer,$actor,'customers','create')||$acl->can($issuer,$actor,'customers','edit')||($client!==null&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'view')))))throw new DomainException('company_contact_preferences_denied');
    }
    public function read(int $issuer,int $actor,?int $client=null):array
    {
        $this->authorize($issuer,$actor,false,$client);$store=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(self::fields()as$field)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):self::defaultValue($field);
        self::prepare($values);$this->authorize($issuer,$actor,false,$client);return ['values'=>$values,'permissions'=>self::permissions($values[self::PERMISSIONS]),'calling'=>$values[self::CALLING]==='1','revision'=>hash('sha256',json_encode([$issuer,$values],JSON_THROW_ON_ERROR))];
    }
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','options'];}
    public function save(int $issuer,int $actor,string $revision,array $values):void
    {
        IssuerTransaction::run($this->db,$this->p,self::tables(),fn()=>$this->saveInOwnedTransaction($issuer,$actor,$revision,$values));
    }
    /** Same participant for the native Clients settings form; no nested transaction. */
    public function saveInOwnedTransaction(int $issuer,int $actor,string $revision,array $values):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());self::prepare($values);$this->authorize($issuer,$actor,true);
        if(!hash_equals($this->read($issuer,$actor)['revision'],$revision))throw new DomainException('company_contact_preferences_stale');
        $store=new CompanySettingsStorage($this->db,$this->p);
        if($values){
            if($store->isPrimary($issuer)){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$issuer,$actor,'edit');foreach($values as$key=>$value)$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}
            else $store->updateBatchInOwnedTransaction($issuer,$actor,$values);
        }
        $this->authorize($issuer,$actor,true);$after=$this->read($issuer,$actor);
        foreach($values as$key=>$value)if($after['values'][$key]!==$value)throw new RuntimeException('company_contact_preferences_unconfirmed');
    }
}
