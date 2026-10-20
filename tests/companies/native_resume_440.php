<?php
/** Composed440 real body and native tracking; recovered prototypes are not proof. */
declare(strict_types=1);
$schemas440 = ['CompanyInvoiceDraftsSchema','CompanyInvoiceRecurringModesSchema','CompanyInvoiceRecurringDraftsSchema','CompanyInvoiceRecurringDiscoverySchema','CompanyProjectCopiesSchema','CompanyTaskCopiesSchema','CompanyPostRefundManualReversalSchema','CompanyTicketConnectSchema'];
register_shutdown_function(static function () use ($schemas440): void {
    if (!isset($GLOBALS['db'],$GLOBALS['p']) || !preg_match('/^essential_[a-f0-9]{10}_$/D',$GLOBALS['p'])) return;
    $tables=[];
    foreach ($schemas440 as $schema) {
        if (!class_exists($schema)) continue;
        $method=new ReflectionMethod($schema,'definitions');
        $definitions=$method->getNumberOfRequiredParameters()?$schema::definitions($GLOBALS['p']):$schema::definitions();
        $tables=array_merge($tables,array_keys($definitions));
    }
    foreach(array_reverse($tables) as $table)$GLOBALS['db']->query('DROP TABLE IF EXISTS `'.$GLOBALS['p'].$table.'`');
    unset($GLOBALS['db']->data_cache['table_names']);
});
$mode440=$argv[1]??'';
$argv=[__FILE__]; // The parent tracking proof is separate and remains untouched.
require __DIR__.'/native_resume_439.php';
foreach($schemas440 as $schema)require_once APPPATH.'services/'.$schema.'.php';
if($mode440==='--tracking'){
    $pipes440=[];$process440=proc_open([PHP_BINARY,__DIR__.'/native_resume_440_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes440);
    if(!is_resource($process440))throw new RuntimeException('native440_process_failed');
    fclose($pipes440[0]);$output440=stream_get_contents($pipes440[1]);fclose($pipes440[1]);$status440=proc_close($process440);echo$output440;
    essentialCheck($status440===0&&str_contains($output440,'Native440 actual executor confirmed:'),'actual composed440 runner DDL/metadata/marker failure, retry and replay');exit;
}
$beforeTables440=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');
$snapshot440=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$before440=[];foreach($beforeTables440 as$table)$before440[$table]=$snapshot440($table);
require_once APPPATH.'migrations/440_version_440.php';$migration440=new Migration_Version_440();$migration440->db=$db;$migration440->up();$migration440->up();
$wanted440=[];foreach($schemas440 as$schema){$method=new ReflectionMethod($schema,'definitions');$definitions=$method->getNumberOfRequiredParameters()?$schema::definitions($p):$schema::definitions();$wanted440=array_merge($wanted440,array_map(fn($t)=>$p.$t,array_keys($definitions)));if(method_exists($schema,'assertValid'))$schema::assertValid($db,$p);else $schema::ensure($db,$p,false);}
$afterTables440=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');
$added440=array_values(array_diff($afterTables440,$beforeTables440));sort($added440);sort($wanted440);
essentialCheck(count($wanted440)===11&&$added440===$wanted440,'composed440 creates exactly eleven declared additive tables');
foreach($wanted440 as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$table.'`')->row()->n===0,'composed440 does not adopt old records into '.$table);
foreach($before440 as$table=>$rows)essentialCheck($rows===$snapshot440($table),'composed440 preserves historical bytes '.$table);
echo 'Composed440 actual eleven tables, replay, metadata and unchanged rows: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
