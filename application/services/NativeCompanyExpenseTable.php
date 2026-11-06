<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/../libraries/App_table_filter.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';
require_once __DIR__.'/utilities/Js.php';
require_once __DIR__.'/NativeCompanyExpenseRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Original filters over explicit native/legacy identities; no ambient filters_model. */
final class NativeCompanyExpenseTable
{
    private array $rules=[];
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private string $identifier,array $options,private array $savedFilters=[])
    {
        if($identifier!=='company_expenses'||array_keys($options)!==['year','category','paymentmode'])throw new DomainException('native_expense_filter_invalid');$this->authorize();
        foreach($options as$list){if(!is_array($list)||count($list)>10000)throw new DomainException('native_expense_filter_invalid');foreach($list as$row)if(!is_array($row)||array_keys($row)!==['value','label']||(!is_int($row['value'])&&!is_string($row['value']))||!is_string($row['label']))throw new DomainException('native_expense_filter_invalid');}
        $labels=['expense_name'=>'expense_name','date'=>'expense_date','year'=>'year','amount'=>'expense_amount','category'=>'expense_report_category','billable'=>'expenses_list_billable','unbilled'=>'expenses_list_unbilled','recurring'=>'expenses_list_recurring','paymentmode'=>'payment_mode'];
        foreach(NativeCompanyExpenseRules::TYPES as$id=>$type){$rule=App_table_filter::new($id,$type)->label(rtrim(_l($labels[$id]),':'));if(isset($options[$id]))$rule->options($options[$id]);if($id==='paymentmode')$rule->withEmptyOperators()->emptyOperatorValue('');$this->rules[$id]=$rule;}
        foreach($savedFilters as$filter){if(!is_array($filter)||($filter['identifier']??null)!=='company_expenses'||IssuerPolicy::id($filter['issuer_id']??null)!==$issuer||(IssuerPolicy::id($filter['staff_id']??null)!==$actor&&!in_array($filter['is_shared']??null,[1,'1',true],true))||!is_array($filter['builder']??null))throw new DomainException('native_expense_filter_invalid');$this->normalizeBuilder($filter['builder']);}
    }
    private function authorize():void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($this->issuer,$this->actor,'expenses','view'))throw new DomainException('company_expense_denied');}
    public function contextIdentity():array{$this->authorize();return['issuer_id'=>$this->issuer,'actor_id'=>$this->actor];}
    public function normalizeBuilder(array $builder):array{$this->authorize();$store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($this->issuer,'dateformat')?$store->get($this->issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');$value=NativeCompanyExpenseRules::normalizeBuilder($builder,$format);$this->authorize();return$value;}
    public function id():string{$this->authorize();return$this->identifier;}
    public function viewName():string{$this->authorize();return'expenses';}
    public function rules():array{$this->authorize();return array_map(static fn($r)=>clone$r,array_values($this->rules));}
    public function findRule(string $id):App_table_filter{$this->authorize();if(!isset($this->rules[$id]))throw new DomainException('native_expense_filter_invalid');return clone$this->rules[$id];}
    public function rulesJs():\app\services\utilities\Js{return \app\services\utilities\Js::from($this->rules());}
    public function filtersJs():\app\services\utilities\Js{$this->authorize();$filters=$this->savedFilters;foreach($filters as&$filter)foreach($filter['builder']['rules']as&$rule)$rule=$this->findRule($rule['id'])->setValue($rule['value'])->setOperator($rule['operator'])->dynamic($rule['has_dynamic_value']??false);unset($rule,$filter);return \app\services\utilities\Js::from($filters);}
}
