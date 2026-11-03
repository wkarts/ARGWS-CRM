<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRoles.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** The original default_staff_role option, with explicit company role lineage. */
final class NativeCompanyStaffPreferences
{
    public function __construct(private $db,private string $p){}
    public static function prepare(array $values):array
    {
        if(array_keys($values)!==['default_staff_role']||!is_string($values['default_staff_role']))throw new DomainException('native_staff_preferences_invalid');
        if($values['default_staff_role']!==''){$ref=NativeCompanyResourceId::decode('staff_role',$values['default_staff_role']);if($ref['source_kind']!=='company_staff_role')throw new DomainException('native_staff_preferences_invalid');}
        return$values;
    }
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyStaffRoles($this->db,$this->p))->tables(),['roles','options'])));}
    public function configured(array $roles,int $i,int $a):string
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||(!$acl->can($i,$a,'staff','view')&&!$acl->can($i,$a,'settings','view')))throw new DomainException('native_staff_preferences_denied');
        $store=new CompanySettingsStorage($this->db,$this->p);$value=$store->exists($i,'default_staff_role')?$store->get($i,'default_staff_role'):'';self::prepare(['default_staff_role'=>$value]);
        if($value!==''){$ref=NativeCompanyResourceId::decode('staff_role',$value);if(!isset($roles[$ref['source_id']]))throw new DomainException('native_staff_preferences_invalid');}
        if(!$acl->can($i,$a,'staff','view')&&!$acl->can($i,$a,'settings','view'))throw new DomainException('native_staff_preferences_denied');return$value;
    }
    private function primaryPage(int$i,int$a):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()!==$i||!$acl->can($i,$a,'settings','view'))throw new DomainException('native_staff_preferences_denied');
        $rows=IssuerStorage::query($this->db,'SELECT roleid,name FROM '.$this->p.'roles ORDER BY roleid')->result_array();
        $values=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=?',['default_staff_role'])->result_array();
        if(count($values)!==1||!is_string($values[0]['value']))throw new DomainException('native_staff_preferences_invalid');$value=$values[0]['value'];
        if($value!==''&&(!preg_match('/^[1-9][0-9]*$/D',$value)||!in_array($value,array_map('strval',array_column($rows,'roleid')),true)))throw new DomainException('native_staff_preferences_invalid');
        if($acl->primaryId()!==$i||!$acl->can($i,$a,'settings','view'))throw new DomainException('native_staff_preferences_denied');
        return['values'=>['default_staff_role'=>$value],'roles'=>$rows,'revision'=>hash('sha256',json_encode([$i,'native_roles',$rows,$value],JSON_THROW_ON_ERROR))];
    }
    public function page(int $i,int $a):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view'))throw new DomainException('native_staff_preferences_denied');
        if($acl->primaryId()===$i)return$this->primaryPage($i,$a);
        $roles=(new NativeCompanyStaffRoles($this->db,$this->p))->page($i,$a);$value=$this->configured($roles['roles'],$i,$a);
        if(!$acl->can($i,$a,'settings','view')||$roles['revision']!==(new NativeCompanyStaffRoles($this->db,$this->p))->page($i,$a)['revision']||$value!==$this->configured($roles['roles'],$i,$a))throw new DomainException('native_staff_preferences_stale');
        return['values'=>['default_staff_role'=>$value],'roles'=>array_map(fn($id,$role)=>['roleid'=>NativeCompanyResourceId::encode('company_staff_role',(int)$id),'name'=>$role->name],array_keys($roles['roles']),array_values($roles['roles'])),'revision'=>hash('sha256',json_encode([$i,$roles['revision'],$value],JSON_THROW_ON_ERROR))];
    }
    public function saveInOwnedTransaction(int $i,int $a,string $revision,array $values):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','edit'))throw new DomainException('native_staff_preferences_denied');$acl->lockFinancialIdentity($i,$a);
        $primary=$acl->primaryId()===$i;
        if($primary){if(array_keys($values)!==['default_staff_role']||!is_string($values['default_staff_role']))throw new DomainException('native_staff_preferences_invalid');}else $values=self::prepare($values);
        $old=$this->page($i,$a);if(!hash_equals($old['revision'],$revision))throw new DomainException('native_staff_preferences_stale');
        if($values['default_staff_role']!==''&&!in_array($values['default_staff_role'],array_map('strval',array_column($old['roles'],'roleid')),true))throw new DomainException('native_staff_preferences_invalid');
        if($primary){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$i,$a,'edit');if($old['values']!==$values){$guard->writeOption('update','default_staff_role',$values['default_staff_role']);$guard->audit('company_settings_batch_updated');$guard->confirm();}}else (new CompanySettingsStorage($this->db,$this->p))->updateBatchInOwnedTransaction($i,$a,$values);
        if($this->page($i,$a)['values']!==$values)throw new RuntimeException('native_staff_preferences_unconfirmed');
        if(!$acl->can($i,$a,'settings','edit'))throw new DomainException('native_staff_preferences_denied');
    }
}
