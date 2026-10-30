<?php
/** Recreated439 full-refund canonical race: real InnoDB waiters, retry and actor revocation. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/InvoicePaymentWriter.php';
if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');require_once FCPATH.'modules/asaas/libraries/Asaas/ConfirmedFullRefund.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function resumeFullRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=resumeFullRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{echo(new \app\services\InvoicePaymentWriter($db))->recordCompanyAsaasFullRefund($data['issuer'],7,new \Asaas\ConfirmedFullRefund($db,$p,$data['request'],$data['observation'],$data['declaration']));}catch(DomainException $e){if(!in_array($e->getMessage(),['asaas_company_access_denied','company_asaas_full_refund_access_denied','asaas_refund_access_denied'],true))throw$e;echo'denied';}exit(0);
}
require __DIR__.'/finance_resume_439_asaas.php';$observer=resumeFullRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same','revoked'] as $case){
 $fresh=$rfFresh($b,$customerB,'cus_b','resume-race-'.$case);$request=$refunds->prepare($b,7,$fresh['origin'],'105.53','Independent full refund race',$rfKey('resume-race-'.$case),$dec,$rfMock);$refunds->dispatch($b,7,$request['request_key'],$dec,$rfMock);$refundReplies[$fresh['remote']][0]['status']='DONE';$responses[$fresh['remote']]['refunds']=$refundReplies[$fresh['remote']];$responses[$fresh['remote']]['status']='REFUNDED';$queried=$refunds->refresh($b,7,$request['request_key'],$dec,$rfMock);
 $declaration=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'105.53','date'=>gmdate('Y-m-d'),'reference'=>'external-declared-race-'.$case,'reason'=>'Synthetic independent evidence only','independent_evidence_hash'=>hash('sha256','independent race evidence '.$case)];$paymentsBefore=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('refund worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$b,'request'=>$request['request_key'],'observation'=>$queried['observation_id'],'declaration'=>$declaration],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiting>=2,'both full-refund writers observed waiting on actual InnoDB installation row');
  if($case==='revoked')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','full-refund writer exits without diagnostic leakage');}
  if($case==='same')essentialCheck($results[0]===$results[1]&&preg_match('/^[a-f0-9]{64}$/D',$results[0])===1,'two identical full-refund declarations converge to one immutable receipt');else essentialCheck($results===['denied','denied'],'actor revocation blocks both waiting full refunds');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=?',[$fresh['invoice']])->row()->n===($case==='same'?1:0),'race records one full refund or none after revocation');
  essentialCheck($paymentsBefore===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array(),'both race outcomes preserve original native receipts');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
}
echo 'Recreated439 full declared refunds/InnoDB duplicate/revocation races: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
