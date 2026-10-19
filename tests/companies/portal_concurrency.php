<?php
/** Public recovery/reset with two independent MySQL connections, no external transport. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyPortal.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);
    try{
        $portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));
        if($input['operation']==='request'){$portal->requestRecovery($input['issuer'],'new@example.invalid','https://crm.example.invalid',fn($v)=>'decrypted:'.$v);echo 'requested';}
        else{echo $portal->reset($input['issuer'],$input['token'],'concurrent-new-password-a',fn($v)=>password_hash($v,PASSWORD_DEFAULT))?'1':'0';}
    }catch(DomainException $error){if($error->getMessage()==='company_client_access_denied'){echo 'denied';}else{throw $error;}}
    exit(0);
}
require __DIR__.'/portal_positive.php';
$observer=raceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function race(array $input,?callable $beforeRelease=null): array
{
    global $db,$observer,$p,$blocker;
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){
            $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process)){throw new RuntimeException('worker unavailable');}
            fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+15;$waiters=0;
        do{
            $waiters=(int)$observer->query('SELECT COUNT(DISTINCT w.REQUESTING_THREAD_ID) n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE b.PROCESSLIST_ID=?',[$blocker])->row()->n;
            if($waiters>=2){break;}usleep(20000);
        }while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'both real connections observed waiting on installation row lock');
        if($beforeRelease){$beforeRelease();}
        $db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){
            $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            essentialCheck(proc_close($process)===0&&$err==='','worker completes without diagnostic leakage');$outputs[]=$out;
        }
        return $outputs;
    }finally{if($db->pdo->inTransaction()){$db->trans_rollback();}}
}
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n;
essentialCheck(race(['operation'=>'request','issuer'=>$a])===['requested','requested'],'concurrent recovery requests complete without ambiguous success');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$before+1,'only one recovery message and token after concurrent request');
$last=$db->query("SELECT message FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row()->message;preg_match('/#token=([a-f0-9]{64})/',$last,$match);
$out=race(['operation'=>'reset','issuer'=>$a,'token'=>$match[1]]);sort($out);
essentialCheck($out===['0','1'],'exactly one concurrent token completion changes password');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_portal_tokens WHERE issuer_id=?",[$a])->row()->n===0,'token consumed after successful reset commit');
$portal->requestRecovery($a,'new@example.invalid','https://crm.example.invalid',$decrypt);
$last=$db->query("SELECT message FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row()->message;preg_match('/#token=([a-f0-9]{64})/',$last,$match);
essentialCheck(race(['operation'=>'reset','issuer'=>$a,'token'=>$match[1]],fn()=>$db->query("UPDATE {$p}contacts SET active=0 WHERE id=?",[$ca]))===['0','0'],'contact revocation while reset writers wait blocks both');
echo 'Portal MySQL concurrency: '.$GLOBALS['essentialChecks'].' checks passed with observed row locks.'.PHP_EOL;
