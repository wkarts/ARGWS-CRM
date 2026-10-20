<?php
/** Actual445 history and current own task defaults/tasks/receipts -> official NOOP446, no domain DDL. */
declare(strict_types=1);
$mode446=$argv[1]??'';$argv=[__FILE__];require __DIR__.'/native_ci_445.php';
require_once APPPATH.'services/CompanyTaskPreferences.php';require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';require_once APPPATH.'services/CompanyProjects.php';
$config446=[];$config=[];require APPPATH.'config/migration.php';$config446=$config;$version446=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
essentialCheck(preg_match('/^(\d)\.(\d)\.(\d)$/D',$version446,$parts446)===1,'canonical shipped version has three native digits');
$shipped446=(int)($parts446[1].$parts446[2].$parts446[3]);essentialCheck(($config446['migration_version']??null)===$shipped446&&$shipped446>=446,'shipped config matches VERSION and supports explicit historical446');
$target446=446; // Historical boundary remains446 when a later shipped release is tested.
$primary446=(new IssuerResourceAccess($db,$p))->primaryId();if($primary446===null)throw new RuntimeException('native446_primary_unresolved');
$fixtureQueries446=count($db->queries);$preferences446=new CompanyTaskPreferences($db,$p);$projects446=new CompanyProjects($db,$p);$tasks446=new CompanyTasks($db,$p);$standalone446=new CompanyStandaloneTasks($db,$p);
// Existing services execute on genuine history445. This is current-writer preservation evidence,
// never a claim that an old writer binary or legacy hash was executed.
foreach([[$a,$ca,'in_progress','1'],[$b,$cb,'todo','0'],[$primary446,$clientPrimary444,'in_progress','0']]as[$issuer446,$client446,$status446,$assign446]){
 essentialCheck($preferences446->page($issuer446,7)===['revision'=>0,'default_task_status'=>'todo','new_task_auto_assign_current_member'=>'1'],'before446 own task preference is absent/default including principal');
 $preferences446->save($issuer446,7,0,['default_task_status'=>$status446,'new_task_auto_assign_current_member'=>$assign446]);
 $project446=$projects446->create($issuer446,7,['client_id'=>$client446,'responsible_id'=>7,'name'=>'Native446 retained task project '.$issuer446,'description'=>'Synthetic current writer on history445','start_date'=>'2026-10-19','due_date'=>'2026-11-19'],hash('sha256','native446-project-'.$issuer446));
 foreach(['task_created'=>$tasks446,'standalone_task_created'=>$standalone446]as$operation446=>$writer446){
  $body446=['name'=>'Native446 retained task '.$issuer446.' '.$operation446,'description'=>'Preserve row receipt and preference across NOOP','due_date'=>'2026-11-01','checklist'=>[['text'=>'Pending retained checklist','done'=>0]]];if($operation446==='task_created')$body446['project_id']=$project446;if($assign446==='0')$body446['assignee_id']=7;
  $request446=hash('sha256','native446-'.$operation446.'-'.$issuer446);$task446=$writer446->create($issuer446,7,$body446,$request446);$record446=$writer446->get($issuer446,7,$task446);
  essentialCheck($record446['status']===$status446&&(int)$record446['assignee_id']===7&&$record446['timers']===[]&&$record446['billing_state']==='none','current native446 writer consumes own pre-capture task defaults without timer/finance');
  $receipt446=$db->query('SELECT * FROM '.$p.'company_project_requests WHERE request_key=?',[$request446])->row_array();essentialCheck((int)$receipt446['issuer_id']===$issuer446&&(int)$receipt446['actor_id']===7&&$receipt446['operation']===$operation446&&(int)$receipt446['result_id']===$task446&&preg_match('/^[a-f0-9]{64}$/D',$receipt446['input_hash'])===1,'actual receipt exists before capture with current canonical hash');
  essentialCheck($writer446->create($issuer446,7,$body446,$request446)===$task446&&$db->query('SELECT * FROM '.$p.'company_project_requests WHERE request_key=?',[$request446])->row_array()===$receipt446,'pre-capture replay preserves original task identity and exact receipt');
 }
}
essentialCheck(!array_filter(array_slice($db->queries,$fixtureQueries446),static fn($query446)=>preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$query446[0])),'task preference/project/task fixture uses real historical schema without domain DDL');
require_once APPPATH.'services/CompanyCalendarPreferences.php';
$calendarQueries446=count($db->queries);$calendar446=new CompanyCalendarPreferences($db,$p);
foreach([$a,$b,$primary446]as$calendarIssuer446){
 $documentBefore446=json_decode($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$calendarIssuer446])->row()->settings_json,true,32,JSON_THROW_ON_ERROR);
 essentialCheck(isset($documentBefore446['task_creation_preferences'],$documentBefore446['ticket_preferences'])&&!isset($documentBefore446[CompanyCalendarPreferences::NODE]),'A B principal have own Task/Ticket nodes before calendar preparation');
 essentialCheck($calendar446->page($calendarIssuer446,7)===['revision'=>0,'values'=>CompanyCalendarPreferences::defaults()],'own absent calendar defaults precede446 capture including principal');
 $calendarValues446=array_replace(CompanyCalendarPreferences::defaults(),['show_tasks_on_calendar'=>'1','calendar_events_limit'=>'5']);
 $calendar446->save($calendarIssuer446,7,0,$calendarValues446);
 essentialCheck($calendar446->page($calendarIssuer446,7)===['revision'=>1,'values'=>$calendarValues446],'current real calendar writer saves task optin and limit5 before446 capture');
 $documentAfter446=json_decode($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$calendarIssuer446])->row()->settings_json,true,32,JSON_THROW_ON_ERROR);
 essentialCheck($documentAfter446[CompanyCalendarPreferences::NODE]===['format'=>1,'revision'=>1,'values'=>$calendarValues446],'canonical own calendar node persisted before capture');
 unset($documentAfter446[CompanyCalendarPreferences::NODE]);essentialCheck($documentAfter446===$documentBefore446,'calendar preparation preserves complete other JSON nodes including Task/Ticket');
}
essentialCheck(!array_filter(array_slice($db->queries,$calendarQueries446),static fn($query446)=>preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$query446[0])),'real calendar preference preparation uses existing history without DDL or invented grants');
if($mode446==='--tracking'){
 $pipes446=[];$process446=proc_open([PHP_BINARY,__DIR__.'/native_resume_446_tracking.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','php://stderr','w']],$pipes446);if(!is_resource($process446))throw new RuntimeException('native446_process_failed');
 fclose($pipes446[0]);$output446=stream_get_contents($pipes446[1]);fclose($pipes446[1]);$status446=proc_close($process446);echo$output446;
 essentialCheck($status446===0&&str_contains($output446,'Native446 actual executor confirmed:'),'official NOOP446 actual executor failed/ignored marker readback retry replay metadata and historical bytes');exit;
}
$tables446=static function()use($db,$p):array{$tables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($tables,SORT_STRING);return$tables;};
$rows446=static function(string$table)use($db):array{$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);return$rows;};
$metadata446=static fn(string$table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$before446=[];foreach($tables446()as$table446)$before446[$table446]=['rows'=>$rows446($table446),'metadata'=>$metadata446($table446)];
require_once APPPATH.'migrations/446_version_446.php';$migration446=new Migration_Version_446();$migration446->db=$db;$queryCount446=count($db->queries);$migration446->up();$migration446->up();
essentialCheck(count($db->queries)===$queryCount446,'official446 NOOP body and replay issue no SQL or DDL');
essentialCheck($tables446()===array_keys($before446),'official446 NOOP retains exact historical table set');
foreach($before446 as$table446=>$snapshot446){essentialCheck($snapshot446['metadata']===$metadata446($table446),'NOOP446 preserves exact native metadata '.$table446);essentialCheck($snapshot446['rows']===$rows446($table446),'NOOP446 preserves all historical bytes including own task settings and receipts '.$table446);}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'NOOP446 preserves native NULL-owner invoice bytes');
echo 'Native446 official NOOP body/replay, exact metadata and historical own task preference/row/receipt bytes: '.$GLOBALS['essentialChecks'].' cumulative checks; tracking tested separately.'.PHP_EOL;
