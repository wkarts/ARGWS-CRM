<?php
/** Actual immutable group identity + refund outbox/writer, disposable MySQL, simulated provider. */
require __DIR__.'/asaas_installment_receipts.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/ConfirmedFullRefund.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentIdentity.php';
\Asaas\CompanyRefundSchema::ensure($db,$p,true);CompanyAsaasRefundSchema::ensure($db,$p,true);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.1','asaas']);
register_shutdown_function(function()use($db,$p){foreach(['asaas_company_refund_observations','asaas_company_refund_requests','asaas_company_refund_grants','company_asaas_refund_postings'] as $table)$db->query('DROP TABLE IF EXISTS '.$p.$table);});
$start=$GLOBALS['essentialChecks'];$refunds=new \Asaas\CompanyRefunds($db,$p);
$origin=(int)$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND remote_id=?',[$invoice,'pay_group_one'])->row()->id;
$historical=fn()=>IssuerTransaction::run($db,$p,array_merge(['issuers_lock'],\Asaas\CompanyInstallmentIdentity::tables()),fn()=>\Asaas\CompanyInstallmentIdentity::member($db,$p,$b,$origin));
require_once APPPATH.'services/CompanySettingsRuntime.php';
essentialThrows(fn()=>CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$refunds->page($b,7,$origin)),'asaas_company_access_denied','request company cannot be substituted even for actor authorized in both');
essentialThrows(fn()=>$refunds->page($a,7,$origin),'asaas_company_origin_denied','known foreign installment cannot hydrate refund page');
$identity=$historical();essentialCheck($identity['identity']['member_number']===1&&$identity['identity']['member_value']==='40.00'&&count($identity['identity']['bindings'])===3,'identity covers root and both members with immutable proofs/audits');
$account=$db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row_array();
$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$account['id']]);$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
essentialCheck($historical()['identity']===$identity['identity'],'historical proof survives account and creator revocation');
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$account['id']]);
$refunds->setGrant($b,7,$account['id'],7,str_repeat('0',64),true);
$paymentResponse=array_replace($first,['deleted'=>false,'refunds'=>[]]);$posts=0;
$refundTransport=function($env,$method,$path,$payload,$secret)use(&$paymentResponse,&$posts,$account){
 essentialCheck($env==='sandbox'&&$secret==='key-B','refund uses owning account credential/environment');
 if($path==='wallets/'&&$method==='GET')return ['data'=>[['id'=>$account['account_id']]],'hasMore'=>false];
 if($path==='payments/pay_group_one'&&$method==='GET')return $paymentResponse;
 if($path==='payments/pay_group_one/refund'&&$method==='POST'){$posts++;$paymentResponse['status']='REFUND_IN_PROGRESS';$paymentResponse['refunds']=[['dateCreated'=>'2026-11-01 12:00:00','status'=>'PENDING','value'=>$payload['value'],'description'=>$payload['description'],'refundedSplits'=>[]]];return $paymentResponse;}
 throw new RuntimeException('unexpected synthetic refund transport');
};
$key=hash('sha256','installment-refund');$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$prepared=$refunds->prepare($b,7,$origin,'40.00','Synthetic member refund',$key,$dec,$refundTransport);
essentialCheck($prepared['source']['installment_identity']===$identity['identity']&&$prepared['source']['nominal']==='40.00'&&$prepared['source']['invoice_id']===$invoice,'intent freezes exact historical group and member, not parent total');
$wrong=$paymentResponse;$wrong['installment']='foreign';essentialThrows(fn()=>\Asaas\CompanyRefundContract::normalize($wrong,$prepared['source']),'asaas_refund_response_conflict','different remote group rejected');
$wrong=$paymentResponse;$wrong['installmentNumber']=2;essentialThrows(fn()=>\Asaas\CompanyRefundContract::normalize($wrong,$prepared['source']),'asaas_refund_response_conflict','different installment number rejected');
$refunds->dispatch($b,7,$key,$dec,$refundTransport);$refunds->dispatch($b,7,$key,$dec,$refundTransport);essentialCheck($posts===1,'sent refund is never posted twice');
$paymentResponse['status']='REFUNDED';$paymentResponse['refunds'][0]['status']='DONE';$observed=$refunds->refresh($b,7,$key,$dec,$refundTransport);
$declaration=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'40.00','date'=>gmdate('Y-m-d'),'reference'=>'synthetic-installment-return','reason'=>'Independent synthetic proof','independent_evidence_hash'=>hash('sha256','synthetic installment evidence')];
$source=new \Asaas\ConfirmedFullRefund($db,$p,$key,$observed['observation_id'],$declaration);
$proof=$db->query("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='installment_bind'",[$origin])->row_array();
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$proof['id']]);
essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,$source),'asaas_installment_identity_conflict','corrupt historical group prevents financial writer');
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$proof['evidence_hash'],$proof['id']]);
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,$source),'issuer_storage_failed','late audit failure rolls back refund posting');$db->failOn=null;
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['revoked','same'] as $case){
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/finance_resume_439_full_refund_concurrency.php','worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('refund worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$b,'request'=>$key,'observation'=>$observed['observation_id'],'declaration'=>$declaration],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiting>=2,'both member refund writers wait on actual InnoDB lock');
  if($case==='revoked')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','member refund worker completes without leakage');}
  essentialCheck($case==='revoked'?$results===['denied','denied']:($results[0]===$results[1]&&preg_match('/^[a-f0-9]{64}$/D',$results[0])===1),'current authority and idempotence hold under simultaneous declarations');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=?',[$invoice])->row()->n===($case==='same'?1:0),'one posting or zero after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
}
$posted=$writer->recordCompanyAsaasFullRefund($b,7,$source);essentialCheck($writer->recordCompanyAsaasFullRefund($b,7,$source)===$posted,'full member refund replay idempotent');
$position=$s->position($b,7,$invoice);essentialCheck($position['received_original']==='105.53'&&$position['received']==='65.53'&&$position['receivable']==='40.00','only target installment reverses; sibling remains paid');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array(),'all native receipts preserved byte for byte');
$panel=$asaas->installmentGroupPage($b,7,$root);essentialCheck($panel['members'][0]['origin_id']===$origin,'native group page exposes exact refund target, never infers by description');
$db->query('UPDATE '.$p.'asaas_company_proofs SET result_json=?,evidence_hash=? WHERE id=?',[str_replace('40.00','41.00',$proof['result_json']),hash('sha256',str_replace('40.00','41.00',$proof['result_json'])),$proof['id']]);
essentialThrows(fn()=>$s->position($b,7,$invoice),'asaas_installment_identity_conflict','rehashing altered member does not replace immutable binding audit');
$db->query('UPDATE '.$p.'asaas_company_proofs SET result_json=?,evidence_hash=? WHERE id=?',[$proof['result_json'],$proof['evidence_hash'],$proof['id']]);
$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$account['id']]);essentialCheck($s->position($b,7,$invoice)===$position,'historical ledger survives later account disable');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$account['id']]);
echo 'Installment refunds: '.($GLOBALS['essentialChecks']-$start).' additional checks, MySQL with simulated provider.'.PHP_EOL;
