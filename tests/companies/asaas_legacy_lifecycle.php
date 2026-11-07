<?php
if(($argv[1]??'')==='refund-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';
 require_once APPPATH.'services/InvoicePaymentWriter.php';
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
 require_once FCPATH.'modules/asaas/libraries/Asaas/ConfirmedFullRefund.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
 $GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyAsaasFullRefund($in['issuer'],7,new \Asaas\ConfirmedFullRefund($db,$p,$in['request'],$in['observation'],$in['declaration']));exit;
}
require __DIR__.'/asaas_legacy_binding.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRemoteOperations.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRefunds.php';
$start=$GLOBALS['essentialChecks'];
$removal=new \Asaas\CompanyRemoteOperations($db,$p);
$refunds=new \Asaas\CompanyRefunds($db,$p);
\Asaas\CompanyRefundSchema::ensure($db,$p,true);
$pendingInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','legacy-remove-pending'));
$legacyRemote='pay_legacy_remove';$legacyResponse=array_replace($originalResponse,['id'=>$legacyRemote,'externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$pendingInvoice])->row()->hash,'status'=>'PENDING','paymentDate'=>'','creditDate'=>'']);
$preview=$asaas->reviewLegacyBinding($b,7,$pendingInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport);
$pendingOrigin=$asaas->reviewLegacyBinding($b,7,$pendingInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport,$preview['review_hash'])['origin_id'];
$removeCalls=[];$loseDelete=true;
$removeMock=function($env,$method,$path,$payload,$secret)use(&$removeCalls,&$loseDelete,&$legacyResponse,$legacyRemote,$mock){
    $removeCalls[]=[$method,$path,$payload];
    if($path==='payments/'.$legacyRemote){if($method==='DELETE'){$legacyResponse['deleted']=true;if($loseDelete)throw new RuntimeException('simulated lost response');return ['id'=>$legacyRemote,'deleted'=>true];}return $legacyResponse;}
    return $mock($env,$method,$path,$payload,$secret);
};
$key=hash('sha256','reviewed-legacy-remove');
essentialThrows(fn()=>$removal->remove($b,7,$pendingOrigin,$key,$dec,$removeMock),'asaas_module_178_required','legacy lifecycle requires tracking178');
essentialCheck($removeCalls===[],'old tracking blocks before HTTP');
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.8','asaas']);
essentialThrows(fn()=>$removal->remove($a,7,$pendingOrigin,$key,$dec,$removeMock),'asaas_company_origin_denied','foreign legacy removal denied');
essentialCheck($removeCalls===[],'foreign removal has no HTTP');
$before=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$pendingInvoice])->result_array();
$removed=$removal->remove($b,7,$pendingOrigin,$key,$dec,$removeMock);
essentialCheck($removed['state']==='uncertain','lost DELETE response remains uncertain');
$removal->remove($b,7,$pendingOrigin,$key,$dec,$removeMock);
essentialCheck(count(array_filter($removeCalls,fn($call)=>$call[0]==='DELETE'))===1,'retry never resends an uncertain DELETE');
essentialCheck($removal->refresh($b,7,$pendingOrigin,$dec,$removeMock)['state']==='deleted','canonical GET resolves uncertain removal');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$pendingInvoice])->result_array()===$before,'remote deletion does not cancel or erase native invoice');
essentialCheck(\Asaas\CompanyOriginPolicy::isManual($db,$p,$pendingOrigin),'removal preserves manual-only legacy authority');
// Paid partial origins cannot be removed. No real provider traffic is made here.
$legacyRemote='pay_legacy_prior';$legacyResponse=array_replace($originalResponse,['id'=>$legacyRemote,'externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$priorInvoice])->row()->hash]);
essentialThrows(fn()=>$removal->remove($b,7,$priorBound['origin_id'],hash('sha256','paid-delete'),$dec,$legacyTransport),'asaas_company_remote_financial_history','paid partial legacy removal stays denied');
$refundCalls=[];$refundMock=function($env,$method,$path,$payload,$secret)use(&$refundCalls,&$legacyResponse,$legacyRemote,$mock){
    $refundCalls[]=[$method,$path,$payload];
    if($path==='payments/'.$legacyRemote.'/refund'){$legacyResponse['status']='REFUND_IN_PROGRESS';$legacyResponse['refunds']=[['dateCreated'=>gmdate('Y-m-d H:i:s'),'status'=>'PENDING','value'=>$payload['value'],'description'=>$payload['description'],'endToEndIdentifier'=>null,'transactionReceiptUrl'=>null,'refundedSplits'=>[]]];return $legacyResponse;}
    if($path==='payments/'.$legacyRemote)return $legacyResponse;
    return $mock($env,$method,$path,$payload,$secret);
};
$refundKey=hash('sha256','legacy-partial-full-refund');
essentialThrows(fn()=>$refunds->prepare($b,7,$priorBound['origin_id'],'40.00','Synthetic refund',$refundKey,$dec,$refundMock),'asaas_refund_grant_required','binding never implies refund consent');
essentialCheck($refundCalls===[],'default-off refund grant blocks before HTTP');
$refunds->setGrant($b,7,$accounts[$b],7,str_repeat('0',64),true); // disposable fixture grant only
$cash=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$priorInvoice])->result_array();
$intent=$refunds->prepare($b,7,$priorBound['origin_id'],'40.00','Synthetic refund',$refundKey,$dec,$refundMock);
essentialCheck($intent['source']['nominal']==='40.00'&&$intent['source']['payment_nominal']==='40.00','refund freezes remote reviewed principal, not invoice total');
$refunds->dispatch($b,7,$refundKey,$dec,$refundMock);$refunds->dispatch($b,7,$refundKey,$dec,$refundMock);
essentialCheck(count(array_filter($refundCalls,fn($call)=>$call[0]==='POST'))===1,'refund outbox sends exactly once on retry');
$legacyResponse['status']='REFUNDED';$legacyResponse['refunds'][0]['status']='DONE';
$observed=$refunds->refresh($b,7,$refundKey,$dec,$refundMock);
essentialCheck($observed['state']==='full_observed'&&$observed['observation']['done_total']==='40.00'&&$observed['financial_state']==='not_posted','full refund is relative to remote partial charge and requires separate native declaration');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$priorInvoice])->result_array()===$cash,'refund observation preserves both original partial and prior manual receipts');
require_once FCPATH.'modules/asaas/libraries/Asaas/ConfirmedFullRefund.php';
\CompanyAsaasRefundSchema::ensure($db,$p,true);
$declaration=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'40.00','date'=>gmdate('Y-m-d'),'reference'=>'Synthetic independent legacy return','reason'=>'Full return of reviewed partial charge','independent_evidence_hash'=>hash('sha256','independent legacy external proof')];
$beforePosition=$s->position($b,7,$priorInvoice);
essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,new \Asaas\ConfirmedFullRefund($db,$p,$refundKey,$observed['observation_id'],array_replace($declaration,['returned_amount'=>'41.00']))),'company_asaas_full_refund_full_amount_required','cannot declare more than exact original partial receipt');
$source=new \Asaas\ConfirmedFullRefund($db,$p,$refundKey,$observed['observation_id'],$declaration);
$db->failOn='INSERT INTO '.$p.'company_asaas_refund_postings';
essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,$source),'issuer_storage_failed','failed journal write rolls partial-charge reversal back');$db->failOn=null;
essentialCheck($s->position($b,7,$priorInvoice)===$beforePosition,'failed refund write leaves canonical balance unchanged');
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'refund-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'request'=>$refundKey,'observation'=>$observed['observation_id'],'declaration'=>$declaration]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
 if($waiters<2){foreach($workers as [$proc,$pipes]){stream_set_blocking($pipes[2],false);fwrite(STDERR,stream_get_contents($pipes[2]));stream_set_blocking($pipes[1],false);fwrite(STDERR,stream_get_contents($pipes[1]));}}
 essentialCheck($waiters>=2,'both partial-charge refund writers wait on native mutex');$db->trans_commit();$results=[];
 foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','refund contenders complete without diagnostics');}
 essentialCheck($results[0]===$results[1]&&preg_match('/^[a-f0-9]{64}$/D',$results[0])===1,'concurrent declarations share one canonical reversal');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
$afterPosition=$s->position($b,7,$priorInvoice);
essentialCheck($afterPosition['received']==='10.00'&&\CompanyInvoiceAmounts::minor($afterPosition['receivable'])-\CompanyInvoiceAmounts::minor($beforePosition['receivable'])===4000,'full return of partial charge reinstates exactly40 and preserves prior10');
$writer->recordCompanyAsaasFullRefund($b,7,$source);
essentialCheck($s->position($b,7,$priorInvoice)===$afterPosition,'full partial-charge refund declaration replays without double reversal');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$priorInvoice])->result_array()===$cash,'declared refund preserves both historical native payment records byte for byte');
// Explicit invoice edit is safe only when the reviewed charge covers the entire invoice.
$editInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','legacy-edit-full'));
$legacyRemote='pay_legacy_edit';$legacyResponse=array_replace($originalResponse,['id'=>$legacyRemote,'externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$editInvoice])->row()->hash,'value'=>'105.53','netValue'=>'104.53','status'=>'PENDING','paymentDate'=>'','creditDate'=>'']);
$editReview=$asaas->reviewLegacyBinding($b,7,$editInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport);
$editOrigin=$asaas->reviewLegacyBinding($b,7,$editInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport,$editReview['review_hash'])['origin_id'];
$originBefore=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$editOrigin])->row_array();
$metaBefore=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$editOrigin])->row_array();
$puts=0;$loseEdit=true;$editMock=function($env,$method,$path,$payload,$secret)use(&$puts,&$loseEdit,&$legacyResponse,$legacyRemote,$mock){
 if($path==='payments/'.$legacyRemote){if($method==='PUT'){$puts++;$legacyResponse=array_replace($legacyResponse,$payload);$legacyResponse['netValue']=$legacyResponse['value'];if($loseEdit)throw new RuntimeException('simulated lost edit response');}return $legacyResponse;}return $mock($env,$method,$path,$payload,$secret);
};
$revision=$s->revision($b,7,$editInvoice);$editPayload=array_replace($input,['client_id'=>$customerB,'due_date'=>'2026-12-15']);$editPayload['items'][0]['rate']='170.00';
$lines=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$editInvoice])->result_array();
$metadata=['invoice_fields'=>[],'line_fields'=>array_fill(0,count($lines),[]),'tags'=>[],'tags_revision'=>(new NativeCompanyResourceTags($db,$p))->read($b,7,'invoice',$editInvoice)['revision'],'line_sources'=>array_map('intval',array_column($lines,'id')),'removed_line_ids'=>[]];
$editKey=hash('sha256','legacy-full-edit');
essentialThrows(fn()=>$edits->update($b,7,$editInvoice,$revision,$editPayload,$metadata,$editKey,$dec,$editMock),'asaas_invoice_edit_remote_unconfirmed','lost legacy edit response is not local success');
$loseEdit=false;$edits->recover($b,7,$editKey,$dec,$editMock);
essentialCheck($puts===1,'legacy edit recovers by GET without duplicate PUT');
$asaas->observe($b,7,$editOrigin,$dec,$editMock);
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$editOrigin])->row_array()===$metaBefore,'legacy edit preserves initial binding metadata');
$originAfter=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$editOrigin])->row_array();
essentialCheck($originAfter['invoice_total']===$originBefore['invoice_total']&&$originAfter['invoice_due_date']===$originBefore['invoice_due_date'],'legacy edit preserves original invoice amount/date evidence');
essentialCheck(\Asaas\CompanyOriginPolicy::isManual($db,$p,$editOrigin),'explicit edit never enables automatic reconciliation');
$refundPage=$refunds->page($b,7,$editOrigin);
essentialCheck($refundPage['source']['nominal']===$legacyResponse['value'],'refund read model uses audited effective legacy amount after edit');
$legacyResponse['status']='RECEIVED';$legacyResponse['paymentDate']=gmdate('Y-m-d');$legacyResponse['creditDate']=gmdate('Y-m-d');
$editedPaid=$batch->item($b,7,$editOrigin,true,$dec,$editMock);
essentialCheck($editedPaid['payment_id']>0,'explicit settlement verifies edited legacy charge against audited current amount');
$editedRefundMock=function($env,$method,$path,$payload,$secret)use(&$legacyResponse,$editMock){
 if($method==='POST'&&str_ends_with($path,'/refund')){$legacyResponse['status']='REFUND_IN_PROGRESS';$legacyResponse['refunds']=[['dateCreated'=>gmdate('Y-m-d H:i:s'),'status'=>'PENDING','value'=>$payload['value'],'description'=>$payload['description'],'endToEndIdentifier'=>null,'transactionReceiptUrl'=>null,'refundedSplits'=>[]]];return $legacyResponse;}return $editMock($env,$method,$path,$payload,$secret);
};
$editedRefundKey=hash('sha256','edited-legacy-refund');$editedAmount=$legacyResponse['value'];
$refunds->prepare($b,7,$editOrigin,$editedAmount,'Synthetic edited legacy refund',$editedRefundKey,$dec,$editedRefundMock);
$refunds->dispatch($b,7,$editedRefundKey,$dec,$editedRefundMock);$legacyResponse['status']='REFUNDED';$legacyResponse['refunds'][0]['status']='DONE';
$editedObservation=$refunds->refresh($b,7,$editedRefundKey,$dec,$editedRefundMock);
$writer->recordCompanyAsaasFullRefund($b,7,new \Asaas\ConfirmedFullRefund($db,$p,$editedRefundKey,$editedObservation['observation_id'],array_replace($declaration,['returned_amount'=>$editedAmount,'reference'=>'Independent edited legacy return','independent_evidence_hash'=>hash('sha256','independent edited return')])));
essentialCheck($s->position($b,7,$editInvoice)['received']==='0.00','edited legacy refund validates edit journal and reinstates current principal');
echo 'Reviewed legacy lifecycle: '.($GLOBALS['essentialChecks']-$start).' additional checks ('.$db->driver().'); provider simulated.'.PHP_EOL;
