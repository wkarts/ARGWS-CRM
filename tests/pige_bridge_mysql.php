<?php
// This test requires an isolated disposable MySQL database. Never point it at a CRM database.
$dsn=getenv('PIGE_BRIDGE_TEST_DSN');
if (!$dsn) { echo "PIGE bridge MySQL: NOT RUN (PIGE_BRIDGE_TEST_DSN absent)\n"; exit(0); }
define('BASEPATH',__DIR__);
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require_once dirname(__DIR__).'/application/libraries/App_module_migration.php';
function db_prefix(){return 'pige_test_';}
class App_Model {public $db;}
class MysqlBridgeDb
{
 public $pdo;private $filters=[];
 public function __construct($dsn){$this->pdo=new PDO($dsn,getenv('PIGE_BRIDGE_TEST_USER'),getenv('PIGE_BRIDGE_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
 public function table_exists($name){$s=$this->pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$name]);return(bool)$s->fetchColumn();}
 public function query($sql,$params=[]){$q=$this->pdo->prepare($sql);$q->execute($params);return $q->columnCount()?new MysqlBridgeResult($q):true;}
 public function select($fields){return $this;}
 public function update($table,$data){$sets=[];$values=[];foreach($data as $k=>$v){$sets[]='`'.$k.'`=?';$values[]=$v;}$where=[];foreach($this->filters as $k=>$v){$where[]='`'.$k.'`=?';$values[]=$v;}$this->filters=[];$q=$this->pdo->prepare('UPDATE `'.$table.'` SET '.implode(',',$sets).' WHERE '.implode(' AND ',$where));return $q->execute($values);}
 public function insert_string($table,$row){return 'INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_map(fn($v)=>$this->pdo->quote((string)$v),array_values($row))).')';}
 public function where($key,$value){$this->filters[$key]=$value;return $this;}
 public function get($table){$where=[];foreach($this->filters as $key=>$value)$where[]='`'.$key.'`='.$this->pdo->quote((string)$value);$this->filters=[];$statement=$this->pdo->query('SELECT * FROM `'.$table.'` WHERE '.implode(' AND ',$where));return new MysqlBridgeResult($statement);}
}
class MysqlBridgeResult{private $statement;public function __construct($s){$this->statement=$s;}public function row_array(){return $this->statement->fetch(PDO::FETCH_ASSOC)?:[];} public function row(){return $this->statement->fetch(PDO::FETCH_OBJ)?:null;} public function result_array(){return $this->statement->fetchAll(PDO::FETCH_ASSOC);}}
$CI=new stdClass();$CI->db=new MysqlBridgeDb($dsn);
function &get_instance(){global $CI;return $CI;}
if($CI->db->table_exists('pige_test_pige_bridge_requests'))throw new RuntimeException('Test table exists; use a fresh disposable database.');
$CI->load=new class {public function dbforge(){}};
$CI->lang=new class {public function load($v){} public function line($v){return $v;}};
$CI->app_modules=new class {public function get($v){return ['headers'=>['version'=>'1.0.1']];}};
$CI->db->query('CREATE TABLE pige_test_modules (module_name VARCHAR(64) PRIMARY KEY, installed_version VARCHAR(20), active INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$CI->db->query("INSERT INTO pige_test_modules VALUES ('pige_bridge','1.0.1',0)");
require __DIR__.'/../modules/pige_bridge/install.php';
require __DIR__.'/../modules/pige_bridge/install.php'; // repeatable install
if($CI->db->where('module_name','pige_bridge')->get('pige_test_modules')->row()->installed_version!=='1.0.1')throw new RuntimeException('Native version tracking required');
$CI->db->where('module_name','pige_bridge')->update('pige_test_modules',['installed_version'=>'0.1.0']);
foreach (['0.1.0','0.1.1','1.0.0'] as $old) {
$CI->db->where('module_name','pige_bridge')->update('pige_test_modules',['installed_version'=>$old]);
if ((new App_module_migration('pige_bridge'))->to_latest()===false || $CI->db->where('module_name','pige_bridge')->get('pige_test_modules')->row()->installed_version!=='1.0.1') throw new RuntimeException('Earlier draft upgrade failed');
}
$migration=new App_module_migration('pige_bridge');
if($migration->to_latest()===false)throw new RuntimeException('Native upgrade failed');
if($migration->to_latest()!==true)throw new RuntimeException('Upgrade must be idempotent');
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

// Failed validation must not advance installed_version or destroy receipts.
$CI->db->query('ALTER TABLE pige_test_pige_bridge_requests DROP INDEX scope_charge');
$CI->db->where('module_name','pige_bridge')->update('pige_test_modules',['installed_version'=>'0.1.0']);
$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}
if(!$failed || $CI->db->where('module_name','pige_bridge')->get('pige_test_modules')->row()->installed_version!=='0.1.0')throw new RuntimeException('Failed migration must remain pending');
if((int)$CI->db->pdo->query('SELECT COUNT(*) FROM pige_test_pige_bridge_requests')->fetchColumn()!==2)throw new RuntimeException('Migration must preserve receipts');
echo "PIGE bridge native module migration: fresh activation, existing schema, repeat install, upgrade, tracking and failed-schema preservation passed\n";

$CI->db->query('ALTER TABLE pige_test_pige_bridge_requests ADD UNIQUE KEY scope_charge (scope_sha256,charge_id)');
$CI->db->query('ALTER TABLE pige_test_pige_bridge_requests MODIFY amount_cents INT NOT NULL');
$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}
if(!$failed || $CI->db->where('module_name','pige_bridge')->get('pige_test_modules')->row()->installed_version!=='0.1.0')throw new RuntimeException('Incompatible type must remain pending');
echo "PIGE bridge native migration incompatible type rejection: passed\n";
