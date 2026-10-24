<?php
define('BASEPATH',__DIR__);
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'custom_';}
class MigrationResult {public $rows;function __construct($rows){$this->rows=$rows;}function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}function result_array(){return $this->rows;}}
class MigrationDb {
 public $version='1.0.6';public $extra=[];public $originsExist=false;public $originCreates=0;public $badOrigin=false;public $connectionsExist=false;public $connectionCreates=0;public $badConnection=false;public $exists=false;public $broken=false;public $failCreate=false;public $creates=0;public $badType=false;
 function where($a,$b){return $this;}function select($a){return $this;}
 function get($t){if($t!=='custom_modules')throw new Exception('prefix');return new MigrationResult([['installed_version'=>$this->version]]);}
 function update($t,$row){$this->version=$row['installed_version'];return true;}
 function table_exists($t){if(str_ends_with($t,'pige_bridge_external_config')||str_ends_with($t,'pige_bridge_charge_origins'))return !empty($this->extra[$t]);return str_ends_with($t,'pige_bridge_invoice_origins')?$this->originsExist:(str_ends_with($t,'pige_bridge_connections')?$this->connectionsExist:$this->exists);}
 function query($sql,$params=[]){
  foreach(['pige_bridge_external_config','pige_bridge_charge_origins'] as $suffix){if(!str_contains($sql,'`custom_'.$suffix.'`'))continue;
   if(str_starts_with($sql,'CREATE TABLE')){$this->extra['custom_'.$suffix]=true;return true;}
   if($suffix==='pige_bridge_charge_origins'){$types=['request_key'=>'char(64)','request_id'=>'char(36)','connection_key'=>'varchar(64)','event_id'=>'char(36)','charge_id'=>'char(36)','payload_hash'=>'char(64)','origin_hash'=>'char(64)','origin_json'=>'text','created_at'=>'datetime'];$indexes=['request_key','request_id','connection_key,charge_id','connection_key,event_id'];}
   else{$types=['connection_key'=>'varchar(64)','connection_revision'=>'bigint unsigned','revision'=>'bigint unsigned','mapping_json'=>'text','account_revision'=>'char(64)','scope_hash'=>'char(64)','updated_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'];$indexes=['connection_key'];}
   if(str_starts_with($sql,'SHOW FULL')){$rows=[];foreach($types as $field=>$type)$rows[]=['Field'=>$field,'Type'=>$type,'Null'=>'NO','Collation'=>'ascii_bin'];return new MigrationResult($rows);}
   if(str_starts_with($sql,'SHOW INDEX')){$rows=[];foreach($indexes as $key)foreach(explode(',',$key) as $i=>$field)$rows[]=['Key_name'=>$key,'Non_unique'=>0,'Sub_part'=>null,'Seq_in_index'=>$i+1,'Column_name'=>$field];return new MigrationResult($rows);}
  }
  if(str_contains($sql,'`custom_pige_bridge_invoice_origins`')){
   if(str_starts_with($sql,'CREATE TABLE')){if($this->failCreate)return false;$this->originsExist=true;$this->originCreates++;return true;}
   if(str_starts_with($sql,'SHOW FULL')){$types=['request_key'=>'char(64)','request_id'=>'char(36)','connection_key'=>'varchar(64)','event_id'=>'char(36)','charge_id'=>'char(36)','payload_hash'=>'char(64)','origin_hash'=>'char(64)','origin_json'=>'text','created_at'=>'datetime'];$rows=[];foreach($types as $field=>$type)$rows[]=['Field'=>$field,'Type'=>$type,'Null'=>'NO','Collation'=>'ascii_bin'];return new MigrationResult($rows);}
   if(str_starts_with($sql,'SHOW INDEX')){$rows=[];foreach(['request_key','request_id','connection_key,charge_id','connection_key,event_id'] as $key){if($this->badOrigin&&$key==='connection_key,event_id')continue;foreach(explode(',',$key) as $i=>$field)$rows[]=['Key_name'=>$key,'Non_unique'=>0,'Sub_part'=>null,'Seq_in_index'=>$i+1,'Column_name'=>$field];}return new MigrationResult($rows);}
  }
  if(str_contains($sql,'`custom_pige_bridge_connections`')){
   if(str_starts_with($sql,'CREATE TABLE')){if($this->failCreate)return false;$this->connectionsExist=true;$this->connectionCreates++;return true;}
   if(str_starts_with($sql,'SHOW FULL')){$types=['connection_key'=>'varchar(64)','scope_json'=>'text','scope_hash'=>'char(64)','secret_cipher'=>'text','secret_hash'=>'char(64)','enabled'=>'tinyint','revision'=>'bigint unsigned','mapping_json'=>'text','updated_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'];$rows=[];foreach($types as $field=>$type)$rows[]=['Field'=>$field,'Type'=>$type,'Null'=>$field==='mapping_json'?'YES':'NO','Collation'=>'ascii_bin','Default'=>$field==='enabled'?($this->badConnection?'1':'0'):null];return new MigrationResult($rows);}
   if(str_starts_with($sql,'SHOW INDEX')){$rows=[];foreach(['connection_key','scope_hash','secret_hash'] as $field)$rows[]=['Key_name'=>$field,'Non_unique'=>0,'Sub_part'=>null,'Seq_in_index'=>1,'Column_name'=>$field];return new MigrationResult($rows);}
  }

  if(str_starts_with($sql,'CREATE TABLE')){if($this->failCreate)return false;if(!str_contains($sql,'`custom_pige_bridge_requests`'))throw new Exception('prefix');$this->exists=true;$this->creates++;return true;}
  if(str_starts_with($sql,'SHOW FULL')){$rows=[];foreach(['id','connection_key','event_id','request_id','payload_sha256','scope_sha256','school_id','issuer_id','account_id','environment','charge_id','amount_cents','due_date','created_at'] as $f)$rows[]=['Field'=>$f,'Null'=>'NO','Collation'=>'ascii_bin','Type'=>(['id'=>'bigint unsigned','connection_key'=>'varchar(64)','event_id'=>'char(36)','request_id'=>'char(36)','payload_sha256'=>'char(64)','scope_sha256'=>'char(64)','school_id'=>'char(36)','issuer_id'=>'varchar(19)','account_id'=>'varchar(128)','environment'=>'varchar(16)','charge_id'=>'char(36)','amount_cents'=>'bigint unsigned','due_date'=>'date','created_at'=>'datetime'][$f]),'Extra'=>$f==='id'?'auto_increment':''];if($this->badType)$rows[0]['Type']='int';return new MigrationResult($rows);}
  if(str_starts_with($sql,'SHOW INDEX')){$rows=[];foreach(['id','connection_key,event_id','request_id','scope_sha256,charge_id'] as $k){if($this->broken&&$k==='scope_sha256,charge_id')continue;foreach(explode(',',$k) as $i=>$c)$rows[]=['Key_name'=>$k,'Non_unique'=>0,'Sub_part'=>null,'Seq_in_index'=>$i+1,'Column_name'=>$c];}return new MigrationResult($rows);}
  return new MigrationResult([['ENGINE'=>'InnoDB']]);
 }
}
$CI=(object)['db'=>new MigrationDb(),'load'=>new class{function dbforge(){}},'lang'=>new class{function load($a){}function line($a){return $a;}},'app_modules'=>new class{function get($a){return ['headers'=>['version'=>'1.0.6']];}}];
function &get_instance(){global $CI;return $CI;}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
$checks=0;function check($v){global $checks;$checks++;if(!$v)throw new Exception('migration check '.$checks);}
require dirname(__DIR__).'/modules/pige_bridge/install.php';check($CI->db->exists&&$CI->db->version==='1.0.6');
require dirname(__DIR__).'/modules/pige_bridge/install.php';check($CI->db->creates===1&&$CI->db->version==='1.0.6');
foreach (['0.1.0','0.1.1','1.0.0','1.0.1','1.0.2','1.0.3','1.0.4','1.0.5'] as $old) {$CI->db->version=$old;check((string)(new App_module_migration('pige_bridge'))->to_latest()==='106');check($CI->db->version==='1.0.6');}
$CI->db->version='0.1.0';check((string)(new App_module_migration('pige_bridge'))->to_latest()==='106');check($CI->db->version==='1.0.6');
check((new App_module_migration('pige_bridge'))->to_latest()===true);
check($CI->db->connectionsExist&&$CI->db->connectionCreates===1);check($CI->db->originsExist&&$CI->db->originCreates===1);
$CI->db->badOrigin=true;$CI->db->version='1.0.2';$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='1.0.2');$CI->db->badOrigin=false;
$CI->db->badConnection=true;$CI->db->version='1.0.1';$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='1.0.1');$CI->db->badConnection=false;
$CI->db->broken=true;$CI->db->version='0.1.0';$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='0.1.0');
$CI->db->broken=false;$CI->db->badType=true;$failed=false;try{(new App_module_migration('pige_bridge'))->to_latest();}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='0.1.0');
$CI->db=new MigrationDb();$CI->db->failCreate=true;$failed=false;try{require dirname(__DIR__).'/modules/pige_bridge/install.php';}catch(RuntimeException $e){$failed=true;}check($failed&&$CI->db->version==='1.0.0');
check(!str_contains(file_get_contents(dirname(__DIR__).'/modules/pige_bridge/install.php'),'CREATE TABLE'));
echo "PIGE bridge native migration: {$checks} checks passed (DB metadata adapter; real framework runner)\n";
