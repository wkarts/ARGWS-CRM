<?php
// This test requires an isolated disposable MySQL database. Never point it at a CRM database.
$dsn=getenv('PIGE_BRIDGE_TEST_DSN');
if (!$dsn) { echo "PIGE bridge MySQL: NOT RUN (PIGE_BRIDGE_TEST_DSN absent)\n"; exit(0); }
define('BASEPATH',__DIR__);
function db_prefix(){return 'pige_test_';}
class App_Model {public $db;}
class MysqlBridgeDb
{
 public $pdo;private $filters=[];
 public function __construct($dsn){$this->pdo=new PDO($dsn,getenv('PIGE_BRIDGE_TEST_USER'),getenv('PIGE_BRIDGE_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
 public function table_exists($name){$s=$this->pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$name]);return(bool)$s->fetchColumn();}
 public function query($sql){return $this->pdo->exec($sql)!==false;}
 public function insert_string($table,$row){return 'INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_map(fn($v)=>$this->pdo->quote((string)$v),array_values($row))).')';}
 public function where($key,$value){$this->filters[$key]=$value;return $this;}
 public function get($table){$where=[];foreach($this->filters as $key=>$value)$where[]='`'.$key.'`='.$this->pdo->quote((string)$value);$this->filters=[];$statement=$this->pdo->query('SELECT * FROM `'.$table.'` WHERE '.implode(' AND ',$where));return new MysqlBridgeResult($statement);}
}
class MysqlBridgeResult{private $statement;public function __construct($s){$this->statement=$s;}public function row_array(){return $this->statement->fetch(PDO::FETCH_ASSOC)?:[];}}
$CI=new stdClass();$CI->db=new MysqlBridgeDb($dsn);
function &get_instance(){global $CI;return $CI;}
if($CI->db->table_exists('pige_test_pige_bridge_requests'))throw new RuntimeException('Test table exists; use a fresh disposable database.');
require __DIR__.'/../modules/pige_bridge/install.php';
require __DIR__.'/../modules/pige_bridge/install.php'; // repeatable install
require_once __DIR__.'/../modules/pige_bridge/models/Pige_bridge_model.php';
$model=new Pige_bridge_model();$model->db=$CI->db;
$event=json_decode(json_decode(file_get_contents(__DIR__.'/fixtures/pige-crm-v1.json'),true)['raw_body'],true);
$raw=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$a=$model->accept_request('school-test',$event,$raw);
$b=$model->accept_request('school-test',$event,$raw);
if($a!==$b)throw new RuntimeException('Retry must preserve entire receipt');
$conflict=false;try{$model->accept_request('school-test',$event,$raw.' ');}catch(DomainException $e){$conflict=true;}
if(!$conflict)throw new RuntimeException('Payload conflict required');
$other=$event;$other['event_id']='44444444-4444-4444-8444-444444444444';
$conflict=false;try{$model->accept_request('school-test',$other,json_encode($other));}catch(DomainException $e){$conflict=true;}
if(!$conflict)throw new RuntimeException('Unique scope/charge must prevent second event');
$count=$CI->db->pdo->query('SELECT COUNT(*) FROM pige_test_pige_bridge_requests')->fetchColumn();
if((int)$count!==1)throw new RuntimeException('Unexpected duplicate rows');
$other['scope']['issuer_id']='2';
$c=$model->accept_request('another-issuer',$other,json_encode($other));
if($c['request_id']===$a['request_id'])throw new RuntimeException('Distinct scope must be independent');
echo "PIGE bridge MySQL: real schema, repeatable install, stable retry, payload conflict, unique scope/charge and independent issuer passed\n";
