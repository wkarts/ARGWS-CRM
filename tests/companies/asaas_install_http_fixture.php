<?php
/** Native Asaas activation/151 only in the explicitly named disposable HTTP database. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'libraries/App_module_migration.php';
function db_prefix(){return 'tbl';}function &get_instance(){return $GLOBALS['CI'];}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$GLOBALS['CI']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.5.1']];}}];
$old=$db->query("SELECT installed_version FROM tblmodules WHERE module_name='asaas'")->row_array();
if(!$old)$db->query("INSERT INTO tblmodules(module_name,installed_version,active) VALUES('asaas','1.4.5',1)");
else{if(version_compare($old['installed_version'],'1.5.1','>'))throw new RuntimeException('synthetic Asaas downgrade forbidden');$db->query("UPDATE tblmodules SET active=1 WHERE module_name='asaas'");}
require APP_MODULES_PATH.'asaas/install.php';
$installed=$db->query("SELECT installed_version FROM tblmodules WHERE module_name='asaas' AND active=1")->row_array();
if(($installed['installed_version']??'')!=='1.5.1')throw new RuntimeException('native fixture Asaas151 unconfirmed');
\Asaas\CompanySchema::ensure($db,'tbl',false);require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperationsSchema.php';\Asaas\CompanyRemoteOperationsSchema::ensure($db,'tbl',false);require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCheckoutSchema.php';\Asaas\CompanyCheckoutSchema::ensure($db,'tbl',false);echo "Native Asaas151 installed and verified in disposable HTTP fixture.\n";
