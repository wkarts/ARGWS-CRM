<?php
/** Native module installation only in the explicitly named disposable HTTP database. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require APPPATH.'config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require __DIR__.'/support/BbPdoDatabase.php';require APPPATH.'libraries/App_module_migration.php';
function db_prefix(){return 'tbl';}function &get_instance(){return $GLOBALS['CI'];}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new BbPdoDatabase($pdo);
$GLOBALS['CI']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.1.6']];}}];
$rows=$db->query("SELECT installed_version FROM tblmodules WHERE module_name='banco_brasil'")->result_array();
if(count($rows)>1)throw new RuntimeException('native fixture requires one Banco do Brasil module row');
$baseline=$rows?(string)$rows[0]['installed_version']:'0.0.0';
if(!preg_match('/^(?:0\.0\.0|1\.0\.[0-9]|1\.1\.[0-6])$/D',$baseline))throw new RuntimeException('native fixture refuses unexpected Banco do Brasil version');
if(!$rows)$db->query("INSERT INTO tblmodules(module_name,installed_version,active) VALUES('banco_brasil','0.0.0',1)");
$result=(new App_module_migration('banco_brasil'))->to_latest();
if($baseline==='1.1.6'?$result!==true:(string)$result!=='116')throw new RuntimeException('native fixture BB installation failed');
$persisted=$db->query("SELECT installed_version FROM tblmodules WHERE module_name='banco_brasil'")->result_array();
if(count($persisted)!==1||$persisted[0]['installed_version']!=='1.1.6')throw new RuntimeException('native fixture BB target tracking not confirmed');
echo $baseline==='1.1.6'?"Native Banco do Brasil116 already current; real runner and tracking confirmed.\n":"Native Banco do Brasil116 installed in disposable HTTP fixture.\n";
