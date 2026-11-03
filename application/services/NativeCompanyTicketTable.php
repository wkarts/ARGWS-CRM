<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyTicketRules.php';

/** Native app-filters presentation contract without App_table registrations, global models or hooks. */
final class NativeCompanyTicketTable
{
    private array $rules;
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor,
        private string $identifier,array $options,private array $savedFilters,private array $customDefinitions=[])
    {
        if($identifier!=='tickets')throw new DomainException('native_company_ticket_table_invalid');
        $this->authorize();
        if(array_keys($options)!==['department','status','priority','service','assigned'])throw new DomainException('native_company_ticket_table_invalid');
        foreach($options as$list){if(!is_array($list)||count($list)>10000)throw new DomainException('native_company_ticket_table_invalid');foreach($list as$o)if(!is_array($o)||array_keys($o)!==['value','label']||(!is_string($o['value'])&&!is_int($o['value']))||!is_string($o['label']))throw new DomainException('native_company_ticket_table_invalid');}
        $labels=['subject'=>'ticket_dt_subject','department'=>'ticket_dt_department','status'=>'ticket_dt_status','priority'=>'ticket_dt_priority','service'=>'ticket_dt_service','merged'=>'merged','my_tickets'=>'my_tickets_assigned','assigned'=>'ticket_assigned'];
        $this->rules=[];
        foreach(NativeCompanyTicketRules::TYPES as$id=>$type){
            $rule=App_table_filter::new($id,$type)->label(_l($labels[$id]));
            if(isset($options[$id]))$rule->options($options[$id]);
            if($id==='assigned')$rule->withEmptyOperators()->emptyOperatorValue(0);
            if(in_array($id,['department','assigned'],true))$rule->isVisible(fn()=>$this->administrative());
            $this->rules[$id]=$rule;
        }
        // Persisted filters are supplied by the scoped Filters service, never fetched through filters_model.
        foreach($savedFilters as$filter){
            if(!is_array($filter)||!isset($filter['builder']['rules'])||!is_array($filter['builder']['rules'])
                ||!in_array($filter['identifier']??null,['tickets'],true)
                ||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer
                ||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true)))throw new DomainException('native_company_ticket_table_invalid');
            $this->normalizeBuilder($filter['builder']);
            foreach($filter['builder']['rules']as$rule)if(!is_array($rule)||!isset($this->rules[$rule['id']??''])
                ||($rule['type']??null)!==$this->rules[$rule['id']]->type)throw new DomainException('native_company_ticket_table_invalid');
        }
    }
    private function authorize():void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($this->issuer,$this->actor,'tickets','view'))throw new DomainException('native_ticket_access_denied');
        if($this->customDefinitions){
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            if((new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($this->issuer,$this->actor,'tickets',['active'=>1])!==$this->customDefinitions)throw new DomainException('native_company_ticket_columns_changed');
        }
    }
    public function contextIdentity():array
    {
        $this->authorize();return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor];
    }
    public function normalizeBuilder(array $builder):array
    {
        $this->authorize();$normalized=NativeCompanyTicketRules::normalizeBuilder($builder);
        if(!$this->administrative())foreach($normalized['rules']as$r)if(in_array($r['id'],['department','assigned'],true))throw new DomainException('native_ticket_filter_denied');
        $this->authorize();return $normalized;
    }
    private function administrative():bool
    {
        $this->authorize();return(new IssuerResourceAccess($this->db,$this->prefix))->administrative($this->issuer,$this->actor);
    }
    public function id():string{$this->authorize();return$this->identifier;}
    public function viewName():string{$this->authorize();return'tickets';}
    public function rules():array{$this->authorize();return array_map(static fn($rule)=>clone $rule,array_values($this->rules));}
    public function findRule(string $id):App_table_filter
    {
        $this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_company_ticket_table_invalid');return clone $this->rules[$id];
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
