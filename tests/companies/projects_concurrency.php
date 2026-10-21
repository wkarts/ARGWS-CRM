<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyTasks.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function projectRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=projectRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{(new CompanyTasks($db,$p))->activity($input['issuer'],12,$input['task'],$input['revision'],'comment',$input['key'],'Concurrent synthetic comment');echo 'saved';}
 catch(DomainException $e){if(!in_array($e->getMessage(),['company_project_access_denied','company_project_stale'],true))throw $e;echo $e->getMessage();}exit(0);
}
require __DIR__.'/projects_operations.php';$observer=projectRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$b]);
foreach(['same-key','stale','revoked'] as $case){
 $revision=(int)$ts->get($b,12,$tc)['revision'];$before=count($ts->get($b,12,$tc)['comments']);$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'task'=>$tc,'revision'=>$revision,'key'=>hash('sha256','race-'.$case.($case==='same-key'?'same':$i))],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both task writers observed waiting on actual InnoDB row lock');
  if($case==='revoked')$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$b]);$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','task worker finishes without data-bearing diagnostics');}
  sort($out);$expected=$case==='same-key'?['saved','saved']:($case==='stale'?['company_project_stale','saved']:['company_project_access_denied','company_project_access_denied']);essentialCheck($out===$expected,'receipt replay converges, stale editor loses, and revoked waiting writers fail closed');
  essentialCheck(count($ts->get($b,7,$tc)['comments'])===$before+($case==='revoked'?0:1),'race commits exactly one comment or zero after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Projects/tasks MySQL lock/idempotency/stale/revocation: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
