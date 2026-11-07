<?php
/** Two real MySQL writers contend for the same monitored receipt; remote fixture is synthetic. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReceivedReceipt.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function batchRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
 $db=batchRaceDb($p);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 try{echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($in['issuer'],7,\Asaas\CompanyReceivedReceipt::forMonitor($in['origin'],$in['proof']));}
 catch(DomainException $e){if($e->getMessage()!=='asaas_company_access_denied'&&$e->getMessage()!=='company_payment_access_denied')throw $e;echo 'denied';}exit;
}
require __DIR__.'/asaas_batch_reconciliation.php';
$start=$GLOBALS['essentialChecks'];$observer=batchRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['retry','revocation'] as $mode){
 $invoiceRace=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','monitor-race-'.$mode));
 $originRace=asaasFixtureIssue($asaas,$b,7,$invoiceRace,$accounts[$b],'cus_b','PIX',$s->revision($b,7,$invoiceRace),hash('sha256','monitor-race-origin-'.$mode),$dec,$mock);
 $remoteRace=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$originRace])->row()->remote_id;
 $responses[$remoteRace]['status']='RECEIVED';$responses[$remoteRace]['paymentDate']=$input['date'];$responses[$remoteRace]['creditDate']=$input['date'];
 $proofRace=$asaas->query($b,7,$originRace,$dec,$mock);$workers=[];$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'origin'=>$originRace,'proof'=>$proofRace]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'both monitored receipt writers wait on observed native mutex');
  if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
  $db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','monitored contenders terminate without diagnostics');}
  essentialCheck($mode==='retry'?($out[0]===$out[1]&&ctype_digit($out[0])&&(int)$out[0]>0):$out===['denied','denied'],'retry shares canonical payment; revocation blocks both');
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoiceRace])->row()->n===($mode==='retry'?1:0),'race creates one receipt or none after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
}
echo 'Monitored receipt race: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; two actual processes, no remote writes.'.PHP_EOL;
