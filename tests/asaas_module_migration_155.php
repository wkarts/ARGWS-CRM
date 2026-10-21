<?php
declare(strict_types=1);
// Preserve and run the actual native154 failure/replay checkpoints before the new155 migration.
require __DIR__.'/asaas_module_migration_154.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPreferencesSchema.php';
$start155=$checks;$GLOBALS['migration154Prefix']='migration155_'.bin2hex(random_bytes(6)).'_';$p=db_prefix();
$db=new Migration154Db($pdo);$GLOBALS['migration154Ci']->db=$db;
if($mysql)$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';else{$engine='';$pdo->exec('PRAGMA foreign_keys=ON');}
$tables155=['modules','issuers_registry','asaas_payment_origins','asaas_company_accounts',...\Asaas\CompanyPreferencesSchema::tables()];
$version155=fn()=>$db->get_where($p.'modules',['module_name'=>'asaas'])->row_array()['installed_version'];
try{
    migration154Check(($match[1]??null)==='1.5.5','actual155 header accompanies native155 migration');
    $db->query('CREATE TABLE `'.$p.'modules` (module_name VARCHAR(50) PRIMARY KEY,installed_version VARCHAR(20))'.$engine);
    $db->query('CREATE TABLE `'.$p.'issuers_registry` (id INT UNSIGNED NOT NULL PRIMARY KEY)'.$engine);
    $db->query('CREATE TABLE `'.$p.'asaas_payment_origins` (id INT NOT NULL PRIMARY KEY,environment VARCHAR(16) NOT NULL)'.$engine);
    $db->query('CREATE TABLE `'.$p.'asaas_company_accounts` (id INT NOT NULL PRIMARY KEY,issuer_id INT UNSIGNED NOT NULL,environment VARCHAR(16) NOT NULL,revision CHAR(64) NOT NULL,enabled INT NOT NULL,token_hash CHAR(64) NULL,token_cipher TEXT NULL,automation_actor INT NULL)'.$engine);
    $db->insert($p.'modules',['module_name'=>'asaas','installed_version'=>'1.5.4']);$db->insert($p.'issuers_registry',['id'=>7]);
    $db->insert($p.'asaas_payment_origins',['id'=>9,'environment'=>'sandbox']);
    foreach(['sandbox','production'] as $i=>$env)$db->insert($p.'asaas_company_accounts',['id'=>$i+1,'issuer_id'=>7,'environment'=>$env,'revision'=>str_repeat('b',64),'enabled'=>1,'token_hash'=>null,'token_cipher'=>null,'automation_actor'=>7]);
    $oldAccounts=$db->get($p.'asaas_company_accounts')->result_array();$oldOrigins=$db->get($p.'asaas_payment_origins')->result_array();
    migration154Check((new App_module_migration('asaas'))->version('154')===true,'154 checkpoint remains explicit before155');
    migration154Throws(fn()=>\Asaas\ModuleReadiness::require155($db,$p),'asaas_module_155_required');
    $db->failQuery='CREATE TABLE IF NOT EXISTS `'.$p.'asaas_company_origin_profiles`';
    migration154Throws(fn()=>(new App_module_migration('asaas'))->to_latest(),'issuer_storage_failed');
    migration154Check($version155()==='1.5.4'&&$db->table_exists($p.'asaas_company_preferences')&&!$db->table_exists($p.'asaas_company_origin_profiles'),'partial155 DDL cannot advance native tracking');
    migration154Check(!\Asaas\ModuleReadiness::ready155($db,$p),'partial155 readiness fails closed');
    $db->failQuery=null;$db->failVersionWrite=true;$runner=new App_module_migration('asaas');
    migration154Check($runner->to_latest()===false&&$runner->error_string()==='module_migration_version_write_failed','complete155 schema cannot hide failed native tracking write');
    \Asaas\CompanyPreferencesSchema::ensure($db,$p,false);
    migration154Check($version155()==='1.5.4'&&!\Asaas\ModuleReadiness::ready155($db,$p),'structure without155 tracking does not enable new capabilities');
    $db->failVersionWrite=false;$db->ignoreVersionWrite=true;$runner=new App_module_migration('asaas');
    migration154Check($runner->to_latest()===false&&$runner->error_string()==='module_migration_version_unconfirmed','lying successful tracking update is rejected by readback');
    $db->ignoreVersionWrite=false;
    migration154Check((string)(new App_module_migration('asaas'))->to_latest()==='155'&&$version155()==='1.5.5','retry completes real native155 tracking');
    migration154Check(\Asaas\ModuleReadiness::ready155($db,$p),'confirmed native155 becomes ready');
    foreach(\Asaas\CompanyPreferencesSchema::tables() as $table)migration154Check($db->get($p.$table)->result_array()===[],'migration never selects an environment or fabricates historical profile rows: '.$table);
    migration154Check($db->get($p.'asaas_company_accounts')->result_array()===$oldAccounts&&$db->get($p.'asaas_payment_origins')->result_array()===$oldOrigins,'155 preserves both accounts, revisions, tokens, automation and old origins');
    $db->insert($p.'asaas_company_preferences',['issuer_id'=>7,'active_environment'=>'sandbox','revision'=>str_repeat('c',64)]);
    $db->insert($p.'asaas_company_profiles',['issuer_id'=>7,'environment'=>'production','label'=>'Custom production','description'=>'','allow_pix'=>0,'allow_boleto'=>1,'allow_card'=>0,'revision'=>str_repeat('d',64)]);
    $snapshot=json_encode(['preserved'=>'immutable synthetic evidence'],JSON_THROW_ON_ERROR);
    $db->insert($p.'asaas_company_origin_profiles',['origin_id'=>9,'issuer_id'=>7,'account_row'=>1,'snapshot_json'=>$snapshot,'snapshot_hash'=>hash('sha256',$snapshot),'created_at'=>'2026-10-21 00:00:00']);
    $before=[];foreach($tables155 as $table)$before[$table]=$db->get($p.$table)->result_array();
    migration154Check((new App_module_migration('asaas'))->to_latest()===true,'native155 replay is no-op');
    foreach($tables155 as $table)migration154Check($db->get($p.$table)->result_array()===$before[$table],'155 replay preserves '.$table);
    migration154Check((string)(new App_module_migration('asaas'))->version('154')==='154','code rollback records154 without deleting configuration/evidence');
    migration154Check(!\Asaas\ModuleReadiness::ready155($db,$p),'rollback disables155 capabilities despite preserved schema');
    migration154Check((string)(new App_module_migration('asaas'))->to_latest()==='155','155 replay after rollback revalidates its additive schema');
    foreach($tables155 as $table)migration154Check($db->get($p.$table)->result_array()===$before[$table],'155 rollback/replay preserves '.$table);
    foreach(['1.5.4','1.5.50','1.5.5-preview','',null] as $invalid){$db->where('module_name','asaas')->update($p.'modules',['installed_version'=>$invalid]);migration154Check(!\Asaas\ModuleReadiness::ready155($db,$p),'malformed/old tracking never enables155');}
    $db->where('module_name','asaas')->update($p.'modules',['installed_version'=>'1.5.5']);
    $db->query('ALTER TABLE `'.$p.'asaas_company_profiles` ADD `unexpected` INT NULL');
    migration154Throws(fn()=>\Asaas\CompanyPreferencesSchema::ensure($db,$p,false),'issuer_asaas_preferences_schema_invalid');
    echo 'Asaas native155 migration: '.($checks-$start155).' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).'). Failure/retry/readback/replay; no provider calls.'.PHP_EOL;
}finally{foreach(array_reverse($tables155) as $table)$db->query('DROP TABLE IF EXISTS `'.$p.$table.'`');}
