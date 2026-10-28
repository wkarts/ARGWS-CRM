<?php
/** Real InnoDB module upgrade, exact invoice snapshot and recovery; no bank/provider calls. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_return_rejections_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400Preparation.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
require_once __DIR__.'/support/BbPdoDatabase.php';
$nativeDb=new BbPdoDatabase($db->pdo);$store=new \BancoBrasil\ModuleStore($nativeDb,$p);
$storedConnection=$store->connection($connection,$a);
$binding400=$store->registerLayout($storedConnection,7,\BancoBrasil\LayoutCatalog::CNAB400);
$invoice400=$s->create($a,7,$input,hash('sha256','cnab400-real-source'));
$prepare400=new \BancoBrasil\Cnab400Preparation($db,$p);$key400=hash('sha256','cnab400-real-request');
$beforeInvoice=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice400])->row_array();
$beforePayments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->result_array();
$beforeOrigins=$db->query('SELECT * FROM '.$p.'bb_origins WHERE invoice_id=?',[$invoice400])->result_array();
$db->lastQueryFailure=null;
try{$one=$prepare400->prepare($a,7,$connection,$binding400,$invoice400,$key400);}
catch(Throwable $error){if($db->lastQueryFailure)fwrite(STDERR,'Synthetic DB diagnostic '.json_encode($db->lastQueryFailure,JSON_THROW_ON_ERROR).PHP_EOL);throw $error;}

essentialCheck($prepare400->prepare($a,7,$connection,$binding400,$invoice400,$key400)===$one,'InnoDB retry returns the same immutable preparation');
essentialCheck($prepare400->recover($a,7,$one['id'])===$one,'InnoDB recovery rereads source and provenance');
essentialCheck(!$one['bank_number_reserved']&&!$one['remittance_generated']&&$one['financial_effect']==='not_applied','no unsupported CNAB400 bank or payment operation');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice400])->row_array()===$beforeInvoice&&$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->result_array()===$beforePayments&&$db->query('SELECT * FROM '.$p.'bb_origins WHERE invoice_id=?',[$invoice400])->result_array()===$beforeOrigins,'source preparation preserves invoice, payments and bank origins');
essentialThrows(fn()=>$prepare400->recover($b,7,$one['id']),'bb400_preparation_unavailable','other company cannot recover private draft');
essentialThrows(fn()=>$prepare400->prepare($a,7,$connection,$binding,$invoice400,$key400),'bb400_preparation_request_conflict','same idempotency key refuses changed binding');
$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',['2026-12-20',$invoice400]);
essentialCheck($prepare400->recover($a,7,$one['id'])['stale']===true,'source drift is detected without rewriting historical snapshot');
$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',[$beforeInvoice['duedate'],$invoice400]);
$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_cnab_preparations')->row()->n;
$db->failOn='INSERT INTO '.$p.'bb_audit';essentialThrows(fn()=>$prepare400->prepare($a,7,$connection,$binding400,$invoice400,hash('sha256','mysql-audit-fail')),'issuer_storage_failed','failed audit aborts the InnoDB source preparation');$db->failOn=null;
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_cnab_preparations')->row()->n===$count,'failed audit leaves no prepared source');
echo 'CNAB400 preparation InnoDB integration passed; no bank operation or homologation claimed.'.PHP_EOL;
$raceInvoice=$s->create($a,7,$input,hash('sha256','cnab400-preparation-race-invoice'));
$raceKey=hash('sha256','cnab400-preparation-race-key');
$raceInput=['issuer'=>$a,'actor'=>7,'connection'=>$connection,'binding'=>$binding400,'invoice'=>$raceInvoice,'key'=>$raceKey];
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
    for($n400=0;$n400<2;$n400++){
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/support/BbCnab400PreparationWorker.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('worker unavailable');
        fwrite($pipes[0],json_encode($raceInput,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
    essentialCheck($waiters>=2,'both preparation workers wait on the real installation lock');$db->trans_commit();$outputs=[];
    foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','preparation worker completes without diagnostic leakage');$outputs[]=$out;}
    essentialCheck(count($outputs)===2&&ctype_digit($outputs[0])&&$outputs[0]===$outputs[1],'concurrent same-key preparations return one ID');
    essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_cnab_preparations WHERE issuer_id=? AND request_key=?',[$a,$raceKey])->row()->n===1,'concurrent retry preserves one immutable source row');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}
echo 'CNAB400 preparation concurrency checked with two MySQL/MariaDB connections; no bank calls.'.PHP_EOL;
$contract400=$bank->declareContract($a,7,$connection,$binding400,$contract,\BancoBrasil\LayoutCatalog::CNAB400);
essentialCheck($contract400>0,'400 declaration uses the existing shared exclusive number namespace');
$namespace400=\BancoBrasil\Origination::bankNamespace(\BancoBrasil\ModuleStore::agreement($storedConnection));
$counterBefore400=$db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array();
$reserved400=$prepare400->reserve($a,7,$one['id'],'1');
essentialCheck($reserved400['bank_number_reserved']&&$reserved400['state']==='reserved_awaiting_layout'&&!$reserved400['remittance_generated'],'400 invoice origin reserved with no invented file');
essentialCheck($prepare400->reserve($a,7,$one['id'],'1')===$reserved400&&$prepare400->recover($a,7,$one['id'])===$reserved400,'400 number reserve and recovery are idempotent');
$counterAfter400=$db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array();
essentialCheck((int)$counterAfter400['next_number']===(int)$counterBefore400['next_number']+1&&(int)$counterAfter400['next_file']===(int)$counterBefore400['next_file']+1,'one 400 reservation advances the same shared 240/API namespace exactly once');
$origin400=$db->query('SELECT invoice_id,channel,state FROM '.$p.'bb_origins WHERE id=?',[$reserved400['origin_id']])->row_array();$batch400=$db->query('SELECT generation_state,content,financial_state FROM '.$p.'bb_batches WHERE id=?',[$reserved400['batch_id']])->row_array();
essentialCheck((int)$origin400['invoice_id']===$invoice400&&$origin400['channel']==='cnab400'&&$origin400['state']==='reserved_awaiting_layout'&&$batch400['generation_state']==='awaiting_layout'&&$batch400['content']===''&&$batch400['financial_state']==='not_applied','scoped invoice origin and empty batch remain safely blocked');
essentialThrows(fn()=>$bank->finish($a,7,$reserved400['batch_id']),'bb_batch_corrupt','existing 240 generator cannot reinterpret reserved 400 batch');
echo 'CNAB400 invoice-bound shared-number reservation tested on InnoDB; no bank submission or payment.'.PHP_EOL;

$counterBeforeDuplicate=$db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array();
essentialThrows(fn()=>$bank->generate($a,7,$connection,$binding,'2026-10-19',[array_replace($title,['invoice_id'=>$invoice400])],hash('sha256','duplicate-240-after-400')),'bb_invoice_already_bound','240 cannot issue the same invoice with reserved400 origin');
essentialCheck($db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array()===$counterBeforeDuplicate,'rejected cross-channel duplicate consumes no number');
$invoice240=$s->create($a,7,$input,hash('sha256','cnab400-after-240-invoice'));
$batch240=$bank->generate($a,7,$connection,$binding,'2026-10-19',[array_replace($title,['invoice_id'=>$invoice240])],hash('sha256','cnab400-after-240-batch'));
$draft240=$prepare400->prepare($a,7,$connection,$binding400,$invoice240,hash('sha256','cnab400-after-240-preparation'));
$counterAfter240=$db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array();
essentialThrows(fn()=>$prepare400->reserve($a,7,$draft240['id'],'1'),'bb_invoice_already_bound','400 cannot reserve another origin when 240 already has the invoice');
essentialCheck($db->query('SELECT next_number,next_file FROM '.$p.'bb_number_reservations WHERE bank_namespace=?',[$namespace400])->row_array()===$counterAfter240,'rejected 400 duplicate never consumes another number');
$payer400=array_intersect_key($title,array_flip(['payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance']));
$beforeCandidate400=$db->query('SELECT * FROM '.$p.'bb_batches WHERE id=?',[$reserved400['batch_id']])->row_array();
$candidate400=$prepare400->candidate($a,7,$one['id'],'2026-10-19',$payer400);
essentialCheck($candidate400['operational']===false&&$candidate400['financial_effect']==='not_applied'&&!isset($candidate400['content'])&&$candidate400['record_count']===3,'candidate uses the reserved invoice source but never exposes wire bytes');
essentialCheck($db->query('SELECT * FROM '.$p.'bb_batches WHERE id=?',[$reserved400['batch_id']])->row_array()===$beforeCandidate400,'candidate leaves the blocked batch unchanged');
