<?php
/** Actual443 history and real own preferences/replies -> official NOOP444, no domain DDL. */
declare(strict_types=1);
$mode444=$argv[1]??'';$argv=[__FILE__];require __DIR__.'/native_resume_443.php';
require_once APPPATH.'services/CompanyTicketPreferences.php';require_once APPPATH.'services/CompanyTickets.php';
$config444=[];$config=[];require APPPATH.'config/migration.php';$config444=$config;$version444=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
essentialCheck(preg_match('/^(\d)\.(\d)\.(\d)$/D',$version444,$parts444)===1,'canonical shipped version has three native digits');
$shipped444=(int)($parts444[1].$parts444[2].$parts444[3]);essentialCheck(($config444['migration_version']??null)===$shipped444&&$shipped444>=444,'shipped config matches VERSION and supports explicit historical444');
$target444=444; // Historical test boundary remains444 when a later shipped release is tested.
$primary444=(new IssuerResourceAccess($db,$p))->primaryId();if($primary444===null)throw new RuntimeException('native444_primary_unresolved');
$fixtureQueries444=count($db->queries);
$clientPrimary444=(new CompanyClients($db,$p))->create($primary444,7,['company'=>'Native444 principal ticket recipient']);
$preferences444=new CompanyTicketPreferences($db,$p);$tickets444=new CompanyTickets($db,$p);
foreach([[$a,$ca,'desc'],[$b,$cb,'asc'],[$primary444,$clientPrimary444,'desc']]as[$issuer444,$client444,$order444]){
 essentialCheck($preferences444->page($issuer444,7)===['revision'=>0,'ticket_replies_order'=>'asc'],'before444 each own preference is absent/defaultasc including principal');
 $preferences444->save($issuer444,7,0,['ticket_replies_order'=>$order444]);
 $ticket444=$tickets444->create($issuer444,7,['client_id'=>$client444,'assignee_id'=>7,'subject'=>'Native444 retained preference '.$issuer444,'body'=>'Native443 private support before NOOP444','priority'=>'normal'],hash('sha256','native444-ticket-'.$issuer444));
 $tickets444->reply($issuer444,7,$ticket444,1,'Native443 first retained private reply',hash('sha256','native444-reply-first-'.$issuer444));
 $tickets444->reply($issuer444,7,$ticket444,2,'Native443 second retained private reply',hash('sha256','native444-reply-second-'.$issuer444));
 $replyIds444=array_column($tickets444->get($issuer444,7,$ticket444)['replies'],'id');
 essentialCheck(count($replyIds444)===2&&($order444==='desc'?$replyIds444[0]>$replyIds444[1]:$replyIds444[0]<$replyIds444[1]),'real own writer and administrative reader consume preference before native444 capture');
}
essentialCheck(!array_filter(array_slice($db->queries,$fixtureQueries444),static fn($query444)=>preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$query444[0])),'own preference/ticket fixture uses existing historical schema without DDL');
if($mode444==='--tracking'){
 $pipes444=[];$process444=proc_open([PHP_BINARY,__DIR__.'/native_resume_444_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes444);if(!is_resource($process444))throw new RuntimeException('native444_process_failed');
 fclose($pipes444[0]);$output444=stream_get_contents($pipes444[1]);fclose($pipes444[1]);$status444=proc_close($process444);echo$output444;
 essentialCheck($status444===0&&str_contains($output444,'Native444 actual executor confirmed:'),'official NOOP444 actual executor failed/ignored marker readback retry replay metadata and historical bytes');exit;
}
$tables444=static function()use($db,$p):array{$tables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($tables,SORT_STRING);return$tables;};
$rows444=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$metadata444=static fn(string$table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$before444=[];foreach($tables444()as$table444)$before444[$table444]=['rows'=>$rows444($table444),'metadata'=>$metadata444($table444)];
require_once APPPATH.'migrations/444_version_444.php';$migration444=new Migration_Version_444();$migration444->db=$db;$queryCount444=count($db->queries);$migration444->up();$migration444->up();
essentialCheck(count($db->queries)===$queryCount444,'official444 NOOP body and replay issue no SQL or DDL');
essentialCheck($tables444()===array_keys($before444),'official444 NOOP retains exact historical table set');
foreach($before444 as$table444=>$snapshot444){essentialCheck($snapshot444['metadata']===$metadata444($table444),'NOOP444 preserves exact native metadata '.$table444);essentialCheck($snapshot444['rows']===$rows444($table444),'NOOP444 preserves all historical bytes including own settings and replies '.$table444);}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'NOOP444 preserves native NULL-owner invoice bytes');
echo 'Native444 official NOOP body/replay, exact metadata and historical own preference/reply bytes: '.$GLOBALS['essentialChecks'].' cumulative checks; tracking tested separately.'.PHP_EOL;
