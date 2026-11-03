<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Native app-filters presentation contract without App_table registrations, global models or hooks. */
final class NativeCompanyInvoiceTable
{
    private array $rules;
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor,
        private string $identifier,array $options,private array $savedFilters,private array $customDefinitions=[])
    {
        if(!in_array($identifier,['invoices','project_invoices'],true))throw new DomainException('native_company_invoice_table_invalid');
        $this->authorize();
        // Every list must come from an explicitly authorized source. Missing lists are errors, not invented empty state.
        if(count($options)!==4||array_diff(array_keys($options),['sale_agent','status','year','made_payment_by']))throw new DomainException('native_company_invoice_table_invalid');
        foreach($options as$list){
            if(!is_array($list)||count($list)>10000)throw new DomainException('native_company_invoice_table_invalid');
            foreach($list as$option)if(!is_array($option)||array_keys($option)!==['value','label']
                ||(!is_string($option['value'])&&!is_int($option['value']))||!is_string($option['label'])
                ||strlen((string)$option['value'])>100||strlen($option['label'])>500)throw new DomainException('native_company_invoice_table_invalid');
        }
        $specs=[
            'number'=>['NumberRule','invoice_add_edit_number'], 'total'=>['NumberRule','invoice_total'],
            'subtotal'=>['NumberRule','invoice_subtotal'], 'date'=>['DateRule','invoice_add_edit_date'],
            'duedate'=>['DateRule','invoice_dt_table_heading_duedate'], 'sent'=>['BooleanRule','estimate_status_sent'],
            'sale_agent'=>['SelectRule','sale_agent_string'], 'status'=>['MultiSelectRule','invoice_dt_table_heading_status'],
            'year'=>['MultiSelectRule','year'], 'recurring'=>['BooleanRule','invoices_list_recurring'],
            'not_have_payment'=>['BooleanRule','invoices_list_not_have_payment'],
            'made_payment_by'=>['MultiSelectRule','invoices_list_made_payment_by'],
        ];
        $this->rules=[];
        foreach($specs as$id=>[$type,$label]){
            $rule=App_table_filter::new($id,$type)->label(str_replace(' %s','',_l($label)));
            if(isset($options[$id]))$rule->options($options[$id]);
            if(in_array($id,['duedate','sale_agent'],true))$rule->withEmptyOperators();
            if($id==='sale_agent')$rule->emptyOperatorValue(0);
            $this->rules[$id]=$rule;
        }
        foreach(NativeCompanyInvoiceCustomRules::catalogue($customDefinitions)as$id=>$definition){
            $rule=App_table_filter::new($id,NativeCompanyInvoiceCustomRules::TYPES[$definition['type']])->label($definition['name']);
            $choices=array_values(array_filter(array_map('trim',explode(',',$definition['options']??'')),static fn($v)=>$v!==''));
            if($choices)$rule->options(array_map(static fn($value)=>['value'=>$value,'label'=>$value],$choices));
            $this->rules[$id]=$rule;
        }
        // Persisted filters are supplied by the scoped Filters service, never fetched through filters_model.
        foreach($savedFilters as$filter){
            if(!is_array($filter)||!isset($filter['builder']['rules'])||!is_array($filter['builder']['rules'])
                ||!in_array($filter['identifier']??null,['invoices','project_invoices'],true)
                ||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer
                ||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true)))throw new DomainException('native_company_invoice_table_invalid');
            foreach($filter['builder']['rules']as$rule)if(!is_array($rule)||!isset($this->rules[$rule['id']??''])
                ||($rule['type']??null)!==$this->rules[$rule['id']]->type)throw new DomainException('native_company_invoice_table_invalid');
        }
    }
    private function authorize():void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($this->issuer,$this->actor,'invoices','view'))throw new DomainException('company_invoice_access_denied');
        if($this->customDefinitions){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            if((new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($this->issuer,$this->actor,'invoice',['active'=>1])!==$this->customDefinitions)throw new DomainException('native_company_invoice_columns_changed');
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
        $normalized=NativeCompanyInvoiceRules::normalizeBuilder($builder,$format,$this->customDefinitions);
        $this->authorize();return $normalized;
    }
    public function id():string{$this->authorize();return$this->identifier;}
    public function viewName():string{$this->authorize();return'invoices';}
    public function rules():array{$this->authorize();return array_map(static fn($rule)=>clone $rule,array_values($this->rules));}
    public function findRule(string $id):App_table_filter
    {
        $this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_company_invoice_table_invalid');return clone $this->rules[$id];
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
