<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyTaskCopies.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function taskCopyRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=taskCopyRaceDb($p);$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    try{echo json_encode(['result'=>(new CompanyTaskCopies($db,$p))->create($in['issuer'],$in['actor'],$in['kind'],$in['source'],$in['review'],$in['key'])],JSON_THROW_ON_ERROR);}catch(DomainException $e){if(!in_array($e->getMessage(),['company_task_copy_denied','company_task_copy_stale','company_project_reference_denied','company_project_closed'],true))throw $e;echo json_encode(['denied'=>$e->getMessage()],JSON_THROW_ON_ERROR);}exit;
}
require __DIR__.'/task_copies.php';$raceStart=$GLOBALS['essentialChecks'];$observer=taskCopyRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$target=$ps->create($b,7,['client_id'=>$cb,'responsible_id'=>7,'name'=>'Independent target race','description'=>'','start_date'=>'2026-10-19','due_date'=>'2026-11-19'],$key('task-copy-target-race'));
function spawnIndividualCopy(array $in):array{global$p;$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);return[$proc,$pipes];}
function joinIndividualCopy(array $worker):array{[$proc,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0||$err!=='')fwrite(STDERR,$out.$err.PHP_EOL);essentialCheck($exit===0&&$err==='','independent task-copy worker exits without diagnostics');return json_decode($out,true,32,JSON_THROW_ON_ERROR);}
$scenarios=0;
foreach(['project'=>$tb,'standalone'=>$sourceStandalone[$b]]as$kind=>$source)foreach(['same_key','different_keys','revoke_actor','source_changed','assignee_revoked',...($kind==='project'?['target_closed']:[])]as$mode){
    ++$scenarios;$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$b]);foreach([['tasks','view'],['tasks','create'],['projects','view']]as[$f,$c])$db->query('INSERT IGNORE INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$b,$f,$c]);
    $actor=$mode==='revoke_actor'?12:7;$review=taskCopyReview($taskCopies->preview($b,$actor,$kind,$source),'Individual real race '.$kind.' '.$mode,$target,$mode==='assignee_revoked'?12:7);
    $in=['issuer'=>$b,'actor'=>$actor,'kind'=>$kind,'source'=>$source,'review'=>$review,'key'=>$key('individual-race-'.$kind.'-'.$mode)];$other=$in;if($mode==='different_keys')$other['key']=$key('individual-race-second-'.$kind.'-'.$mode);
    $before=taskCopyState($db,$p);$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[spawnIndividualCopy($in),spawnIndividualCopy($other)];
    $until=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiters>=2,'two independent InnoDB workers visibly wait for '.$kind.' '.$mode);
    $table=$kind==='project'?'company_tasks':'company_standalone_tasks';
    if($mode==='revoke_actor')$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=? AND capability=?',[$b,'tasks','create']);
    elseif($mode==='source_changed')$db->query('UPDATE '.$p.$table.' SET revision=revision+1 WHERE id=?',[$source]);
    elseif($mode==='assignee_revoked')$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12',[$b]);
    elseif($mode==='target_closed')$db->query('UPDATE '.$p.'company_projects SET status=? WHERE issuer_id=? AND id=?',['cancelled',$b,$target]);
    $db->trans_commit();$out=array_map('joinIndividualCopy',$workers);$after=taskCopyState($db,$p);
    if($mode==='same_key'){essentialCheck($out[0]['result']===$out[1]['result'],'same attempt returns identical task and receipt to both waiters');essentialCheck(count($after[$table])===count($before[$table])+1,'same-key race creates exactly one new task');}
    elseif($mode==='different_keys')essentialCheck($out[0]['result']['task_id']!==$out[1]['result']['task_id']&&count($after[$table])===count($before[$table])+2,'different explicit attempts create separate atomic tasks');
    else{$expected=match($mode){'revoke_actor'=>'company_task_copy_denied','source_changed'=>'company_task_copy_stale','assignee_revoked'=>'company_project_reference_denied','target_closed'=>'company_project_closed'};essentialCheck($out===[['denied'=>$expected],['denied'=>$expected]],'both waiters revalidate '.$kind.' '.$mode.' under lock');foreach(['company_tasks','company_standalone_tasks']as$t)essentialCheck(count($after[$t])===count($before[$t]),'denied race creates no task in '.$t);foreach(['company_task_copy_requests','issuers_audit']as$t)essentialCheck($after[$t]===$before[$t],'denied race leaves exact receipt/audit rows '.$t);}
    if($mode==='source_changed')$db->query('UPDATE '.$p.$table.' SET revision=revision-1 WHERE id=?',[$source]);elseif($mode==='assignee_revoked')$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$b]);elseif($mode==='target_closed')$db->query('UPDATE '.$p.'company_projects SET status=? WHERE issuer_id=? AND id=?',['active',$b,$target]);
}
echo 'Individual task-copy InnoDB races: '.($GLOBALS['essentialChecks']-$raceStart).' added / '.$GLOBALS['essentialChecks'].' cumulative; '.$scenarios.' scenarios with two observed independent waiters each.'.PHP_EOL;
