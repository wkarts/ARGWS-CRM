<?php
if(($argv[1]??'')==='replace-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixReplacement.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 echo (new Asaas\CompanyPixReplacement($db,$p))->confirm($in['issuer'],7,$in['old'],$in['new'],$in['schedule'],[],$in['binding_revision'],$in['replacement_revision'],true);exit;
}
require __DIR__.'/asaas_pix_future_schedules.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixReplacement.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixRevocation.php';
Asaas\CompanyPixFutureWorkerSchema::ensure($db,$p,true);unset($db->data_cache['table_names']);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.0','asaas']);
$start=$GLOBALS['essentialChecks'];$replace=new Asaas\CompanyPixReplacement($db,$p);$worker=new Asaas\CompanyPixFutureWorker($db,$p);
$worker->configure($b,7,$origin,$review['revision'],str_repeat('0',64),true,true);$oldPolicy=$worker->page($b,7,$origin);$oldBinding=$future->status($b,7,$origin);
$newInvoice=$s->create($b,7,array_replace($baseInput,['date'=>'2026-07-19','due_date'=>'2026-08-19']),hash('sha256','pix-replacement-first'));
$newOrigin=$asaas->reservePixAuthorization($b,7,$newInvoice,$accounts[$b],'cus_b',$s->revision($b,7,$newInvoice),hash('sha256','pix-replacement-key'),'MONTHLY','2026-07-19');
$newSource=json_decode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$newOrigin,'pix_reserve'])->row()->result_json,true);
$newAnswer=['id'=>'auth_replacement_new','customerId'=>'cus_b','contractId'=>$newSource['contract_id'],'frequency'=>'MONTHLY','startDate'=>$newSource['start_date'],'status'=>'ACTIVE','immediateQrCode'=>['conciliationIdentifier'=>'replacement_new','expirationDate'=>'2030-01-01T12:00:00-03:00']];
$replacementCalls=0;$deletes=0;
$replacementTransport=function($env,$method,$path,$payload,$secret)use($transport,$newSource,$newAnswer,&$answer,&$replacementCalls,&$deletes){$replacementCalls++;if($method==='DELETE'&&$path==='pix/automatic/authorizations/'.$answer['id']){$deletes++;$answer['status']='CANCELLED';return ['success'=>true];}
 if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$answer,$newAnswer],'hasMore'=>false,'totalCount'=>2];
 if($path==='pix/automatic/authorizations/'.$newAnswer['id'])return $newAnswer;
 if($path==='payments/pay_replacement_initial')return ['id'=>'pay_replacement_initial','customer'=>'cus_b','billingType'=>'PIX','pixTransaction'=>'tx_replacement_initial','value'=>$newSource['amount'],'netValue'=>$newSource['amount'],'paymentDate'=>'2026-07-19','status'=>'RECEIVED'];
 if($path==='pix/transactions/tx_replacement_initial')return ['id'=>'tx_replacement_initial','payment'=>'pay_replacement_initial','type'=>'CREDIT','conciliationIdentifier'=>'replacement_new'];
 return $transport($env,$method,$path,$payload,$secret);};
$read=fn()=>$replace->review($b,7,$origin,$newOrigin,$schedule,[],$dec,$replacementTransport);
essentialThrows($read,'asaas_pix_replacement_cancellation_required','active old mandate cannot be replaced');essentialCheck($replacementCalls===0,'cancellation required before HTTP');
essentialThrows(fn()=>$replace->review($a,7,$origin,$newOrigin,$schedule,[],$dec,$replacementTransport),'asaas_pix_replacement_denied','foreign pair rejected before HTTP');
$accountRevision=$db->query('SELECT revision FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row()->revision;
$cancel=(new Asaas\CompanyPixRevocation($db,$p))->cancel($b,7,$origin,$accountRevision,true,$dec,$replacementTransport);essentialCheck($cancel['state']==='cancelled'&&$deletes===1,'old cancellation is canonical, not inferred from local stop');
essentialThrows($read,'asaas_pix_explicit_consent_required','old consent cannot authorize new mandate');
$newTerms=$consent->review($portal,$sessions[$b],$b,$newInvoice);$consent->accept($portal,$sessions[$b],$b,$newInvoice,$newTerms['revision'],true);
$asaas->recoverPixAuthorization($b,7,$newOrigin,'',$dec,$replacementTransport);
essentialThrows($read,'asaas_pix_initial_receipt_required','new first receipt is not copied from old mandate');
$proof=$asaas->observePixInitialPayment($b,7,$newOrigin,'pay_replacement_initial',$dec,$replacementTransport);(new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($b,7,new Asaas\CompanyPixInitialReceipt($newOrigin,$proof));
$proposal=$read();$confirm=fn($revision,$accepted=true)=>$replace->confirm($b,7,$origin,$newOrigin,$schedule,[],$proposal['revision'],$revision,$accepted);
essentialThrows(fn()=>$confirm($proposal['replacement_revision'],false),'asaas_company_confirmation_required','replacement requires explicit review confirmation');
essentialThrows(fn()=>$confirm(str_repeat('0',64)),'asaas_pix_replacement_stale','replacement checks both immutable bindings');
$payments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();$invoices=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$db->failOn='UPDATE '.$p.'asaas_company_pix_future_workers';essentialThrows(fn()=>$confirm($proposal['replacement_revision']),'issuer_storage_failed','late worker failure rolls back both bindings');$db->failOn=null;
essentialCheck(!$future->status($b,7,$origin)['stopped']&&$future->status($b,7,$newOrigin)===null&&$worker->page($b,7,$origin)===$oldPolicy,'old binding/policy preserved and new binding absent after rollback');
$before=$replacementCalls;$workers=[];$in=['issuer'=>$b,'old'=>$origin,'new'=>$newOrigin,'schedule'=>$schedule,'binding_revision'=>$proposal['revision'],'replacement_revision'=>$proposal['replacement_revision']];
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'replace-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'replacement processes wait on the real financial mutex');$db->trans_commit();$ids=[];
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err===''&&ctype_digit($out),'replacement process returns a binding: '.$err);$ids[]=(int)$out;}
 essentialCheck($ids[0]===$ids[1],'concurrent replacement converges on one successor');$bindingId=$ids[0];
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
essentialCheck($confirm($proposal['replacement_revision'])===$bindingId,'replacement replay returns same immutable successor');
essentialCheck($replacementCalls===$before,'confirmation never sends bank request');
essentialCheck($future->status($b,7,$origin)['stopped']&&$future->status($b,7,$origin)['binding']===$oldBinding['binding'],'old binding frozen and stopped without rewriting history');
essentialCheck((int)$worker->page($b,7,$origin)['enabled']===0&&$worker->page($b,7,$newOrigin)===null,'old automation disabled atomically; new worker not implicitly enabled');
essentialCheck($future->status($b,7,$newOrigin)['binding']['authorization_id']==='auth_replacement_new','new mandate independently consented and bound');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$payments&&$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoices,'replacement preserves all issued invoices and receipts');
echo 'Pix mandate replacement: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; simulated provider only.'.PHP_EOL;
