<?php
/** Existing module markers -> code-only targets through the real native module runners. */
declare(strict_types=1);
require __DIR__ . '/legacy_financial_module_fixture.php';
require BASEPATH . 'helpers/file_helper.php';
require APPPATH . 'libraries/App_modules.php';
require APPPATH . 'libraries/App_module_migration.php';
$prefix='module454_';$GLOBALS['moduleReaderPrefix']=$prefix;$db->set_dbprefix($prefix);$db->reset_query();
moduleReaderFixture($db,$prefix);
$db->query('DROP TABLE IF EXISTS ' . $prefix . 'modules');
$db->query('CREATE TABLE ' . $prefix . 'modules (module_name varchar(40) PRIMARY KEY, installed_version varchar(20) NOT NULL,active int NOT NULL) ENGINE=InnoDB');
$ci=&get_instance();
$ci->load=new class { public int $forge=0; public function dbforge():void{++$this->forge;} };
$ci->lang=new class { public function load($name):void{} public function line($name){return $name;} };
$reflection=new ReflectionClass(App_modules::class);$modules=$reflection->newInstanceWithoutConstructor();
$moduleMap=[];
foreach(['finance'=>'1.0.0','accounting'=>'1.3.5'] as $name=>$baseline){
    $headers=$modules->get_headers(APP_MODULES_PATH.$name.'/'.$name.'.php');
    $moduleMap[$name]=['system_name'=>$name,'headers'=>$headers,'installed_version'=>$baseline,'activated'=>1];
    $db->insert($prefix.'modules',['module_name'=>$name,'installed_version'=>$baseline,'active'=>1]);
}
foreach(['ci'=>$ci,'modules'=>$moduleMap,'db_modules'=>[],'active_modules'=>$moduleMap] as $property=>$value)$reflection->getProperty($property)->setValue($modules,$value);
$ci->app_modules=$modules;
$checks=0;
function migrationCheck($condition,string $message):void{++$GLOBALS['checks'];if(!$condition)throw new RuntimeException($message);}
function migrationBusinessSnapshot($db):array{
    $out=[];foreach(['clients','invoices','expenses','invoicepaymentrecords','acc_account_history','acc_bill_mappings'] as $table)$out[$table]=$db->query('SELECT * FROM '.db_prefix().$table)->result_array();return $out;
}
$before=migrationBusinessSnapshot($db);
foreach(['finance'=>['1.0.0','1.0.1'],'accounting'=>['1.3.5','1.3.6']] as $name=>[$baseline,$target]){
    migrationCheck($moduleMap[$name]['headers']['version']===$target,$name.' actual header');
    $offset=count($db->queries);
    migrationCheck($modules->upgrade_database($name)===true,$name.' native upgrade');
    migrationCheck($db->query('SELECT installed_version FROM '.$prefix.'modules WHERE module_name=?',[$name])->row()->installed_version===$target,$name.' persisted target');
    migrationCheck(!array_filter(array_slice($db->queries,$offset),fn($sql)=>preg_match('/^\s*(CREATE|ALTER|DROP|DELETE|INSERT)\b/i',$sql)),$name.' migration has no DDL or business writes');
    migrationCheck(migrationBusinessSnapshot($db)===$before,$name.' all native financial data preserved');
    migrationCheck($modules->upgrade_database($name)===true,$name.' native replay');
    migrationCheck(migrationBusinessSnapshot($db)===$before,$name.' replay preserves data');
    $db->query('UPDATE '.$prefix.'modules SET installed_version=? WHERE module_name=?',[$baseline,$name]);
    $db->query('CREATE TRIGGER module_financial_ignore_version BEFORE UPDATE ON '.$prefix.'modules FOR EACH ROW SET NEW.installed_version=OLD.installed_version');
    try{
        migrationCheck($modules->upgrade_database($name)==='module_migration_version_unconfirmed',$name.' ignored version write fails closed');
        migrationCheck($db->query('SELECT installed_version FROM '.$prefix.'modules WHERE module_name=?',[$name])->row()->installed_version===$baseline,$name.' ignored marker unchanged');
        migrationCheck(migrationBusinessSnapshot($db)===$before,$name.' ignored marker no business effects');
    }finally{$db->query('DROP TRIGGER module_financial_ignore_version');}
    migrationCheck($modules->upgrade_database($name)===true,$name.' recovery through native runner');
    migrationCheck($db->query('SELECT installed_version FROM '.$prefix.'modules WHERE module_name=?',[$name])->row()->installed_version===$target,$name.' recovery target independently read');
}
echo json_encode(['runtime'=>PHP_VERSION,'engine'=>$db->query('SELECT VERSION() AS version')->row()->version,
    'checks'=>$checks,'passed'=>$checks,'acceptance'=>'PASS','scope'=>'Native existing-marker upgrade/replay and actual ignored-write trigger. Original module fresh installers are not exercised.','targets'=>['finance'=>'1.0.1','accounting'=>'1.3.6']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
