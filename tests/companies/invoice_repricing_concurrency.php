<?php
/** Real SQL sessions contend on the same existing issuer mutex as the emitter. */
require_once dirname(__DIR__).'/issuers_core_support.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function repriceConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=repriceConnection($p);$GLOBALS['CI']=(object)['db'=>$db];
 require_once APPPATH.'services/CompanyInvoiceRepricing.php';require_once APPPATH.'services/CompanyInvoiceRecurring.php';
 $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 try{$result=$in['action']==='confirm'?(new CompanyInvoiceRepricing($db,$p))->confirm($in['issuer'],7,$in['key'],$in['revision'])['state']:(new CompanyInvoiceRecurring($db,$p))->run($in['issuer'],7,$in['schedule'],'2026-01-01');echo json_encode(['result'=>$result]);}
 catch(DomainException $e){if(!in_array($e->getMessage(),['invoice_reprice_schedule_busy_or_closed','invoice_reprice_stale'],true))throw$e;echo json_encode(['blocked'=>$e->getMessage()]);}exit(0);
}
require __DIR__.'/invoice_repricing.php';
$observer=repriceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same_confirmation','emission']as$scenario){
 $schedule=$ir->create($a,7,$base,$spec,hash('sha256','reprice-race-'.$scenario));$row=array_column($prices->directory($a,7),null,'id')[$schedule];$prices->reviewLink($a,7,$schedule,0,$x,$row['payload_hash'],CompanyInvoiceRepricing::ZERO);$row=array_column($prices->directory($a,7),null,'id')[$schedule];
 $in=['rules'=>[['item_id'=>$x,'rate'=>'50.00']],'targets'=>[['schedule_id'=>$schedule,'source_hash'=>$row['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'']],'additions'=>[]]],'timezone'=>'America/Sao_Paulo','execute_local'=>''];
 $proposal=$prices->preview($a,7,$in,hash('sha256','reprice-race-proposal-'.$scenario));$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($n=0;$n<2;$n++){$command=['action'=>$scenario==='emission'&&$n===1?'emit':'confirm','issuer'=>$a,'schedule'=>$schedule,'key'=>$proposal['request_key'],'revision'=>$proposal['revision']];$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode($command));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+20;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both real SQL sessions wait on canonical financial lock: '.$scenario);$db->trans_commit();$results=[];
  foreach($workers as[$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$stderr==='','race worker completes with explicit receipt '.$stderr);$results[]=json_decode($stdout,true,32,JSON_THROW_ON_ERROR);}
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
 $journal=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices WHERE schedule_id=?',[$schedule])->result_array();essentialCheck(count($journal)<=1,'no duplicate price revision after concurrent commands');
 if($scenario==='same_confirmation')essentialCheck(count($journal)===1&&$results[0]['result']==='applied'&&$results[1]['result']==='applied','both confirmation retries share one applied result');
 else{$document=$ir->document($a,7,$schedule);essentialCheck(count($document['runs'])===1&&$document['completed_cycles']===1,'concurrent emission advances exactly one canonical occurrence');$invoiceId=$document['runs'][0]['canonical_invoice_id'];$amount=CompanyInvoiceAmounts::minor((string)$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$invoiceId])->row()->total);essentialCheck(in_array($amount,[2020,10000],true),'emission uses complete old or new price, never mixed quantities');if($journal)essentialCheck(($journal[0]['effective_date']==='2026-01-01'&&$amount===10000)||($journal[0]['effective_date']==='2026-02-01'&&$amount===2020),'effective date matches lock ordering and preserves issued amount');}
}
echo 'Repricing real MySQL two-process confirmation/emission races passed; no provider calls.'.PHP_EOL;
