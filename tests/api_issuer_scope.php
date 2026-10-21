<?php
// Runs the actual API methods against a small SQLite query-builder adapter.
define('BASEPATH', __DIR__);
require getenv('INVOICE_ISSUER_SCOPE_FILE') ?: __DIR__.'/../application/services/InvoiceIssuerScope.php';
class App_Model { public $db; public $load; public $payment_modes_model; public $Payments_model; }
require __DIR__.'/../modules/api/models/Api_model.php';
class ApiScopeModel extends Api_model { public function __construct() {} }
class ApiScopeResult {
 public function __construct(private array $rows) {}
 public function result_array(){ return $this->rows; }
 public function result(){return array_map(fn($x)=>(object)$x,$this->rows);}
 public function row(){return $this->rows?(object)$this->rows[0]:null;}
}
class ApiScopeDb {
 private $select='*'; private $from=''; private $join=[]; private $where=[]; private $args=[];
 public function __construct(public PDO $pdo) {}
 public function field_exists($field,$table){if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$this->pdo->prepare('SHOW COLUMNS FROM '.$table.' LIKE ?');$q->execute([$field]);return (bool)$q->fetch();}return in_array($field,array_column($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name'));}
 public function escape_str($v){return str_replace("'","''",$v);}
 public function select($v){$this->select=$v;return $this;}
 public function from($v){$this->from=$v;return $this;}
 public function join($t,$on,$type=''){ $this->join[]="$type JOIN $t ON $on";return $this;}
 public function where($k,$v=null,$escape=null){$this->where[]=$k.($v===null?'':' = ?');if($v!==null)$this->args[]=$v;return $this;}
 public function group_start(){ $this->where[]='(';return $this; }
 public function group_end(){ $this->where[]=')';return $this; }
 public function like($k,$v){ $this->where[]="$k LIKE ?";$this->args[]='%'.$v.'%';return $this; }
 public function or_like($k,$v){$this->where[]="OR $k LIKE ?";$this->args[]='%'.$v.'%';return $this;}
 public function order_by(...$x){return $this;}
 public function limit(...$x){return $this;}
 public function get($table=''){
  $where=str_replace(['( AND ',' AND )',' AND OR '],['(',')',' OR '],implode(' AND ',$this->where));
  $q=$this->pdo->prepare('SELECT '.$this->select.' FROM '.($table?:$this->from).' '.implode(' ',$this->join).($where?' WHERE '.$where:''));$q->execute($this->args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
  $this->select='*';$this->from='';$this->join=[];$this->where=[];$this->args=[];return new ApiScopeResult($rows);
 }
}
function get_custom_fields($x){return [];} function startsWith($a,$b){return str_starts_with($a,$b);} function strafter($a,$b){return substr($a,strlen($b));} function get_option($x){return 'INV-';} function get_sql_select_client_company(){return 't_clients.company';} function prefixed_table_fields_array($t){return [$t.'.*'];}
function db_prefix(){return 't_';} function has_permission(...$a){return true;} function _l($s){return $s;}
$pdo=new PDO(getenv('ISSUERS_TEST_DSN')?:'sqlite::memory:',getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE t_invoices(id INT PRIMARY KEY,issuer_id INT NULL); CREATE TABLE t_invoicepaymentrecords(id INT,invoiceid INT,paymentmode TEXT,amount INT); CREATE TABLE t_payment_modes(id TEXT,name TEXT)');
$pdo->exec("INSERT INTO t_invoices VALUES(1,NULL),(2,11),(3,22); INSERT INTO t_payment_modes VALUES('m','Manual'); INSERT INTO t_invoicepaymentrecords VALUES(1,1,'m',100),(2,2,'m',100),(3,3,'m',100),(4,999,'m',100)");
$model=new ApiScopeModel;$model->db=new ApiScopeDb($pdo);$model->load=new class {function model($x){}};$model->payment_modes_model=new class {function get_payment_gateways($x){return [];}};
$model->Payments_model=new class { function get($id){return (object)['paymentid'=>$id];} };
$n=0;function check($yes,$msg){global $n;$n++;if(!$yes)throw new RuntimeException($msg);}
check($model->get_table('payments',2)===false,'generic payment getter blocks X before hydration');
check($model->get_table('payments',3)===false,'generic payment getter blocks Y before hydration');
check($model->get_table('payments',1)->paymentid===1,'generic payment getter preserves legacy');
check(array_column($model->payment_get(),'paymentid')===[1],'list includes only legacy');
check($model->payment_get(2)===false,'issuer X ID hidden');check($model->payment_get(3)===false,'issuer Y ID hidden');check($model->payment_get(4)===false,'orphan excluded');
foreach(['Manual','100','m'] as $term)check(array_column($model->_search_payment($term,0,true)['result'],'id')===[1],'OR search cannot escape scope: '.$term);

$pdo->exec("ALTER TABLE t_invoices ADD currency INT DEFAULT 1; ALTER TABLE t_invoices ADD clientid INT DEFAULT 1; CREATE TABLE t_clients(userid INT,company TEXT,vat TEXT); INSERT INTO t_clients VALUES(1,'Test',''); CREATE TABLE t_currencies(id INT); INSERT INTO t_currencies VALUES(1); CREATE TABLE t_contacts(userid INT,is_primary INT); CREATE TABLE t_tags(id INT,name TEXT); INSERT INTO t_tags VALUES(1,'same'); CREATE TABLE t_taggables(rel_id INT,tag_id INT,rel_type TEXT); INSERT INTO t_taggables VALUES(1,1,'invoice'),(2,1,'invoice'),(3,1,'invoice')");
check(array_column($model->_search_invoices('#same',0,[],true)['result'],'invoiceid')===[1],'tag invoice search excludes X/Y');
$pdo->exec("ALTER TABLE t_clients ADD phonenumber VARCHAR(50); UPDATE t_clients SET phonenumber='sensitive-private-phone'; CREATE TABLE t_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO t_issuers_clients VALUES(1,11)");
$identity=$model->_search_invoices('#same',0,[],true)['result'][0];
check($identity['company']==='Test' && !array_key_exists('phonenumber',$identity),'legacy financial search returns minimal identity of adopted client, never full directory row');

$pdo->exec('ALTER TABLE t_invoices RENAME TO old_invoices; CREATE TABLE t_invoices(id INT PRIMARY KEY); INSERT INTO t_invoices VALUES(1),(2),(3)');
check(count($model->payment_get())===4,'pre-migration behavior preserved');
echo "API issuer scope: $n checks passed\n";
