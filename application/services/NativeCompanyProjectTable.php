<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyProjectRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Native app-filters presentation contract without App_table registrations, global models or hooks. */
final class NativeCompanyProjectTable
{
    private array $rules;
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor,
        private string $identifier,array $options,private array $savedFilters,private array $customDefinitions=[])
    {
        if($identifier!=='projects')throw new DomainException('native_company_project_table_invalid');
        $this->authorize();
        if(array_keys($options)!==['billing_type','status','members'])throw new DomainException('native_company_project_table_invalid');
        foreach($options as$list){if(!is_array($list)||count($list)>10000)throw new DomainException('native_company_project_table_invalid');foreach($list as$o)if(!is_array($o)||array_keys($o)!==['value','label']||(!is_string($o['value'])&&!is_int($o['value']))||!is_string($o['label']))throw new DomainException('native_company_project_table_invalid');}
        $labels=['name'=>'project_name','start_date'=>'project_start_date','deadline'=>'project_deadline','billing_type'=>'project_billing_type','status'=>'project_status','members'=>'project_members'];
        $this->rules=[];
        foreach(NativeCompanyProjectRules::TYPES as$id=>$type){$rule=App_table_filter::new($id,$type)->label(_l($labels[$id]));if(isset($options[$id]))$rule->options($options[$id]);$this->rules[$id]=$rule;}
        // Persisted filters are supplied by the scoped Filters service, never fetched through filters_model.
        foreach($savedFilters as$filter){
            if(!is_array($filter)||!isset($filter['builder']['rules'])||!is_array($filter['builder']['rules'])
                ||!in_array($filter['identifier']??null,['projects'],true)
                ||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer
                ||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true)))throw new DomainException('native_company_project_table_invalid');
            foreach($filter['builder']['rules']as$rule)if(!is_array($rule)||!isset($this->rules[$rule['id']??''])
                ||($rule['type']??null)!==$this->rules[$rule['id']]->type)throw new DomainException('native_company_project_table_invalid');
        }
    }
    private function authorize():void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($this->issuer,$this->actor,'projects','view'))throw new DomainException('native_project_access_denied');
        if($this->customDefinitions){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            if((new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($this->issuer,$this->actor,'projects',['active'=>1])!==$this->customDefinitions)throw new DomainException('native_company_project_columns_changed');
        }
    }
    public function contextIdentity():array
    {
        $this->authorize();return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor];
    }
    public function normalizeBuilder(array $builder):array
    {
        $this->authorize();$store=new CompanySettingsStorage($this->db,$this->prefix);
        $format=$store->exists($this->issuer,'dateformat')?$store->get($this->issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $normalized=NativeCompanyProjectRules::normalizeBuilder($builder,$format);
        $this->authorize();return $normalized;
    }
    public function id():string{$this->authorize();return$this->identifier;}
    public function viewName():string{$this->authorize();return'projects';}
    public function rules():array{$this->authorize();return array_map(static fn($rule)=>clone $rule,array_values($this->rules));}
    public function findRule(string $id):App_table_filter
    {
        $this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_company_project_table_invalid');return clone $this->rules[$id];
    }
    public function rulesJs():\app\services\utilities\Js{return \app\services\utilities\Js::from($this->rules());}
    public function filtersJs():\app\services\utilities\Js
    {
        $this->authorize();$filters=$this->savedFilters;
        foreach($filters as&$filter)foreach($filter['builder']['rules']as&$rule){
            $rule=$this->findRule($rule['id'])->setValue($rule['value']??null)->setOperator($rule['operator']??null)->dynamic($rule['has_dynamic_value']??false);
        }unset($rule,$filter);
        return \app\services\utilities\Js::from($filters);
    }
}
