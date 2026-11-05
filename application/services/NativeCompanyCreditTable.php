<?php
declare(strict_types=1);
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyCreditDirectory.php';

/** The existing app-filters interface backed only by the active company's catalogue. */
final class NativeCompanyCreditTable
{
    private array $rules=[];
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private array $options,private array $savedFilters=[],private array $definitions=[])
    {
        $this->contextIdentity();
        if (array_keys($options)!==['status','year']) throw new DomainException('native_credit_table_invalid');
        $labels=['number'=>'credit_note_number','reference_no'=>'reference_no','date'=>'credit_note_date','total'=>'credit_note_amount','remaining_amount'=>'credit_note_remaining_credits','status'=>'credit_note_status','year'=>'year'];
        foreach (NativeCompanyCreditRules::TYPES as $id=>$type) {
            $rule=App_table_filter::new($id,$type)->label(_l($labels[$id]));
            if (isset($options[$id])) $rule->options($options[$id]);
            $this->rules[$id]=$rule;
        }
        foreach (NativeCompanyCreditRules::catalogue($definitions) as $id=>$definition) {
            $rule=App_table_filter::new($id,NativeCompanyInvoiceCustomRules::TYPES[$definition['type']])->label($definition['name']);
            $choices=array_values(array_filter(array_map('trim',explode(',',$definition['options']??'')),static fn($value)=>$value!==''));
            if ($choices) $rule->options(array_map(static fn($value)=>['value'=>$value,'label'=>$value],$choices));
            $this->rules[$id]=$rule;
        }
        foreach ($savedFilters as $filter) {
            if (!is_array($filter)||($filter['identifier']??null)!=='credit_notes'||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true))) throw new DomainException('native_credit_filter_invalid');
            $this->normalizeBuilder($filter['builder']??[]);
        }
    }
    public function contextIdentity(): array
    {
        if (!(new IssuerResourceAccess($this->db,$this->p))->can($this->issuer,$this->actor,'credit_notes','view')) throw new DomainException('company_credit_access_denied');
        if ((new NativeCompanyCreditColumns($this->db,$this->p,$this->issuer,$this->actor))->definitions()!==$this->definitions) throw new DomainException('native_credit_columns_changed');
        return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor];
    }
    public function normalizeBuilder(array $builder): array
    {
        $this->contextIdentity(); $settings=new CompanySettingsStorage($this->db,$this->p);
        $format=$settings->exists($this->issuer,'dateformat')?$settings->get($this->issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        return NativeCompanyCreditRules::normalizeBuilder($builder,$format,$this->definitions);
    }
    public function id(): string { $this->contextIdentity(); return 'credit_notes'; }
    public function viewName(): string { $this->contextIdentity(); return 'credit_notes'; }
    public function rules(): array { $this->contextIdentity(); return array_map(static fn($rule)=>clone $rule,array_values($this->rules)); }
    public function findRule(string $id): App_table_filter
    {
        $this->contextIdentity(); if (!isset($this->rules[$id])) throw new DomainException('native_credit_filter_invalid'); return clone $this->rules[$id];
    }
    public function rulesJs(): \app\services\utilities\Js { return \app\services\utilities\Js::from($this->rules()); }
    public function filtersJs(): \app\services\utilities\Js
    {
        $this->contextIdentity(); $filters=$this->savedFilters;
        foreach ($filters as &$filter) foreach ($filter['builder']['rules'] as &$rule) $rule=$this->findRule($rule['id'])->setValue($rule['value']??null)->setOperator($rule['operator']??null)->dynamic($rule['has_dynamic_value']??false);
        unset($rule,$filter); return \app\services\utilities\Js::from($filters);
    }
}
