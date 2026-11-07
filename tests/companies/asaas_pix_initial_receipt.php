<?php
if(($argv[1]??'')==='initial-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixInitialReceipt.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($in['issuer'],7,new \Asaas\CompanyPixInitialReceipt($in['origin'],$in['proof']));exit;
}
require __DIR__.'/asaas_pix_reservations.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixInitialReceipt.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.5','asaas']);
// This initial payment still belongs to the first reserved invoice; bank mandate status is separate.
$pixOrigin=$origin;$pixInvoice=(int)$stored['invoice_id'];
$source=json_decode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$pixOrigin,'pix_reserve'])->row()->result_json,true);
$remotePayment=['id'=>'pay_initial_test','customer'=>'cus_b','billingType'=>'PIX','pixTransaction'=>'tx_initial_test','value'=>$source['amount'],'netValue'=>$source['amount'],'paymentDate'=>$input['date'],'status'=>'RECEIVED'];
$remoteTransaction=['id'=>'tx_initial_test','payment'=>'pay_initial_test','type'=>'CREDIT','conciliationIdentifier'=>'synthetic_conciliation'];$initialCalls=[];$during=null;
$initialTransport=function($env,$method,$path,$payload,$secret)use($mock,&$remotePayment,&$remoteTransaction,&$initialCalls,&$during){$initialCalls[]=[$method,$path];if($path==='payments/pay_initial_test'){if($during)$during();return $remotePayment;}if($path==='pix/transactions/tx_initial_test')return $remoteTransaction;return $mock($env,$method,$path,$payload,$secret);};
$observe=fn()=>$asaas->observePixInitialPayment($b,7,$pixOrigin,'pay_initial_test',$dec,$initialTransport);
essentialThrows(fn()=>$asaas->observePixInitialPayment($a,7,$pixOrigin,'pay_initial_test',$dec,$initialTransport),'asaas_pix_reservation_denied','foreign invoice denied before remote GET');essentialCheck($initialCalls===[],'no cross-company provider call');
$remotePayment['status']='CONFIRMED';essentialThrows($observe,'asaas_pix_initial_not_received','bank confirmation alone never creates receipt');$remotePayment['status']='RECEIVED';
$remoteTransaction['conciliationIdentifier']='other';essentialThrows($observe,'asaas_pix_payment_conflict','exact conciliation identity required');$remoteTransaction['conciliationIdentifier']='synthetic_conciliation';
$remotePayment['externalReference']='other';essentialThrows($observe,'asaas_pix_payment_conflict','present conflicting externalReference denied');unset($remotePayment['externalReference']);
$during=fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');essentialThrows($observe,'asaas_company_access_denied','revocation during GET blocks proof');$during=null;$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($observe,'issuer_storage_failed','late observation failure rolls back remote binding and proof');$db->failOn=null;
essentialCheck($db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$pixOrigin])->row()->remote_id===null,'failed observation leaves remote identity unbound');
$answer['status']='REFUSED';$asaas->recoverPixAuthorization($b,7,$pixOrigin,'auth_synthetic_reserved',$dec,$transport);
$initialProof=$observe();essentialCheck($initialProof>0&&!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$pixInvoice])->row(),'canonical received observation is separate from local posting');
$receive=fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyPixInitialReceipt($pixOrigin,$initialProof));
$savedProof=$db->query('SELECT evidence_hash,created_at FROM '.$p.'asaas_company_proofs WHERE id=?',[$initialProof])->row_array();
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$initialProof]);essentialThrows($receive,'asaas_pix_initial_requires_review','altered canonical evidence denied');$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$savedProof['evidence_hash'],$initialProof]);
$db->query('UPDATE '.$p.'asaas_company_proofs SET created_at=? WHERE id=?',['2020-01-01 00:00:00',$initialProof]);essentialThrows($receive,'asaas_pix_initial_requires_review','expired initial proof requires new GET');$db->query('UPDATE '.$p.'asaas_company_proofs SET created_at=? WHERE id=?',[$savedProof['created_at'],$initialProof]);
$db->failOn='INSERT INTO '.$p.'asaas_company_receipts';essentialThrows($receive,'issuer_storage_failed','late provider link failure rolls back native financial receipt');$db->failOn=null;
essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$pixInvoice])->row(),'failed receipt has no native payment');
$in=['issuer'=>$b,'origin'=>$pixOrigin,'proof'=>$initialProof];$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'initial-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two initial payment writers contend on real native mutex');$db->trans_commit();$out=[];
 foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','initial receipt workers exit cleanly');}
 essentialCheck(ctype_digit($out[0])&&$out[0]===$out[1],'concurrent initial receipts return same native payment');$payment=(int)$out[0];
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
essentialCheck($receive()===$payment,'native first receipt retry is idempotent');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$pixInvoice])->row()->n===1,'exactly one native payment');
essentialCheck($db->query('SELECT channel FROM '.$p.'asaas_payment_origins WHERE id=?',[$pixOrigin])->row()->channel==='pix_mandate','receipt preserves mandate origin identity and history');
essentialCheck(!array_filter($initialCalls,fn($call)=>$call[0]!=='GET'),'only synthetic GET; no financial provider writes');
echo 'Pix initial receipt: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
