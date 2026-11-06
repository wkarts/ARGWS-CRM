<?php
/** Real MySQL lock and two PHP processes. Provider responses are synthetic; no external traffic. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCardPayments.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function directCardDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
 $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$db=directCardDb($p);$GLOBALS['essentialPrefix']=$p;
 $transport=static function($env,$method,$path,$payload,$secret)use($in,$db){
  if($db->pdo->inTransaction())throw new LogicException('HTTP under transaction');
  if($method==='POST'){file_put_contents($in['log'],"dispatch\n",FILE_APPEND|LOCK_EX);return ['id'=>$in['remote']];}
  if($path==='wallets/')return ['data'=>[['id'=>'wallet-A']],'hasMore'=>false];
  if(str_starts_with($path,'customers/'))return $in['customer'];
  if($path==='payments/'.$in['remote'])return $in['response'];
  throw new LogicException('unexpected provider path');
 };
 try{$result=(new \Asaas\CompanyCardPayments($db,$p))->pay($in['issuer'],7,$in['origin'],'payWithCreditCard',['creditCardToken'=>'synthetic-race-token'],$in['key'],fn($s)=>base64_decode(substr($s,10),true),$transport);echo $result['state'];}
 catch(DomainException $e){if($e->getMessage()!=='asaas_company_access_denied')throw $e;echo 'denied';}exit;
}
require __DIR__.'/asaas_card_payments.php';
$observer=directCardDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['retry','revocation'] as $mode){
 $invoiceRace=$s->create($a,7,$input,hash('sha256','direct-race-'.$mode));
 $originRace=asaasFixtureHosted($asaas,$a,7,$invoiceRace,$accounts[$a],$registered[$a]['customer_id'],$s->revision($a,7,$invoiceRace),hash('sha256','direct-race-origin-'.$mode),$dec,$checkoutTransport);
 $remoteRace=$db->query("SELECT remote_id FROM {$p}asaas_payment_origins WHERE id=?",[$originRace])->row()->remote_id;
 $log=tempnam(sys_get_temp_dir(),'card-race-');$workers=[];
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$a,'origin'=>$originRace,'remote'=>$remoteRace,'key'=>hash('sha256','direct-race-command-'.$mode),'log'=>$log,'response'=>$responses[$remoteRace],'customer'=>$providerCustomers['sandbox-A'][$registered[$a]['customer_id']]],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'both direct card contenders wait on observed native mutex');
  if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
  $db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','direct payment contenders terminate without diagnostics');}
  essentialCheck(substr_count(file_get_contents($log),'dispatch')===($mode==='retry'?1:0),'concurrent retry sends exactly once; revocation sends nothing');
  essentialCheck($mode==='retry'?count(array_diff($out,['sending','awaiting_query','review_required']))===0:$out===['denied','denied'],'contenders expose only durable outcome or denial');
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoiceRace])->row()->n===0,'direct contention never invents receipt');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}unlink($log);$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
}
echo 'Direct card concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks; actual MySQL lock, synthetic provider.'.PHP_EOL;
