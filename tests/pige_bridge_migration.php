<?php
define('BASEPATH',__DIR__);
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'custom_';}
class MigrationResult {public $rows;function __construct($rows){$this->rows=$rows;}function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}function result_array(){return $this->rows;}}
class MigrationDb {
 public $version='0.1.1';public $exists=false;public $broken=false;public $failCreate=false;public $creates=0;public $badType=false;
 function where($a,$b){return $this;}function select($a){return $this;}
 function get($t){if($t!=='custom_modules')throw new Exception('prefix');return new MigrationResult([['installed_version'=>$this->version]]);}
 function update($t,$row){$this->version=$row['installed_version'];return true;}
 function table_exists($t){return $this->exists;}
 function query($sql,$params=[]){
  if(str_starts_with($sql,'CREATE TABLE')){if($this->failCreate)return false;if(!str_contains($sql,'`custom_pige_bridge_requests`'))throw new Exception('prefix');$this->exists=true;$this->creates++;return true;}
  if(str_starts_with($sql,'SHOW FULL')){$rows=[];foreach(['id','connection_key','event_id','request_id','payload_sha256','scope_sha256','school_id','issuer_id','account_id','environment','charge_id','amount_cents','due_date','created_at'] as $f)$rows[]=['Field'=>$f,'Null'=>'NO','Collation'=>'ascii_bin','Type'=>(['id'=>'bigint unsigned','connection_key'=>'varchar(64)','event_id'=>'char(36)','request_id'=>'char(36)','payload_sha256'=>'char(64)','scope_sha256'=>'char(64)','school_id'=>'char(36)','issuer_id'=>'varchar(19)','account_id'=>'varchar(128)','environment'=>'varchar(16)','charge_id'=>'char(36)','amount_cents'=>'bigint unsigned','due_date'=>'date','created_at'=>'datetime'][$f]),'Extra'=>$f==='id'?'auto_increment':''];if($this->badType)$rows[0]['Type']='int';return new MigrationResult($rows);}
  if(str_starts_with($sql,'SHOW INDEX')){$rows=[];foreach(['id','connection_key,event_id','request_id','scope_sha256,charge_id'] as $k){if($this->broken&&$k==='scope_sha256,charge_id')continue;foreach(explode(',',$k) as $i=>$c)$rows[]=['Key_name'=>$k,'Non_unique'=>0,'Sub_part'=>null,'Seq_in_index'=>$i+1,'Column_name'=>$c];}return new MigrationResult($rows);}
  return new MigrationResult([['ENGINE'=>'InnoDB']]);
 }
}
$CI=(object)['db'=>new MigrationDb(),'load'=>new class{function dbforge(){}},'lang'=>new class{function load($a){}function line($a){return $a;}},'app_modules'=>new class{function get($a){return ['headers'=>['version'=>'0.1.1']];}}];
function &get_instance(){global $CI;return $CI;}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
$checks=0;function check($v){global $checks;$checks++;if(!$v)throw new Exception('migration check '.$checks);}
require dirname(__DIR__).'/modules/pige_bridge/install.php';check($CI->db->exists&&$CI->db->version==='0.1.1');
require dirname(__DIR__).'/modules/pige_bridge/install.php';check($CI->db->creates===1&&$CI->db->version==='0.1.1');
$CI->db->version='0.1.0';check((new App_module_migration('pige_bridge'))->to_latest()==='011');check($CI->db->version==='0.1.1');
check((new App_module_migration('pige_bridge'))->to_latest()===true);
$CI->db->broken=true;$CI->db->version='0.1.0';$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='0.1.0');
$CI->db->broken=false;$CI->db->badType=true;$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='0.1.0');
$CI->db=new MigrationDb();$CI->db->failCreate=true;$failed=false;try{require dirname(__DIR__).'/modules/pige_bridge/install.php';}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='0.1.0');
check(!str_contains(file_get_contents(dirname(__DIR__).'/modules/pige_bridge/install.php'),'CREATE TABLE'));
echo "PIGE bridge native migration: {$checks} checks passed (DB metadata adapter; real framework runner)\n";
