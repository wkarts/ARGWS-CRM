<?php
/** Actual independent workers contend for leased reconciliation and canonical fee/expense posting. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReconciliation.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyFeeExpense.php';
require_once __DIR__.'/asaas_job_transport_fixture.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function asaasJobConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=asaasJobConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{
        if(($argv[3]??'fee')==='fee')echo (new \Asaas\CompanyFeeExpense($db,$p))->post($in['issuer'],7,$in['origin'],$in['proof'],$in['category']);
        else{
            $mock=asaasJobTransportFixture($db,$p);
            echo json_encode((new \Asaas\CompanyReconciliation($db,$p))->runOnce(fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$mock,1),JSON_THROW_ON_ERROR);
        }
    }catch(DomainException $e){if(!in_array($e->getMessage(),['company_expense_denied','asaas_company_access_denied'],true))throw $e;echo 'denied';}exit(0);
}
require __DIR__.'/asaas_jobs_fees.php';$observer=asaasJobConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$config=$asaas->accounts($b,7)[0];$asaas->configureAutomation($b,7,$accounts[$b],$config['revision'],true);
foreach(['async','fee','fee-revocation'] as $mode){
    [$raceInvoice,$raceOrigin,$raceJob]=asaasAsyncEvent($b,$customerB,'cus_b','real-race-'.$mode);
    if($mode!=='async')$worker->runOnce($dec,$mock);$raceProof=(int)$db->query("SELECT MAX(id) id FROM {$p}asaas_company_proofs WHERE origin_id=?",[$raceOrigin])->row()->id;
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p,$mode==='async'?'async':'fee'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'origin'=>$raceOrigin,'proof'=>$raceProof,'category'=>$categoryB],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two asynchronous/fee workers observed waiting on actual native transaction lock');
        if($mode==='fee-revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$out=[];
        foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','independent job/fee worker completes without diagnostic output');}
        if($mode==='async'){$results=array_map(fn($v)=>json_decode($v,true,16,JSON_THROW_ON_ERROR),$out);essentialCheck(array_sum(array_column($results,'completed'))===1,'one leased job is reconciled once under two actual workers');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$raceInvoice])->row()->n===1,'two async workers leave one canonical native receipt');}
        elseif($mode==='fee'){essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'two tariff confirmations share one native expense');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_fee_expenses WHERE origin_id=?",[$raceOrigin])->row()->n===1,'one durable fee link after actual contention');}
        else{essentialCheck($out===['denied','denied'],'revocation under lock denies both expense writers');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_fee_expenses WHERE origin_id=?",[$raceOrigin])->row()->n===0,'revoked fee writers leave no partial link or expense');}
        $db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Company Asaas leased job/native expense concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; provider simulated.'.PHP_EOL;
