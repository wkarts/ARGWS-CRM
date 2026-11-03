<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyRelatedTaskRules.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';require_once __DIR__.'/utilities/Str.php';require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/CompanySettingsStorage.php';require_once __DIR__.'/CompanySettingsCatalog.php';
/** Original app-filters contract, with issuer-bound capabilities and actual option data. */
final class NativeCompanyRelatedTaskTable
{
    private array$rules=[];
    public function __construct(private$db,private string$p,private int$i,private int$a,array$options,private array$saved=[],private string$identifier='related_tasks')
    {
        $acl=$this->access();if(!in_array($this->identifier,['tasks','related_tasks'],true)||array_keys($options)!==['status','priority','assigned'])throw new DomainException('native_task_filter_invalid');
        $labels=['name'=>'tasks_dt_name','startdate'=>'tasks_dt_datestart','duedate'=>'task_duedate','status'=>'task_status','priority'=>'tasks_list_priority','todays_tasks'=>'todays_tasks','duedate_passed'=>'task_list_duedate_passed','not_assigned'=>'task_list_not_assigned','my_tasks'=>'tasks_view_assigned_to_user','my_following_tasks'=>'tasks_view_follower_by_user','upcoming_tasks'=>'upcoming_tasks','recurring'=>'recurring_tasks','billable'=>'task_billable','billed'=>'task_billed','assigned'=>'task_assigned'];
        foreach(NativeCompanyRelatedTaskRules::TYPES as$id=>$type){
            if($id==='recurring'&&!$acl->can($i,$a,'tasks','create')&&!$acl->can($i,$a,'tasks','edit'))continue;
            if(in_array($id,['billable','billed'],true)&&!$acl->can($i,$a,'invoices','create'))continue;
            $r=App_table_filter::new($id,$type)->label(_l($labels[$id]));if($id==='duedate')$r->withEmptyOperators();if(isset($options[$id])){foreach($options[$id]as$o)if(!is_array($o)||array_keys($o)!==['value','label']||!is_string($o['label'])||(!is_string($o['value'])&&!is_int($o['value'])))throw new DomainException('native_task_filter_invalid');$r->options($options[$id]);}$this->rules[$id]=$r;
        }
        foreach($saved as$f){if(($f['identifier']??null)!==$this->identifier||IssuerPolicy::id($f['issuer_id']??null)!==$i||(IssuerPolicy::id($f['staff_id']??null)!==$a&&!in_array($f['is_shared']??null,[1,'1',true],true)))throw new DomainException('native_task_filter_invalid');$this->normalizeBuilder($f['builder']??[]);}
    }
    private function access():IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($this->i,$this->a,'tasks','view'))throw new DomainException('native_company_task_denied');return$acl;}
    public function contextIdentity():array{$this->access();return['issuer_id'=>$this->i,'actor_id'=>$this->a];}
    public function id():string{$this->access();return$this->identifier;}
    public function viewName():string{$this->access();return$this->identifier==='tasks'?'tasks':'tasks_relations';}
    public function normalizeBuilder(array$b):array
    {
        $acl=$this->access();$store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($this->i,'dateformat')?$store->get($this->i,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');$b=NativeCompanyRelatedTaskRules::normalizeBuilder($b,$format);
        foreach($b['rules']as$r){$this->findRule($r['id']);if(in_array($r['id'],['billable','billed'],true)&&!$acl->can($this->i,$this->a,'invoices','create'))throw new DomainException('native_task_filter_denied');if($r['id']==='recurring'&&!$acl->can($this->i,$this->a,'tasks','create')&&!$acl->can($this->i,$this->a,'tasks','edit'))throw new DomainException('native_task_filter_denied');}$this->access();return$b;
    }
    public function rules():array{$this->access();return array_map(static fn($r)=>clone$r,array_values($this->rules));}
    public function findRule(string$id):App_table_filter{$this->access();if(!isset($this->rules[$id]))throw new DomainException('native_task_filter_invalid');return clone$this->rules[$id];}
    public function rulesJs():\app\services\utilities\Js{return \app\services\utilities\Js::from($this->rules());}
    public function filtersJs():\app\services\utilities\Js{$this->access();$filters=$this->saved;foreach($filters as&$f)foreach($f['builder']['rules']as&$r)$r=$this->findRule($r['id'])->setValue($r['value']??null)->setOperator($r['operator']??null)->dynamic($r['has_dynamic_value']??false);unset($r,$f);return \app\services\utilities\Js::from($filters);}
}
