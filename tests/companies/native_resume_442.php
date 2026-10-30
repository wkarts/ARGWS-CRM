<?php
/** Actual442 additive body, native441 fixture, exact metadata and historical bytes. */
declare(strict_types=1);
register_shutdown_function(static function():void{
 if(!isset($GLOBALS['db'],$GLOBALS['p'])||!preg_match('/^essential_[a-f0-9]{10}_$/D',$GLOBALS['p']))return;
 foreach(['company_ai_requests','company_expense_copies']as$table)$GLOBALS['db']->query('DROP TABLE IF EXISTS `'.$GLOBALS['p'].$table.'`');
 $index=$GLOBALS['db']->query('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$GLOBALS['p'].'company_expenses','expense_copy_parent'])->result_array();
 if($index)$GLOBALS['db']->query('ALTER TABLE `'.$GLOBALS['p'].'company_expenses` DROP INDEX `expense_copy_parent`');
 unset($GLOBALS['db']->data_cache['table_names']);
});
$mode442=$argv[1]??'';$argv=[__FILE__];require __DIR__.'/native_resume_441.php';
require_once APPPATH.'services/CompanyExpenseCopiesSchema.php';require_once APPPATH.'services/CompanyAiSchema.php';
if($mode442==='--tracking'){
 $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/native_resume_442_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes);if(!is_resource($process))throw new RuntimeException('native442_process_failed');
 fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);$status=proc_close($process);echo$output;
 essentialCheck($status===0&&str_contains($output,'Native442 actual executor confirmed:'),'actual442 DDL/metadata/marker/readback/retry/replay');exit;
}
$tables442=static fn()=>array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');
$rows442=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$indexes442=static fn()=>$db->query('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.'company_expenses'])->result_array();
$before442=[];foreach($tables442()as$table)$before442[$table]=$rows442($table);$oldIndexes442=$indexes442();
require_once APPPATH.'migrations/442_version_442.php';$migration442=new Migration_Version_442();$migration442->db=$db;$migration442->up();$migration442->up();
$wanted442=[$p.'company_ai_requests',$p.'company_expense_copies'];$added442=array_values(array_diff($tables442(),array_keys($before442)));sort($added442);sort($wanted442);essentialCheck($added442===$wanted442,'actual442 creates exactly two additive tables');
foreach($wanted442 as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$table.'`')->row()->n===0,'actual442 does not adopt historical data into '.$table);
CompanyExpenseCopiesSchema::assertValid($db,$p);CompanyAiSchema::ensure($db,$p,false);
$newIndexes442=$indexes442();$existing442=array_values(array_filter($newIndexes442,static fn($r)=>$r['INDEX_NAME']!=='expense_copy_parent'));$parent442=array_values(array_filter($newIndexes442,static fn($r)=>$r['INDEX_NAME']==='expense_copy_parent'));
essentialCheck($existing442===$oldIndexes442&&array_column($parent442,'COLUMN_NAME')===['issuer_id','id']&&!array_filter($parent442,static fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null),'actual442 preserves existing indexes and adds one full unique parent index');
foreach([['company_expense_copies','review_json','mediumtext','issuer_expense_copies_schema_invalid'],['company_ai_requests','response_cipher','longtext','issuer_ai_schema_invalid']]as[$table,$column,$type,$expected]){
 $db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
 try{$migration442->up();throw new LogicException('invalid442 metadata accepted');}catch(RuntimeException$e){essentialCheck($e->getMessage()===$expected,'actual442 refuses wrong metadata '.$table.'.'.$column);}
 $db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');$migration442->up();
}
foreach($before442 as$table=>$rows)essentialCheck($rows===$rows442($table),'actual442 replay/failure preserves every historical row '.$table);
echo 'Native442 actual two tables/one index, metadata failure, replay and historical bytes: '.$GLOBALS['essentialChecks'].' cumulative checks; tracking tested separately.'.PHP_EOL;
