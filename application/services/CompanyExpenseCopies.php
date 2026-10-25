<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseCopiesSchema.php';
require_once __DIR__.'/CompanyExpenses.php';
require_once __DIR__.'/CompanyClients.php';

/** Recreated reviewed manual cost only. No source, financial link, file or recurring authority is inherited. */
final class CompanyExpenseCopies
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function hash(array $v):string{return hash('sha256',self::json($v));}
    private static function key($v):string{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_expense_copy_invalid');return$v;}
    public static function nativeKey(int $issuer,int $actor,string $key):string{return hash('sha256','company-expense-copy|'.$issuer.'|'.$actor.'|'.self::key($key));}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyExpenses($this->db,$this->p))->tables(),['company_expense_copies'])));}
    private function auth(int $i,int $a):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$acl=new IssuerResourceAccess($this->db,$this->p);foreach(['view','create']as$cap)if(!$acl->can($i,$a,'expenses',$cap))throw new DomainException('company_expense_copy_denied');}
    private function tx(callable $work){CompanyExpenseSchema::assertValid($this->db,$this->p);CompanyExpenseCopiesSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    private static function row(array $r):array
    {
        foreach(['id','issuer_id','number','category_id','client_id','project_id','amount_cents','tax_cents','tax_rate','total_cents','revision','created_by']as$f){if($f==='tax_rate'&&array_key_exists($f,$r)&&$r[$f]===null)continue;if(!isset($r[$f])||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$r[$f]))throw new RuntimeException('issuer_expense_copy_unconfirmed');$r[$f]=(int)$r[$f];}
        if(!in_array($r['state']??null,['recorded','cancelled'],true))throw new RuntimeException('issuer_expense_copy_unconfirmed');return$r;
    }
    private function source(int $i,int $id):array{$r=$this->rows('SELECT * FROM '.$this->p.'company_expenses WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])[0]??null;if(!$r)throw new DomainException('company_expense_copy_denied');$profile=(new NativeCompanyExpenseFormProfile($this->db,$this->p))->verifyRow($r);$r=self::row($r);if($profile!==null)$r['native_form']=$profile;return$r;}
    private static function input(array $r):array{return['category_id'=>$r['category_id'],'client_id'=>$r['client_id'],'project_id'=>$r['project_id'],'currency'=>$r['currency'],'date'=>$r['expense_date'],'description'=>$r['description'],'note'=>$r['note'],'amount'=>CompanyExpenses::money($r['amount_cents']),]+($r['tax_rate']===null?['native_form'=>NativeCompanyExpenseFormValues::normalize($r['native_form']??[])]:['tax_rate'=>CompanyExpenses::money($r['tax_rate'])]);}
    private function audit(int $i,int $a,int $id,string $event,array $metadata,string $time):int
    {
        $json=self::json($metadata);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,$event,$i,$id,$time,$json]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=? AND created_at=?',[$audit,$a,$i,$id,$event,$json,$time]))throw new RuntimeException('issuer_expense_copy_unconfirmed');return$audit;
    }
    public function preview($issuer,$actor,$source):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($source);return$this->tx(function()use($i,$a,$id){$this->auth($i,$a);$r=$this->source($i,$id);$expense=new CompanyExpenses($this->db,$this->p);$categories=$expense->categories($i,$a);$projects=$expense->projectOptions($i,$a);$clients=[];if((new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'customers','view'))$clients=(new CompanyClients($this->db,$this->p))->page($i,$a,0,100)['items'];$this->auth($i,$a);return['source'=>$r,'source_revision'=>self::hash($r),'input'=>self::input($r),'categories'=>$categories,'projects'=>$projects,'clients'=>$clients];});
    }
    /** No write: operator selects every current reference/value before the independent confirmation step. */
    public function review($issuer,$actor,$source,string $revision,array $input):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($source);self::key($revision);return$this->tx(function()use($i,$a,$id,$revision,$input){$this->auth($i,$a);$r=$this->source($i,$id);if(!hash_equals(self::hash($r),$revision))throw new DomainException('company_expense_copy_stale');$review=(new CompanyExpenses($this->db,$this->p))->previewInOwnedTransaction($i,$a,$input);$this->auth($i,$a);if($this->source($i,$id)!==$r)throw new DomainException('company_expense_copy_stale');return['source_revision'=>$revision,'reference_revision'=>$review['reference_revision'],'input'=>$review['input'],'reference_names'=>array_intersect_key($review,array_flip(['category_name','client_name','project_name'])),'tax'=>$review['tax'],'total'=>$review['total']];});
    }
    private static function reviewInput(array $r):array
    {
        if(array_diff(array_keys($r),['source_revision','reference_revision','input','confirm'])||count($r)!==4||!is_array($r['input']??null)||!in_array($r['confirm']??null,[1,'1',true],true))throw new DomainException('company_expense_copy_invalid');self::key($r['source_revision']??null);self::key($r['reference_revision']??null);
        $fields=['category_id','client_id','project_id','currency','date','description','note','amount',array_key_exists('native_form',$r['input'])?'native_form':'tax_rate'];if(array_diff(array_keys($r['input']),$fields)||count($r['input'])!==9)throw new DomainException('company_expense_copy_invalid');
        // HTML transports identifiers as strings; preserve the same canonical receipt as the reviewed DTO.
        $input=[];foreach($fields as$f){$value=$r['input'][$f];if(in_array($f,['category_id','client_id','project_id'],true))$value=$f!=='category_id'&&in_array($value,[0,'0'],true)?0:IssuerPolicy::id($value);elseif($f==='native_form'){$value=is_array($value)?NativeCompanyExpenseFormValues::normalize($value):throw new DomainException('company_expense_copy_invalid');}elseif(!is_string($value))throw new DomainException('company_expense_copy_invalid');$input[$f]=$value;}return['source_revision'=>$r['source_revision'],'reference_revision'=>$r['reference_revision'],'input'=>$input,'confirm'=>1];
    }
    private static function recordFields(array $r):array
    {
        $out=[];foreach(['request_key','issuer_id','actor_id','source_expense_id','result_expense_id','native_key','source_hash','input_hash','result_hash','source_json','review_json','result_json','created_at']as$f){if(!array_key_exists($f,$r))throw new RuntimeException('issuer_expense_copy_unconfirmed');$out[$f]=in_array($f,['issuer_id','actor_id','source_expense_id','result_expense_id'],true)?(int)$r[$f]:$r[$f];}return$out;
    }
    private function nativeReceipt(string $key):array
    {
        $r=$this->rows('SELECT issuer_id,actor_id,operation,input_hash,result_id,created_at FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;if(!$r)throw new RuntimeException('issuer_expense_copy_unconfirmed');foreach(['issuer_id','actor_id','result_id']as$f)$r[$f]=(int)$r[$f];return$r;
    }
    /** Reconfirm the canonical creation effects after every adapter audit/write, including receipt reads. */
    private function nativeEffects(array $creation,int $i,int $a):array
    {
        $id=(int)$creation['id'];$key=hash('sha256','company-expense-journal|'.$i.'|'.$id.'|1|expense_recorded');
        $events=$this->rows('SELECT * FROM '.$this->p.'company_financial_events WHERE issuer_id=? AND source_type=? AND source_id=? AND kind=? FOR UPDATE',[$i,'expense',(string)$id,'expense_recorded']);
        if(count($events)!==1)throw new RuntimeException('issuer_expense_copy_unconfirmed');$event=$events[0];foreach(['issuer_id','actor_id','issuer_sequence']as$field)$event[$field]=IssuerPolicy::id($event[$field]);
        try{$decoded=(new CompanyFinancialJournal($this->db,$this->p))->decode($event);$event['amount']=$decoded['amount'];}catch(Throwable$e){throw new RuntimeException('issuer_expense_copy_unconfirmed',0,$e);}
        $snapshot=['date'=>$creation['expense_date'],'currency'=>$creation['currency'],'state'=>'recorded','revision'=>1,'base_amount'=>CompanyExpenses::money((int)$creation['amount_cents']),'tax_amount'=>CompanyExpenses::money((int)$creation['tax_cents']),'total'=>CompanyExpenses::money((int)$creation['total_cents']),'category_id'=>(int)$creation['category_id'],'client_id'=>(int)$creation['client_id'],'project_id'=>(int)$creation['project_id']];
        if(($event['evidence_key']??null)!==$key||IssuerPolicy::id($decoded['issuer_id'])!==$i||IssuerPolicy::id($decoded['actor_id'])!==$a||$decoded['source_type']!=='expense'||(string)$decoded['source_id']!==(string)$id||$decoded['kind']!=='expense_recorded'||$decoded['basis']!==CompanyFinancialJournal::CAPTURED||$decoded['book_date']!==$creation['expense_date']||$decoded['previous_book_date']!==null||$decoded['currency']!==$creation['currency']||$decoded['currency_basis']!=='explicit_document_iso'||$decoded['amount']!==$snapshot['total']||$decoded['before']!==[]||$decoded['after']!==$snapshot)throw new RuntimeException('issuer_expense_copy_unconfirmed');
        $expenseAudits=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? FOR UPDATE',[$i,$id,'company_expense_created']);
        $expected=json_encode(['number'=>(int)$creation['number'],'total_cents'=>(int)$creation['total_cents'],'currency'=>$creation['currency']],JSON_THROW_ON_ERROR);
        if(count($expenseAudits)!==1||(int)$expenseAudits[0]['actor_id']!==$a||$expenseAudits[0]['metadata_json']!==$expected)throw new RuntimeException('issuer_expense_copy_unconfirmed');
        $meta=json_encode(['evidence_key'=>$key,'kind'=>'expense_recorded','source_type'=>'expense','source_id'=>(string)$id,'payload_hash'=>$event['payload_hash']],JSON_THROW_ON_ERROR);
        $journalAudits=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$a,$i,$i,'company_financial_event_recorded',$meta]);
        if(count($journalAudits)!==1)throw new RuntimeException('issuer_expense_copy_unconfirmed');
        foreach(['id','actor_id','issuer_id','subject_id']as$field){$expenseAudits[0][$field]=IssuerPolicy::id($expenseAudits[0][$field]);$journalAudits[0][$field]=IssuerPolicy::id($journalAudits[0][$field]);}
        return ['journal'=>$event,'expense_audit'=>$expenseAudits[0],'journal_audit'=>$journalAudits[0]];
    }
    private function confirmed(array $r,int $i,int $a):array
    {
        if(!isset($r['issuer_id'],$r['actor_id'],$r['record_hash'],$r['audit_id']))throw new RuntimeException('issuer_expense_copy_unconfirmed');if((int)$r['issuer_id']!==$i||(int)$r['actor_id']!==$a)throw new DomainException('company_expense_copy_denied');$fields=self::recordFields($r);
        if(!hash_equals((string)$r['record_hash'],self::hash($fields))||!hash_equals((string)$r['source_hash'],hash('sha256',$r['source_json']))||!hash_equals((string)$r['input_hash'],hash('sha256',$r['review_json']))||!hash_equals((string)$r['result_hash'],hash('sha256',$r['result_json'])))throw new RuntimeException('issuer_expense_copy_unconfirmed');
        try{$source=json_decode($r['source_json'],true,32,JSON_THROW_ON_ERROR);$review=self::reviewInput(json_decode($r['review_json'],true,32,JSON_THROW_ON_ERROR));$result=json_decode($r['result_json'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('issuer_expense_copy_unconfirmed');}
        if(($source['id']??null)!==(int)$r['source_expense_id']||($source['issuer_id']??null)!==$i||($result['expense_id']??null)!==(int)$r['result_expense_id']||$result['expense_id']===$source['id']||($result['request_key']??null)!==$r['request_key']||($result['native_key']??null)!==$r['native_key']||self::nativeKey($i,$a,$r['request_key'])!==$r['native_key']||$review['source_revision']!==$r['source_hash'])throw new RuntimeException('issuer_expense_copy_unconfirmed');
        if(($result['native_effects']??null)!==$this->nativeEffects($result['creation']??[],$i,$a))throw new RuntimeException('issuer_expense_copy_unconfirmed');
        if(($result['native_receipt']??null)!==$this->nativeReceipt($r['native_key'])||$result['native_receipt']['issuer_id']!==$i||$result['native_receipt']['actor_id']!==$a||$result['native_receipt']['operation']!=='create'||$result['native_receipt']['result_id']!==$result['expense_id'])throw new RuntimeException('issuer_expense_copy_unconfirmed');
        $currentSource=$this->source($i,(int)$r['source_expense_id']);$current=$this->source($i,$result['expense_id']);foreach(['id','issuer_id','number','created_by','created_at']as$f){if(($result['creation'][$f]??null)!==$current[$f]||$source[$f]!==$currentSource[$f])throw new RuntimeException('issuer_expense_copy_unconfirmed');}
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=? AND created_at=?',[$r['audit_id'],$a,$i,$result['expense_id'],'company_expense_copied',self::json(['format'=>1,'record_hash'=>$r['record_hash']]),$r['created_at']]))throw new RuntimeException('issuer_expense_copy_unconfirmed');
        return['request_key'=>$r['request_key'],'expense_id'=>$result['expense_id'],'source'=>$source,'review'=>$review,'creation'=>$result['creation'],'current'=>$current,'source_state'=>$currentSource['state'],'reference_names'=>$result['reference_names']];
    }
    public function create($issuer,$actor,$source,array $review,string $key):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($source);self::key($key);$review=self::reviewInput($review);$inputHash=self::hash($review);
        return$this->tx(function()use($i,$a,$id,$review,$key,$inputHash){$this->auth($i,$a);$this->source($i,$id);$old=$this->rows('SELECT * FROM '.$this->p.'company_expense_copies WHERE request_key=? FOR UPDATE',[$key])[0]??null;if($old){if((int)$old['source_expense_id']!==$id||!hash_equals($old['input_hash'],$inputHash))throw new DomainException('company_expense_copy_conflict');$ack=$this->confirmed($old,$i,$a);$this->auth($i,$a);return['request_key'=>$key,'expense_id'=>$ack['expense_id']];}
            $before=$this->source($i,$id);if(!hash_equals(self::hash($before),$review['source_revision']))throw new DomainException('company_expense_copy_stale');$writer=new CompanyExpenses($this->db,$this->p);$prepared=$writer->previewInOwnedTransaction($i,$a,$review['input']);if(!hash_equals($prepared['reference_revision'],$review['reference_revision'])||$prepared['input']!==$review['input'])throw new DomainException('company_expense_copy_stale');
            $native=self::nativeKey($i,$a,$key);if($this->rows('SELECT request_key FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$native]))throw new DomainException('company_expense_copy_conflict');$resultId=$writer->createInOwnedTransaction($i,$a,$review['input'],$native);$creation=$this->source($i,$resultId);$receipt=$this->nativeReceipt($native);$result=['expense_id'=>$resultId,'request_key'=>$key,'native_key'=>$native,'creation'=>$creation,'native_receipt'=>$receipt,'native_effects'=>$this->nativeEffects($creation,$i,$a),'reference_names'=>array_intersect_key($prepared,array_flip(['category_name','client_name','project_name']))];$time=gmdate('Y-m-d H:i:s');$fields=['request_key'=>$key,'issuer_id'=>$i,'actor_id'=>$a,'source_expense_id'=>$id,'result_expense_id'=>$resultId,'native_key'=>$native,'source_hash'=>self::hash($before),'input_hash'=>$inputHash,'result_hash'=>self::hash($result),'source_json'=>self::json($before),'review_json'=>self::json($review),'result_json'=>self::json($result),'created_at'=>$time];$hash=self::hash(self::recordFields($fields));$audit=$this->audit($i,$a,$resultId,'company_expense_copied',['format'=>1,'record_hash'=>$hash],$time);$fields+=['record_hash'=>$hash,'audit_id'=>$audit];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_copies (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
            $saved=$this->rows('SELECT * FROM '.$this->p.'company_expense_copies WHERE request_key=? FOR UPDATE',[$key])[0]??[];$ack=$this->confirmed($saved,$i,$a);$this->auth($i,$a);$after=$writer->previewInOwnedTransaction($i,$a,$review['input']);if($after!==$prepared||$this->source($i,$id)!==$before||$ack['current']!==$creation)throw new DomainException('company_expense_copy_stale');return['request_key'=>$key,'expense_id'=>$resultId];
        });
    }
    public function receipt($issuer,$actor,string $key):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);return$this->tx(function()use($i,$a,$key){$this->auth($i,$a);$r=$this->rows('SELECT * FROM '.$this->p.'company_expense_copies WHERE request_key=? FOR UPDATE',[$key])[0]??null;if(!$r)throw new DomainException('company_expense_copy_denied');$dto=$this->confirmed($r,$i,$a);$this->audit($i,$a,$dto['expense_id'],'company_expense_copy_viewed',['request_key'=>$key],gmdate('Y-m-d H:i:s'));$this->auth($i,$a);$again=$this->rows('SELECT * FROM '.$this->p.'company_expense_copies WHERE request_key=? FOR UPDATE',[$key])[0]??[];if($this->confirmed($again,$i,$a)!==$dto)throw new DomainException('company_expense_copy_stale');return$dto;});
    }
}
