<?php
/** Official test-only release445: actual444 data, NOOP and persisted native tracking. */
declare(strict_types=1);
$argv=[__FILE__];require __DIR__.'/native_resume_444.php';
$tables445=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return$names;};
$rows445=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$before445=[];foreach($tables445()as$table)$before445[$table]=['rows'=>$rows445($table),'metadata'=>$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table']];
require_once APPPATH.'migrations/445_version_445.php';$migration445=new Migration_Version_445();$migration445->db=$db;$count445=count($db->queries);$migration445->up();$migration445->up();
essentialCheck(count($db->queries)===$count445,'official445 NOOP body/replay performs no SQL');essentialCheck($tables445()===array_keys($before445),'official445 NOOP preserves exact actual444 table set');
foreach($before445 as$table=>$capture){essentialCheck($capture['rows']===$rows445($table),'NOOP445 preserves actual444 historical rows '.$table);essentialCheck($capture['metadata']===$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'],'NOOP445 preserves exact actual444 metadata '.$table);}
$pipes445=[];$process445=proc_open([PHP_BINARY,__DIR__.'/native_ci_445_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes445);if(!is_resource($process445))throw new RuntimeException('native_ci445_process_failed');fclose($pipes445[0]);$output445=stream_get_contents($pipes445[1]);fclose($pipes445[1]);$exit445=proc_close($process445);echo$output445;
essentialCheck($exit445===0&&str_contains($output445,'Native445 actual executor confirmed:'),'official445 actual App runner validates failed/ignored marker, retry/readback/replay');
echo 'CI445 native NOOP/replay and actual444 history: '.$GLOBALS['essentialChecks'].' cumulative checks; core445 only, modules unchanged.'.PHP_EOL;
