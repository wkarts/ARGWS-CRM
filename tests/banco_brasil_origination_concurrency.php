<?php
/** Two actual MySQL remittance writers; retries and revocation under observed row locks. */
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Origination.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{
        echo (new \BancoBrasil\Origination($db,$p))->generate($input['issuer'],7,$input['connection'],$input['binding'],'2026-10-19',[$input['title']],$input['key']);
    }catch(DomainException $error){if($error->getMessage()==='bb_origination_access_denied'){echo 'denied';}else{throw $error;}}
    exit(0);
}
require __DIR__.'/banco_brasil_origination_mysql.php';
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
            $waiters=essentialLockWaiters($observer,$blocker);
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
$newInvoice=$s->create($a,7,$input,str_repeat('7',64));
$raceTitle=array_replace($title,['invoice_id'=>$newInvoice]);
$raceInput=['issuer'=>$a,'connection'=>$connection,'binding'=>$binding,'title'=>$raceTitle,'key'=>str_repeat('7',64)];
$out=race($raceInput);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent remittance retry returns one batch and reserved number');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_origins WHERE invoice_id=?",[$newInvoice])->row()->n===1,'exactly one immutable origin after two real writers');
essentialCheck((int)$db->query("SELECT next_number FROM {$p}bb_number_reservations")->row()->next_number===104,'concurrent retry consumes only one additional number');
$revokedInvoice=$s->create($a,7,$input,str_repeat('6',64));$raceInput['title']['invoice_id']=$revokedInvoice;$raceInput['key']=str_repeat('6',64);
essentialCheck(race($raceInput,fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation while both writers wait prevents reservation');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_origins WHERE invoice_id=?",[$revokedInvoice])->row()->n===0,'revocation leaves no orphan bank number or charge');
echo 'BB origination concurrency: '.$GLOBALS['essentialChecks'].' checks passed; actual MySQL lock waits, no bank calls.'.PHP_EOL;
