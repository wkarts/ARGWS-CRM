<?php
/** Real NOOP441 body, unchanged historical rows, and independent persisted tracking. */
declare(strict_types=1);
$mode441=$argv[1]??'';
$argv=[__FILE__];
require __DIR__.'/native_resume_440.php';
if($mode441==='--tracking'){
    $pipes441=[];
    $process441=proc_open([PHP_BINARY,__DIR__.'/native_resume_441_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes441);
    if(!is_resource($process441))throw new RuntimeException('native441_process_failed');
    fclose($pipes441[0]);$output441=stream_get_contents($pipes441[1]);fclose($pipes441[1]);$status441=proc_close($process441);echo$output441;
    essentialCheck($status441===0&&str_contains($output441,'Native441 actual executor confirmed:'),'actual441 marker failure/readback/retry/replay');
    exit;
}
$tables441=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');
$snapshot441=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$before441=[];foreach($tables441 as$table)$before441[$table]=$snapshot441($table);
require APPPATH.'migrations/441_version_441.php';$migration441=new Migration_Version_441();$migration441->db=$db;$migration441->up();$migration441->up();
$after441=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($tables441);sort($after441);
essentialCheck($tables441===$after441,'actual441 NOOP does not create or remove a table');
foreach($before441 as$table=>$rows)essentialCheck($rows===$snapshot441($table),'actual441 replay preserves complete historical rows '.$table);
echo 'Native441 NOOP body, replay and historical row bytes: '.$GLOBALS['essentialChecks'].' cumulative checks passed; executor tracking is separate.'.PHP_EOL;
