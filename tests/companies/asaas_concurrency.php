<?php
/** Two independent provider/manual writers and revocation under an observed InnoDB lock. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReceivedReceipt.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function asaasRaceConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=asaasRaceConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{$writer=new \app\services\InvoicePaymentWriter($db);if(($argv[3]??'provider')==='manual')echo $writer->recordCompanyManual($input['issuer'],7,$input['invoice'],['amount'=>'105.53','date'=>'2026-10-19','reference'=>'synthetic-manual-conflict'],hash('sha256','asaas-race-manual-'.$input['invoice']));else echo $writer->recordCompanyProviderReceipt($input['issuer'],7,new \Asaas\CompanyReceivedReceipt($input['origin'],$input['proof']));}
    catch(DomainException $e){if(!in_array($e->getMessage(),['asaas_company_access_denied','company_payment_access_denied','company_payment_provider_review_required','company_payment_invoice_not_eligible'],true))throw $e;echo 'denied';}exit(0);
}
require __DIR__.'/asaas_positive.php';$observer=asaasRaceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['retry','mixed','revocation'] as $mode){
    [$raceInvoice,$raceOrigin,$raceProof]=asaasFreshCredit($b,$customerB,'cus_b','race-'.$mode);
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p,$mode==='mixed'&&$i===1?'manual':'provider'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'invoice'=>$raceInvoice,'origin'=>$raceOrigin,'proof'=>$raceProof],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'two Asaas/manual receipt writers observed waiting on actual native row lock');if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$out=[];
        foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','Asaas contention worker completes without diagnostic output');}
        if($mode==='retry')essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'two confirmations use one provider and native receipt');
        if($mode==='mixed')essentialCheck(ctype_digit($out[0])&&$out[1]==='denied','manual writer cannot bypass a reserved Asaas origin');
        if($mode==='revocation')essentialCheck($out===['denied','denied'],'revocation while provider writers await denies both without payment');
        essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$raceInvoice])->row()->n===($mode==='revocation'?0:1),'actual contention leaves no duplicate or orphan payment');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Company Asaas concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks. API simulated.'.PHP_EOL;
