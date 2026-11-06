<?php
if(($argv[1]??'')==='worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
 $GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$GLOBALS['CI']=(object)['db'=>$db];
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
 require_once APPPATH.'services/CompanyAsaasPartialManualSource.php';
 $v=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$source=new CompanyAsaasPartialManualSource($db,$p,$v['origin']);
 try{echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyManualWithMode(...array_merge($v['arguments'],[$source]));}
 catch(DomainException $e){if(in_array($e->getMessage(),['native_payment_mode_denied','asaas_company_access_denied','company_payment_access_denied'],true))echo 'denied';else throw $e;}exit(0);
}
require __DIR__.'/asaas_positive.php';
require_once APPPATH.'services/CompanyAsaasPartialManualSource.php';
require_once APPPATH.'services/NativeCompanyPaymentModes.php';
require_once APPPATH.'services/NativeCompanyPaymentNoticeSchema.php';
$partialStart=$GLOBALS['essentialChecks'];
NativeCompanyPaymentModesSchema::ensure($db,$p);
require_once __DIR__.'/../support/NativeInstalledSchemaFixture.php';
$plan=NativeInstalledSchemaFixture::plan(file_get_contents(dirname(__DIR__,2).'/install/database.sql'),$p);
foreach(['expenses','mail_queue','payment_modes'] as $table)if(!$db->table_exists($p.$table)){$db->query($plan[$table]['create']);foreach($plan[$table]['alters'] as $sql)$db->query($sql);}
require_once APPPATH.'services/CompanyInvoiceDraftsSchema.php';CompanyInvoiceDraftsSchema::ensure($db,$p);
require_once APPPATH.'migrations/475_version_475.php';$migration=new Migration_Version_475();$migration->db=$db;$migration->up();
unset($db->data_cache['table_names']);
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);
$db->query("UPDATE {$p}modules SET installed_version='1.6.8' WHERE module_name='asaas'");
$catalog=new NativeCompanyPaymentModes($db,$p);$mode=$catalog->save($b,7,['name'=>'Synthetic manual transfer','description'=>'','active'=>1,'show_on_pdf'=>1,'selected_by_default'=>0,'invoices_only'=>1,'expenses_only'=>0]);
[$invoice,$origin,$proof,$remote]=asaasFreshCredit($b,$customerB,'cus_b','partial-manual','PENDING');
$responses[$remote]['paymentDate']='';$responses[$remote]['creditDate']='';$asaas->observe($b,7,$origin,$dec,$mock);
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$invoice]);
$source=new CompanyAsaasPartialManualSource($db,$p,$origin);
$inputPartial=['amount'=>'1.00','date'=>'2026-10-19','reference'=>'synthetic-partial-one','note'=>'Test independent offline receipt'];$partialKey=hash('sha256','partial-manual-one');
$write=function($input,$key,$revision=null)use($writer,$b,$invoice,$mode,$source){return $writer->recordCompanyManualWithMode($b,7,$invoice,$input,$key,$mode['id'],$mode['revision'],false,$revision,$source);};
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array();$beforeCalls=count($calls);
$db->failOn='INSERT INTO '.$p.'company_payment_notice_intents';
try{essentialThrows(fn()=>$write($inputPartial,$partialKey),'issuer_storage_failed','notice failure rolls back receipt and Asaas partial audit');}finally{$db->failOn=null;}
essentialCheck(!$db->query('SELECT payment_id FROM '.$p.'company_payment_receipts WHERE request_key=?',[$partialKey])->row_array(),'rollback leaves no partial receipt');
$payment=$write($inputPartial,$partialKey);essentialCheck($payment>0&&$write($inputPartial,$partialKey)===$payment,'partial receipt and exact replay use one native payment');
essentialCheck(count($calls)===$beforeCalls&&$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array()===$before,'local partial leaves remote origin bytes and API calls unchanged');
$balanceService=new CompanyInvoiceBalance($db,$p);$balance=IssuerTransaction::run($db,$p,array_unique(array_merge($source->tables(),$balanceService->tables())),fn()=>$balanceService->inOwned($b,7,$invoice));
essentialCheck($balance['received']==='1.00'&&CompanyInvoiceAmounts::minor($balance['receivable'])===CompanyInvoiceAmounts::minor($balance['total'])-100,'only the partial amount reduces the native balance');
$second=array_replace($inputPartial,['reference'=>'synthetic-partial-two']);$key2=hash('sha256','partial-manual-two');
$proofId=$db->query("SELECT id FROM {$p}asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1",[$origin])->row()->id;
$db->query("UPDATE {$p}asaas_company_proofs SET created_at=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-301),$proofId]);
essentialThrows(fn()=>$write($second,$key2),'asaas_partial_manual_fresh_query_required','new partial requires a fresh unpaid proof');
essentialCheck($write($inputPartial,$partialKey)===$payment,'exact committed replay does not require a new external query');
$asaas->observe($b,7,$origin,$dec,$mock);$secondPayment=$write($second,$key2);essentialCheck($secondPayment!==$payment,'second distinct partial preserves first receipt');
$balanceService=new CompanyInvoiceBalance($db,$p);$balance=IssuerTransaction::run($db,$p,array_unique(array_merge($source->tables(),$balanceService->tables())),fn()=>$balanceService->inOwned($b,7,$invoice));
essentialThrows(fn()=>$write(array_replace($inputPartial,['amount'=>$balance['receivable'],'reference'=>'synthetic-full']),hash('sha256','full-not-partial')),'asaas_partial_manual_partial_only','full balance cannot silently use partial policy');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");try{essentialThrows(fn()=>$write($inputPartial,$partialKey),'native_payment_mode_denied','permission revoked prevents replay');}finally{$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']='2026-10-19';$responses[$remote]['creditDate']='2026-10-19';$receivedProof=$asaas->observe($b,7,$origin,$dec,$mock);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$receivedProof)),'asaas_company_balance_requires_review','canonical bank receipt after local partial cannot duplicate full invoice payment');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===2,'provider conflict leaves both partials and no third payment');
// Two real workers must serialize on the same canonical financial mutex.
$responses[$remote]['status']='PENDING';$responses[$remote]['paymentDate']='';$responses[$remote]['creditDate']='';$asaas->observe($b,7,$origin,$dec,$mock);
foreach([false,true] as $revoke){
 $key=hash('sha256','partial-race-'.(int)$revoke);$payload=array_replace($inputPartial,['reference'=>'synthetic-race-'.(int)$revoke]);
 $arguments=[$b,7,$invoice,$payload,$key,$mode['id'],$mode['revision'],false,null];
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(compact('origin','arguments'),JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+20;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'two partial receipt workers wait on actual InnoDB lock');
  if($revoke)$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','partial worker exits cleanly: '.$error);}
  essentialCheck($results[0]===$results[1]&&($revoke?$results[0]==='denied':ctype_digit($results[0])),'contenders return one receipt or both observe revoked ACL');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_payment_receipts WHERE request_key=?',[$key])->row()->n===($revoke?0:1),'concurrent request writes exactly the authorized receipt count');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();if($revoke)$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
}
require_once APPPATH.'services/NativeCompanyInvoicePaymentAction.php';
$form=new NativeCompanyInvoicePaymentForm($db,$p);$formData=$form->read($b,7,$invoice);
essentialCheck($formData['asaas_partial_manual']===true,'native form explicitly explains partial-only Asaas policy');
$wire=['invoiceid'=>(string)$invoice,'amount'=>'1.00','date'=>'2026-10-19','paymentmode'=>$mode['id'],'transactionid'=>'synthetic-native-action','note'=>'Test','do_not_send_email_template'=>'on','_native_payment_key'=>hash('sha256','partial-native-action'),'_native_payment_mode_revision'=>$mode['revision'],'_native_invoice_revision'=>$formData['revision']];
$failedAction=new NativeCompanyInvoicePaymentAction($db,$p,static function(){throw new RuntimeException('synthetic HTTP timeout');});
$beforeFailure=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n;
essentialThrows(fn()=>$failedAction->submit($b,7,$wire,'https://crm.example.invalid',$dec),'asaas_company_transport_unconfirmed','failed canonical HTTP query cannot become a manual receipt');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===$beforeFailure,'HTTP failure leaves native balance untouched');
$before=count($calls);$action=new NativeCompanyInvoicePaymentAction($db,$p,$mock);$result=$action->submit($b,7,$wire,'https://crm.example.invalid',$dec);
essentialCheck($result['payment_id']>0&&$result['email_status']==='suppressed','native form action records the selected mode and notice choice');
$newCalls=array_slice($calls,$before);essentialCheck(count($newCalls)>0&&!array_filter($newCalls,fn($c)=>$c[1]!=='GET'),'native action performs only canonical read requests');
$after=count($calls);essentialCheck($action->submit($b,7,$wire,'https://crm.example.invalid',$dec)['payment_id']===$result['payment_id']&&count($calls)===$after,'native action replay returns same receipt with zero new remote requests');
essentialThrows(fn()=>$action->submit($a,7,$wire,'https://crm.example.invalid',$dec),'company_invoice_access_denied','native partial action rejects foreign company');
$audit=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=?',[$b,$invoice,'company_asaas_partial_manual_recorded'])->row_array();$audit=json_decode($audit['metadata_json'],true,32,JSON_THROW_ON_ERROR);
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$audit['proof_id']]);
try{essentialThrows(fn()=>$write($inputPartial,$partialKey),'asaas_partial_manual_history_conflict','replay rejects corrupted historical proof bound to its receipt');}finally{$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$audit['proof_hash'],$audit['proof_id']]);}
echo 'Asaas partial manual: '.($GLOBALS['essentialChecks']-$partialStart).' added SQL checks passed. API simulated; no financial transaction.'.PHP_EOL;
