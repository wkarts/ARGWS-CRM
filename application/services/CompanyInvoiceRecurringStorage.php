<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';require_once __DIR__.'/CompanyInvoiceRecurringSchema.php';require_once __DIR__.'/CompanyInvoiceRecurringDates.php';

/** Owned schedule storage;425 series live in different tables and are never adopted. */
abstract class CompanyInvoiceRecurringStorage extends CompanySalesBase
{
    public static function input(array $input):array
    {
        if(!array_key_exists('calculation_contract',$input))return parent::input($input);if(array_diff(array_keys($input),['client_id','currency','date','due_date','items','note','discount_mode','discount_value','discount_type','adjustment','calculation_contract']))throw new DomainException('company_sales_invalid');require_once __DIR__.'/CompanyInvoiceDraftsInput.php';$base=CompanyInvoiceDraftsInput::normalize($input);self::date($base['due_date']);return$base;
    }
    public function tables():array
    {
        $tables=array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','issuers_client_links','clients','currencies','company_currency_units','options','issuers_audit','company_invoice_requests','invoices','issuers_invoice_documents','itemable','item_tax'],array_keys(CompanyInvoiceRecurringSchema::definitions()));$tables=array_merge($tables,(new CompanyInvoiceRecurringModes($this->db,$this->prefix))->tables());
        foreach(['contacts','contact_permissions','emailtemplates','mail_queue','company_invoice_deliveries']as$t)if($this->db->table_exists($this->prefix.$t))$tables[]=$t;
        foreach(['CompanyGoogleMailOAuth','CompanyMailTransportPolicy']as$type)if(is_file(__DIR__.'/'.$type.'.php')){require_once __DIR__.'/'.$type.'.php';$tables=array_merge($tables,$type::participantTables($this->db,$this->prefix));}return array_values(array_unique($tables));
    }
    protected function access(int$i,int$a,bool$write=true):void{foreach($write?['view','create','edit']:['view']as$c)$this->auth($i,$a,'invoices',$c);if($write)$this->auth($i,$a,'customers');}
    protected function scheduleTx(int$i,int$a,callable$fn,bool$write=true)
    {
        CompanyInvoiceRecurringSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),function()use($i,$a,$fn,$write){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($i,$a);$this->access($i,$a,$write);$result=$fn();$this->access($i,$a,$write);return$result;});
    }
    public static function runKey(int$i,int$id,string$date):string{return hash('sha256','company-invoice-schedule:'.$i.':'.$id.':'.$date);}
    public static function deliveryKey(int$i,int$id,string$date):string{return hash('sha256','company-invoice-schedule-mail:'.$i.':'.$id.':'.$date);}
    protected function schedule(int$i,int$id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_schedules WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])[0]??null;if(!$r)throw new DomainException('company_invoice_recurring_denied');
        foreach(['payload','config','context','transport']as$k)if(!hash_equals($r[$k.'_hash'],self::hash($this->decode($r[$k.'_json']))))throw new RuntimeException('issuer_invoice_recurring_corrupt');$payload=$this->decode($r['payload_json']);$config=$this->decode($r['config_json']);$context=$this->decode($r['context_json']);
        if(CompanyInvoiceRecurringDates::configuration($config)!==$config||!hash_equals($r['input_hash'],self::hash([$payload,$config]))||(int)($context['issuer_id']??0)!==$i||(int)($context['recipient']['client_id']??0)!==(int)$payload['client_id']||(int)$r['completed_cycles']<0||(int)$r['completed_cycles']>4000000||($config['cycles']>0&&(int)$r['completed_cycles']>$config['cycles'])||!in_array($r['state'],['active','processing','paused','paused_processing','cancelled','completed'],true))throw new RuntimeException('issuer_invoice_recurring_corrupt');
        $header=[];foreach(['request_key','input_hash','payload_hash','context_hash','config_hash','transport_hash']as$k)$header[$k]=$r[$k];$mode=(new CompanyInvoiceRecurringModes($this->db,$this->prefix))->row($r);if($mode)$header['mode_hash']=$mode['mode_hash'];$creation=$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$r['actor_id'],$id,'company_invoice_schedule_created',self::encode($header)]);if(count($creation)!==1)throw new RuntimeException('issuer_invoice_recurring_corrupt');
        $next=CompanyInvoiceRecurringDates::occurrence($payload['date'],$config,(int)$r['completed_cycles']);if($next!==$r['next_date']&&!($next===null&&$r['state']==='completed'&&$r['last_error']==='calendar_limit'))throw new RuntimeException('issuer_invoice_recurring_corrupt');return$r;
    }
    protected function occurrence(int$i,int$id,string$date):?array{return$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_schedule_runs WHERE issuer_id=? AND schedule_id=? AND run_date=? FOR UPDATE',[$i,$id,$date])[0]??null;}
    protected function payloadFor(array$r,string$date):array
    {
        $payload=$this->decode($r['payload_json']);$days=(int)(new DateTimeImmutable($payload['date'],new DateTimeZone('UTC')))->diff(new DateTimeImmutable($payload['due_date'],new DateTimeZone('UTC')))->format('%a');$payload['date']=$date;$payload['due_date']=(new DateTimeImmutable($date,new DateTimeZone('UTC')))->modify('+'.$days.' days')->format('Y-m-d');CompanyInvoiceRecurringDates::date($payload['due_date']);return$payload;
    }
    protected function validate(int$i,int$a,array$r,?array$run=null,bool$processing=true):array
    {
        $this->access($i,$a);if((int)$r['actor_id']!==$a||($processing&&!in_array($r['state'],['active','processing'],true)))throw new DomainException('company_invoice_recurring_paused');$config=$this->decode($r['config_json']);$payload=$this->decode($r['payload_json']);$date=$run['run_date']??$r['next_date'];$current=$this->payloadFor($r,$date);
        $modes=new CompanyInvoiceRecurringModes($this->db,$this->prefix);$mode=$modes->row($r);if($run!==null&&!$modes->principalPaid($i,$a,$r,$mode))throw new DomainException('company_invoice_recurring_principal_unpaid');
        $this->assertOriginOrReceipt($i,$a,$current,$r['context_hash'],$this->decode($r['context_json']),self::runKey($i,(int)$r['id'],$date));
        if($config['send']){require_once __DIR__.'/CompanyInvoiceRecurringDeliverySource.php';CompanyInvoiceRecurringDeliverySource::assertFrozenInOwned($this->db,$this->prefix,$i,$a,$payload,$this->decode($r['transport_json']));}return$config;
    }
    protected function setSchedule(int$i,int$id,array$fields):void{$this->set('company_invoice_schedules',['issuer_id'=>$i,'id'=>$id],$fields);$r=$this->schedule($i,$id);foreach($fields as$k=>$v)if((string)($r[$k]??null)!==(string)$v)throw new RuntimeException('issuer_invoice_recurring_unconfirmed');}
    protected function setRun(int$i,int$id,string$date,array$fields):void{$this->set('company_invoice_schedule_runs',['issuer_id'=>$i,'schedule_id'=>$id,'run_date'=>$date],$fields);$r=$this->occurrence($i,$id,$date);if(!$r)throw new RuntimeException('issuer_invoice_recurring_unconfirmed');foreach($fields as$k=>$v)if((string)($r[$k]??null)!==(string)$v)throw new RuntimeException('issuer_invoice_recurring_unconfirmed');}
    private function set(string$table,array$where,array$fields):void{$set=implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($fields)));$conditions=implode(' AND ',array_map(fn($k)=>'`'.$k.'`=?',array_keys($where)));IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET '.$set.' WHERE '.$conditions,array_merge(array_values($fields),array_values($where)));}
    protected function scheduleAudit(int$i,int$a,int$id,string$event,array$metadata=[]):void
    {
        $json=self::encode($metadata);$this->insert('issuers_audit',['issuer_id'=>$i,'actor_id'=>$a,'subject_id'=>$id,'event'=>$event,'metadata_json'=>$json,'created_at'=>gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$i,$a,$id,$event,$json]))throw new RuntimeException('issuer_invoice_recurring_unconfirmed');
    }
}

require_once __DIR__.'/CompanyInvoiceRecurringModes.php';
