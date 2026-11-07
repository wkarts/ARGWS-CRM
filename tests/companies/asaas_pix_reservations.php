<?php
if(($argv[1]??'')==='reserve-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyService.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 try{echo (new \Asaas\CompanyService($db,$p))->reservePixAuthorization($in['issuer'],7,$in['invoice'],$in['account'],'cus_b',$in['revision'],$in['key'],'MONTHLY',$in['date']);}catch(DomainException $e){if($e->getMessage()!=='asaas_company_access_denied')throw $e;echo $e->getMessage();}exit;
}
register_shutdown_function(static function(){global $db,$p;if(isset($db,$p)&&preg_match('/^essential_[a-f0-9]{10}_$/D',$p))$db->query('DROP TABLE IF EXISTS '.$p.'asaas_company_invoice_edits');});
require __DIR__.'/asaas_origin_policy.php';
$start=$GLOBALS['essentialChecks'];
\Asaas\CompanyOriginGenerations::ensure($db,$p,true);
\Asaas\CompanyInvoiceEditSchema::ensure($db,$p,true);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.3','asaas']);
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-reservation-invoice'));
$revision=$s->revision($b,7,$invoice);$key=hash('sha256','pix-reservation');
$reserve=fn()=>$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$revision,$key,'MONTHLY',$input['date']);
$origin=$reserve();essentialCheck($reserve()===$origin,'reservation retry returns same durable origin');
$stored=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array();
essentialCheck($stored['channel']==='pix_mandate'&&$stored['remote_id']===null&&$stored['state']==='reserved','dedicated invoice exclusion, no remote claim');
essentialCheck(\Asaas\CompanyOriginPolicy::isManual($db,$p,$origin),'reservation cannot activate ordinary automatic receipt');
essentialThrows(fn()=>$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$revision,hash('sha256','other-key'),'MONTHLY',$input['date']),'asaas_company_origin_conflict','different request cannot adopt reservation');
essentialThrows(fn()=>$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$revision,$key,$dec,$mock),'asaas_company_origin_conflict','ordinary issuance cannot bypass dedicated reservation even with same key');
essentialThrows(fn()=>$asaas->reservePixAuthorization($a,7,$invoice,$accounts[$b],'cus_b',$revision,$key,'MONTHLY',$input['date']),'asaas_company_access_denied','foreign account denied');
essentialThrows(fn()=>$writer->recordCompanyManual($b,7,$invoice,['amount'=>'1.00','date'=>$input['date'],'reference'=>'synthetic-pix-reserved'],hash('sha256','pix-manual-block')),'company_payment_provider_review_required','reservation blocks native manual receipt');
$proof=$db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_reserve'])->row_array();$source=json_decode($proof['result_json'],true);
$answer=['id'=>'auth_synthetic_reserved','customerId'=>$source['customer_id'],'contractId'=>$source['contract_id'],'frequency'=>$source['frequency'],'startDate'=>$source['start_date'],'status'=>'CREATED','immediateQrCode'=>['conciliationIdentifier'=>'synthetic_conciliation','expirationDate'=>'2030-01-01T12:00:00-03:00'],'payload'=>'000201synthetic-never-a-real-code','encodedImage'=>''];
$requests=[];$after=null;
$transport=function($env,$method,$path,$body,$secret)use($mock,&$requests,&$answer,&$after){$requests[]=[$method,$path];if(str_starts_with($path,'pix/automatic/authorizations/')){if($after)$after();return $answer;}return $mock($env,$method,$path,$body,$secret);};
$recover=fn()=>$asaas->recoverPixAuthorization($b,7,$origin,'auth_synthetic_reserved',$dec,$transport);
$payments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();$jobs=$db->query('SELECT * FROM '.$p.'asaas_company_jobs ORDER BY event_id')->result_array();
essentialThrows(fn()=>$asaas->recoverPixAuthorization($a,7,$origin,'auth_synthetic_reserved',$dec,$transport),'asaas_pix_reservation_denied','cross-company origin denied before provider lookup');essentialCheck($requests===[],'cross-company recovery performs no HTTP');
$out=$recover();essentialCheck(!isset($out['qr'])&&$out['status']==='CREATED','recovery does not publish or persist QR');
$proofCount=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=?',[$origin])->row()->n;$recover();essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=?',[$origin])->row()->n===$proofCount,'same canonical recovery does not append duplicate proof');
essentialCheck(!array_filter($requests,fn($r)=>$r[0]!=='GET'),'recovery only queries, never repeats POST');
$answer['contractId']='other';essentialThrows($recover,'asaas_pix_authorization_conflict','foreign remote contract rejected');$answer['contractId']=$source['contract_id'];
$after=fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');essentialThrows($recover,'asaas_company_access_denied','revoked actor after GET cannot publish observation');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');$after=null;
$answer['status']='ACTIVE';$recover();$answer['status']='CREATED';essentialThrows($recover,'asaas_pix_authorization_conflict','out of order state does not regress');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$payments&&$db->query('SELECT * FROM '.$p.'asaas_company_jobs ORDER BY event_id')->result_array()===$jobs,'active observation grants neither payment nor financial job');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array()===$stored,'recovery preserves invoice reservation and origin identity');
$failedInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-reserve-rollback'));
$failedRevision=$s->revision($b,7,$failedInvoice);$failedKey=hash('sha256','pix-reserve-rollback-key');$failedReserve=fn()=>$asaas->reservePixAuthorization($b,7,$failedInvoice,$accounts[$b],'cus_b',$failedRevision,$failedKey,'MONTHLY',$input['date']);
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($failedReserve,'issuer_storage_failed','late reservation audit failure rolls back entire intention');$db->failOn=null;
essentialCheck(!$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?',[$failedInvoice])->row(),'failed reservation leaves no blocking origin');essentialCheck($failedReserve()>0,'reservation retry after rollback succeeds');
$savedProof=$db->query('SELECT id,evidence_hash FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1',[$origin,'pix_recover'])->row_array();$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$savedProof['id']]);
essentialThrows($recover,'asaas_pix_reservation_conflict','corrupted historical recovery proof rejected');$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$savedProof['evidence_hash'],$savedProof['id']]);
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['retry','revocation'] as $mode){
 $next=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-contender-'.$mode));$in=['issuer'=>$b,'invoice'=>$next,'account'=>$accounts[$b],'revision'=>$s->revision($b,7,$next),'key'=>hash('sha256','pix-contender-key-'.$mode),'date'=>$input['date']];$workers=[];
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'reserve-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'both reservation writers wait on actual native lock');if($mode==='revocation')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','reservation workers terminate cleanly');}
  essentialCheck($out[0]===$out[1]&&($mode==='retry'?ctype_digit($out[0]):$out[0]==='asaas_company_access_denied'),'concurrent retry shares one origin; revoked workers denied');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_payment_origins WHERE invoice_id=?',[$next])->row()->n===($mode==='retry'?1:0),'exact durable origin count after contention');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
}
echo 'Pix invoice reservations: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; only synthetic GET, no enrollment send.'.PHP_EOL;
