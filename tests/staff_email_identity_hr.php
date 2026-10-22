<?php
/** Real MySQL/MariaDB HR writer; settings runtime is simulated active and rejects unintended use. */
require __DIR__.'/companies/staff_native_atomic.php';
require_once APPPATH.'../modules/hr_profile/models/Hr_profile_model.php';
final class CompanySettingsRuntime { public static function active(): bool { return true; } }
function update_option(...$args) { throw new RuntimeException('HR staff must not mutate company settings'); }
function get_hr_profile_option($name) { global $db,$p; return $db->query('SELECT option_val FROM '.$p.'hr_profile_option WHERE option_name=?',[$name])->row()->option_val; }
function to_sql_date($value) { return $value; }
$beforeChecks=$GLOBALS['staffChecks'];
$db->query('ALTER TABLE '.$p.'staff ADD birthday DATE NULL, ADD days_for_identity DATE NULL');
$db->query('CREATE TABLE '.$p.'hr_profile_option(option_name VARCHAR(100) PRIMARY KEY,option_val VARCHAR(100) NOT NULL) ENGINE=InnoDB');
try {
    $db->query('INSERT INTO '.$p.'hr_profile_option(option_name,option_val) VALUES(?,?)',['staff_code_number','42']);
    $hr=new Hr_profile_model();$hr->db=$db;$GLOBALS['nativeActor']=17;
    $links=staffSnapshot($db);$effects=$GLOBALS['nativeEffects'];
    $data=['firstname'=>'Synthetic HR','lastname'=>'Identity','email'=>' hr-positive@example.invalid ','password'=>'synthetic-hr-password','permissions'=>[]];
    staffCheck(CompanySettingsRuntime::active(),'company settings runtime simulated active');
    $created=$hr->add_staff($data);
    staffCheck(is_int($created)&&$created>0,'real HR add succeeds with active settings runtime');
    staffCheck($db->query('SELECT email FROM '.$p.'staff WHERE staffid=?',[$created])->row()->email==='hr-positive@example.invalid','HR email canonical');
    staffCheck(get_hr_profile_option('staff_code_number')==='43','HR counter advances preserving existing arithmetic');
    $after=staffSnapshot($db);
    foreach(['issuers_installation_identity','issuers_staff','issuers_staff_defaults','issuers_resource_permissions'] as $table){staffCheck($links[$table]===$after[$table],'HR add preserves '.$table);}
    foreach(array_slice($GLOBALS['nativeEffects'],count($effects)) as $effect){staffCheck($effect[1]===false,'HR effect occurs only after commit');}
    $baseline=staffSnapshot($db);$effects=$GLOBALS['nativeEffects'];
    $db->failOn='UPDATE '.$p.'hr_profile_option SET';
    staffThrows(fn()=>$hr->add_staff(array_replace($data,['email'=>'hr-rollback@example.invalid'])),'issuer_storage_failed','HR counter failure rejects whole identity transaction');
    $db->failOn=null;
    staffCheck(staffSnapshot($db)===$baseline,'counter failure rolls back inserted staff and other participants');
    staffCheck(get_hr_profile_option('staff_code_number')==='43','counter failure preserves prior value');
    staffCheck($effects===$GLOBALS['nativeEffects'],'counter failure sends no effects');
    echo 'HR identity writers: '.($GLOBALS['staffChecks']-$beforeChecks).' additional checks passed.'.PHP_EOL;
} finally {
    $db->failOn=null;
    if($db->pdo->inTransaction()){$db->pdo->rollBack();}
    $db->pdo->exec('DROP TABLE IF EXISTS '.$p.'hr_profile_option');
}
