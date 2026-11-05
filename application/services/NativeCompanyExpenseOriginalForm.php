<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenses.php';
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/CompanyExpenseFiles.php';
require_once __DIR__.'/CompanyExpenseRecurring.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyCatalogUi.php';
require_once __DIR__.'/NativeCompanyPaymentModes.php';
require_once __DIR__.'/NativeCompanyCurrencyReference.php';
require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Adapts the original expense fields to the existing owned financial contracts. */
final class NativeCompanyExpenseOriginalForm
{
    public const WIRE='original_expense_v1';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int $issuer,int $actor,string $cap):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'expenses','view')||!$acl->can($issuer,$actor,'expenses',$cap))throw new DomainException('company_expense_denied');
        return $acl;
    }
    public static function source($id):int
    {
        $ref=NativeCompanyResourceId::decode('expense',$id);
        if($ref['source_kind']!=='company_expense')throw new DomainException('company_expense_denied');
        return $ref['source_id'];
    }
    private function dateFormat(int $issuer):string
    {
        $settings=new CompanySettingsStorage($this->db,$this->p);
        return $settings->exists($issuer,'dateformat')?$settings->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
    }
    private static function optionalId($value):int{return in_array($value,[null,'',0,'0'],true)?0:IssuerPolicy::id($value);}
    private static function checked(array $wire,string $key):bool
    {
        if(!array_key_exists($key,$wire))return false;
        if(!in_array($wire[$key],['on','1',1,'0',0],true))throw new DomainException('native_expense_form_invalid');
        return in_array($wire[$key],['on','1',1],true);
    }
    private function category(int $issuer,int $actor,$id):array
    {
        $ref=NativeCompanyResourceId::decode('expense_category',$id);
        if($ref['source_kind']!=='company_expense_category')throw new DomainException('company_expense_reference_denied');
        foreach((new CompanyExpenses($this->db,$this->p))->categories($issuer,$actor) as $row)if((int)$row['id']===$ref['source_id']&&(int)$row['active']===1)return $row;
        throw new DomainException('company_expense_reference_denied');
    }
    /** Existing customer-change route, with live issuer/customer authority before all related reads. */
    public function customer(int $issuer,int $actor,$id):array
    {
        $acl=$this->access($issuer,$actor,'view');$id=self::optionalId($id);
        if($id===0)return ['customer_has_projects'=>false,'client_currency'=>0,'client_name'=>'','recurring_contacts'=>[]];
        $customers=new CompanyClients($this->db,$this->p);$client=$customers->get($issuer,$actor,$id);
        if(!$client||(int)$client['active']!==1)throw new DomainException('company_expense_reference_denied');
        $row=$this->rows('SELECT default_currency FROM '.$this->p.'clients WHERE userid=?',[$id])[0]??null;
        if(!$row)throw new DomainException('company_expense_reference_denied');
        $currency=self::optionalId($row['default_currency']);if($currency)(new NativeCompanyCurrencyReference($this->db,$this->p,$issuer,$actor))->currency($currency);
        $projects=$acl->can($issuer,$actor,'projects','view')&&$this->rows('SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? AND status=? LIMIT 1',[$issuer,$id,'active'])!==[];
        $contacts=[];
        if((int)$client['owner_issuer_id']===$issuer&&$acl->can($issuer,$actor,'invoices','view')&&$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'invoices','edit')){
            $contacts=$this->rows('SELECT c.id,c.firstname,c.lastname,c.email FROM '.$this->p.'contacts c WHERE c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions cp WHERE cp.userid=c.id AND cp.permission_id=1)=1 ORDER BY c.id LIMIT 100',[$id]);
        }
        if($customers->get($issuer,$actor,$id)!==$client||$this->rows('SELECT default_currency FROM '.$this->p.'clients WHERE userid=?',[$id])[0]!==$row)throw new DomainException('company_expense_stale');
        if($projects&&!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('company_expense_reference_denied');
        if($contacts)foreach(['view','create','edit'] as $cap)if(!$acl->can($issuer,$actor,'invoices',$cap))throw new DomainException('company_expense_reference_denied');
        $this->access($issuer,$actor,'view');
        return ['customer_has_projects'=>$projects,'client_currency'=>$currency,'client_name'=>$client['company'],'recurring_contacts'=>$contacts];
    }
    private function liveSchedules(int $issuer,int $id):array
    {
        return $this->rows("SELECT id FROM {$this->p}company_expense_recurring WHERE issuer_id=? AND source_expense_id=? AND state IN ('active','processing','paused','paused_processing') ORDER BY id",[$issuer,$id]);
    }
    /** Returns null only when the retained modern editor is required by an existing source contract. */
    public function form(int $issuer,int $actor,$canonical='', $customer=''):?array
    {
        $id=$canonical===''||$canonical===null?null:self::source($canonical);
        $acl=$this->access($issuer,$actor,$id===null?'create':'edit');$expenses=new CompanyExpenses($this->db,$this->p);
        $expense=$id===null?null:$expenses->get($issuer,$actor,$id);
        if($id!==null&&!$expense)throw new DomainException('company_expense_denied');
        if($expense&&(!isset($expense['native_form'])||$expense['state']!=='recorded'||$this->liveSchedules($issuer,$id)!==[]||$this->rows("SELECT expense_id FROM {$this->p}company_expense_billing_links WHERE issuer_id=? AND expense_id=? AND state IN ('converting','converted')",[$issuer,$id])!==[]))return null;
        $categories=array_values(array_filter($expenses->categories($issuer,$actor),static fn($r)=>(int)$r['active']===1));
        foreach($categories as &$row)$row['id']=NativeCompanyResourceId::encode('company_expense_category',$row['id']);unset($row);
        $currencies=array_values(array_filter((new NativeCompanyCurrencyReference($this->db,$this->p,$issuer,$actor))->currencies(),static fn($r)=>in_array($r['name'],['BRL','USD','EUR'],true)));
        $catalog=new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor);
        $taxes=array_values(array_filter($catalog->expenseEditorTaxes(),static fn($r)=>NativeCompanyResourceId::decode('tax',$r['id'])['source_kind']==='company_tax'));
        $modes=array_values(array_filter((new NativeCompanyPaymentModes($this->db,$this->p))->listing($issuer,$actor,'expenses',false),static fn($r)=>$r['source_kind']==='company_payment_mode'));
        $client=$expense?(int)$expense['client_id']:self::optionalId($customer);$customerData=$this->customer($issuer,$actor,$client);
        $data=['native_expense_form'=>true,'native_expense_id'=>$id===null?'':NativeCompanyResourceId::encode('company_expense',$id),'native_expense_revision'=>$expense['revision_token']??'',
            'categories'=>$categories,'currencies'=>$currencies,'taxes'=>$taxes,'payment_modes'=>$modes,'bodyclass'=>'expense','customer_currency'=>$customerData['client_currency'],
            'native_expense_customer'=>$customerData,'native_expense_files'=>$id===null?[]:(new CompanyExpenseFiles($this->db,$this->p))->page($issuer,$actor,$id),
            'native_expense_can_upload'=>$acl->can($issuer,$actor,'expenses','edit'),'native_expense_can_create_category'=>$acl->can($issuer,$actor,'expenses','create'),
            'native_expense_can_recur'=>$acl->can($issuer,$actor,'expenses','create')&&$acl->can($issuer,$actor,'expenses','edit'),
            'native_expense_can_bill'=>$acl->can($issuer,$actor,'expenses','edit')&&$acl->can($issuer,$actor,'invoices','view')&&$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'customers','view'),
            'native_expense_can_send'=>$acl->can($issuer,$actor,'invoices','edit'),
            'title'=>_l($id===null?'add_new':'edit',_l('expense'))];
        if($client)$data['customer_id']=$client;
        if($expense){
            $profile=$expense['native_form'];$currency=array_values(array_filter($currencies,static fn($r)=>$r['name']===$expense['currency']));
            if(count($currency)!==1)throw new DomainException('native_expense_form_invalid');
            $billability=IssuerTransaction::run($this->db,$this->p,$expenses->tables(),fn()=>(new NativeCompanyExpenseBillability($this->db,$this->p))->readInOwned($issuer,$actor,$id));
            $data['expense']=(object)['expenseid'=>$canonical,'expense_name'=>$profile['name'],'reference_no'=>$profile['reference_no'],'paymentmode'=>$profile['payment_mode'],
                'tax'=>$profile['taxes'][0]['id']??0,'tax2'=>$profile['taxes'][1]['id']??0,'note'=>$expense['note'],'category'=>NativeCompanyResourceId::encode('company_expense_category',$expense['category_id']),
                'date'=>$expense['expense_date'],'amount'=>CompanyExpenses::money((int)$expense['amount_cents']),'currency'=>$currency[0]['id'],'clientid'=>$client,
                'project_id'=>(int)$expense['project_id']===0?0:NativeCompanyResourceId::encode('company_project',$expense['project_id']),'billable'=>$billability['billable']??0,
                'invoiceid'=>null,'attachment'=>'','recurring_from'=>null,'recurring'=>0,'last_recurring_date'=>null,'repeat_every'=>0,'recurring_type'=>'','custom_recurring'=>0,'cycles'=>0,'total_cycles'=>0,'create_invoice_billable'=>0,'send_invoice_to_customer'=>0];
            $data['native_expense_project_name']=$expense['project_name'];
        }
        $this->access($issuer,$actor,$id===null?'create':'edit');return $data;
    }
    private function normalized(int $issuer,int $actor,array $wire,string $dateFormat,string $baseUrl):array
    {
        $category=$this->category($issuer,$actor,$wire['category']??null);$name=$wire['expense_name']??'';
        if(!is_string($name))throw new DomainException('native_expense_form_invalid');
        $taxes=[];foreach(['tax','tax2'] as $position=>$key){$value=$wire[$key]??'';if($value===''||$value==='0')continue;if($position===1&&!$taxes)throw new DomainException('native_expense_form_invalid');$tax=(new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor))->expenseEditorTax($value);$taxes[]=['position'=>$position+1,'id'=>$tax['id'],'name'=>$tax['name'],'rate'=>$tax['taxrate']];}
        $currency=(new NativeCompanyCurrencyReference($this->db,$this->p,$issuer,$actor))->currency($wire['currency']??null);
        $project=0;if(($wire['project_id']??'')!==''){$ref=NativeCompanyResourceId::decode('project',$wire['project_id']);if($ref['source_kind']!=='company_project')throw new DomainException('company_expense_reference_denied');$project=$ref['source_id'];}
        $fields=$wire['custom_fields']??[];if(!is_array($fields)||array_diff(array_keys($fields),['expenses'])||isset($fields['expenses'])&&!is_array($fields['expenses']))throw new DomainException('native_expense_form_invalid');
        $input=['category_id'=>(int)$category['id'],'client_id'=>self::optionalId($wire['clientid']??''),'project_id'=>$project,'currency'=>$currency['name'],
            'date'=>NativeCompanyInvoiceRules::dateValue($wire['date']??null,$dateFormat),'description'=>trim($name)===''?$category['name']:$name,'note'=>$wire['note']??'','amount'=>$wire['amount']??null,
            'native_form'=>['contract'=>NativeCompanyExpenseFormValues::CONTRACT,'name'=>$name,'reference_no'=>$wire['reference_no']??'','payment_mode'=>$wire['paymentmode']??'','taxes'=>$taxes],
            'billable'=>self::checked($wire,'billable')?1:0,'custom_fields'=>$fields['expenses']??[]];
        $repeat=$wire['repeat_every']??'';if(!is_string($repeat))throw new DomainException('native_expense_form_invalid');
        $invoice=self::checked($wire,'create_invoice_billable');$send=self::checked($wire,'send_invoice_to_customer');$schedule=null;
        if($repeat!==''){
            if($invoice&&!$input['billable']||$send&&!$invoice)throw new DomainException('native_expense_form_invalid');
            $custom=$repeat==='custom';if($custom){$unit=$wire['repeat_type_custom']??'';$count=$wire['repeat_every_custom']??null;}
            elseif(preg_match('/^([1-9][0-9]*)-(week|month|year)$/D',$repeat,$m)){$count=$m[1];$unit=$m[2];}else throw new DomainException('native_expense_form_invalid');
            if($invoice&&($wire['native_due_days']??'')==='')throw new DomainException('native_expense_form_due_days_required');
            $schedule=['interval_unit'=>$unit,'interval_value'=>$count,'custom'=>$custom,'max_cycles'=>$wire['cycles']??0,'billable'=>$invoice,'invoice_currency'=>$invoice?(int)$currency['id']:0,'due_days'=>$invoice?$wire['native_due_days']:0,'send'=>$send,'contact_id'=>$send?($wire['native_contact_id']??null):0,'base_url'=>$send?$baseUrl:''];
        }elseif($invoice||$send)throw new DomainException('native_expense_form_invalid');
        return [$input,$schedule];
    }
    /** Parent, form fields and an optional schedule commit together, or the full attempt rolls back. */
    public function save(int $issuer,int $actor,$canonical,array $wire,string $baseUrl='',?callable $decrypt=null):array
    {
        $allowed=['native_expense_form','request_key','expense_id','revision','expense_name','reference_no','note','category','date','amount','currency','tax','tax2','paymentmode','clientid','billable','project_id','custom_fields','repeat_every','repeat_every_custom','repeat_type_custom','cycles','create_invoice_billable','send_invoice_to_customer','native_due_days','native_contact_id'];
        if(array_diff(array_keys($wire),$allowed)||($wire['native_expense_form']??null)!==self::WIRE||!is_string($wire['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$wire['request_key']))throw new DomainException('native_expense_form_invalid');
        $id=$canonical===''||$canonical===null?null:self::source($canonical);$posted=$wire['expense_id']??'';
        if(($id===null&&$posted!=='')||$id!==null&&(string)$posted!==(string)$canonical)throw new DomainException('company_expense_denied');
        $cap=$id===null?'create':'edit';$this->access($issuer,$actor,$cap);$expenses=new CompanyExpenses($this->db,$this->p);$recurring=new CompanyExpenseRecurring($this->db,$this->p);
        $tables=array_values(array_unique(array_merge($expenses->tables(),$recurring->tables(),['company_taxes','company_payment_modes','currencies','company_custom_fields','company_custom_field_values'])));
        $key=$wire['request_key'];$hash=hash('sha256',json_encode([(string)$canonical,$wire,$baseUrl],JSON_THROW_ON_ERROR));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$id,$canonical,$wire,$baseUrl,$decrypt,$cap,$expenses,$recurring,$key,$hash){
            $this->access($issuer,$actor,$cap)->lockFinancialIdentity($issuer,$actor);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){
                if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||$receipt['operation']!=='original_form'||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('company_expense_conflict');
                $saved=$expenses->get($issuer,$actor,(int)$receipt['result_id']);if(!$saved)throw new DomainException('company_expense_denied');$this->access($issuer,$actor,$cap);
                return ['expenseid'=>NativeCompanyResourceId::encode('company_expense',$saved['id']),'source_id'=>(int)$saved['id']];
            }
            $format=$this->dateFormat($issuer);[$input,$schedule]=$this->normalized($issuer,$actor,$wire,$format,$baseUrl);
            if($id===null)$savedId=$expenses->createInOwnedTransaction($issuer,$actor,$input,hash('sha256','original-expense-parent|'.$key));
            else{
                $old=$expenses->get($issuer,$actor,$id);if(!$old||!isset($old['native_form']))throw new DomainException('native_expense_form_profile_required');
                if($this->liveSchedules($issuer,$id)!==[])throw new DomainException('native_expense_form_schedule_control_required');
                $revision=$wire['revision']??null;if(!is_string($revision))throw new DomainException('native_expense_form_invalid');
                $expenses->changeInOwnedTransaction($issuer,$actor,$id,$revision,hash('sha256','original-expense-parent|'.$key),$input);$savedId=$id;
            }
            $saved=$expenses->get($issuer,$actor,$savedId);
            if($schedule!==null)$recurring->createInOwnedTransaction($issuer,$actor,$savedId,$saved['revision_token'],$schedule,hash('sha256','original-expense-recurring|'.$key),$decrypt);
            if($format!==$this->dateFormat($issuer))throw new DomainException('company_expense_stale');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_requests(request_key,issuer_id,actor_id,operation,input_hash,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$actor,'original_form',$hash,$savedId,gmdate('Y-m-d H:i:s')]);
            $proof=$this->rows('SELECT * FROM '.$this->p.'company_expense_requests WHERE request_key=?',[$key])[0]??null;
            if(!$proof||(int)$proof['issuer_id']!==$issuer||(int)$proof['actor_id']!==$actor||$proof['operation']!=='original_form'||$proof['input_hash']!==$hash||(int)$proof['result_id']!==$savedId)throw new RuntimeException('issuer_expense_write_unconfirmed');
            $this->access($issuer,$actor,$cap);return ['expenseid'=>NativeCompanyResourceId::encode('company_expense',$savedId),'source_id'=>$savedId];
        });
    }
}
