<?php
/** Recreated439 API guard race: two real waiters and bearer revocation before commit. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyFinancialApiService.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function resumeApiRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=resumeApiRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{$r=(new CompanyFinancialApiService($db,$p))->recordManual($data['secret'],$data['invoice'],$data['payment'],$data['key']);echo$r['payment_id'];}catch(DomainException $e){if($e->getMessage()!=='company_api_unauthenticated')throw$e;echo'denied';}exit(0);
}
require __DIR__.'/finance_resume_439_api.php';$observer=resumeApiRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same','revoked'] as $case){
 $token=$tokens->issue($a,7,'Financial API concurrent '.$case,['payments:create'],1,$resumeKey('race-token-'.$case));$target=$s->create($a,7,array_replace($input,['client_id'=>$ca,'items'=>[['description'=>'Concurrent financial API','qty'=>'1.00','rate'=>'100.00']]]),$resumeKey('race-invoice-'.$case));$payment=['amount'=>'15.00','date'=>'2026-10-20','reference'=>'resume439-api-race-'.$case,'note'=>'Synthetic'];$key=$resumeKey('race-payment-'.$case);
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('API worker unavailable');fwrite($pipes[0],json_encode(['secret'=>$token['secret'],'invoice'=>$target,'payment'=>$payment,'key'=>$key],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiting>=2,'both financial API writers observed waiting on actual InnoDB installation row');
  if($case==='revoked')$db->query('UPDATE '.$p.'company_api_tokens SET active=0 WHERE token_id=?',[$token['token_id']]);$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','financial API writer exits without bearer or diagnostic leakage');}
  if($case==='same')essentialCheck($results[0]===$results[1]&&ctype_digit($results[0]),'identical API requests share one canonical native receipt');else essentialCheck($results===['denied','denied'],'bearer revoked during wait blocks both financial API writers');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$target])->row()->n===($case==='same'?1:0),'financial API race commits one payment or none after bearer revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Recreated439 financial API/InnoDB idempotency/token revocation races: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
