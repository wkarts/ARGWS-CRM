<?php
if(($argv[1]??'')==='job-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixReceiptJobs.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $transport=function($env,$method,$path,$payload,$secret)use($in){if($method!=='GET')throw new RuntimeException('unexpected mutation');return match($path){'wallets/'=>['data'=>[['id'=>'wallet-B']],'hasMore'=>false],'customers/cus_b'=>['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false],'payments/'.$in['payment']['id']=>$in['payment'],'pix/transactions/'.$in['tx']['id']=>$in['tx'],default=>throw new RuntimeException('unexpected path')};};
 echo json_encode((new \Asaas\CompanyPixReceiptJobs($db,$p))->tick(fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$transport,1),JSON_THROW_ON_ERROR);exit;
}
/** Actual account-authenticated inbox, leased financial queue and native MySQL writer. */
require __DIR__.'/asaas_pix_initial_receipt.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixReceiptJobs.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.6','asaas']);
\Asaas\CompanyPixReceiptJobSchema::ensure($db,$p,true);$queue=new \Asaas\CompanyPixReceiptJobs($db,$p);
$token=str_repeat('synthetic-receipt-token-',3);$db->query('UPDATE '.$p.'asaas_company_accounts SET token_hash=? WHERE id=?',[hash('sha256',$token),$accounts[$b]]);$account=$db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row_array();
$headers=['Content-Type'=>'application/json','asaas-access-token'=>$token];
$queue->configure($b,7,$accounts[$b],$account['revision'],str_repeat('0',64),true);
$revision=fn()=>$queue->page($b,7)['workers'][0]['revision'];
essentialThrows(fn()=>$queue->configure($a,7,$accounts[$b],$account['revision'],str_repeat('0',64),true),'asaas_pix_receipt_worker_denied','foreign company cannot configure worker');
essentialCheck($queue->tick($dec,$mock)===[],'enable has no historical backfill');
foreach(['normal','revoke','retry','concurrent'] as $scenario){
 $invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-job-invoice-'.$scenario));$origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','pix-job-key-'.$scenario),'MONTHLY',$input['date']);
 $source=json_decode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_reserve'])->row()->result_json,true);
 $auth=['id'=>'auth_job_'.$scenario,'customerId'=>'cus_b','contractId'=>$source['contract_id'],'frequency'=>'MONTHLY','startDate'=>$input['date'],'status'=>'REFUSED','immediateQrCode'=>['conciliationIdentifier'=>'conc_job_'.$scenario,'expirationDate'=>'2030-01-01T12:00:00-03:00']];
 $payment=['id'=>'pay_job_'.$scenario,'customer'=>'cus_b','billingType'=>'PIX','pixTransaction'=>'tx_job_'.$scenario,'value'=>$source['amount'],'netValue'=>$source['amount'],'paymentDate'=>$input['date'],'status'=>'RECEIVED'];$tx=['id'=>$payment['pixTransaction'],'payment'=>$payment['id'],'type'=>'CREDIT','conciliationIdentifier'=>'conc_job_'.$scenario];$jobCalls=[];$revoke=false;
 $transport=function($env,$method,$path,$payload,$secret)use($mock,&$jobCalls,$auth,$payment,$tx,&$revoke,$queue,$b,$accounts,$account,$revision){$jobCalls[]=[$method,$path];if($path==='pix/automatic/authorizations/'.$auth['id'])return $auth;if($path==='payments/'.$payment['id']){if($revoke){$revoke=false;$queue->configure($b,7,$accounts[$b],$account['revision'],$revision(),false);}return $payment;}if($path==='pix/transactions/'.$tx['id'])return $tx;return $mock($env,$method,$path,$payload,$secret);};
 $asaas->recoverPixAuthorization($b,7,$origin,$auth['id'],$dec,$transport);
 $body=json_encode(['id'=>'evt_pix_job_'.$scenario,'event'=>'PAYMENT_RECEIVED','payment'=>['id'=>$payment['id'],'value'=>'9999.99']]);
 essentialCheck($asaas->receive($account['webhook_public_id'],$headers,$body)['status']===200,'authenticated event accepted, webhook amount not used');
 essentialCheck($asaas->receive($account['webhook_public_id'],$headers,$body)['duplicate']===true,'duplicate event stays unique');
 if($scenario==='revoke')$revoke=true;if($scenario==='retry')$db->failOn='INSERT INTO '.$p.'asaas_company_receipts';
 if($scenario==='concurrent'){
  $workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
  try{
   for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'job-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['payment'=>$payment,'tx'=>$tx],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
   $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two cron workers wait on native mutex');$db->trans_commit();$result=[];
   foreach($workers as [$proc,$pipes]){$result=array_merge($result,json_decode(stream_get_contents($pipes[1]),true,32,JSON_THROW_ON_ERROR));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','cron workers exit cleanly');}
  }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
 }else $result=$queue->tick($dec,$transport);
 $db->failOn=null;essentialCheck(count($result)===1&&$result[0]['state']===match($scenario){'normal','concurrent'=>'applied','revoke'=>'review_required',default=>'retry'},'worker outcome '.$scenario);
 $payments=$db->query('SELECT id,amount FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array();essentialCheck(count($payments)===(in_array($scenario,['normal','concurrent'],true)?1:0),'no partial financial effect '.$scenario);
 if($scenario==='normal'){essentialCheck(CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($payments[0]['amount']))===$source['amount'],'canonical nominal amount, not webhook value');essentialCheck($queue->tick($dec,$transport)===[],'completed job never repeats');}
 if($scenario==='revoke')$queue->configure($b,7,$accounts[$b],$account['revision'],$revision(),true);
 if($scenario==='retry'){$db->query('UPDATE '.$p.'asaas_company_pix_receipt_jobs SET next_attempt=? WHERE event_id=?',['2020-01-01 00:00:00',$result[0]['event_id']]);essentialCheck($queue->tick($dec,$transport)[0]['state']==='applied','storage failure recovers with canonical GET and one native receipt');}
 essentialCheck(!array_filter($jobCalls,fn($call)=>$call[0]!=='GET'),'financial queue performs no remote financial writes');
}
echo 'Initial Pix financial jobs: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; synthetic provider only.'.PHP_EOL;
