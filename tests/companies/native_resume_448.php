<?php
/** Real446 bootstrap, actual447 runner, then native448 body/tracking with unchanged historical data. */
declare(strict_types=1);
$mode448=$argv[1]??'';$argv=[__FILE__];require __DIR__.'/native_resume_446.php';
require_once APPPATH.'services/CompanyLocalNoncashSchema.php';require_once APPPATH.'services/CompanyTaskPrioritySchema.php';
function native448Child(string $file,string $p):string{$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/'.$file,$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes);if(!is_resource($process))throw new RuntimeException('native448_child_failed');fclose($pipes[0]);$text=stream_get_contents($pipes[1]);fclose($pipes[1]);$exit=proc_close($process);echo$text;essentialCheck($exit===0,'native448 previous runner completed '.$file);return$text;}
native448Child('native_resume_446_tracking.php',$p);
foreach(array_reverse(CompanyLocalNoncashSchema::tables())as$table){essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$p.$table.'`')->row_array()['n']===0,'only empty current LOCAL fixture removed before real447');$db->query('DROP TABLE `'.$p.$table.'`');}unset($db->data_cache['table_names']);
native448Child('native_resume_447_tracking.php',$p);essentialCheck((int)$db->query('SELECT version FROM `'.$p.'migrations`')->row_array()['version']===447,'native448 starts at actual persisted447, no fabricated marker');
foreach(CompanyTaskPrioritySchema::definitions()as$table=>$parent)if($db->table_exists($p.$table)){essentialCheck((int)$db->query('SELECT COUNT(*) n FROM `'.$p.$table.'`')->row_array()['n']===0,'only empty current task sidecar can prepare old447');$db->query('DROP TABLE `'.$p.$table.'`');}unset($db->data_cache['table_names']);
native448Child('native_resume_448_tracking.php',$p);
echo 'Native448 composed predecessor and actual App runner: '.$GLOBALS['essentialChecks'].' cumulative prerequisite checks.'.PHP_EOL;
