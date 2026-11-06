<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseOriginalForm.php';

/** Original four-field bulk edit composed with the existing financial writer in one owned transaction. */
final class NativeCompanyExpenseBulk
{
    public const WIRE='original_expense_bulk_v1';
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int$i,int$a,string$cap):IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'expenses','view')||!$acl->can($i,$a,'expenses',$cap))throw new DomainException('company_expense_denied');return$acl;}
    private static function input(array$wire):array
    {
        if(array_diff(array_keys($wire),['native_expense_bulk','ids','revisions','request_key','mass_delete','amount','date','category','paymentmode'])||($wire['native_expense_bulk']??null)!==self::WIRE||!is_string($wire['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$wire['request_key'])||!is_array($wire['ids']??null)||!array_is_list($wire['ids'])||count($wire['ids'])<1||count($wire['ids'])>200||!is_array($wire['revisions']??null)||count($wire['revisions'])!==count($wire['ids'])||!in_array($wire['mass_delete']??'0',['0','1',0,1],true))throw new DomainException('native_expense_bulk_invalid');
        $selection=[];foreach($wire['ids']as$id){if(!is_string($id))throw new DomainException('native_expense_bulk_invalid');$ref=NativeCompanyResourceId::decode('expense',$id);if($ref['source_kind']!=='company_expense')throw new DomainException('company_expense_denied');$revision=$wire['revisions'][$id]??null;if(isset($selection[$id])||!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_expense_bulk_invalid');$selection[$id]=['id'=>$id,'source_id'=>$ref['source_id'],'revision'=>$revision];}
        if(array_diff(array_map('strval',array_keys($wire['revisions'])),array_column($selection,'id')))throw new DomainException('native_expense_bulk_invalid');uasort($selection,static fn($left,$right)=>$left['source_id']<=>$right['source_id']);
        $changes=[];foreach(['amount','date','category','paymentmode']as$field){$value=$wire[$field]??'';if(!is_string($value)||strlen($value)>64)throw new DomainException('native_expense_bulk_invalid');if($value!=='')$changes[$field]=$value;}
        $operation=(string)($wire['mass_delete']??'0')==='1'?'cancel':'update';if($operation==='cancel'&&$changes||$operation==='update'&&!$changes)throw new DomainException('native_expense_bulk_invalid');
        return['contract'=>self::WIRE,'operation'=>$operation,'selection'=>array_values($selection),'changes'=>$changes];
    }
    private function dateFormat(int$i):string{$store=new CompanySettingsStorage($this->db,$this->p);return$store->exists($i,'dateformat')?$store->get($i,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');}
    private static function childKey(string$key,string$id):string{return hash('sha256','native-expense-bulk-command|'.$key.'|'.$id);}
    private function receipt(string$key):?array{return$this->rows('SELECT * FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;}
    private function confirm(int$i,int$a,array$payload,string$key,string$hash,CompanyExpenses$expenses):array
    {
        $receipt=$this->receipt($key);if(!$receipt||(int)$receipt['issuer_id']!==$i||(int)$receipt['actor_id']!==$a||$receipt['operation']!=='original_bulk'||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('company_expense_conflict');
        $audit=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND event=? FOR UPDATE',[$receipt['result_id'],$i,$a,'native_expense_bulk_saved'])[0]??null;
        if(!$audit)throw new RuntimeException('native_expense_bulk_unconfirmed');$proof=json_decode($audit['metadata_json'],true,64,JSON_THROW_ON_ERROR);
        if(!is_array($proof)||array_keys($proof)!==['contract','request_key','input_hash','commands']||$proof['contract']!==self::WIRE||$proof['request_key']!==$key||$proof['input_hash']!==$hash||!is_array($proof['commands'])||!array_is_list($proof['commands'])||count($proof['commands'])!==count($payload['selection'])||(int)$audit['subject_id']!==$payload['selection'][0]['source_id'])throw new RuntimeException('native_expense_bulk_unconfirmed');
        foreach($payload['selection']as$position=>$selected){$command=$proof['commands'][$position]??null;$child=$this->receipt(self::childKey($key,$selected['id']));if(!is_array($command)||array_keys($command)!==['id','receipt']||$command['id']!==$selected['id']||!$child||$command['receipt']!==$child||(int)$child['issuer_id']!==$i||(int)$child['actor_id']!==$a||$child['operation']!==$payload['operation']||(int)$child['result_id']!==$selected['source_id'])throw new RuntimeException('native_expense_bulk_unconfirmed');if(!$expenses->get($i,$a,$selected['source_id']))throw new DomainException('company_expense_denied');}
        $this->access($i,$a,$payload['operation']==='cancel'?'delete':'edit');
        if($this->receipt($key)!==$receipt||$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE id=? FOR UPDATE',[$audit['id']])[0]!==$audit)throw new RuntimeException('native_expense_bulk_unconfirmed');return['processed'=>count($payload['selection']),'operation'=>$payload['operation']];
    }
    public function apply(int$i,int$a,array$wire):array
    {
        $payload=self::input($wire);$cap=$payload['operation']==='cancel'?'delete':'edit';$this->access($i,$a,$cap);$expenses=new CompanyExpenses($this->db,$this->p);$key=hash('sha256','native-expense-bulk-v1|'.$wire['request_key']);$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        $tables=array_values(array_unique(array_merge($expenses->tables(),['company_expense_recurring','options'])));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($i,$a,$payload,$cap,$expenses,$key,$hash){
            $this->access($i,$a,$cap)->lockFinancialIdentity($i,$a);if($this->receipt($key)!==null)return$this->confirm($i,$a,$payload,$key,$hash,$expenses);
            $changes=$payload['changes'];$format=$this->dateFormat($i);if(isset($changes['date']))$changes['date']=NativeCompanyInvoiceRules::dateValue($changes['date'],$format);
            if(isset($changes['category'])){$ref=NativeCompanyResourceId::decode('expense_category',$changes['category']);if($ref['source_kind']!=='company_expense_category')throw new DomainException('company_expense_reference_denied');$changes['category']=$ref['source_id'];}
            if(isset($changes['paymentmode'])){$ref=NativeCompanyResourceId::decode('payment_mode',$changes['paymentmode']);if($ref['source_kind']!=='company_payment_mode')throw new DomainException('native_payment_mode_denied');(new NativeCompanyPaymentModes($this->db,$this->p))->get($i,$a,$changes['paymentmode'],'expenses',false);}
            $frames=[];$commands=[];
            foreach($payload['selection']as$selected){
                $id=$selected['source_id'];$old=$expenses->get($i,$a,$id);if(!$old)throw new DomainException('company_expense_denied');
                if($this->rows("SELECT id FROM {$this->p}company_expense_recurring WHERE issuer_id=? AND source_expense_id=? AND state IN ('active','processing','paused','paused_processing') FOR UPDATE",[$i,$id]))throw new DomainException('native_expense_form_schedule_control_required');
                $input=null;if($payload['operation']==='update'){
                    $input=['category_id'=>$changes['category']??(int)$old['category_id'],'client_id'=>(int)$old['client_id'],'project_id'=>(int)$old['project_id'],'currency'=>$old['currency'],'date'=>$changes['date']??$old['expense_date'],'description'=>$old['description'],'note'=>$old['note'],'amount'=>$changes['amount']??CompanyExpenses::money((int)$old['amount_cents'])];
                    if(isset($old['native_form'])){$input['native_form']=$old['native_form'];if(isset($changes['paymentmode']))$input['native_form']['payment_mode']=$changes['paymentmode'];}
                    else{if(isset($changes['paymentmode']))throw new DomainException('native_expense_form_profile_required');$input['tax_rate']=CompanyExpenses::money((int)$old['tax_rate']);}
                }
                $child=self::childKey($key,$selected['id']);$expenses->changeInOwnedTransaction($i,$a,$id,$selected['revision'],$child,$input);$frames[$id]=$expenses->get($i,$a,$id);$command=$this->receipt($child);if(!$command)throw new RuntimeException('native_expense_bulk_unconfirmed');$commands[]=['id'=>$selected['id'],'receipt'=>$command];
            }
            $metadata=json_encode(['contract'=>self::WIRE,'request_key'=>$key,'input_hash'=>$hash,'commands'=>$commands],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$i,$a,$payload['selection'][0]['source_id'],'native_expense_bulk_saved',gmdate('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();if($audit<1)throw new RuntimeException('native_expense_bulk_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_requests(request_key,issuer_id,actor_id,operation,input_hash,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$i,$a,'original_bulk',$hash,$audit,gmdate('Y-m-d H:i:s')]);
            foreach($frames as$id=>$expected)if($expenses->get($i,$a,$id)!==$expected)throw new RuntimeException('native_expense_bulk_unconfirmed');if($this->dateFormat($i)!==$format)throw new DomainException('company_expense_stale');return$this->confirm($i,$a,$payload,$key,$hash,$expenses);
        });
    }
}
