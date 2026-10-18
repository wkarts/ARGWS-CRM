<?php

declare(strict_types=1);

// Dedicated disposable MySQL database; no production connection or provider I/O.
if (!getenv('ASAAS_TEST_MYSQL_DSN')) {
    fwrite(STDERR, "ASAAS_TEST_MYSQL_DSN is required for the database contract test.\n");
    exit(2);
}
function db_prefix() { return 'test_'; }
require_once __DIR__ . '/../modules/asaas/libraries/Asaas/PaymentOriginSchema.php';
require_once __DIR__ . '/../modules/asaas/libraries/Asaas/PaymentOriginStore.php';
class DbResult {
    public function __construct(private PDOStatement $statement) {}
    public function row_array() { return $this->statement->fetch(PDO::FETCH_ASSOC) ?: []; }
    public function result_array() { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
}
class Db {
    public bool $db_debug = false;
    private array $conditions = [];
    private int $affected = 0;
    public function __construct(public PDO $pdo) {}
    public function table_exists($table) { $s=$this->pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$table]);return (bool)$s->fetch(); }
    public function query($sql) { return $this->pdo->query($sql); }
    public function get_where($table,$conditions) { $s=$this->pdo->prepare('SELECT * FROM `'.$table.'` WHERE '.implode(' AND ',array_map(fn($k)=>'`'.$k.'` = ?',array_keys($conditions))));$s->execute(array_values($conditions));return new DbResult($s); }
    public function insert($table,$row) { try{$s=$this->pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');return $s->execute(array_values($row));}catch(PDOException $e){return false;} }
    public function where($key,$value=null,$escape=true) { $this->conditions[$key]=$value;return $this; }
    public function update($table,$row) {
        $params=array_values($row);$where=[];
        foreach($this->conditions as $key=>$value){if($key==='remote_id IS NULL'){$where[]=$key;}else{$where[]='`'.$key.'` = ?';$params[]=$value;}}
        $this->conditions=[];$sql='UPDATE `'.$table.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'` = ?',array_keys($row))).' WHERE '.implode(' AND ',$where);
        $s=$this->pdo->prepare($sql);$ok=$s->execute($params);$this->affected=$s->rowCount();return $ok;
    }
    public function affected_rows(){return $this->affected;}
}
$pdo = new PDO(getenv('ASAAS_TEST_MYSQL_DSN'), getenv('ASAAS_TEST_MYSQL_USER') ?: 'root', getenv('ASAAS_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new Db($pdo);
Asaas\PaymentOriginSchema::install($db);
Asaas\PaymentOriginSchema::install($db); // idempotent install/upgrade
$store=new Asaas\PaymentOriginStore($db);
$checks=0;
function check($value,$message){global $checks;if(!$value)throw new RuntimeException($message);$checks++;}
$invoice=['id'=>1,'hash'=>'hash_one','total'=>'123.45','duedate'=>'2026-12-01'];
$context=['provider'=>'asaas','channel'=>'api','account_id'=>'wallet_one','environment'=>'sandbox'];
$store->reserve($invoice,$context);
$store->attachRemote(1,'pay_one');
check($store->byInvoice(1)['remote_id']==='pay_one','remote binding persisted in actual MySQL');
check($store->byInvoice(1)['issuer_id']===null,'legacy scope remains NULL, not a guessed issuer');
try{$store->reserve($invoice,$context);throw new RuntimeException('duplicate reservation accepted');}catch(RuntimeException $e){check($e->getMessage()==='payment_origin_already_reserved','second emission blocked');}
try{$store->attachRemote(1,'pay_other');throw new RuntimeException('mutation accepted');}catch(RuntimeException $e){check($e->getMessage()==='payment_origin_is_immutable','immutable remote enforced');}
$duplicate=$store->byInvoice(1);unset($duplicate['id']);$duplicate['invoice_id']=2;
check(!$db->insert('test_asaas_payment_origins',$duplicate),'unique remote tuple rejects duplicate invoice link');
$duplicate['remote_id']='pay_other';$duplicate['invoice_id']=1;
check(!$db->insert('test_asaas_payment_origins',$duplicate),'unique invoice reserves across concurrent connections');
$store->state(1,'review_required','lookup_failed');
$after=$store->byInvoice(1);
check($after['account_id']==='wallet_one'&&$after['environment']==='sandbox'&&$after['remote_id']==='pay_one','state update preserves origin');
check($after['state']==='review_required','operational state persisted');
check(count($pdo->query('SELECT * FROM test_asaas_payment_origin_events')->fetchAll())===3,'append-only audit events survive state updates');
$store->reserve(array_replace($invoice,['id'=>3,'hash'=>'hash_three']),$context);
check($store->byInvoice(3)['remote_id']===null,'uncertain issuance can retain reservation without remote id');
Asaas\PaymentOriginSchema::install($db);
check($store->byInvoice(1)['account_id']==='wallet_one','repeated upgrade never backfills/replaces identities');
echo "OK: {$checks} MySQL assertions; schema, constraints, durable origin and audit.\n";
