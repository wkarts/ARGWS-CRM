<?php
if(($argv[1]??'')==='worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
 $GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$GLOBALS['CI']=(object)['db'=>$db];
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
 require_once APPPATH.'services/CompanyAsaasFullManualSource.php';
 $v=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyManualWithMode(...array_merge($v['arguments'],[new CompanyAsaasFullManualSource($db,$p,$v['arguments'][4])]));exit(0);
}
require __DIR__.'/asaas_partial_manual.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyManualSettlements.php';
require_once APPPATH.'services/CompanyAsaasFullManualSource.php';
$db->queries=[];
$fullStart=$GLOBALS['essentialChecks'];
\Asaas\CompanyManualSettlementSchema::ensure($db,$p);
$db->query("UPDATE {$p}modules SET installed_version='1.6.9' WHERE module_name='asaas'");
$manual=new \Asaas\CompanyManualSettlements($db,$p);
$account=$asaas->accounts($b,7)[0];
$policy=$manual->policy($b,7,$accounts[$b]);
essentialCheck($policy['operation']==='disabled','full receipt policy defaults disabled');
$mutations=[];$simulateTimeout=false;
$fullTransport=function($environment,$method,$path,$payload,$secret)use($mock,&$responses,&$mutations,&$simulateTimeout){
 if(($method==='POST'&&str_ends_with($path,'/receiveInCash'))||$method==='DELETE'){
  $mutations[]=[$method,$path,$payload];$id=explode('/',$path)[1];
  if($method==='POST'){$responses[$id]['status']='RECEIVED';$responses[$id]['billingType']='RECEIVED_IN_CASH';$responses[$id]['paymentDate']=$payload['paymentDate'];}
  else $responses[$id]['deleted']=true;
  if($simulateTimeout)throw new RuntimeException('simulated response lost after remote commit');
  return $responses[$id];
 }
 return $mock($environment,$method,$path,$payload,$secret);
};
foreach(['cash','remove'] as $operation){
 $policy=$manual->policy($b,7,$accounts[$b]);
 $manual->configure($b,7,$accounts[$b],$account['revision'],$policy['revision'],$operation,false);
 essentialThrows(fn()=>$manual->configure($b,7,$accounts[$b],$account['revision'],$policy['revision'],$operation,false),'asaas_manual_policy_stale','stale policy cannot overwrite reviewed choice');
 [$invoice,$origin,$proof,$remote]=asaasFreshCredit($b,$customerB,'cus_b','full-manual-'.$operation,'PENDING');
 $responses[$remote]['paymentDate']='';$responses[$remote]['creditDate']='';$asaas->observe($b,7,$origin,$dec,$mock);
 $db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$invoice]);
 if($operation==='cash'){
  $partial=['amount'=>'1.00','date'=>'2026-10-19','reference'=>'synthetic-before-full','note'=>'First offline part'];
  $writer->recordCompanyManualWithMode($b,7,$invoice,$partial,hash('sha256','before-full'),$mode['id'],$mode['revision'],false,null,new CompanyAsaasPartialManualSource($db,$p,$origin));
 }
 $previous=$operation==='cash'?1:0;
 $formData=(new NativeCompanyInvoicePaymentForm($db,$p))->read($b,7,$invoice);
 $total=$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->total;
 $remaining=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($total)-($previous*100));
 $receipt=['amount'=>$remaining,'date'=>'2026-10-19','reference'=>'synthetic-full-'.$operation,'note'=>'Offline receipt test'];$key=hash('sha256','full-manual-'.$operation);
 $reserve=fn()=>$manual->reserve($b,7,$origin,$invoice,$receipt,$key,$mode['id'],$mode['revision'],$formData['revision'],false);
 $command=$reserve();essentialCheck($command['state']==='reserved'&&$reserve()['request_key']===$key,'reservation replays without remote mutation');
 $responses[$remote]['dunning']=['id'=>'synthetic'];
 essentialThrows(fn()=>$manual->execute($b,7,$key,$dec,$fullTransport),'asaas_manual_canonical_conflict','dunning rejected before dispatch');unset($responses[$remote]['dunning']);
 $before=count($mutations);$simulateTimeout=true;
 essentialThrows(fn()=>$manual->execute($b,7,$key,$dec,$fullTransport),'asaas_manual_remote_unconfirmed','lost response remains uncertain');$simulateTimeout=false;
 essentialCheck(count($mutations)===$before+1,'one remote mutation despite uncertain result');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===$previous,'uncertain response creates no native receipt');
 $command=$manual->execute($b,7,$key,$dec,$fullTransport);
 essentialCheck($command['state']==='confirmed'&&count($mutations)===$before+1,'canonical GET recovers without repeating mutation');
 $db->failOn='INSERT INTO '.$p.'company_payment_notice_intents';
 try{essentialThrows(fn()=>$manual->recover($b,7,$key,$dec,$fullTransport),'issuer_storage_failed','late notice failure rolls local receipt back');}finally{$db->failOn=null;}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===$previous,'native failure leaves zero payment');
 // Two native writers contend on the real financial mutex after confirmation.
 $arguments=[$b,7,$invoice,$receipt,$key,$mode['id'],$mode['revision'],false,$formData['revision']];
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(compact('arguments'),JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+20;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'two full receipt writers wait on InnoDB mutex');$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','full receipt worker clean: '.$error);}
  essentialCheck($results[0]===$results[1]&&ctype_digit($results[0]),'concurrent full receipt writers return the same payment');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
 $payment=$manual->recover($b,7,$key,$dec,$fullTransport);
 essentialCheck($payment>0&&$manual->recover($b,7,$key,$dec,$fullTransport)===$payment,'recover and replay produce one native receipt');
 essentialCheck(count($mutations)===$before+1,'native rollback recovery never repeats provider operation');
 essentialThrows(fn()=>$manual->recover($a,7,$key,$dec,$fullTransport),'asaas_manual_command_missing','foreign company cannot recover command');
}
// The ordinary native payment action must select the full authority and replay it.
[$invoice,$origin,$proof,$remote]=asaasFreshCredit($b,$customerB,'cus_b','full-native-action','PENDING');
$responses[$remote]['paymentDate']='';$responses[$remote]['creditDate']='';$asaas->observe($b,7,$origin,$dec,$mock);
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$invoice]);
$formData=(new NativeCompanyInvoicePaymentForm($db,$p))->read($b,7,$invoice);
$wire=['invoiceid'=>(string)$invoice,'amount'=>$responses[$remote]['value'],'date'=>'2026-10-19','paymentmode'=>$mode['id'],'transactionid'=>'synthetic-native-full','note'=>'Test','do_not_send_email_template'=>'on','_native_payment_key'=>hash('sha256','full-native-action'),'_native_payment_mode_revision'=>$mode['revision'],'_native_invoice_revision'=>$formData['revision']];
$action=new NativeCompanyInvoicePaymentAction($db,$p,$fullTransport);$before=count($mutations);
$result=$action->submit($b,7,$wire,'https://crm.example.invalid',$dec);
essentialCheck($result['payment_id']>0&&$result['email_status']==='suppressed'&&count($mutations)===$before+1,'native full action uses explicit remove policy exactly once');
$after=count($calls);essentialCheck($action->submit($b,7,$wire,'https://crm.example.invalid',$dec)['payment_id']===$result['payment_id']&&count($calls)===$after&&count($mutations)===$before+1,'native full replay does not call provider');
echo 'Asaas full manual: '.($GLOBALS['essentialChecks']-$fullStart).' added SQL checks passed; provider simulated.'.PHP_EOL;
