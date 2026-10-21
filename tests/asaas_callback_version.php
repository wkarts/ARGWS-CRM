<?php
/** Native tracking test only. SQL correctness/metadata are exercised by real DB migration suites. */
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'test_';}function &get_instance(){return $GLOBALS['CI'];}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
require APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperationsSchema.php';
require APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCheckoutSchema.php';
class CallbackMigrationDb {
 public $version='1.5.1';public $fail=false;public $pretend=false;
 function where($k,$v){return $this;}function select($v){return $this;}
 function get($table){return new class($this->version){function __construct(private $version){}function row(){return (object)['installed_version'=>$this->version];}};}
 function update($table,$row){if($this->fail&&$row['installed_version']==='1.5.1')return false;if(!$this->pretend||$row['installed_version']!=='1.5.1')$this->version=$row['installed_version'];return true;}
 function field_exists(...$args){return true;}function table_exists(...$args){return true;}
 function query($sql,$args=[]){
  $rows=[];
  if(str_starts_with($sql,'CREATE TABLE IF NOT EXISTS'))return true;
  if(str_starts_with($sql,'SELECT COLLATION_NAME'))$rows=[['COLLATION_NAME'=>'utf8mb4_bin']];
  elseif(str_starts_with($sql,'SHOW INDEX'))$rows=[['Key_name'=>'asaas_webhook_scope_event_uq'],['Key_name'=>'asaas_webhook_pending_idx']];
  else{
   $suffix=substr($args[0]??'',strlen('test_asaas_company_'));
   $remote=$suffix==='remote_operations';$checkout=isset(\Asaas\CompanyCheckoutSchema::FIELDS[$suffix]);
   if(!$checkout&&!$remote&&!isset(\Asaas\CompanySchema::FIELDS[$suffix]))throw new RuntimeException('Unexpected tracking fixture query');
   $fields=$checkout?\Asaas\CompanyCheckoutSchema::FIELDS[$suffix]:($remote?(new ReflectionClass(\Asaas\CompanyRemoteOperationsSchema::class))->getConstant('FIELDS'):\Asaas\CompanySchema::FIELDS[$suffix]);
   if(str_starts_with($sql,'SELECT ENGINE,TABLE_COLLATION'))$rows=[['ENGINE'=>'InnoDB','TABLE_COLLATION'=>'utf8mb4_bin']];
   elseif(str_starts_with($sql,'SELECT COLUMN_NAME,COLUMN_TYPE'))foreach($fields as $name=>$type)$rows[]=['COLUMN_NAME'=>$name,'COLUMN_TYPE'=>rtrim($type,'?'),'IS_NULLABLE'=>str_ends_with($type,'?')?'YES':'NO','COLUMN_DEFAULT'=>null,'COLLATION_NAME'=>'utf8mb4_bin','EXTRA'=>$name==='id'?'auto_increment':''];
   elseif(str_starts_with($sql,'SELECT INDEX_NAME'))foreach(($checkout?(new ReflectionClass(\Asaas\CompanyCheckoutSchema::class))->getConstant('INDEXES')[$suffix]:($remote?['PRIMARY'=>[['origin_id'],0],'request_key'=>[['request_key'],0]]:(new ReflectionClass(\Asaas\CompanySchema::class))->getConstant('INDEXES')[$suffix])) as $name=>[$columns,$nonUnique])foreach($columns as $column)$rows[]=['INDEX_NAME'=>$name,'COLUMN_NAME'=>$column,'NON_UNIQUE'=>$nonUnique,'SUB_PART'=>null];
   elseif(str_starts_with($sql,'SELECT REFERENCED_TABLE_NAME')){$fk=($checkout?(new ReflectionClass(\Asaas\CompanyCheckoutSchema::class))->getConstant('FKS')[$suffix]:($remote?['origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']]:(new ReflectionClass(\Asaas\CompanySchema::class))->getConstant('FKS')[$suffix]))[$args[1]];$rows=[['REFERENCED_TABLE_NAME'=>'test_'.$fk[0],'REFERENCED_COLUMN_NAME'=>$fk[1]]];}
   else throw new RuntimeException('Unexpected tracking fixture query');
  }
  return new class($rows){function __construct(private array $rows){}function row_array(){return $this->rows[0]??[];}function result_array(){return $this->rows;}};
 }
}
$GLOBALS['CI']=(object)['db'=>new CallbackMigrationDb(),'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.5.1']];}}];
$n=0;function verify($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
require APP_MODULES_PATH.'asaas/install.php';verify($CI->db->version==='1.5.1','activation native sequence146 through151 tracked');
$CI->db->version='1.4.6';verify((string)(new App_module_migration('asaas'))->version('147')==='147','historical147 remains a native no-op');verify((string)(new App_module_migration('asaas'))->version('148')==='148','historical148 remains independently trackable');
verify((string)(new App_module_migration('asaas'))->version('149')==='149'&&$CI->db->version==='1.4.9','historical149 remains independently trackable');
verify((string)(new App_module_migration('asaas'))->version('150')==='150'&&$CI->db->version==='1.5.0','historical150 remains independently trackable');
verify((string)(new App_module_migration('asaas'))->to_latest()==='151','upgrade executes151 after historical150');verify($CI->db->version==='1.5.1','version151 tracked');
verify((new App_module_migration('asaas'))->to_latest()===true,'repeat no-op');
$CI->db->fail=true;$failed=false;try{require APP_MODULES_PATH.'asaas/install.php';}catch(RuntimeException $e){$failed=$e->getMessage()==='asaas_native_migration_failed';}verify($failed&&$CI->db->version==='1.5.0','native151 tracking failure preserves confirmed150');
$CI->db->fail=false;$CI->db->pretend=true;$failed=false;try{require APP_MODULES_PATH.'asaas/install.php';}catch(RuntimeException $e){$failed=$e->getMessage()==='asaas_native_migration_failed';}verify($failed&&$CI->db->version==='1.5.0','unconfirmed151 readback is not success');
$CI->db->pretend=false;require APP_MODULES_PATH.'asaas/install.php';verify($CI->db->version==='1.5.1','retry tracks final151 version');
$CI->db->version='1.5.2';$failed=false;try{require APP_MODULES_PATH.'asaas/install.php';}catch(RuntimeException $e){$failed=$e->getMessage()==='asaas_activation_version_invalid';}verify($failed&&$CI->db->version==='1.5.2','older installer cannot downgrade');
$header=file_get_contents(APP_MODULES_PATH.'asaas/asaas.php');verify(str_contains($header,'Version: 1.5.4')&&str_contains($header,'Requires at least: 4.5.8'),'current module header154 requires native core458 tab-parent handler; historical151 tracking cases above remain independent');
echo "OK: {$n} callback version assertions with real native runner and metadata double.\n";
