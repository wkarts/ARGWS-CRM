<?php
/** New recovery test: two authenticated contact writers waiting on a real InnoDB lock. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyProjectDiscussions.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function operationsDiscussionConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
    $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$GLOBALS['essentialPrefix']=$p;$db=operationsDiscussionConnection($p);
    try{$row=(new CompanyProjectDiscussions($db,$p,str_repeat('synthetic-',4)))->contactOpen($in['session'],$in['issuer'],$in['project'],$in['project_revision'],$in['collection_revision'],'Concurrent own thread','Synthetic independent contact body',$in['key']);echo 'thread:'.$row['id'];}
    catch(DomainException $e){if(!in_array($e->getMessage(),['company_project_discussion_unavailable','company_project_discussion_stale'],true))throw$e;echo$e->getMessage();}
    exit(0);
}
require __DIR__.'/operations_resume_439_discussions.php';
$observer=operationsDiscussionConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$project=$portalParents['projects'][$a];
foreach(['same_key','stale','revoked'] as $scenario){
    $access=new CompanyContactOperationAccess($db,$p,'projects');$current=$access->page($a,7,$ca)[$contactIds[$a]];$access->set($a,7,$ca,$contactIds[$a],$current['revision'],'1');
    $page=$resumeDiscussion->staffPage($a,7,$project);$beforeThreads=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_project_discussions WHERE issuer_id=? AND project_id=?',[$a,$project])->row()->n;$beforeReceipts=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_project_discussion_requests WHERE issuer_id=? AND project_id=?',[$a,$project])->row()->n;
    $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['session'=>$sessions[$a],'issuer'=>$a,'project'=>$project,'project_revision'=>$page['project']['revision'],'collection_revision'=>$page['collection_revision'],'key'=>$resumeKey('race-'.$scenario.($scenario==='same_key'?'same':$i))],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both contact discussion writers observed waiting on InnoDB: '.$scenario);
        if($scenario==='revoked')$db->query('DELETE FROM '.$p.'contact_permissions WHERE userid=? AND permission_id=6',[$contactIds[$a]]);
        $db->trans_commit();$out=[];foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','contact discussion worker completes without diagnostics: '.$scenario);}
        if($scenario==='same_key')essentialCheck($out[0]===$out[1]&&str_starts_with($out[0],'thread:'),'same-key contact writers converge to one immutable discussion receipt');
        elseif($scenario==='stale')essentialCheck(count(array_filter($out,fn($v)=>str_starts_with($v,'thread:')))===1&&in_array('company_project_discussion_stale',$out,true),'competing stale contact form loses without overwriting first discussion');
        else essentialCheck($out===['company_project_discussion_unavailable','company_project_discussion_unavailable'],'revoked project permission blocks both already-waiting contacts');
        $delta=$scenario==='revoked'?0:1;
        essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_project_discussions WHERE issuer_id=? AND project_id=?',[$a,$project])->row()->n===$beforeThreads+$delta,'discussion race inserts exactly one thread or zero after revocation');
        essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_project_discussion_requests WHERE issuer_id=? AND project_id=?',[$a,$project])->row()->n===$beforeReceipts+$delta,'discussion race confirms exactly one durable receipt or zero after revocation');
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}}
}
echo 'Rebuilt recovery439 contact discussion InnoDB retry/stale/revocation races: '.$GLOBALS['essentialChecks'].' cumulative checks passed across3 scenarios.'.PHP_EOL;
