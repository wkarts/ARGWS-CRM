<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanySalesRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';

/** Original app-filters contract, backed exclusively by the selected company's catalogue. */
final class NativeCompanySalesTable
{
    private array $rules=[];
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor,
        private string $kind,array $options,private array $savedFilters,private array $definitions=[])
    {
        $this->authorize();$types=NativeCompanySalesRules::types($kind);
        $labels=$kind==='estimates'?[
            'number'=>'estimate_add_edit_number','reference_no'=>'reference_no','total'=>'estimate_total',
            'subtotal'=>'estimate_subtotal','date'=>'estimate_data_date','expirydate'=>'estimate_dt_table_heading_expirydate',
            'sent'=>'estimate_status_sent','invoiced'=>'estimate_invoiced','signed'=>'contracts_view_signed',
            'sale_agent'=>'sale_agent_string','status'=>'estimate_status','year'=>'year',
        ]:[
            'subject'=>'proposal_subject','total'=>'proposal_total','subtotal'=>'estimate_subtotal','date'=>'proposal_date',
            'open_till'=>'proposal_open_till','signed'=>'contracts_view_signed','expired'=>'proposal_expired',
            'rel_type'=>'proposal_related','assigned'=>'proposal_assigned','status'=>'proposal_status','year'=>'year',
        ];
        foreach($labels as $id=>$label){
            $rule=App_table_filter::new($id,$types[$id])->label(_l($label));
            if(isset($options[$id]))$rule->options($options[$id]);
            if(in_array($id,['expirydate','open_till','sale_agent','assigned'],true))$rule->withEmptyOperators();
            if(in_array($id,['sale_agent','assigned'],true))$rule->emptyOperatorValue(0);
            $this->rules[$id]=$rule;
        }
        foreach(NativeCompanySalesRules::custom($kind,$definitions) as $id=>$definition){
            $rule=App_table_filter::new($id,NativeCompanyInvoiceCustomRules::TYPES[$definition['type']])->label($definition['name']);
            $choices=array_values(array_filter(array_map('trim',explode(',',$definition['options']??'')),static fn($v)=>$v!==''));
            if($choices)$rule->options(array_map(static fn($v)=>['value'=>$v,'label'=>$v],$choices));
            $this->rules[$id]=$rule;
        }
        foreach($savedFilters as $filter){
            if(!is_array($filter)||($filter['identifier']??null)!==$kind
                ||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer
                ||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true))
                ||!is_array($filter['builder']??null))throw new DomainException('native_sales_filter_invalid');
            $this->normalizeBuilder($filter['builder']);
        }
    }
    private function authorize():void
    {
        NativeCompanySalesRules::types($this->kind);
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($this->issuer,$this->actor,$this->kind,'view'))throw new DomainException('company_sales_denied');
        if($this->db->table_exists($this->prefix.'company_custom_fields')){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            $fieldto=$this->kind==='estimates'?'estimate':'proposal';
            if((new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($this->issuer,$this->actor,$fieldto,['active'=>1])!==$this->definitions)
                throw new DomainException('native_sales_columns_changed');
        }elseif($this->definitions!==[])throw new DomainException('native_sales_columns_changed');
    }
    public function contextIdentity():array { $this->authorize();return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor]; }
    public function id():string { $this->authorize();return $this->kind; }
    public function viewName():string { return $this->id(); }
    public function normalizeBuilder(array $builder):array
    {
        $this->authorize();$store=new CompanySettingsStorage($this->db,$this->prefix);
        $format=$store->exists($this->issuer,'dateformat')?$store->get($this->issuer,'dateformat'):'Y-m-d|%Y-%m-%d';
        return NativeCompanySalesRules::normalizeBuilder($this->kind,$builder,$format,$this->definitions);
    }
    public function rules():array { $this->authorize();return array_map(static fn($rule)=>clone $rule,array_values($this->rules)); }
    public function findRule(string $id):App_table_filter
    {
        $this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_sales_filter_invalid');return clone $this->rules[$id];
    }
    public function rulesJs():\app\services\utilities\Js { return \app\services\utilities\Js::from($this->rules()); }
    public function filtersJs():\app\services\utilities\Js
    {
        $this->authorize();$filters=$this->savedFilters;
        foreach($filters as &$filter)foreach($filter['builder']['rules'] as &$rule)
            $rule=$this->findRule($rule['id'])->setValue($rule['value']??null)->setOperator($rule['operator']??null)->dynamic($rule['has_dynamic_value']??false);
        unset($rule,$filter);return \app\services\utilities\Js::from($filters);
    }
    /** Scoped presentation: no ambient module hooks or legacy model hydration. */
    public static function statusHtml(string $kind,int $status,bool $badge=true):string
    {
        $label=htmlspecialchars(self::statusLabel($kind,$status),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        if(!$badge)return $label;
        $type=$kind==='estimates'?'estimate':'proposal';
        $color=self::statusColor($kind,$status);
        return '<span class="label label-'.$color.' s-status '.$type.'-status-'.$status.'">'.$label.'</span>';
    }
    public static function statusColor(string $kind,int $status):string
    {
        self::statusLabel($kind,$status);
        return ($kind==='estimates'?[1=>'default',2=>'info',3=>'danger',4=>'success',5=>'warning']:
            [1=>'default',2=>'danger',3=>'success',4=>'info',5=>'info',6=>'default'])[$status];
    }
    public static function statusLabel(string $kind,int $status):string
    {
        $suffix=($kind==='estimates'?[1=>'draft',2=>'sent',3=>'declined',4=>'accepted',5=>'expired']:
            [1=>'open',2=>'declined',3=>'accepted',4=>'sent',5=>'revised',6=>'draft'])[$status]??null;
        if($suffix===null)throw new DomainException('company_sales_invalid');
        return _l(($kind==='estimates'?'estimate':'proposal').'_status_'.$suffix);
    }
}
