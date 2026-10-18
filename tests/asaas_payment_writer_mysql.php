<?php
declare(strict_types=1);
if (!getenv('ASAAS_TEST_MYSQL_DSN')) { fwrite(STDERR,"ASAAS_TEST_MYSQL_DSN required.\n"); exit(2); }
define('BASEPATH',__DIR__);
function db_prefix(){return 'writer_test_';}
require __DIR__.'/../application/services/InvoicePaymentWriter.php';
require __DIR__.'/../modules/asaas/libraries/Asaas/CanonicalReceiptRecorder.php';
require __DIR__.'/../modules/asaas/libraries/Asaas/WebhookTransactionGuard.php';
use app\services\InvoicePaymentWriter as Writer;
class WriterResult {function __construct(private PDOStatement $stmt){}function row_array(){return $this->stmt->fetch(PDO::FETCH_ASSOC)?:[];}function result_array(){return $this->stmt->fetchAll(PDO::FETCH_ASSOC);}}
class WriterMysql {
    public bool $db_debug=true,$failInsert=false;protected int $_trans_depth=0;private array $where=[];private int $affected=0;
    public $conn_id;
    function __construct(public PDO $pdo){$this->conn_id=$pdo;}
    function query($sql,$args=[]){$stmt=$this->pdo->prepare($sql);$stmt->execute($args);return new WriterResult($stmt);}
    function protect_identifiers($name,...$rest){return '`'.$name.'`';}
    function trans_begin(){if(!$this->pdo->beginTransaction())return false;$this->_trans_depth=1;return true;}
    function trans_status(){return true;}
    function trans_commit(){if(!$this->pdo->commit())return false;$this->_trans_depth=0;return true;}
    function trans_rollback(){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->_trans_depth=0;return true;}
    function insert($table,$data){if($this->failInsert)return false;$stmt=$this->pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',array_fill(0,count($data),'?')).')');return $stmt->execute(array_values($data));}
    function insert_id(){return (int)$this->pdo->lastInsertId();}
    function where($key,$value){$this->where[$key]=$value;return $this;}
    function update($table,$data){$stmt=$this->pdo->prepare('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($data))).' WHERE '.implode(' AND ',array_map(fn($key)=>'`'.$key.'`=?',array_keys($this->where))));$params=array_merge(array_values($data),array_values($this->where));$this->where=[];$ok=$stmt->execute($params);$this->affected=$stmt->rowCount();return $ok;}
    function affected_rows(){return $this->affected;}
}
function connection(){return new PDO(getenv('ASAAS_TEST_MYSQL_DSN'),getenv('ASAAS_TEST_MYSQL_USER')?:'root',getenv('ASAAS_TEST_MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
function receiptData($invoice){return ['invoiceid'=>$invoice,'amount'=>'100.00','paymentmode'=>'1','date'=>'2026-10-18'];}
function creditData($invoice){return ['invoice_id'=>$invoice,'amount'=>'80.00','staff_id'=>7,'date'=>'2026-10-18'];}
function canonical($db,$id){
    $invoice=$db->query('SELECT * FROM writer_test_invoices WHERE id=? FOR UPDATE',[$id])->row_array();
    $origin=['id'=>$id,'invoice_id'=>$id,'invoice_hash'=>'hash_'.$id,'invoice_total'=>'100.00','invoice_due_date'=>'2026-10-01','issuer_id'=>null,'state'=>'issued','provider'=>'asaas','channel'=>'api','remote_id'=>'pay_'.$id,'account_id'=>'wallet_fixture','environment'=>'sandbox'];
    $payment=['id'=>'pay_'.$id,'status'=>'RECEIVED','billingType'=>'BOLETO','paymentDate'=>'2026-10-17','clientPaymentDate'=>'2026-10-16','value'=>'100.00','externalReference'=>'hash_'.$id];
    return (new Asaas\CanonicalReceiptRecorder($db))->writeLocked($invoice,$origin,$payment,['id'=>$id,'event_id'=>'evt_'.$id]);
}
if (isset($argv[1])) {
    $db=new WriterMysql(connection());$id=(int)$argv[2];$db->pdo->exec('SET SESSION innodb_lock_wait_timeout=8');
    echo 'READY:'.$db->pdo->query('SELECT CONNECTION_ID()')->fetchColumn()."\n";flush();
    try {
        if($argv[1]==='manual'){(new Writer($db))->record($id,receiptData($id),fn()=>true);}
        elseif($argv[1]==='credit'){(new Writer($db))->applyCredit($id,$id,creditData($id),fn()=>true);}
        else{$db->trans_begin();canonical($db,$id);$db->trans_commit();}
        echo "WRITTEN\n";
    }catch(Throwable $e){$db->trans_rollback();echo 'REJECTED:'.$e->getMessage()."\n";}
    exit;
}
$n=0;function verify($ok,$message){global $n;++$n;if(!$ok)throw new RuntimeException($message);}
function child($mode,$id){
    global $pdo;
    $process=proc_open([PHP_BINARY,__FILE__,$mode,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('child unavailable');fclose($pipes[0]);stream_set_timeout($pipes[1],10);
    try {
        $ready=trim((string)fgets($pipes[1]));
        verify(preg_match('/^READY:([1-9][0-9]*)$/D',$ready,$matches)===1,'second connection ready with MySQL connection id');
        // Prove the child reached the invoice row lock, not merely that it is alive.
        // MySQL 8.4 Performance Schema is a test prerequisite; absence fails closed.
        $wait=$pdo->prepare("SELECT 1
            FROM performance_schema.data_lock_waits AS w
            JOIN performance_schema.threads AS requester ON requester.THREAD_ID=w.REQUESTING_THREAD_ID
            JOIN performance_schema.threads AS blocker ON blocker.THREAD_ID=w.BLOCKING_THREAD_ID
            JOIN performance_schema.data_locks AS requested
              ON requested.ENGINE=w.ENGINE AND requested.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID
            WHERE requester.PROCESSLIST_ID=? AND blocker.PROCESSLIST_ID=CONNECTION_ID()
              AND w.ENGINE='INNODB' AND requested.OBJECT_SCHEMA=DATABASE()
              AND requested.OBJECT_NAME='writer_test_invoices' AND requested.INDEX_NAME='PRIMARY'
              AND requested.LOCK_TYPE='RECORD' AND requested.LOCK_STATUS='WAITING'
            LIMIT 1");
        $observed=false;$deadline=hrtime(true)+5000000000;
        do {
            $wait->execute([$matches[1]]);$observed=$wait->fetchColumn()!==false;$wait->closeCursor();
            if($observed || !proc_get_status($process)['running'])break;
            usleep(20000);
        } while(hrtime(true)<$deadline);
        verify($observed,'second connection is waiting for the parent invoice row lock');
    } catch (Throwable $error) {
        // This test owns the child; do not leave a blocked process after a failed assertion.
        proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
        throw $error;
    }
    return [$process,$pipes];
}
function finishChild($child){[$process,$pipes]=$child;$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);verify($code===0,'child completed: '.$error);return $output;}
$pdo=connection();$db=new WriterMysql($pdo);
$pdo->exec('CREATE TABLE writer_test_invoices(id INT PRIMARY KEY,issuer_id INT NULL,status INT NOT NULL,total DECIMAL(15,2) NOT NULL,clientid INT NOT NULL,currency INT NOT NULL,hash VARCHAR(32),duedate DATE) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_invoicepaymentrecords(id INT AUTO_INCREMENT PRIMARY KEY,invoiceid INT NOT NULL,amount DECIMAL(15,2) NOT NULL,paymentmode VARCHAR(40),paymentmethod VARCHAR(191),date DATE NOT NULL,daterecorded DATETIME NOT NULL,note MEDIUMTEXT,transactionid LONGTEXT,KEY(invoiceid)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_credits(id INT AUTO_INCREMENT PRIMARY KEY,invoice_id INT NOT NULL,credit_id INT NOT NULL,staff_id INT NOT NULL,date DATE NOT NULL,date_applied DATETIME NOT NULL,amount DECIMAL(15,2) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_creditnotes(id INT PRIMARY KEY,issuer_id INT NULL,status INT,total DECIMAL(15,2),clientid INT,currency INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_creditnote_refunds(id INT AUTO_INCREMENT PRIMARY KEY,credit_note_id INT,amount DECIMAL(15,2)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_sales_activity(id INT AUTO_INCREMENT PRIMARY KEY,rel_type VARCHAR(20),rel_id INT NOT NULL,description MEDIUMTEXT NOT NULL,additional_data MEDIUMTEXT,staffid VARCHAR(11),full_name VARCHAR(100),date DATETIME NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE writer_test_asaas_settlement_outbox(id INT AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNIQUE,origin_id INT UNIQUE,receipt_id INT,event_row_id INT UNIQUE,event_id VARCHAR(128),provider VARCHAR(32),account_id VARCHAR(128),environment VARCHAR(16),remote_id VARCHAR(64),verified_status VARCHAR(32),payment_date DATE,billing_type VARCHAR(20),state VARCHAR(32),created_at DATETIME,processed_at DATETIME NULL) ENGINE=InnoDB');
for($id=1;$id<=8;$id++){$pdo->exec("INSERT INTO writer_test_invoices VALUES({$id},NULL,1,100.00,10,1,'hash_{$id}','2026-10-01')");$pdo->exec("INSERT INTO writer_test_creditnotes VALUES({$id},NULL,1,80.00,10,1)");}
// Canonical first: a stale manual form cannot insert once the same row lock is released.
$db->trans_begin();canonical($db,1);$manual=child('manual',1);$db->trans_commit();
verify(str_contains(finishChild($manual),'REJECTED:payment_invoice_not_eligible'),'canonical first rejects manual after lock');
verify((int)$pdo->query('SELECT COUNT(*) FROM writer_test_invoicepaymentrecords WHERE invoiceid=1')->fetchColumn()===1,'canonical/manual overlap leaves one receipt');
// Manual first: canonical writer sees the committed manual receipt, even before core status effects run.
$canonicalChild=null;(new Writer($db))->record(2,receiptData(2),function()use(&$canonicalChild){$canonicalChild=child('canonical',2);return true;});
verify(str_contains(finishChild($canonicalChild),'REJECTED:partial_or_credits_require_review'),'manual first forces canonical review');
verify((int)$pdo->query('SELECT COUNT(*) FROM writer_test_invoicepaymentrecords WHERE invoiceid=2')->fetchColumn()===1,'manual/canonical overlap leaves one receipt');
// The same ordering applies to credit application.
$db->trans_begin();canonical($db,3);$credit=child('credit',3);$db->trans_commit();
verify(str_contains(finishChild($credit),'REJECTED:payment_invoice_not_eligible'),'canonical first rejects stale credit application');
$canonicalChild=null;(new Writer($db))->applyCredit(4,4,creditData(4),function()use(&$canonicalChild){$canonicalChild=child('canonical',4);return true;});
verify(str_contains(finishChild($canonicalChild),'REJECTED:partial_or_credits_require_review'),'credit first forces canonical review');
verify((int)$pdo->query('SELECT COUNT(*) FROM writer_test_invoicepaymentrecords WHERE invoiceid=4')->fetchColumn()===0,'credit/canonical overlap creates no full receipt');
// Reject caller transactions without committing/rolling them back, including raw SQL transactions.
$pdo->beginTransaction();try{(new Writer($db))->record(5,receiptData(5),fn()=>true);verify(false,'outer PDO accepted');}catch(RuntimeException $e){verify($e->getMessage()==='payment_outer_transaction_not_allowed','PDO transaction rejected');}verify($pdo->inTransaction(),'caller PDO transaction still active');$pdo->rollBack();
$pdo->exec('START TRANSACTION');$db->conn_id=null;
try{(new Writer($db))->record(5,receiptData(5),fn()=>true);verify(false,'raw transaction accepted');}catch(Throwable $e){verify($e instanceof PDOException && $e->getCode()==='25001','next-transaction SET detects raw transaction');}
verify($pdo->inTransaction(),'raw transaction not committed or rolled back');$pdo->rollBack();$db->conn_id=$pdo;
$db->failInsert=true;try{(new Writer($db))->record(5,receiptData(5),fn()=>true);verify(false,'failed insert accepted');}catch(RuntimeException $e){verify($e->getMessage()==='payment_insert_failed','insert failure propagated');}$db->failInsert=false;
verify(!$pdo->inTransaction()&&(int)$pdo->query('SELECT COUNT(*) FROM writer_test_invoicepaymentrecords WHERE invoiceid=5')->fetchColumn()===0,'failed insert rolled back before effects');
$pdo->exec('SET SESSION completion_type=1');try{(new Writer($db))->record(5,receiptData(5),fn()=>true);verify(false,'commit-chain accepted');}catch(RuntimeException $e){verify($e->getMessage()==='payment_autocommit_required','commit-chain configuration rejected');}$pdo->exec('SET SESSION completion_type=0');
$pdo->exec('INSERT INTO writer_test_creditnote_refunds(credit_note_id,amount) VALUES(6,30.00)');
try{(new Writer($db))->applyCredit(6,6,creditData(6),fn()=>true);verify(false,'refunded credit overspent');}catch(RuntimeException $e){verify($e->getMessage()==='credit_note_exceeds_locked_balance','credit refunds included in locked balance');}
verify((int)$pdo->query('SELECT COUNT(*) FROM writer_test_credits WHERE invoice_id=6')->fetchColumn()===0,'credit rejection leaves no partial insert');
Asaas\WebhookTransactionGuard::prepare($db);
verify(!$pdo->inTransaction(),'webhook prepare does not open a transaction');
$pdo->beginTransaction();
try{Asaas\WebhookTransactionGuard::prepare($db);verify(false,'webhook outer PDO accepted');}catch(RuntimeException $e){verify($e->getMessage()==='webhook_outer_transaction_not_allowed','webhook rejects PDO transaction');}
verify($pdo->inTransaction(),'webhook leaves caller PDO transaction active');$pdo->rollBack();
$pdo->exec('START TRANSACTION');$db->conn_id=null;
try{Asaas\WebhookTransactionGuard::prepare($db);verify(false,'webhook raw SQL transaction accepted');}catch(Throwable $e){verify($e instanceof PDOException && $e->getCode()==='25001','webhook detects raw SQL transaction without implicit commit');}
verify($pdo->inTransaction(),'webhook raw transaction preserved');$pdo->rollBack();$db->conn_id=$pdo;
$pdo->exec('SET SESSION completion_type=1');
try{Asaas\WebhookTransactionGuard::prepare($db);verify(false,'webhook commit chain accepted');}catch(RuntimeException $e){verify($e->getMessage()==='webhook_autocommit_required','webhook rejects chained commits');}$pdo->exec('SET SESSION completion_type=0');
echo "OK: {$n} MySQL payment writer assertions; actual two-process invoice locking, credits, rollback and outer transaction guards.\n";
