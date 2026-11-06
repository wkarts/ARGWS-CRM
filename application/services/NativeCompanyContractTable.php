<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyContractRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Native app-filters presentation contract without App_table registrations, global models or hooks. */
final class NativeCompanyContractTable
{
    private array $rules;
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor,
        private string $identifier,array $options,private array $savedFilters,private array $customDefinitions=[])
    {
        if($identifier!=='contracts')throw new DomainException('native_company_contract_table_invalid');
        $this->authorize();
        if(array_keys($options)!==['contract_type','year'])throw new DomainException('native_company_contract_table_invalid');
        foreach($options as$list){if(!is_array($list)||count($list)>10000)throw new DomainException('native_company_contract_table_invalid');foreach($list as$o)if(!is_array($o)||array_keys($o)!==['value','label']||(!is_string($o['value'])&&!is_int($o['value']))||!is_string($o['label']))throw new DomainException('native_company_contract_table_invalid');}
        $labels=['subject'=>'contract_subject','datestart'=>'contract_start_date','dateend'=>'contract_end_date','contract_value'=>'contract_value','trash'=>'contract_trash','signed'=>'contracts_view_signed','marked_as_signed'=>'marked_as_signed','expired'=>'contracts_view_expired','contract_type'=>'contract_type','year'=>'year'];
        $this->rules=[];
        foreach(NativeCompanyContractRules::TYPES as$id=>$type){
            $rule=App_table_filter::new($id,$type)->label(_l($labels[$id]));
            if(isset($options[$id]))$rule->options($options[$id]);
            if($id==='dateend')$rule->withEmptyOperators();
            $this->rules[$id]=$rule;
        }
        // Persisted filters are supplied by the scoped Filters service, never fetched through filters_model.
        foreach($savedFilters as$filter){
            if(!is_array($filter)||!isset($filter['builder']['rules'])||!is_array($filter['builder']['rules'])
                ||!in_array($filter['identifier']??null,['contracts'],true)
                ||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer
                ||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true)))throw new DomainException('native_company_contract_table_invalid');
            foreach($filter['builder']['rules']as$rule)if(!is_array($rule)||!isset($this->rules[$rule['id']??''])
                ||($rule['type']??null)!==$this->rules[$rule['id']]->type)throw new DomainException('native_company_contract_table_invalid');
        }
    }
    private function authorize():void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($this->issuer,$this->actor,'contracts','view'))throw new DomainException('native_contract_access_denied');
        if($this->customDefinitions){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            if((new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($this->issuer,$this->actor,'contracts',['active'=>1])!==$this->customDefinitions)throw new DomainException('native_company_contract_columns_changed');
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
        $normalized=NativeCompanyContractRules::normalizeBuilder($builder,$format);
        $this->authorize();return $normalized;
    }
    public function id():string{$this->authorize();return$this->identifier;}
    public function viewName():string{$this->authorize();return'contracts';}
    public function rules():array{$this->authorize();return array_map(static fn($rule)=>clone $rule,array_values($this->rules));}
    public function findRule(string $id):App_table_filter
    {
        $this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_company_contract_table_invalid');return clone $this->rules[$id];
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
