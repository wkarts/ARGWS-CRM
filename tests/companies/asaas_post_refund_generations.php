<?php
/** Real cancel/recreate/receive/refund/local-action composition. Provider I/O is simulated. */
if(($argv[1]??'')==='worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
 $GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$GLOBALS['CI']=(object)['db'=>$db];
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
 require_once APPPATH.'services/CompanyPostRefundCancellation.php';require_once APPPATH.'services/CompanyPostRefundManual.php';
 $v=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 try{if($v['operation']==='cancel'){(new CompanyPostRefundCancellation($db,$p))->cancel(...$v['arguments']);echo 'confirmed';}
 elseif($v['operation']==='manual')echo (new CompanyPostRefundManual($db,$p))->record(...$v['arguments']);else exit(2);}
 catch(DomainException $error){if(in_array($error->getMessage(),['asaas_company_access_denied','company_invoice_access_denied','company_payment_access_denied'],true))echo 'denied';else throw $error;}exit(0);
}
function postRefundGenerationRace(string $operation,array $arguments,bool $revoke=false):array
{
 global $db,$p;
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(compact('operation','arguments'),JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+20;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  if($waiting<2){$db->trans_rollback();$diagnostics=[];foreach($workers as [$proc,$pipes]){$diagnostics[]=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);}essentialCheck(false,'both post-refund successor contenders wait on actual InnoDB lock: '.$operation.' '.implode(' | ',$diagnostics));}
  essentialCheck($waiting>=2,'both post-refund successor contenders wait on actual InnoDB lock: '.$operation);
  if($revoke)$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','post-refund worker exits without diagnostics: '.$error);}
  return $results;
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();if($revoke)$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
}
require __DIR__.'/asaas_origin_generations.php';
// Parent assertions have consumed their SQL diagnostics. Keep database state and
// all checks, but release the accumulated trace before the next full cycles.
$db->queries=[];
if($db->driver()!=='mysql')return;
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/ConfirmedFullRefund.php';
require_once APPPATH.'services/CompanyAsaasRefundSchema.php';
require_once APPPATH.'services/CompanyPostRefundSchema.php';
require_once APPPATH.'services/CompanyPostRefundManualSchema.php';
require_once APPPATH.'services/CompanyPostRefundCancellation.php';
require_once APPPATH.'services/CompanyPostRefundManual.php';
\Asaas\CompanyRefundSchema::ensure($db,$p);
CompanyAsaasRefundSchema::ensure($db,$p);
CompanyPostRefundSchema::ensure($db,$p);
CompanyPostRefundManualSchema::ensure($db,$p);
$db->query("UPDATE {$p}modules SET installed_version='1.6.7' WHERE module_name='asaas'");
$refunds=new \Asaas\CompanyRefunds($db,$p);
$refunds->setGrant($b,7,$accounts[$b],7,str_repeat('0',64),true);
$refundCalls=0;
$refundTransport=function($env,$method,$path,$payload,$secret)use($mock,&$responses,&$refundCalls){
 if($method==='POST'&&str_ends_with($path,'/refund')){
  $refundCalls++;$remote=substr($path,9,-7);
  $responses[$remote]['status']='REFUNDED';
  $responses[$remote]['refunds']=[['dateCreated'=>gmdate('Y-m-d').' 12:00:00','status'=>'DONE','value'=>$payload['value'],'description'=>$payload['description'],'endToEndIdentifier'=>null,'transactionReceiptUrl'=>null,'refundedSplits'=>[]]];
  return $responses[$remote];
 }
 return $mock($env,$method,$path,$payload,$secret);
};
$start=$GLOBALS['essentialChecks'];$postCases=[];$providerToday=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
foreach(['cancel','manual'] as $mode){
 [$invoice,$predecessor]=asaasUnpaid($b,$customerB,'cus_b','post-refund-generation-'.$mode);
 $deleteMode='normal';$cancel->cancel($b,7,$invoice,$invoiceService->revision($b,7,$invoice),$dec,$removeTransport);
 $coordinator->reopen($b,7,$invoice,$invoiceService->revision($b,7,$invoice),$dec,$mock);
 if($mode==='cancel'){
  $cancel->cancel($b,7,$invoice,$invoiceService->revision($b,7,$invoice),$dec,$removeTransport);
  $coordinator->reopen($b,7,$invoice,$invoiceService->revision($b,7,$invoice),$dec,$mock);
 }
 $current=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE invoice_id=? AND active_slot=1",[$invoice])->row_array();
 $remote=$current['remote_id'];$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']=$providerToday;$responses[$remote]['creditDate']=$providerToday;
 $proof=$asaas->query($b,7,(int)$current['id'],$dec,$mock);
 $paid=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt((int)$current['id'],$proof));
 $key=hash('sha256','post-refund-generation-'.$invoice);
 $refunds->prepare($b,7,(int)$current['id'],'105.53','Synthetic independent full return after recreation',$key,$dec,$refundTransport);
 $refunds->dispatch($b,7,$key,$dec,$refundTransport);$observation=$refunds->refresh($b,7,$key,$dec,$refundTransport);
 $writer->recordCompanyAsaasFullRefund($b,7,new \Asaas\ConfirmedFullRefund($db,$p,$key,$observation['observation_id'],['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'105.53','date'=>gmdate('Y-m-d'),'reference'=>'Independent return '.$invoice,'reason'=>'Independent synthetic full return after recreation','independent_evidence_hash'=>hash('sha256','return-evidence-'.$invoice)]));
 $postCases[$mode]=compact('invoice','predecessor','current','paid','key');
 essentialCheck($invoiceService->position($b,7,$invoice)['received']==='0.00','full refund preserves predecessor and reverses only active receipt');
}
$historical=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE active_slot IS NULL ORDER BY id")->result_array();$remoteBefore=$refundCalls;
$cancellation=new CompanyPostRefundCancellation($db,$p);$manual=new CompanyPostRefundManual($db,$p);
$f=$postCases['cancel'];$page=$cancellation->review($b,7,$f['invoice'],$dec,$refundTransport);
$arguments=[$b,7,$f['invoice'],$page['refund_request'],$page['observation_id'],$page['revision'],hash('sha256','cancel-after-refund-'.$f['invoice']),'Explicit local cancellation after independently declared return',true];
$command=fn()=>$cancellation->cancel(...$arguments);
$db->failOn='INSERT INTO '.$p.'company_post_refund_cancellations';
try{essentialThrows($command,'issuer_storage_failed','post-refund cancellation audit failure rolls back status');}finally{$db->failOn=null;}
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$f['invoice']])->row()->status!==5,'failed post-refund cancellation leaves invoice open');
essentialCheck(postRefundGenerationRace('cancel',$arguments)===['confirmed','confirmed'],'concurrent successor cancellations replay one local command');$command();
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$f['invoice']])->row()->status===5&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_post_refund_cancellations WHERE invoice_id=?",[$f['invoice']])->row()->n===1,'successor post-refund cancellation is atomic and idempotent');
$f=$postCases['manual'];$page=$manual->review($b,7,$f['invoice'],$dec,$refundTransport);
$arguments=[$b,7,$f['invoice'],$page['refund_request'],$page['observation_id'],$page['revision'],hash('sha256','manual-after-refund-'.$f['invoice']),['amount'=>'105.53','date'=>gmdate('Y-m-d'),'reference'=>'Independent NEW funds '.$f['invoice'],'note'=>'Synthetic independent funds'],hash('sha256','new-funds-evidence-'.$f['invoice']),'Explicit new independent manual funds after full return',true];
$command=fn()=>$manual->record(...$arguments);
$db->query("UPDATE {$p}asaas_company_recreations SET state='sending' WHERE origin_id=?",[$f['predecessor']]);
try{essentialThrows($command,'company_post_refund_adverse_history','unconfirmed chain cannot authorize a new manual receipt');}finally{$db->query("UPDATE {$p}asaas_company_recreations SET state='reopened' WHERE origin_id=?",[$f['predecessor']]);}
$link=$db->query("SELECT * FROM {$p}asaas_company_recreations WHERE origin_id=?",[$f['predecessor']])->row_array();
$proofHash=$db->query("SELECT evidence_hash FROM {$p}asaas_company_proofs WHERE id=?",[$link['proof_id']])->row()->evidence_hash;
$db->query("UPDATE {$p}asaas_company_proofs SET evidence_hash=? WHERE id=?",[str_repeat('0',64),$link['proof_id']]);
try{essentialThrows($command,'company_post_refund_adverse_history','corrupt predecessor deletion proof cannot authorize manual funds');}finally{$db->query("UPDATE {$p}asaas_company_proofs SET evidence_hash=? WHERE id=?",[$proofHash,$link['proof_id']]);}
$db->query("UPDATE {$p}asaas_company_recreations SET successor_origin_id=NULL WHERE origin_id=?",[$f['predecessor']]);
try{essentialThrows($command,'company_post_refund_adverse_history','unlinked retired origin cannot be ignored by selecting active only');}finally{$db->query("UPDATE {$p}asaas_company_recreations SET successor_origin_id=? WHERE origin_id=?",[$link['successor_origin_id'],$f['predecessor']]);}
$db->query("UPDATE {$p}asaas_company_recreations SET issuer_id=? WHERE origin_id=?",[$a,$f['predecessor']]);
try{essentialThrows($command,'company_post_refund_adverse_history','foreign company chain link cannot authorize local receipt');}finally{$db->query("UPDATE {$p}asaas_company_recreations SET issuer_id=? WHERE origin_id=?",[$b,$f['predecessor']]);}
$db->failOn='INSERT INTO '.$p.'company_post_refund_manual_receipts';
try{essentialThrows($command,'issuer_storage_failed','post-refund manual ledger failure rolls back new payment');}finally{$db->failOn=null;}
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$f['invoice']])->row()->n===1,'failed manual action preserves only original provider receipt');
essentialCheck(postRefundGenerationRace('manual',$arguments,true)===['denied','denied'],'revoked operator cannot post from either waiting process');
$raceResults=postRefundGenerationRace('manual',$arguments);essentialCheck($raceResults[0]===$raceResults[1]&&ctype_digit($raceResults[0]),'concurrent successor receipts return the same payment');
$newPayment=(int)$raceResults[0];essentialCheck($newPayment!==$f['paid']&&$command()===$newPayment,'manual successor receipt is new and idempotent');
essentialCheck($invoiceService->position($b,7,$f['invoice'])['received']==='105.53','new manual funds restore the balance without restoring provider funds');
essentialCheck($db->query("SELECT * FROM {$p}asaas_payment_origins WHERE active_slot IS NULL ORDER BY id")->result_array()===$historical,'all retired origin bytes remain unchanged across both local actions');
essentialCheck($refundCalls===$remoteBefore,'local post-refund commands never send another refund');
echo 'Post-refund successor composition: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks. Provider simulated.'.PHP_EOL;
