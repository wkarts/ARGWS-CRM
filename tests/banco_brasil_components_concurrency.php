<?php
/** Two real MariaDB processes confirm component observations under the canonical installation lock. */
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReviewedComponentsReceipt.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function componentsRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=componentsRaceDb($p);$GLOBALS['essentialPrefix']=$p;
    try{echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($input['issuer'],$input['actor'],new \BancoBrasil\ReviewedComponentsReceipt($input['event'],$input['proof']));}
    catch(DomainException $error){if(in_array($error->getMessage(),['bb_reconciliation_access_denied','company_payment_access_denied','company_bank_components_exceeds_balance','company_bank_components_balance_invalid'],true))echo 'denied';else throw $error;}exit(0);
}
require __DIR__.'/banco_brasil_components_mysql.php';
$observer=componentsRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function freshComponentsRaceOrigin(string $identity):int
{
    global $s,$a,$input,$bank,$connection,$binding,$title,$file,$origin,$db,$p;
    $invoice=$s->create($a,7,$input,hash('sha256','components-race-invoice-'.$identity));$batch=$bank->generate($a,7,$connection,$binding,'2026-10-19',[array_replace($title,['invoice_id'=>$invoice])],hash('sha256','components-race-batch-'.$identity));$file=$bank->download($a,7,$batch);$bank->reportTransmission($a,7,$batch,'SYNTHETIC COMPONENTS RACE '.$identity);$origin=$db->query("SELECT * FROM {$p}bb_origins WHERE invoice_id=?",[$invoice])->row_array();return $invoice;
}
function componentsRace(array $events,?callable $beforeRelease=null):array
{
    global $db,$observer,$p,$blocker,$a,$proof;
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        foreach($events as $event){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$a,'actor'=>15,'event'=>$event,'proof'=>$proof],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'both component workers actually wait on the native installation lock');if($beforeRelease)$beforeRelease();$db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){$outputs[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','component worker completes without diagnostic leakage');}return $outputs;
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$invoice=freshComponentsRaceOrigin('retry');$event=importComponentsCredit('100.53','5.00','10.00');$reconcile->link($a,7,$event);$out=componentsRace([$event,$event]);
essentialCheck(ctype_digit($out[0])&&$out[0]===$out[1],'two component confirmations share one canonical cash receipt and captured balance');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$invoice])->row()->n===1,'same-event race leaves one native and one component receipt');
essentialCheck($s->document($a,7,$invoice)->data()['status']===2,'95.53cash plus10discount closes105.53 exactly once');
$invoice=freshComponentsRaceOrigin('overbalance');$eA=importComponentsCredit('75.00','5.00','10.00');$eB=importComponentsCredit('65.00','5.00','10.00');$reconcile->link($a,7,$eA);$reconcile->link($a,7,$eB);$out=componentsRace([$eA,$eB]);$success=array_values(array_filter($out,'ctype_digit'));essentialCheck(count($success)===1&&count(array_filter($out,fn($v)=>$v==='denied'))===1,'distinct events exceeding shared principal balance cannot both commit');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$invoice])->row()->n===1,'overbalance loser leaves no cash, component or source receipt artifact');
$ledger=$db->query("SELECT principal_extinguished FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$invoice])->row_array();essentialCheck(in_array($ledger['principal_extinguished'],['70.00','80.00'],true)&&$s->document($a,7,$invoice)->data()['status']===3,'winner preserves exact open remainder and separates reductions from cash');
$invoice=freshComponentsRaceOrigin('revoked-capability');$event=importComponentsCredit('100.53','5.00','10.00');$reconcile->link($a,7,$event);
$out=componentsRace([$event,$event],fn()=>$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=15 AND feature='banco_brasil' AND capability='reconcile_components'",[$a]));essentialCheck($out===['denied','denied'],'new capability revocation while both confirmations wait prevents both component applications');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===0&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$invoice])->row()->n===0,'revocation race leaves no financial side effects');
echo 'BB component concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; synthetic independent evidence, no bank API or homologation.'.PHP_EOL;
