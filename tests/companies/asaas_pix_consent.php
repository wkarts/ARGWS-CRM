<?php
if(($argv[1]??'')==='claim-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixConsent.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));
 try{echo json_encode((new \Asaas\CompanyPixConsent($db,$p))->claim($portal,$in['session'],$in['issuer'],$in['invoice'],$in['revision']),JSON_THROW_ON_ERROR);}catch(DomainException $e){if($e->getMessage()!=='asaas_pix_portal_denied')throw $e;echo 'denied';}exit;
}
require __DIR__.'/asaas_pix_recovery.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixConsent.php';
require_once APPPATH.'services/CompanyContacts.php';require_once APPPATH.'services/CompanyContactInvoiceAccess.php';
$start=$GLOBALS['essentialChecks'];$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['contacts','contact_permissions'] as $name){preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);$sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$sql);$db->query($sql);}
$db->query("CREATE TABLE {$p}company_portal_tokens(token_hash varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,contact_id int NOT NULL,client_id int NOT NULL,identity_hash varchar(64) NOT NULL,expires_at datetime NOT NULL,created_at datetime NOT NULL)");unset($db->data_cache['table_names']);
$contacts=new CompanyContacts($db,$p);$permissions=new CompanyContactInvoiceAccess($db,$p);$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$sessions=[];$contactIds=[];
foreach([$a=>$ca,$b=>$customerB] as $issuer=>$client){$person=['firstname'=>'Synthetic','lastname'=>'Pix','email'=>'pix@example.invalid'];$contact=$contacts->save($issuer,7,$client,$person);$contactIds[$issuer]=$contact;$db->query('UPDATE '.$p.'contacts SET password=?,email_verified_at=? WHERE id=?',[password_hash('synthetic-password',PASSWORD_DEFAULT),'2026-01-01 00:00:00',$contact]);$sessions[$issuer]=$portal->login($issuer,$person['email'],'synthetic-password','password_verify');}
$consent=new \Asaas\CompanyPixConsent($db,$p);$review=fn()=>$consent->review($portal,$sessions[$b],$b,$failedInvoice);
essentialThrows($review,'asaas_pix_portal_denied','contact financial permission is explicit');$allow=$permissions->page($b,7,$customerB)[$contactIds[$b]];$permissions->set($b,7,$customerB,$contactIds[$b],$allow['revision'],'1');
$r=$review();essentialCheck($r['terms']['amount']===$autoSource['amount']&&$r['terms']['frequency']==='MONTHLY','review binds exact reserved invoice and frequency');
essentialThrows(fn()=>$consent->accept($portal,$sessions[$b],$b,$failedInvoice,$r['revision'],false),'asaas_pix_explicit_consent_required','false acceptance never infers consent');
essentialThrows(fn()=>$consent->accept($portal,$sessions[$b],$b,$failedInvoice,$r['revision'],'false'),'asaas_pix_explicit_consent_required','string input is never coerced into consent');
essentialThrows(fn()=>$consent->accept($portal,$sessions[$b],$b,$failedInvoice,str_repeat('0',64),true),'asaas_pix_consent_stale','stale review rejected');
essentialThrows(fn()=>$consent->review($portal,$sessions[$a],$b,$failedInvoice),'asaas_pix_portal_denied','foreign session cannot inspect consent');
essentialThrows(fn()=>$consent->claim($portal,$sessions[$b],$b,$failedInvoice,$r['revision']),'asaas_pix_explicit_consent_required','reservation or remote state never grants send permission');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$consent->accept($portal,$sessions[$b],$b,$failedInvoice,$r['revision'],true),'issuer_storage_failed','audit failure rolls back consent');$db->failOn=null;
essentialCheck(!$db->query('SELECT id FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$autoOrigin,'pix_consent'])->row(),'failed consent leaves no proof');
$id=$consent->accept($portal,$sessions[$b],$b,$failedInvoice,$r['revision'],true);essentialCheck($consent->accept($portal,$sessions[$b],$b,$failedInvoice,$r['revision'],true)===$id,'consent retry is idempotent');
$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=?',[$contactIds[$b]]);essentialThrows($review,'asaas_pix_portal_denied','unverified contact cannot reuse old consent');$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contactIds[$b]]);
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');essentialThrows($review,'asaas_company_access_denied','revoked reservation actor prevents consent use');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
essentialCheck($db->query('SELECT state,remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$autoOrigin])->row_array()===['state'=>'reserved','remote_id'=>null],'consent alone does not send or activate mandate');
$known=$consent->claim($portal,$sessions[$b],$b,$failedInvoice,$r['revision']);essentialCheck($known['claimed']===false&&$known['state']==='authorization_observed','known remote authorization can never trigger another send');
$claimInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-claim-invoice'));
$claimOrigin=$asaas->reservePixAuthorization($b,7,$claimInvoice,$accounts[$b],'cus_b',$s->revision($b,7,$claimInvoice),hash('sha256','pix-claim-key'),'MONTHLY',$input['date']);
$claimReview=$consent->review($portal,$sessions[$b],$b,$claimInvoice);$consent->accept($portal,$sessions[$b],$b,$claimInvoice,$claimReview['revision'],true);
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$consent->claim($portal,$sessions[$b],$b,$claimInvoice,$claimReview['revision']),'issuer_storage_failed','failed send audit rolls back claim');$db->failOn=null;
$claim=$consent->claim($portal,$sessions[$b],$b,$claimInvoice,$claimReview['revision']);$retry=$consent->claim($portal,$sessions[$b],$b,$claimInvoice,$claimReview['revision']);essentialCheck($claim['claimed']===true&&$retry['claimed']===false&&$claim['proof_id']===$retry['proof_id']&&$retry['state']==='recovery_required','exactly one persisted send claim, retry requires recovery');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$claimOrigin,'pix_send'])->row()->n===1,'one durable send intention');
foreach(['retry','revocation'] as $mode){
 $next=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-claim-concurrent-'.$mode));$nextOrigin=$asaas->reservePixAuthorization($b,7,$next,$accounts[$b],'cus_b',$s->revision($b,7,$next),hash('sha256','pix-claim-concurrent-key-'.$mode),'MONTHLY',$input['date']);$nextReview=$consent->review($portal,$sessions[$b],$b,$next);$consent->accept($portal,$sessions[$b],$b,$next,$nextReview['revision'],true);
 $in=['session'=>$sessions[$b],'issuer'=>$b,'invoice'=>$next,'revision'=>$nextReview['revision']];$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'claim-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two send claim workers wait on native mutex');
  if($mode==='revocation')$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=?',[$contactIds[$b]]);$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','send claim workers exit cleanly');}
  if($mode==='retry'){$values=array_map(fn($v)=>json_decode($v,true,16,JSON_THROW_ON_ERROR),$out);essentialCheck(count(array_filter($values,fn($v)=>$v['claimed']))===1&&$values[0]['proof_id']===$values[1]['proof_id'],'only one concurrent claimant can send');}else essentialCheck($out===['denied','denied'],'verification revoked under lock denies both senders');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$nextOrigin,'pix_send'])->row()->n===($mode==='retry'?1:0),'exact send intention count after contention');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contactIds[$b]]);}
}
foreach(['success','timeout','revoked'] as $scenario){
 $sendInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-send-'.$scenario));$sendOrigin=$asaas->reservePixAuthorization($b,7,$sendInvoice,$accounts[$b],'cus_b',$s->revision($b,7,$sendInvoice),hash('sha256','pix-send-key-'.$scenario),'MONTHLY',$input['date']);$sendReview=$consent->review($portal,$sessions[$b],$b,$sendInvoice);$consent->accept($portal,$sessions[$b],$b,$sendInvoice,$sendReview['revision'],true);$posts=0;$reply=null;
 $sender=function($env,$method,$path,$payload,$secret)use($mock,$scenario,&$posts,&$reply,$db,$p,$contactIds,$b){
  if($method==='POST'&&$path==='pix/automatic/authorizations'){
   ++$posts;essentialCheck($payload['paymentCreationMode']==='MANUAL'&&$payload['retryPolicy']==='NOT_ALLOWED','typed single mandate request');
   $reply=['id'=>'auth_send_'.$scenario,'customerId'=>$payload['customerId'],'contractId'=>$payload['contractId'],'frequency'=>$payload['frequency'],'startDate'=>$payload['startDate'],'status'=>'CREATED','immediateQrCode'=>['conciliationIdentifier'=>'send_'.$scenario,'expirationDate'=>'2030-01-01T12:00:00-03:00'],'payload'=>'000201synthetic-never-a-real-code','encodedImage'=>''];
   if($scenario==='timeout')throw new RuntimeException('synthetic private upstream error');
   if($scenario==='revoked')$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=?',[$contactIds[$b]]);return $reply;
  }
  if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$reply],'hasMore'=>false,'totalCount'=>1];
  if($path==='pix/automatic/authorizations/auth_send_'.$scenario)return $reply;
  return $mock($env,$method,$path,$payload,$secret);
 };
 $send=fn()=>$consent->dispatch($portal,$sessions[$b],$b,$sendInvoice,$sendReview['revision'],$dec,$sender);
 if($scenario==='revoked'){essentialThrows($send,'asaas_pix_portal_denied','post-flight revocation prevents response delivery');$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contactIds[$b]]);}else{$sent=$send();essentialCheck($sent['state']===($scenario==='timeout'?'uncertain':'authorization_observed'),'send outcome reflects canonical observation or uncertainty');}
 $again=$send();essentialCheck($posts===1&&in_array($again['state'],['authorization_observed','recovery_required'],true),'repeat never sends a second POST');
 $evidence=$db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$sendOrigin,'pix_result'])->row()->result_json;essentialCheck(!str_contains($evidence,'synthetic-never-a-real-code')&&!str_contains($evidence,'private upstream error'),'technical result excludes QR and provider exception');
 $recovered=$asaas->recoverPixAuthorization($b,7,$sendOrigin,'',$dec,$sender);essentialCheck($recovered['remote_id']==='auth_send_'.$scenario&&$posts===1,'unknown or revoked send recovers by GET without duplication');
 essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$sendInvoice])->row(),'authorization never becomes a financial receipt');
}
echo 'Pix portal consent: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; synthetic provider only, no real financial operations.'.PHP_EOL;
