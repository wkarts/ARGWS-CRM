<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskPreferences.php';
require_once __DIR__.'/CompanyTaskOperationPreferences.php';
require_once __DIR__.'/CompanyTaskTimerPreferences.php';
require_once __DIR__.'/CompanyTaskTimerStopPreferences.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** Atomic adapter for the existing Settings/tasks form and existing policy nodes. */
final class NativeCompanyTaskSettings
{
    private const POLICIES=[CompanyTaskPreferences::class=>['default_task_status','new_task_auto_assign_current_member'],CompanyTaskOperationPreferences::class=>['default_task_priority','new_task_auto_follower_current_member'],CompanyTaskTimerPreferences::class=>['timer_started_change_status_in_progress'],CompanyTaskTimerStopPreferences::class=>['auto_stop_tasks_timers_on_new_timer']];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function extras():array{return ['tasks_kanban_limit'=>'50','show_all_tasks_for_project_member'=>'1','client_staff_add_edit_delete_task_comments_first_hour'=>'0','task_biillable_checked_on_creation'=>'1','round_off_task_timer_option'=>'0','round_off_task_timer_time'=>'5','task_modal_class'=>'modal-lg'];}
    public static function fields():array{return array_merge(array_keys(self::extras()),...array_values(self::POLICIES));}
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','issuers_audit','staff','options'];}
    private function authorize(int$i,int$a,string$cap='view'):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view')||!$acl->can($i,$a,'settings',$cap))throw new DomainException('native_task_settings_denied');}
    public static function validateExtras(array$v):void
    {
        if(array_diff(array_keys($v),array_keys(self::extras())))throw new DomainException('native_task_settings_invalid');
        foreach($v as$k=>$x){if(!is_string($x))throw new DomainException('native_task_settings_invalid');$ok=match($k){'tasks_kanban_limit'=>preg_match('/^[1-9][0-9]{0,3}$/D',$x)===1,'round_off_task_timer_option'=>in_array($x,['0','1','2','3'],true),'round_off_task_timer_time'=>in_array($x,['5','10','15','20','25','30','35','40','45'],true),'task_modal_class'=>in_array($x,['modal-lg','modal-xl','modal-xxl'],true),default=>in_array($x,['0','1'],true)};if(!$ok)throw new DomainException('native_task_settings_invalid');}
    }
    public static function prepare(array$v):array
    {
        $keys=array_keys($v);$expected=self::fields();sort($keys);sort($expected);if($keys!==$expected)throw new DomainException('native_task_settings_invalid');
        self::validateExtras(array_intersect_key($v,self::extras()));
        foreach(self::POLICIES as$class=>$fields){$slice=[];foreach($fields as$key)$slice[$key]=$key==='default_task_status'?CompanyTaskPreferences::fromNativeStatus($v[$key]):$v[$key];$class::prepare($slice);}
        $ordered=[];foreach(self::fields()as$key)$ordered[$key]=$v[$key];return$ordered;
    }
    private function capture(int$i,int$a):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($i,$a);$store=new CompanySettingsStorage($this->db,$this->p);$primary=$store->isPrimary($i);$values=[];$policies=[];
        foreach(self::extras()as$key=>$default){if($primary&&!$store->exists($i,$key))throw new DomainException('native_task_settings_invalid');$values[$key]=$store->exists($i,$key)?$store->get($i,$key):$default;}
        foreach(self::POLICIES as$class=>$fields){$policy=$class::readInOwnedTransaction($this->db,$this->p,$i);$policies[$class]=$policy;foreach($fields as$key)$values[$key]=$key==='default_task_status'?(string)CompanyTaskPreferences::toNativeStatus($policy[$key]):$policy[$key];}
        $values=self::prepare($values);$this->authorize($i,$a);return['values'=>$values,'policies'=>$policies,'revision'=>hash('sha256',json_encode([$i,$primary,$values,$policies],JSON_THROW_ON_ERROR))];
    }
    public function page(int$i,int$a):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a){$this->authorize($i,$a);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$v=$this->capture($i,$a);return['values'=>$v['values'],'revision'=>$v['revision'],'assets'=>null,'custom_fields'=>[]];});}
    public function save(int$i,int$a,string$revision,array$values):array
    {
        $values=self::prepare($values);$this->authorize($i,$a,'edit');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$revision,$values){
            $this->authorize($i,$a,'edit');(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$old=$this->capture($i,$a);if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($old['revision'],$revision))throw new DomainException('native_settings_stale');
            foreach(self::POLICIES as$class=>$fields){$slice=[];foreach($fields as$key)$slice[$key]=$key==='default_task_status'?CompanyTaskPreferences::fromNativeStatus($values[$key]):$values[$key];(new $class($this->db,$this->p))->saveInOwnedTransaction($i,$a,$old['policies'][$class]['revision'],$slice);}
            (new CompanySettingsStorage($this->db,$this->p))->updateNativeSettingsInOwnedTransaction($i,$a,'tasks',array_intersect_key($values,self::extras()));
            $now=$this->capture($i,$a);if($now['values']!==$values)throw new RuntimeException('issuer_task_settings_unconfirmed');$this->authorize($i,$a,'edit');return['values'=>$now['values'],'revision'=>$now['revision'],'assets'=>null,'custom_fields'=>[]];
        });
    }
}
