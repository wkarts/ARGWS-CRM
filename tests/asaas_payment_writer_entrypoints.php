<?php
declare(strict_types=1);
// Reuse deterministic DB failures, but exercise actual core model methods below.
ob_start();require __DIR__.'/asaas_payment_writer.php';ob_end_clean();$n=0;
class App_Model { public $db,$session,$invoices_model,$load; }
class EntrySession { function has_userdata($key){return false;} }
final class AfterCommitEffect extends RuntimeException {}
class EntryInvoices {
    function __construct(private WriterDB $db){}
    function get($id){check($this->db->commits===1&&$this->db->_trans_depth===0,'invoice effects only after confirmed commit');throw new AfterCommitEffect('invoice_effect');}
}
class EntryHooks {
    public array $filters=[],$calls=[],$actions=[];
    function apply_filters($name,$value,...$args){$this->calls[]=$name;return isset($this->filters[$name])?($this->filters[$name])($value,...$args):$value;}
    function do_action($name,...$args){$this->actions[]=$name;}
}
$entryHooks=new EntryHooks();$alerts=[];$logs=[];
function hooks(){global $entryHooks;return $entryHooks;}
function is_staff_logged_in(){return true;}
function to_sql_date($date){return $date;}
function get_option($name){return '';}
function get_staff_user_id(){return 7;}
function set_alert($kind,$message){global $alerts;$alerts[]=[$kind,$message];}
function log_message($kind,$message){global $logs;$logs[]=[$kind,$message];}
require __DIR__.'/../application/services/InvoiceIssuerScope.php';
require __DIR__.'/../application/models/Payments_model.php';
require __DIR__.'/../application/models/Credit_notes_model.php';
class EntryPayments extends Payments_model {
    public $existing;
    function __construct(WriterDB $db){$this->db=$db;$this->session=new EntrySession();$this->invoices_model=new EntryInvoices($db);$this->existing=(object)['paymentid'=>99,'invoiceid'=>1,'amount'=>'100.00'];}
    function get($id){return $this->existing;}
}
class EntryCredits extends Credit_notes_model {
    function __construct(WriterDB $db){$this->db=$db;}
    function update_credit_note_status($id){check($this->db->commits===1&&$this->db->_trans_depth===0,'credit status effects only after confirmed commit');throw new AfterCommitEffect('credit_effect');}
}
function resetEntry(){global $entryHooks,$alerts,$logs;$entryHooks=new EntryHooks();$alerts=[];$logs=[];return new WriterDB();}
function firstEffect($call,$message){try{$call();}catch(AfterCommitEffect $e){check(true,$message);return;}check(false,$message);}
function batch($records){return ['invoice'=>$records,'do_not_send_invoice_payment_recorded'=>true];}
foreach(['add','batch'] as $mode){
    $db=resetEntry();$model=new EntryPayments($db);$inside=false;
    hooks()->filters['before_invoice_payment_insert_allowed']=function($allowed,$invoice,$record)use($db,&$inside){$inside=$db->_trans_depth===1&&$invoice['id']===1&&$record['invoiceid']===1;return true;};
    firstEffect(fn()=>($mode==='add'?$model->add(data()):$model->add_batch_payment(batch([data()]))),'real '.$mode.' reaches first post-commit effect');
    check(count($db->records)===1&&$inside,'real '.$mode.' uses locked writer');
    check(hooks()->actions===[],'first invoice effect precedes hooks, emails and notifications');
    $db=resetEntry();$model=new EntryPayments($db);
    hooks()->filters['before_payment_recorded']=function($record){$record['invoiceid']=2;return $record;};
    $result=$mode==='add'?$model->add(data()):$model->add_batch_payment(batch([data()]));
    check($result===($mode==='add'?false:0)&&$db->trace===[]&&$db->records===[],'real '.$mode.' rejects hook target substitution before database');
    foreach(['failInsert','failCommit'] as $failure){
        $db=resetEntry();$db->$failure=true;$model=new EntryPayments($db);
        $result=$mode==='add'?$model->add(data()):$model->add_batch_payment(batch([data()]));
        check($result===($mode==='add'?false:0)&&$db->records===[]&&$db->rollbacks===1,'real '.$mode.' rollback on '.$failure);
        check(hooks()->actions===[]&&count($alerts)===1,'no post-payment effect after '.$failure);
    }
    $db=resetEntry();$model=new EntryPayments($db);$inside=false;
    hooks()->filters['before_invoice_payment_insert_allowed']=function($allowed,$invoice,$record)use($db,&$inside){$inside=$db->_trans_depth===1;return false;};
    $result=$mode==='add'?$model->add(data()):$model->add_batch_payment(batch([data()]));
    check($inside&&$result===($mode==='add'?false:0)&&$db->records===[],'real '.$mode.' known-origin veto under lock');
    check(hooks()->actions===[]&&$db->commits===0,'real '.$mode.' veto has no effects');
}
// Validate every hook result before recording the first item, not just the first target.
$db=resetEntry();$model=new EntryPayments($db);$calls=0;
hooks()->filters['before_payment_recorded']=function($record)use(&$calls){if(++$calls===2)$record['invoiceid']=2;return $record;};
check($model->add_batch_payment(batch([data('40.00'),data('60.00')]))===0,'second batch item cannot swap invoice');
check($calls===2&&$db->records===[]&&$db->trace===[],'whole batch targets prevalidated before first insert');
// Frozen canonical receipts must stop before before-payment hooks or database mutation.
foreach(['update','delete'] as $operation){
    $db=resetEntry();$model=new EntryPayments($db);$seen=[];
    hooks()->filters['before_invoice_payment_mutation_allowed']=function($allowed,$payment,$invoice,$op)use(&$seen){$seen=[$payment,$invoice,$op];return false;};
    $result=$operation==='update'?$model->update(['date'=>'2026-10-18','note'=>'','amount'=>'100.00'],99):$model->delete(99);
    check($result===false&&$seen===[99,1,$operation],'canonical '.$operation.' receives exact identity');
    check($db->trace===[]&&hooks()->actions===[],'canonical '.$operation.' veto precedes all side effects');
    check(count($alerts)===1&&str_contains($alerts[0][1],'revisão'),'canonical '.$operation.' displays review requirement');
}
$db=resetEntry();$model=new EntryPayments($db);$allowed=true;
hooks()->filters['before_invoice_payment_mutation_allowed']=function()use(&$allowed){return $allowed;};
hooks()->filters['before_payment_updated']=function($record)use(&$allowed){$allowed=false;return $record;};
check($model->update(['date'=>'2026-10-18','note'=>'','amount'=>'100.00'],99)===false&&$db->trace===[],'update rechecks canonical veto after extensibility hook');
$db=resetEntry();$model=new EntryPayments($db);
hooks()->filters['before_payment_updated']=function($record){$record['invoiceid']=2;return $record;};
check($model->update(['date'=>'2026-10-18','note'=>'','amount'=>'100.00'],99)===false&&$db->trace===[],'update cannot transfer receipt after hook');
// Default no-origin policy is eligible; detailed outbox filtering is covered by module tests.
$method=new ReflectionMethod(Payments_model::class,'allow_payment_mutation');
check($method->invoke($model,99,1,'update')===true,'legacy mutation retains default eligibility');
$db=resetEntry();$model=new EntryCredits($db);$inside=false;
hooks()->filters['before_invoice_credit_insert_allowed']=function($allowed,$invoice,$record)use($db,&$inside){$inside=$db->_trans_depth===1&&$record['credit_id']===1;return true;};
firstEffect(fn()=>$model->apply_credits(1,creditData()),'real credit application reaches first post-commit effect');
check($inside&&count($db->credits)===1&&hooks()->actions===[],'real credit uses locked writer before status/hooks');
foreach(['veto','failInsert','failCommit'] as $failure){
    $db=resetEntry();$model=new EntryCredits($db);$inside=false;
    if($failure==='veto')hooks()->filters['before_invoice_credit_insert_allowed']=function()use($db,&$inside){$inside=$db->_trans_depth===1;return false;};else $db->$failure=true;
    check($model->apply_credits(1,creditData())===false&&$db->credits===[]&&$db->rollbacks===1,'real credit rolls back on '.$failure);
    check(hooks()->actions===[]&&count($alerts)===1,'real credit has no status/hook on '.$failure);
    if($failure==='veto')check($inside,'real credit origin veto is inside lock');
}
check(!str_contains(file_get_contents(__DIR__.'/../application/services/InvoicePaymentWriter.php'),'InvoiceIssuerScope'),'writer has no phase-2 issuer service dependency');
echo "Asaas real core entry points: {$n} checks passed\n";
