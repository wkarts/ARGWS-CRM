<?php
/** Two actual MySQL reconciliation writers; canonical receipt and revocation under row locks. */
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReviewedReceipt.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{
        echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($input['issuer'],7,new \BancoBrasil\ReviewedReceipt($input['event'],$input['evidence']));
    }catch(DomainException $error){if(in_array($error->getMessage(),['bb_reconciliation_access_denied','company_payment_access_denied'],true)){echo 'denied';}else{throw $error;}}
    exit(0);
}
require __DIR__.'/banco_brasil_reconciliation_mysql.php';
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
function freshBankEvent(string $invoiceKey,string $batchKey):array
{
    global $s,$a,$input,$bank,$connection,$binding,$title,$file,$origin,$db,$p,$reconcile;
    $invoice=$s->create($a,7,$input,$invoiceKey);$batch=$bank->generate($a,7,$connection,$binding,'2026-10-19',[array_replace($title,['invoice_id'=>$invoice])],$batchKey);
    $file=$bank->download($a,7,$batch);$bank->reportTransmission($a,7,$batch,'SYNTHETIC CONCURRENCY STATEMENT');
    $origin=$db->query("SELECT * FROM {$p}bb_origins WHERE invoice_id=?",[$invoice])->row_array();$event=importCredit('105.53');$reconcile->link($a,7,$event);return [$invoice,$event];
}
[$invoice,$event]=freshBankEvent(str_repeat('3',64),str_repeat('3',64));
$raceInput=['issuer'=>$a,'event'=>$event,'evidence'=>$evidence];$out=race($raceInput);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'two concurrent confirmations use one canonical payment');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===1,'exactly one native receipt after real concurrent writers');
[$revoked,$event]=freshBankEvent(str_repeat('4',64),str_repeat('4',64));$raceInput['event']=$event;
essentialCheck(race($raceInput,fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation while both confirmations wait prevents financial application');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$revoked])->row()->n===0,'no payment after revocation race');
echo 'BB assisted receipt concurrency: '.$GLOBALS['essentialChecks'].' actual MySQL checks; synthetic credits, no bank calls.'.PHP_EOL;
