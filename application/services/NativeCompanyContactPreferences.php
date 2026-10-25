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
        $values=unserialize($encoded,['allowed_classes'=>false,'max_depth'=>2]);
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
        if($store->isPrimary($issuer)||($write?!$acl->can($issuer,$actor,'settings','edit'):!($acl->can($issuer,$actor,'settings','view')||$acl->can($issuer,$actor,'customers','view')||$acl->can($issuer,$actor,'customers','create')||$acl->can($issuer,$actor,'customers','edit')||($client!==null&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'view')))))throw new DomainException('company_contact_preferences_denied');
    }
    public function read(int $issuer,int $actor,?int $client=null):array
    {
        $this->authorize($issuer,$actor,false,$client);$store=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(self::fields()as$field)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):self::defaultValue($field);
        self::prepare($values);$this->authorize($issuer,$actor,false,$client);return ['values'=>$values,'permissions'=>self::permissions($values[self::PERMISSIONS]),'calling'=>$values[self::CALLING]==='1','revision'=>hash('sha256',json_encode([$issuer,$values],JSON_THROW_ON_ERROR))];
    }
    public function save(int $issuer,int $actor,string $revision,array $values):void
    {
        self::prepare($values);$this->authorize($issuer,$actor,true);
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'],function()use($issuer,$actor,$revision,$values):void{
            $this->authorize($issuer,$actor,true);if(!hash_equals($this->read($issuer,$actor)['revision'],$revision))throw new DomainException('company_contact_preferences_stale');
            (new CompanySettingsStorage($this->db,$this->p))->updateBatchInOwnedTransaction($issuer,$actor,$values);$this->authorize($issuer,$actor,true);
        });
    }
}
