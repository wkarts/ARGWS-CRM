<?php
/** Actual App_modules activation + shipped install/native module runner; isolated MariaDB only.
 * Uses the current real header. BB_RESUME_EXPECT_VERSION pins an intended composition (e.g.1.2.2).
 * A121 preflight proves only121; it must never be reported as122 evidence.
 */
declare(strict_types=1);
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL/MariaDB DSN is required.\n");exit(2);}
require __DIR__.'/companies/invoices.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleRelease.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
if(!function_exists('read_file')){function read_file($path){return file_get_contents($path);}}
if(!function_exists('register_payment_gateway')){function register_payment_gateway(...$args):void{}}
$nativeBbStart=$GLOBALS['essentialChecks'];$ci=get_instance();$modules=$ci->app_modules;
$headers=$modules->get_headers(APP_MODULES_PATH.'banco_brasil/banco_brasil.php');$target=$headers['version']??'';$targetCore=$headers['requires_at_least']??'';
essentialCheck(preg_match('/^[0-9]\.[0-9]\.[0-9]$/D',$target)===1&&version_compare($target,'1.2.1','>='),'current real module header is a supported native target');
$step=(int)str_replace('.','',$target);$expected=getenv('BB_RESUME_EXPECT_VERSION');$baseline=null;
foreach(array_slice($argv,1)as$argument){if(!preg_match('/^--baseline=(1\.2\.[012])$/D',$argument,$match))throw new RuntimeException('bb_native_test_argument_invalid');$baseline=$match[1];}
essentialCheck($baseline===null||version_compare($baseline,$target,'<'),'historical child installs a real earlier native target');$installVersion=$baseline??$target;
essentialCheck($expected===false||$expected===''||$target===$expected,'optional requested composition matches the exact real header');
essentialCheck(BancoBrasil\ModuleRelease::VERSION===$target&&BancoBrasil\ModuleRelease::MIGRATION===$step&&BancoBrasil\ModuleRelease::MINIMUM_CORE===$targetCore,'exact runtime version/migration/minimum agree with real parsed header');
essentialCheck(preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D',$targetCore)===1&&version_compare(trim(file_get_contents(dirname(__DIR__).'/VERSION')),$targetCore,'>='),'actual core VERSION satisfies module minimum');
essentialCheck(is_file(APP_MODULES_PATH.'banco_brasil/migrations/'.$step.'_version_'.$step.'.php'),'current target has its own shipped native migration');
$property=new ReflectionProperty(App_modules::class,'modules');$available=$property->getValue($modules);$installHeaders=$headers;$installHeaders['version']=$installVersion;$available['banco_brasil']=['system_name'=>'banco_brasil','headers'=>$installHeaders,'init_file'=>APP_MODULES_PATH.'banco_brasil/banco_brasil.php','path'=>APP_MODULES_PATH.'banco_brasil/','installed_version'=>false,'activated'=>0];$property->setValue($modules,$available);
$tables=static fn()=>array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=? ORDER BY TABLE_NAME',[strlen($p),$p])->result_array(),'TABLE_NAME');
$rows=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$snapshotRows=static function()use($tables,$rows,$p):array{$out=[];foreach($tables()as$t)if($t!==$p.'modules')$out[$t]=$rows($t);return$out;};
$snapshotSchema=static function()use($tables,$db):array {
    $out=[];
    foreach($tables()as$t){
        $definition=array_values($db->query('SHOW CREATE TABLE `'.$t.'`')->row_array())[1];
        // MySQL retains an explicit charset flag after MODIFY even when it equals
        // the charset of utf8mb4_bin. Canonicalize only that redundant spelling;
        // retain every actual column metadata field and every other DDL byte.
        $definition=preg_replace('/ CHARACTER SET utf8mb4(?= COLLATE utf8mb4_bin(?:[ ,\n]))/','',$definition);
        $out[$t]=[
            'ddl'=>preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$definition),
            'columns'=>$db->query('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$t])->result_array(),
        ];
    }
    return$out;
};
$marker=static fn()=>$db->query("SELECT installed_version,active FROM {$p}modules WHERE module_name='banco_brasil'")->row_array();
$noDdl=static function(int$from,string$label)use($db):void{$ddl=array_values(array_filter(array_slice($db->queries,$from),static fn($q)=>preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$q[0])));essentialCheck($ddl===[],$label.' executes no extra DDL');};
$beforeCore=$snapshotRows();unset($db->data_cache['table_names']);essentialCheck(!$db->table_exists($p.'bb_api_operations'),'fresh actual activation starts without BB tables');
$previous=wordwrap((string)($step-1),1,'.',true);if($baseline===null){
$db->failOn='CREATE TABLE IF NOT EXISTS `'.$p.'bb_connections`';essentialThrows(fn()=>$modules->activate('banco_brasil'),'bb_migration_failed','actual fresh activation propagates initial native DDL failure');$db->failOn=null;
essentialCheck($marker()['installed_version']==='1.0.0'&&(int)$marker()['active']===0,'fresh DDL failure cannot advertise active/latest module');
$db->failVersion=$target;essentialThrows(fn()=>$modules->activate('banco_brasil'),'bb_migration_failed','actual activation catches failed final target tracking');$db->failVersion=null;
essentialCheck($marker()['installed_version']===$previous&&(int)$marker()['active']===0,'failed target marker retains last successful native step and inactive state');
}
essentialCheck($modules->activate('banco_brasil')===true,'actual activation invokes shipped install and every native migration to '.$installVersion);
essentialCheck($marker()['installed_version']===$installVersion&&(int)$marker()['active']===1,'fresh native install confirms exact installed target before active');
if($baseline!==null){$available=$property->getValue($modules);$available['banco_brasil']['headers']=$headers;$available['banco_brasil']['installed_version']=$baseline;$property->setValue($modules,$available);essentialCheck((new App_module_migration('banco_brasil'))->find_migrations()!==[],'historical fixture will upgrade using real shipped migration files');}
unset($db->data_cache['table_names']);essentialCheck($db->table_exists($p.'bb_api_operations')&&$db->table_exists($p.'bb_api_cnab_contracts'),'actual fresh activation creates required operational and API/CNAB ledgers');
foreach($beforeCore as$table=>$data)essentialCheck($rows($table)===$data,'fresh activation preserves original core rows '.$table);
// Current installs use the actual importer. Historical baselines need synthetic
// pre130 rows: the current importer correctly requires the not-yet-installed archive.
require __DIR__.'/banco_brasil_cnab400.php';require_once __DIR__.'/support/BbPdoDatabase.php';$store=new BancoBrasil\ModuleStore(new BbPdoDatabase($db->pdo),$p);
$connection=$store->createConnection($a,7,['id'=>$a,'tax_id'=>'12345678000195','legal_name'=>'Invoice A'],['account_id'=>'001:00452-3:000000123873-X','environment'=>'sandbox','agreement_number'=>'1234567','wallet'=>'17','variation'=>'019']);$connectionRow=$store->connection($connection,$a);$binding=$store->registerLayout($connectionRow,7,BancoBrasil\LayoutCatalog::CNAB400);$agreement=BancoBrasil\ModuleStore::agreement($connectionRow);$wire=bb400Fixture($agreement);
if($baseline===null){$import=$store->importReturn($connectionRow,7,$wire,$binding);}
else{
 essentialCheck(!$db->table_exists($p.'bb_return_originals'),'historical baseline really predates mandatory original retention');
 $beforeRejectedImport=$snapshotRows();
 essentialThrows(fn()=>$store->importReturn($connectionRow,7,$wire,$binding),'bb_innodb_required','current importer rejects the old schema before any return/history write');
 essentialCheck($snapshotRows()===$beforeRejectedImport,'missing130 archive rejection preserves every historical/core row');
 require_once __DIR__.'/support/BbHistoricalReturnFixture.php';
 $import=seedHistoricalBb400Return(new BbPdoDatabase($db->pdo),$p,$connectionRow,$store->layoutBinding($binding,$connectionRow),7,$wire);
}
essentialCheck(!$import['duplicate']&&$import['events']===1,'fixture persists a decoded parent400 and provenance under its actual native schema');$oldRows=$snapshotRows();$schema=$snapshotSchema();$replayRows=$oldRows;$replaySchema=$schema;$routes=[];
foreach($baseline===null?[]:[$baseline]as$from){
 if(version_compare($from,$target,'>='))continue;$routes[]=$from.'→'.$target;
 $db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$from]);$before=count($db->queries);$freshHistoricalSchema=!$db->table_exists($p.'bb_checkout_publications')||!$db->table_exists($p.'bb_api_origin_policy');$db->failVersion=$target;$result=(new App_module_migration('banco_brasil'))->to_latest();$db->failVersion=null;
 essentialCheck($result===false&&$marker()['installed_version']===$previous,$from.'→'.$target.' rejects failed final marker without inventing advancement');
 $ddl=array_values(array_filter(array_slice($db->queries,$before),static fn($q)=>preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$q[0])));
 if($freshHistoricalSchema){
  $ddlSql=array_map(static fn($q)=>(string)$q[0],$ddl);
  $expectedNewTables=[$p.'bb_api_origin_policy',$p.'bb_checkout_publications',$p.'bb_return_originals'];sort($expectedNewTables,SORT_STRING);
  $newTableDdl=array_filter($ddlSql,static function($sql)use($expectedNewTables){foreach($expectedNewTables as$table)if(str_contains($sql,$table))return true;return false;});
  essentialCheck(count($ddlSql)===3&&count($newTableDdl)===3,$from.' first historical upgrade creates only the new124/125/130 tables');
  // A historical install predates tables124/125/130. Their first creation is
  // additive even when final tracking fails; it is not a mutation of old rows.
  // Prove the entire historical state before recording the post-DDL replay state.
  $afterRows=$snapshotRows();$afterSchema=$snapshotSchema();
  foreach($oldRows as$table=>$data)essentialCheck(isset($afterRows[$table])&&$afterRows[$table]===$data,$from.' first upgrade preserves every historical row '.$table);
  foreach($schema as$table=>$definition)essentialCheck(isset($afterSchema[$table])&&$afterSchema[$table]===$definition,$from.' first upgrade preserves every historical table definition '.$table);
  $addedRows=array_keys(array_diff_key($afterRows,$oldRows));sort($addedRows,SORT_STRING);
  $addedSchema=array_keys(array_diff_key($afterSchema,$schema));sort($addedSchema,SORT_STRING);
  essentialCheck($addedRows===$expectedNewTables&&$addedSchema===$expectedNewTables,$from.' first upgrade adds exactly the declared124/125/130 tables');
  foreach($expectedNewTables as$table)essentialCheck($afterRows[$table]===[],$from.' new upgrade table contains no invented data '.$table);
  $replayRows=$afterRows;$replaySchema=$afterSchema;
 }
 else essentialCheck($ddl===[],$from.' failed marker replay executes no extra DDL');
 // Real SQL ignores only the target marker; native runner must reject false write success.
 $db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$from]);$trigger=$p.'resume_bb_target';$db->query("CREATE TRIGGER `$trigger` BEFORE UPDATE ON `{$p}modules` FOR EACH ROW SET NEW.installed_version=IF(NEW.module_name='banco_brasil' AND NEW.installed_version='$target',OLD.installed_version,NEW.installed_version)");$before=count($db->queries);$result=(new App_module_migration('banco_brasil'))->to_latest();$noDdl($before,$from.' ignored marker');$db->query('DROP TRIGGER `'.$trigger.'`');essentialCheck($result===false&&$marker()['installed_version']===$previous,$from.'→'.$target.' persisted readback rejects actual ignored target update');
 foreach([
  ['bb_number_reservations','beneficiary_tax_id','VARCHAR(14)','bb_origination_schema_invalid'],
  ['bb_event_reviews','reason','TEXT','bb_reconciliation_schema_invalid'],
  ['bb_api_operations','result_json','LONGTEXT','bb_api_schema_invalid'],
  ['bb_api_cnab_contracts','definition_json','TEXT','bb_schema_incompatible'],
 ]as[$table,$column,$type,$error]){
  $db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$from]);$db->query("ALTER TABLE `{$p}$table` MODIFY `$column` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");$before=count($db->queries);essentialThrows(fn()=>(new App_module_migration('banco_brasil'))->to_latest(),$error,$from.'→'.$target.' rejects invalid participant metadata '.$table);$noDdl($before,$from.' metadata fault '.$table);essentialCheck($marker()['installed_version']===$from,'invalid metadata cannot advance the previous marker '.$table);$db->query("ALTER TABLE `{$p}$table` MODIFY `$column` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");essentialCheck($snapshotRows()===$replayRows,'metadata failure preserves every original row '.$table);
 }
 $before=count($db->queries);$result=(new App_module_migration('banco_brasil'))->to_latest();$noDdl($before,$from.' retry');essentialCheck((string)$result===(string)$step&&$marker()['installed_version']===$target,$from.'→'.$target.' real native retry confirms current target');
 $before=count($db->queries);essentialCheck((new App_module_migration('banco_brasil'))->to_latest()===true&&$marker()['installed_version']===$target,'current '.$target.' native replay is stable');$noDdl($before,'current '.$target.' replay');essentialCheck($snapshotRows()===$replayRows&&$snapshotSchema()===$replaySchema,'NOOP failure/retry/replay preserves all historical BB/core rows and schema '.$from);
}
if($baseline!==null)essentialCheck($routes===[$baseline.'→'.$target],'historical child exercises its actually installed baseline');
require APP_MODULES_PATH.'banco_brasil/install.php';essentialCheck($marker()['installed_version']===$target&&(int)$marker()['active']===1,'shipped install replay validates every real native step and current tracking');essentialCheck($snapshotRows()===$replayRows&&$snapshotSchema()===$replaySchema,'install replay preserves historical rows and complete original schema');
essentialCheck($store->importReturn($connectionRow,7,$wire,$binding)['duplicate'],'historical return remains readable and idempotent after target upgrade/install replay');
if($baseline!==null){
 $file=$db->query('SELECT * FROM '.$p.'bb_return_files WHERE id=?',[$import['id']])->row_array();
 $retained=(new BancoBrasil\ReturnOriginalArchive(new BbPdoDatabase($db->pdo),$p))->read($file);
 essentialCheck($retained!==null&&$retained['content']===$wire&&(int)$retained['byte_count']===strlen($wire),'exact historical bytes are retained only when the operator reimports them after130');
 $afterRecovery=$snapshotRows();foreach($replayRows as$table=>$data)if($table!==$p.'bb_return_originals')essentialCheck($afterRecovery[$table]===$data,'original recovery preserves historical row bytes '.$table);
 essentialCheck(count($afterRecovery[$p.'bb_return_originals'])===1,'historical reimport creates exactly one private original, not another event or payment');
 essentialCheck($store->importReturn($connectionRow,7,$wire,$binding)['duplicate']&&$snapshotRows()===$afterRecovery,'reimport of recovered original is fully idempotent');
}
if($baseline===null){foreach(['1.2.0','1.2.1','1.2.2']as$from){if(version_compare($from,$target,'>='))continue;$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--baseline='.$from],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('bb_historical_fixture_worker_unavailable');fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);echo$output;if($exit!==0||$errors!=='')fwrite(STDERR,'Native BB child '.$from.' exit='.(string)$exit.PHP_EOL.$errors.PHP_EOL);essentialCheck($exit===0&&$errors==='','independent real fresh native'.$from.'→'.$target.' child passes');$routes[]=$from.'→'.$target;}essentialCheck($routes!==[],'at least one real historical child route executed');if(version_compare($target,'1.2.2','>=')){ $expectedRoutes=array_map(static fn($from)=>$from.'→'.$target,array_values(array_filter(['1.2.0','1.2.1','1.2.2'],static fn($from)=>version_compare($from,$target,'<'))));essentialCheck($routes===$expectedRoutes,'every supported native120/121/122 baseline actually upgrades to the current target');}}
echo 'BB native current-header '.$target.' (minimum core'.$targetCore.') activation/install/tracking: '.($GLOBALS['essentialChecks']-$nativeBbStart).' added checks; actual routes '.implode(', ',$routes).'; synthetic MariaDB only.'.PHP_EOL;
