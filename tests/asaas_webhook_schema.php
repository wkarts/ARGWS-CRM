<?php
declare(strict_types=1);
if (!getenv('ASAAS_TEST_MYSQL_DSN')) { fwrite(STDERR,"ASAAS_TEST_MYSQL_DSN required.\n");exit(2); }
function db_prefix(){return 'hook_test_';}
require __DIR__.'/../modules/asaas/libraries/Asaas/WebhookIntegrationSchema.php';
class HookDbResult {
    public function __construct(private PDOStatement $s){}
    public function row_array(){return $this->s->fetch(PDO::FETCH_ASSOC)?:[];}
    public function num_rows(){return $this->s->rowCount();}
    public function result_array(){return $this->s->fetchAll(PDO::FETCH_ASSOC);}
}
class HookDb {
    private array $where=[];
    private int $affected=0;
    public function __construct(public PDO $pdo){}
    public function table_exists($t){$s=$this->pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$t]);return (bool)$s->fetch();}
    public function field_exists($f,$t){$s=$this->pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$t,$f]);return (bool)$s->fetch();}
    public function query($q,$args=[]){$s=$this->pdo->prepare($q);$s->execute($args);return new HookDbResult($s);}
    public function protect_identifiers($name,...$x){return '`'.$name.'`';}
    public function insert($table,$row){$s=$this->pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');return $s->execute(array_values($row));}
    public function insert_id(){return (int)$this->pdo->lastInsertId();}
    public function where($key,$value){$this->where[$key]=$value;return $this;}
    public function update($table,$row){$s=$this->pdo->prepare('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($row))).' WHERE '.implode(' AND ',array_map(fn($k)=>'`'.$k.'`=?',array_keys($this->where))));$params=array_merge(array_values($row),array_values($this->where));$this->where=[];$ok=$s->execute($params);$this->affected=$s->rowCount();return $ok;}
    public function affected_rows(){return $this->affected;}
}
function connection(){return new PDO(getenv('ASAAS_TEST_MYSQL_DSN'),getenv('ASAAS_TEST_MYSQL_USER')?:'root',getenv('ASAAS_TEST_MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
$pdo=connection();$other=connection();$db=new HookDb($pdo);
$pdo->exec("CREATE TABLE hook_test_asaas_webhook_events (id INT AUTO_INCREMENT PRIMARY KEY,event_id VARCHAR(128) NOT NULL,event_type VARCHAR(64),asaas_payment_id VARCHAR(64),invoice_id INT,received_at DATETIME,processed_at DATETIME,payload LONGTEXT,process_status VARCHAR(32),error_message TEXT,UNIQUE KEY event_id(event_id)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO hook_test_asaas_webhook_events (event_id,payload,process_status) VALUES ('legacy_event','{}','received')");
Asaas\WebhookIntegrationSchema::install($db);Asaas\WebhookIntegrationSchema::install($db);
$n=0;function verify($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
$legacy=$pdo->query('SELECT * FROM hook_test_asaas_webhook_events')->fetch(PDO::FETCH_ASSOC);
verify($legacy['scope_key']==='legacy'&&$legacy['integration_id']===null,'upgrade never guesses old account');
verify($legacy['payload']==='{}','upgrade preserves historical evidence');
verify($pdo->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hook_test_asaas_webhook_events' AND COLUMN_NAME='event_id'")->fetchColumn()==='utf8mb4_bin','opaque provider IDs use case-sensitive uniqueness');
$insert=$pdo->prepare('INSERT IGNORE INTO hook_test_asaas_webhook_events(event_id,scope_key,payload_hash,process_status) VALUES(?,?,?,?)');
$insert->execute(['evt_same','integration:1',str_repeat('a',64),'received']);verify($insert->rowCount()===1,'first event durable');
$duplicate=$other->prepare('INSERT IGNORE INTO hook_test_asaas_webhook_events(event_id,scope_key,payload_hash,process_status) VALUES(?,?,?,?)');
$duplicate->execute(['evt_same','integration:1',str_repeat('b',64),'received']);verify($duplicate->rowCount()===0,'second connection dedup on same integration');
verify($pdo->query("SELECT payload_hash FROM hook_test_asaas_webhook_events WHERE scope_key='integration:1'")->fetchColumn()===str_repeat('a',64),'conflict never overwrites first payload hash');
$duplicate->execute(['evt_same','integration:2',str_repeat('b',64),'received']);verify($duplicate->rowCount()===1,'same provider event id in another explicit scope is separate');
$duplicate->execute(['EVT_SAME','integration:1',str_repeat('c',64),'received']);verify($duplicate->rowCount()===1,'case-distinct provider identifiers are not collapsed');
verify((int)$pdo->query("SELECT GET_LOCK('hook_fixture_lock',0)")->fetchColumn()===1,'worker one gets advisory lock');
verify((int)$other->query("SELECT GET_LOCK('hook_fixture_lock',0)")->fetchColumn()===0,'worker two cannot overlap');
$pdo->query("SELECT RELEASE_LOCK('hook_fixture_lock')");verify((int)$other->query("SELECT GET_LOCK('hook_fixture_lock',0)")->fetchColumn()===1,'lock recoverable after release');$other->query("SELECT RELEASE_LOCK('hook_fixture_lock')");
$pdo->beginTransaction();$pdo->exec("UPDATE hook_test_asaas_webhook_events SET process_status='processed' WHERE scope_key='integration:1'");$pdo->rollBack();
verify($pdo->query("SELECT process_status FROM hook_test_asaas_webhook_events WHERE scope_key='integration:1'")->fetchColumn()==='received','transaction rollback retains pending event');
verify($db->field_exists('reviewed_by','hook_test_asaas_webhook_events'),'assisted review actor audit migrated');

require __DIR__.'/../modules/asaas/libraries/Asaas/CanonicalReceiptRecorder.php';
$pdo->exec("CREATE TABLE hook_test_invoices(id INT PRIMARY KEY,status INT,total DECIMAL(15,2),total_tax DECIMAL(15,2)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE hook_test_invoicepaymentrecords(id INT AUTO_INCREMENT PRIMARY KEY,invoiceid INT NOT NULL,amount DECIMAL(15,2) NOT NULL,paymentmode VARCHAR(40),paymentmethod VARCHAR(191),date DATE NOT NULL,daterecorded DATETIME NOT NULL,note MEDIUMTEXT,transactionid LONGTEXT,KEY(invoiceid)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE hook_test_credits(id INT PRIMARY KEY,invoice_id INT,amount DECIMAL(15,2),KEY(invoice_id)) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE hook_test_sales_activity(id INT AUTO_INCREMENT PRIMARY KEY,rel_type VARCHAR(20),rel_id INT NOT NULL,description MEDIUMTEXT NOT NULL,additional_data MEDIUMTEXT,staffid VARCHAR(11),full_name VARCHAR(100),date DATETIME NOT NULL) ENGINE=InnoDB");
$pdo->exec("INSERT INTO hook_test_invoices VALUES(1,1,100.00,5.00),(2,1,100.00,5.00)");
$invoice=['id'=>1,'status'=>1,'total'=>'100.00'];
$origin=['id'=>1,'remote_id'=>'pay_fixture','account_id'=>'wallet_fixture','environment'=>'sandbox'];
$payment=['id'=>'pay_fixture','status'=>'RECEIVED','paymentDate'=>'2026-01-15','billingType'=>'PIX'];$event=['id'=>10,'event_id'=>'evt_fixture'];
$recorder=new Asaas\CanonicalReceiptRecorder($db);
$pdo->beginTransaction();$pdo->query('SELECT * FROM hook_test_invoices WHERE id=1 FOR UPDATE');
$receipt=$recorder->writeLocked($invoice,$origin,$payment,$event);
$other->exec('SET SESSION innodb_lock_wait_timeout=1');$other->beginTransaction();
try{$other->query('SELECT * FROM hook_test_invoices WHERE id=1 FOR UPDATE');verify(false,'second worker unexpectedly acquired invoice lock');}catch(PDOException $e){verify((int)($e->errorInfo[1]??0)===1205,'second financial worker blocked by actual invoice lock');}$other->rollBack();
$pdo->commit();
verify((int)$pdo->query('SELECT COUNT(*) FROM hook_test_invoicepaymentrecords')->fetchColumn()===1,'one financial record committed');
verify($pdo->query('SELECT date FROM hook_test_invoicepaymentrecords')->fetchColumn()==='2026-01-15','canonical payment date retained across delayed processing');
verify($pdo->query('SELECT paymentmethod FROM hook_test_invoicepaymentrecords')->fetchColumn()==='PIX','canonical billing type retained');
verify((int)$pdo->query('SELECT receipt_id FROM hook_test_asaas_settlement_outbox')->fetchColumn()===$receipt,'outbox and receipt atomic identity');
verify($pdo->query('SELECT state FROM hook_test_asaas_settlement_outbox')->fetchColumn()==='pending','post-commit automation pending durable');
$after=$pdo->query('SELECT * FROM hook_test_invoices WHERE id=1')->fetch(PDO::FETCH_ASSOC);
verify((int)$after['status']===2&&$after['total']==='100.00'&&$after['total_tax']==='5.00','only status changes, totals/taxes preserved');
$pdo->beginTransaction();try{$recorder->writeLocked($invoice,$origin,$payment,['id'=>11,'event_id'=>'evt_second']);verify(false,'duplicate financial write accepted');}catch(RuntimeException $e){verify($e->getMessage()==='partial_or_credits_require_review','retry refuses another full receipt');}$pdo->rollBack();
$pdo->beginTransaction();try{$recorder->writeLocked(array_replace($invoice,['id'=>2]),array_replace($origin,['id'=>2]),$payment,$event);verify(false,'duplicate outbox event accepted');}catch(PDOException $e){verify($e->getCode()==='23000','outbox unique event protects financial operation');}$pdo->rollBack();
verify((int)$pdo->query('SELECT COUNT(*) FROM hook_test_invoicepaymentrecords')->fetchColumn()===1,'outbox failure rollback removes partial second receipt');
verify((int)$pdo->query('SELECT status FROM hook_test_invoices WHERE id=2')->fetchColumn()===1,'outbox failure rollback preserves second invoice');
echo "OK: {$n} MySQL webhook assertions; migration, scoped uniqueness, two-connection lock, rollback.\n";
