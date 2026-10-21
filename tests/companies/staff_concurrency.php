<?php
require __DIR__.'/staff_fixture.php';
if(($argv[1]??'')==='worker'){
    $job=json_decode(base64_decode($argv[2]),true,512,JSON_THROW_ON_ERROR);if(!preg_match('/^staff453_[a-f0-9]{10}_$/D',$job['prefix'])){exit(91);}
    $db=new StaffTestDb(staffPdo(),$job['prefix']);$s=new CompanyStaff($db,$db->prefix);
    if(!empty($job['pause'])){$db->observer=static function($sql,$params,$db){if(str_starts_with($sql,'INSERT INTO '.$db->prefix.'staff ')){$db->observer=null;fwrite(STDOUT,"inserted\n");fflush(STDOUT);fread(STDIN,1);}};}
    try{$id=match($job['operation']){
        'create'=>$s->create(2,$job['actor'],$job['profile'],['tasks'=>['view']],true,$job['key']),
        'update'=>$s->update(2,$job['actor'],$job['staff'],$job['revision'],['firstname'=>$job['name']],['tasks'=>['view']],false,$job['key']),
        'link'=>$s->link(2,17,$job['email'],[],false,$job['key']),
    };echo json_encode(['id'=>$id]).PHP_EOL;}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]).PHP_EOL;}exit;
}
function staffSpawn(array $job):array
{
    $process=proc_open([PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($job))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process)){throw new RuntimeException('Worker start failed');}stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);return [$process,$pipes];
}
function staffWaiters(PDO $observer,int $blocker):int
{
    if(str_contains($observer->getAttribute(PDO::ATTR_SERVER_VERSION),'MariaDB')){usleep(150000);$sql='SELECT COUNT(DISTINCT w.requesting_trx_id) n FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE b.trx_mysql_thread_id=?';}
    else{$sql='SELECT COUNT(DISTINCT w.REQUESTING_THREAD_ID) n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE b.PROCESSLIST_ID=?';}
    $s=$observer->prepare($sql);$s->execute([$blocker]);return (int)$s->fetchColumn();
}
function staffWaitFor(PDO $observer,int $blocker,int $count):void
{
    $deadline=microtime(true)+12;while(microtime(true)<$deadline){if(staffWaiters($observer,$blocker)>=$count){staffCheck(true,'observed '.$count.' real SQL lock waiters');return;}usleep(25000);}throw new RuntimeException('SQL waiter not observed');
}
function staffCollect(array $worker):array
{
    [$process,$pipes]=$worker;$out='';$err='';$deadline=microtime(true)+12;while(true){$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);$status=proc_get_status($process);if(!$status['running']){break;}if(microtime(true)>$deadline){proc_terminate($process,9);throw new RuntimeException('Synthetic worker timeout');}usleep(20000);}
    $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);foreach($pipes as$pipe){fclose($pipe);}proc_close($process);if($err!==''){throw new RuntimeException('Synthetic worker stderr: '.$err);}return json_decode(trim($out),true,512,JSON_THROW_ON_ERROR);
}
$db=staffFixture();$p=$db->prefix;$s=new CompanyStaff($db,$p);$observer=staffPdo();$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$profile=['firstname'=>'Concurrent','lastname'=>'Synthetic','email'=>'race@example.invalid','password'=>'synthetic-race-123','phonenumber'=>'000'];
$job=['prefix'=>$p,'operation'=>'create','actor'=>21,'profile'=>$profile,'key'=>staffKey()];
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$a=staffSpawn($job);$b=staffSpawn($job);staffWaitFor($observer,$blocker,2);$db->trans_commit();$ra=staffCollect($a);$rb=staffCollect($b);staffCheck(isset($ra['id'])&&$ra===$rb,'same key contenders receive one original identity');$id=$ra['id'];staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'staff WHERE email=?',[$profile['email']])->row()->n===1,'same-key contention creates exactly one profile');staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_staff_commands WHERE staff_id=?',[$id])->row()->n===1,'same-key contention stores one receipt');
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$j1=$job;$j1['profile']['email']='independent1@example.invalid';$j1['key']=staffKey();$j2=$j1;$j2['profile']['email']='independent2@example.invalid';$j2['key']=staffKey();$a=staffSpawn($j1);$b=staffSpawn($j2);staffWaitFor($observer,$blocker,2);$db->trans_commit();$ra=staffCollect($a);$rb=staffCollect($b);staffCheck(isset($ra['id'],$rb['id'])&&$ra['id']!==$rb['id'],'independent operations serialize and both complete');
$current=$s->get(2,17,$id);$edit=['prefix'=>$p,'operation'=>'update','actor'=>17,'staff'=>$id,'revision'=>$current['revision'],'name'=>'Concurrent one','key'=>staffKey()];$edit2=$edit;$edit2['name']='Concurrent two';$edit2['key']=staffKey();
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$a=staffSpawn($edit);$b=staffSpawn($edit2);staffWaitFor($observer,$blocker,2);$db->trans_commit();$out=[staffCollect($a),staffCollect($b)];staffCheck(count(array_filter($out,static fn($x)=>isset($x['id'])))===1,'only one competing revision commits');staffCheck(count(array_filter($out,static fn($x)=>($x['error']??'')==='issuer_staff_stale'))===1,'stale revision denied after actual wait');
$revoked=$job;$revoked['profile']['email']='revoked-waiter@example.invalid';$revoked['key']=staffKey();$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$worker=staffSpawn($revoked);staffWaitFor($observer,$blocker,1);$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=2 AND staff_id=21 AND feature=\'staff\' AND capability=\'create\'');$db->trans_commit();staffCheck(staffCollect($worker)===['error'=>'issuer_staff_denied'],'authority revoked while waiting denies newly acquired lock');staffCheck(!$db->query('SELECT staffid FROM '.$p.'staff WHERE email=?',[$revoked['profile']['email']])->result_array(),'revoked waiter creates no profile');$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(2,21,\'staff\',\'create\')');
// Native identity deletion is allowed; a queued company link never resurrects its receipt target.
$delete=['prefix'=>$p,'operation'=>'link','email'=>'staff22@example.invalid','key'=>staffKey()];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$worker=staffSpawn($delete);staffWaitFor($observer,$blocker,1);$db->query('DELETE FROM '.$p.'staff WHERE staffid=22');$db->trans_commit();staffCheck(staffCollect($worker)===['error'=>'issuer_staff_denied'],'queued link denies identity deleted before writer lock');staffCheck(!$db->query('SELECT issuer_id FROM '.$p.'issuers_staff WHERE issuer_id=2 AND staff_id=22')->result_array(),'deleted identity has no new membership');
$killed=$job;$killed['profile']['email']='killed-worker@example.invalid';$killed['key']=staffKey();$killed['pause']=true;$worker=staffSpawn($killed);[$process,$pipes]=$worker;$line='';$deadline=microtime(true)+12;while(!str_contains($line,"inserted\n")&&microtime(true)<$deadline){$line.=stream_get_contents($pipes[1]);usleep(20000);}staffCheck(str_contains($line,"inserted\n"),'worker paused after real profile insertion inside transaction');
$probe=$observer->prepare('SELECT COUNT(*) FROM '.$p.'staff WHERE email=?');$probe->execute([$killed['profile']['email']]);staffCheck((int)$probe->fetchColumn()===0,'uncommitted profile not visible to another connection');staffCheck(proc_terminate($process,9),'SIGKILL sent only to synthetic worker');foreach($pipes as$pipe){fclose($pipe);}proc_close($process);
unset($killed['pause']);$retry=staffCollect(staffSpawn($killed));staffCheck(isset($retry['id']),'retry after killed worker succeeds');staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'staff WHERE email=?',[$killed['profile']['email']])->row()->n===1,'kill/retry leaves exactly one committed identity');staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_staff_commands WHERE request_key=?',[$killed['key']])->row()->n===1,'kill/retry leaves one durable receipt');
staffCheck((int)$db->query('SELECT master_staff_id FROM '.$p.'issuers_installation_identity WHERE id=1')->row()->master_staff_id===17,'concurrency and crash preserve recognized master');
echo 'Staff concurrency: '.$GLOBALS['staffChecks'].' checks, five observed lock races and one SIGKILL/retry passed; '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
