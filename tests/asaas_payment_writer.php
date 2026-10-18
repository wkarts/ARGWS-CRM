<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require __DIR__.'/../application/services/InvoicePaymentWriter.php';
use app\services\InvoicePaymentWriter as Writer;
function db_prefix(){return 'tbl';}
$n=0;function check($value,$message){global $n;++$n;if(!$value)throw new RuntimeException($message);}
function rejects($action,$code){try{$action();}catch(Throwable $e){check($e->getMessage()===$code,'expected '.$code.', got '.$e->getMessage());return;}check(false,'missing failure '.$code);}
class Result {function __construct(private array $rows){}function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}function row_array(){return $this->rows[0]??[];}function result_array(){return $this->rows;}}
class WriterDB {
    public bool $db_debug=true,$rawOuter=false,$nonTransactional=false,$failBegin=false,$failInsert=false,$failCommit=false,$invalidStatus=false;
    public int $autocommit=1,$_trans_depth=0,$rollbacks=0,$commits=0;
    public array $invoice=['id'=>1,'issuer_id'=>null,'status'=>1,'total'=>'100.00','clientid'=>10,'currency'=>1],$records=[],$credits=[],$trace=[];
    public array $note=['id'=>1,'issuer_id'=>null,'status'=>1,'total'=>'80.00','clientid'=>10,'currency'=>1],$refunds=[];
    public $beforeLock=null,$conn_id=null;private int $lastId=0;private ?array $snapshot=null;
    function field_exists($field,$table){return $field==='issuer_id';}
    function query($sql,$args=[]){
        // Read-only tenant preflight is separate from the financial transaction trace.
        if($sql==='SELECT id FROM tblinvoices WHERE id = ? AND tblinvoices.issuer_id IS NULL')return new Result($this->invoice && (string)$this->invoice['id']===(string)$args[0] && $this->invoice['issuer_id']===null ? [$this->invoice] : []);

        $this->trace[]=$sql;
        if(str_starts_with($sql,'SELECT @@'))return new Result([['autocommit'=>$this->autocommit,'completion_type'=>0]]);
        if(str_starts_with($sql,'SELECT ENGINE'))return new Result([['ENGINE'=>$this->nonTransactional?'MyISAM':'InnoDB']]);
        if(str_starts_with($sql,'SET TRANSACTION'))return !$this->rawOuter;
        if(str_contains($sql,'`tblcreditnotes`'))return new Result($this->note?[$this->note]:[]);
        if(str_contains($sql,'`tblcreditnote_refunds`'))return new Result($this->refunds);
        if(str_starts_with($sql,'SELECT * FROM')){if($this->beforeLock){($this->beforeLock)($this);$this->beforeLock=null;}return new Result($this->invoice?[$this->invoice]:[]);}
        if(str_contains($sql,'`tblinvoicepaymentrecords`'))return new Result($this->records);
        if(str_contains($sql,'`tblcredits`'))return new Result($this->credits);
        throw new RuntimeException('unexpected query');
    }
    function protect_identifiers($name,...$rest){return '`'.$name.'`';}
    function trans_begin(){if($this->failBegin)return false;$this->snapshot=[$this->invoice,$this->records,$this->credits];$this->_trans_depth=1;$this->trace[]='begin';return true;}
    function trans_status(){return !$this->invalidStatus;}
    function trans_commit(){if($this->failCommit)return false;$this->_trans_depth=0;$this->snapshot=null;$this->commits++;$this->trace[]='commit';return true;}
    function trans_rollback(){if($this->snapshot)[$this->invoice,$this->records,$this->credits]=$this->snapshot;$this->_trans_depth=0;$this->rollbacks++;return true;}
    function insert($table,$data){if($this->failInsert)return false;if($table==='tblcredits'){$this->credits[]=$data;$this->lastId=count($this->credits);}else{$this->records[]=$data;$this->lastId=count($this->records);}$this->trace[]='insert';return true;}
    function insert_id(){return $this->lastId;}
}
function data($amount='100.00'){return ['invoiceid'=>1,'amount'=>$amount,'date'=>'2026-10-18','paymentmode'=>'1'];}
check(Writer::minorUnits('9999999999999.99')===999999999999999,'max DECIMAL exact');check(Writer::minorUnits('0.01')===1,'one cent exact');
foreach([null,true,false,[],-1,'-1','1.001','1e2',INF,'10000000000000.00'] as $amount)rejects(fn()=>Writer::minorUnits($amount),'invalid_payment_amount');
foreach([0,'0','01','1e0',[],null,'92233720368547758080'] as $id)rejects(fn()=>Writer::invoiceId($id),'invalid_payment_invoice');
$db=new WriterDB();$inside=false;$id=(new Writer($db))->record(1,data(),function($invoice,$record)use($db,&$inside){$inside=$db->_trans_depth===1;return true;});
check($id===1&&$inside,'veto runs inside transaction');check($db->commits===1&&$db->_trans_depth===0,'commit confirmed before return');check($db->records[0]['amount']==='100.00'&&$db->db_debug,'exact insertion and debug restored');
check(array_search('insert',$db->trace,true)<array_search('commit',$db->trace,true),'insert before commit');
$db=new WriterDB();$db->records=[['amount'=>'30.25']];$db->credits=[['amount'=>'20.25']];(new Writer($db))->record(1,data('49.50'),fn()=>true);check(count($db->records)===2,'remaining balance exact with credits');
$db=new WriterDB();$db->records=[['amount'=>'30.25']];$db->credits=[['amount'=>'20.25']];rejects(fn()=>(new Writer($db))->record(1,data('49.51'),fn()=>true),'payment_exceeds_locked_balance');check(count($db->records)===1,'overpayment leaves records intact');
foreach([1,3,4,6] as $status){$db=new WriterDB();$db->invoice['status']=$status;(new Writer($db))->record(1,data(),fn()=>true);check($db->commits===1,'legacy eligible status supported');}
foreach([2,5,0] as $status){$db=new WriterDB();$db->invoice['status']=$status;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_invoice_not_eligible');check($db->records===[],'ineligible state no insert');}
foreach([2,0,'0'] as $issuer){$db=new WriterDB();$db->invoice['issuer_id']=$issuer;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_invoice_not_eligible');}
$db=new WriterDB();rejects(fn()=>(new Writer($db))->record(2,data(),fn()=>true),'payment_target_changed');check($db->trace===[],'target mutation blocked before database');
foreach([false,1,'1',null,[]] as $allowed){$db=new WriterDB();rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>$allowed),'payment_origin_requires_canonical_receipt');check($db->records===[]&&$db->rollbacks===1,'only strict true allows insert');}
foreach(['failInsert'=>'payment_insert_failed','failCommit'=>'payment_commit_unconfirmed','invalidStatus'=>'payment_commit_unconfirmed','failBegin'=>'payment_transaction_unavailable','nonTransactional'=>'payment_transactional_storage_required'] as $flag=>$code){$db=new WriterDB();$db->$flag=true;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),$code);check($db->records===[]&&$db->db_debug,'failure rollback/debug');}
$db=new WriterDB();$db->_trans_depth=1;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_outer_transaction_not_allowed');check($db->rollbacks===0&&$db->_trans_depth===1,'caller transaction untouched');
$db=new WriterDB();$db->rawOuter=true;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_outer_transaction_or_isolation_unavailable');check($db->rollbacks===0&&$db->commits===0,'raw outer transaction never committed or rolled back');
$db=new WriterDB();$db->autocommit=0;rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_autocommit_required');
$db=new WriterDB();$db->beforeLock=function($db){$db->invoice['status']=2;$db->records=[['amount'=>'100.00']];};rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_invoice_not_eligible');
$db=new WriterDB();$db->beforeLock=function($db){$db->records=[['amount'=>'100.00']];};rejects(fn()=>(new Writer($db))->record(1,data(),fn()=>true),'payment_exceeds_locked_balance');
// Assert both real core entry points route their inserts through the same writer.
$core=file_get_contents(__DIR__.'/../application/models/Payments_model.php');
check(substr_count($core,'$this->insert_payment_atomically($targetInvoiceId, $data)')===2,'single and batch use writer');
check(!str_contains($core,"->insert(db_prefix() . 'invoicepaymentrecords'"),'no direct insert remains in core model');
check(str_contains($core,"before_invoice_payment_insert_allowed', true, \$invoice, \$record"),'locked veto hook exposed');
function creditData($amount='80.00'){return ['invoice_id'=>1,'amount'=>$amount,'staff_id'=>7,'date'=>'2026-10-18'];}
$db=new WriterDB();$id=(new Writer($db))->applyCredit(1,1,creditData(),fn()=>true);check($id===1&&$db->records===[]&&count($db->credits)===1,'credit writer separate table');
check($db->credits[0]['credit_id']===1&&!isset($db->credits[0]['invoiceid']),'credit identity controlled by server');
$db=new WriterDB();$db->refunds=[['amount'=>'30.00']];rejects(fn()=>(new Writer($db))->applyCredit(1,1,creditData('50.01'),fn()=>true),'credit_note_exceeds_locked_balance');
$db=new WriterDB();$db->refunds=[['amount'=>'30.00']];(new Writer($db))->applyCredit(1,1,creditData('50.00'),fn()=>true);check($db->commits===1,'refunds deducted exactly');
foreach (['issuer_id'=>2,'status'=>2,'clientid'=>11,'currency'=>2] as $field=>$value){$db=new WriterDB();$db->note[$field]=$value;rejects(fn()=>(new Writer($db))->applyCredit(1,1,creditData(),fn()=>true),'credit_note_not_eligible');}
$db=new WriterDB();rejects(fn()=>(new Writer($db))->applyCredit(1,1,creditData(),fn()=>false),'payment_origin_requires_canonical_receipt');check($db->credits===[]&&$db->rollbacks===1,'credit veto no insert');
$db=new WriterDB();$db->failCommit=true;rejects(fn()=>(new Writer($db))->applyCredit(1,1,creditData(),fn()=>true),'payment_commit_unconfirmed');check($db->credits===[],'credit commit failure rollback');
$db=new WriterDB();$db->invoice['status']=2;rejects(fn()=>(new Writer($db))->applyCredit(1,1,creditData(),fn()=>true),'payment_invoice_not_eligible');
echo "Asaas core payment writer: {$n} checks passed\n";
