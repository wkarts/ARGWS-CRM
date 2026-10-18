<?php
define('BASEPATH',__DIR__);define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'test_';}function &get_instance(){return $GLOBALS['CI'];}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
class CallbackMigrationDb {
 public $version='1.4.8';public $fail=false;
 function where($k,$v){return $this;}function select($v){return $this;}
 function get($table){return new class($this->version){function __construct(private $version){}function row(){return (object)['installed_version'=>$this->version];}};}
 function update($table,$row){if($this->fail&&$row['installed_version']==='1.4.8')return false;$this->version=$row['installed_version'];return true;}
 function field_exists(...$args){return true;}function table_exists(...$args){return true;}
 function query($sql,...$args){
  if(str_starts_with($sql,'SELECT COLLATION_NAME'))return new class{function row_array(){return ['COLLATION_NAME'=>'utf8mb4_bin'];}};
  if(str_starts_with($sql,'SHOW INDEX'))return new class{function result_array(){return [['Key_name'=>'asaas_webhook_scope_event_uq'],['Key_name'=>'asaas_webhook_pending_idx']];}};
  throw new RuntimeException('No DDL expected for existing-schema fixture');
 }
}
$GLOBALS['CI']=(object)['db'=>new CallbackMigrationDb(),'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.4.8']];}}];
$n=0;function verify($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
require APP_MODULES_PATH.'asaas/install.php';verify($CI->db->version==='1.4.8','activation native sequence146 through148 tracked');
$CI->db->version='1.4.6';verify((string)(new App_module_migration('asaas'))->to_latest()==='148','upgrade executes148 after147 no-op');verify($CI->db->version==='1.4.8','version148 tracked');
verify((new App_module_migration('asaas'))->to_latest()===true,'repeat no-op');
$CI->db->fail=true;$failed=false;try{require APP_MODULES_PATH.'asaas/install.php';}catch(RuntimeException $e){$failed=$e->getMessage()==='asaas_migration_version_unconfirmed';}verify($failed&&$CI->db->version==='1.4.7','failed final tracking caught');
$CI->db->fail=false;require APP_MODULES_PATH.'asaas/install.php';verify($CI->db->version==='1.4.8','retry tracks final version');
$CI->db->version='1.4.9';$failed=false;try{require APP_MODULES_PATH.'asaas/install.php';}catch(RuntimeException $e){$failed=$e->getMessage()==='asaas_activation_version_invalid';}verify($failed&&$CI->db->version==='1.4.9','older installer cannot downgrade');
verify(str_contains(file_get_contents(APP_MODULES_PATH.'asaas/asaas.php'),'Version: 1.4.8'),'module header148');
echo "OK: {$n} callback version assertions with real native runner and schema metadata double.\n";
