<?php
/** Three actual InnoDB scenarios with two observed waiters, no external credentials. */
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function ticketPreferencesRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyTicketPreferences.php';$p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$input=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);$db=ticketPreferencesRaceDb($p);
 try{(new CompanyTicketPreferences($db,$p))->save(41,12,$input['revision'],['ticket_replies_order'=>'desc']);echo'saved';}catch(DomainException $e){if(!in_array($e->getMessage(),['company_ticket_preferences_stale','company_ticket_preferences_denied'],true))throw$e;echo$e->getMessage();}exit;
}
require __DIR__.'/ticket_preferences.php';$raceStart=$GLOBALS['essentialChecks'];$observer=ticketPreferencesRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same_revision','revoked_actor','revoked_grant']as$case){
 $current=$preferences->page(41,12);$preferences->save(41,12,$current['revision'],['ticket_replies_order'=>'asc']);$revision=$preferences->page(41,12)['revision'];$auditBefore=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_ticket_preferences_saved'")->row()->n;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['revision'=>$revision],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two actual InnoDB writers observed waiting: '.$case);
  if($case==='revoked_actor')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=12');elseif($case==='revoked_grant')$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='settings' AND capability='edit'");$db->trans_commit();$out=[];
  foreach($workers as[$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','real child exits clean: '.$case);}$workers=[];sort($out);
  if($case==='same_revision'){essentialCheck($out===['company_ticket_preferences_stale','saved'],'one editor commits and one stale editor loses');essentialCheck($preferences->page(41,7)===['revision'=>$revision+1,'ticket_replies_order'=>'desc'],'one coherent revision/order after contention');$expectedAudits=$auditBefore+1;}
  else{essentialCheck($out===['company_ticket_preferences_denied','company_ticket_preferences_denied'],'committed revocation denies both waiting editors');if($case==='revoked_actor')$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=12');else $db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'settings','edit')");essentialCheck($preferences->page(41,12)===['revision'=>$revision,'ticket_replies_order'=>'asc'],'revoked editors preserve preference/revision');$expectedAudits=$auditBefore;}
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_ticket_preferences_saved'")->row()->n===$expectedAudits,'only actual committed preference writer creates audit');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as[$proc,$pipes])if(is_resource($proc))proc_terminate($proc);}
}
echo 'Own ticket preferences contention: '.($GLOBALS['essentialChecks']-$raceStart).' added / '.$GLOBALS['essentialChecks'].' cumulative checks; three two-waiter scenarios.'.PHP_EOL;
