<?php
/** Real native lock: card source/worker/fee races, only simulated financial provider. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReceivedReceipt.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyFeeExpense.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function asaasCardRaceConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=asaasCardRaceConnection($p);$GLOBALS['essentialPrefix']=$p;
 try{if($in['mode']==='fee')echo (new \Asaas\CompanyFeeExpense($db,$p))->post($in['issuer'],7,$in['origin'],$in['proof'],$in['category']);else echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($in['issuer'],7,new \Asaas\CompanyReceivedReceipt($in['origin'],$in['proof'],$in['event']?:null,$in['event']?$in['lease']:null));}
 catch(DomainException $error){if(!in_array($error->getMessage(),['asaas_company_access_denied','asaas_company_receipt_requires_review','asaas_company_adverse_history'],true))throw $error;echo 'denied';}exit(0);
}
require __DIR__.'/asaas_card_settlement.php';$cardRaceStart=$GLOBALS['essentialChecks'];$observer=asaasCardRaceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['receipt','worker-retry','fee','rotation','revocation','adverse'] as $mode){
 [$raceInvoice,$raceOrigin,$raceProof,$raceRemote]=asaasFreshHostedSettlement($b,'native-race-'.$mode);$event=0;$lease=str_repeat('f',64);
 if($mode==='fee')$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($raceOrigin,$raceProof));
 if($mode==='worker-retry'){
  $db->query("INSERT INTO {$p}asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,'PAYMENT_RECEIVED',?,?,'observed',?)",[$accounts[$b],'evt_card_native_worker',$raceRemote,hash('sha256','synthetic-card-race'),gmdate('Y-m-d H:i:s')]);$event=(int)$db->insert_id();
  $db->query("UPDATE {$p}asaas_company_accounts SET automation_actor=7 WHERE id=?",[$accounts[$b]]);
  $db->query("INSERT INTO {$p}asaas_company_jobs(event_id,issuer_id,origin_id,actor_id,state,attempts,lease_token,lease_until,next_attempt_at,last_code,created_at,updated_at) VALUES(?,?,?,7,'processing',1,?,?,?,'synthetic_card_race',?,?)",[$event,$b,$raceOrigin,$lease,gmdate('Y-m-d H:i:s',time()+300),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
 }
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['mode'=>$mode==='fee'?'fee':'receipt','issuer'=>$b,'origin'=>$raceOrigin,'proof'=>$raceProof,'category'=>$cardCategories[$b],'event'=>$mode==='worker-retry'&&$i===0?$event:0,'lease'=>$lease],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two card writers wait on observed native lock: '.$mode);
  if($mode==='rotation')$db->query("UPDATE {$p}asaas_company_accounts SET revision=? WHERE id=?",[bin2hex(random_bytes(32)),$accounts[$b]]);
  if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
  if($mode==='adverse')$db->query("INSERT INTO {$p}asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,'PAYMENT_CHARGEBACK_REQUESTED',?,?,'review_required',?)",[$accounts[$b],'evt_card_race_adverse',$raceRemote,hash('sha256','synthetic-adverse'),gmdate('Y-m-d H:i:s')]);
  $db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','native card race completes without diagnostics: '.$mode);}
  $denied=in_array($mode,['rotation','revocation','adverse'],true);essentialCheck($denied?$out===['denied','denied']:($out[0]===$out[1]&&ctype_digit($out[0])),'card contenders converge or are both denied: '.$mode);
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$raceInvoice])->row()->n===($denied?0:1),'card contention leaves one nominal receipt or none: '.$mode);
  if($mode==='fee')essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_fee_expenses WHERE origin_id=?",[$raceOrigin])->row()->n===1,'concurrent fee posting links exactly one native expense');
  $db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
}
echo 'Company Asaas151 card settlement contention: '.($GLOBALS['essentialChecks']-$cardRaceStart).' new / '.$GLOBALS['essentialChecks'].' cumulative actual DB checks. Provider simulated.'.PHP_EOL;
