<?php
if(($argv[1]??'')==='cancel-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixRevocation.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 if(!preg_match('~^/tmp/pixcancel_[A-Za-z0-9]+$~D',$in['marker']))exit(2);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $transport=function($env,$method,$path,$payload,$secret)use($in){$answer=$in['answer'];if($method==='DELETE'){file_put_contents($in['marker'],"sent\n",FILE_APPEND|LOCK_EX);return ['success'=>true];}if(trim(file_get_contents($in['marker']))!=='')$answer['status']='CANCELLED';
  if($path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];if($path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$answer],'hasMore'=>false,'totalCount'=>1];if($path==='pix/automatic/authorizations/'.$answer['id'])return $answer;throw new RuntimeException('unexpected path');};
 try{echo (new \Asaas\CompanyPixRevocation($db,$p))->cancel($in['issuer'],7,$in['origin'],$in['revision'],true,fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$transport)['state'];}catch(DomainException $e){if($e->getMessage()!=='asaas_company_stale')throw $e;echo 'stale';}exit;
}
require __DIR__.'/asaas_pix_portal_enrollment.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixRevocation.php';
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.7','asaas']);
$start=$GLOBALS['essentialChecks'];$revoke=new Asaas\CompanyPixRevocation($db,$p);
foreach(['success','timeout','active_after_delete','withdraw_paid','audit_failure'] as $scenario){
 $invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-revoke-'.$scenario));
 $origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','pix-revoke-key-'.$scenario),'MONTHLY',$input['date']);
 $review=$consent->review($portal,$sessions[$b],$b,$invoice);$consent->accept($portal,$sessions[$b],$b,$invoice,$review['revision'],true);
 $source=json_decode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_reserve'])->row()->result_json,true);
 $answer=['id'=>'auth_revoke_'.$scenario,'customerId'=>'cus_b','contractId'=>$source['contract_id'],'frequency'=>$source['frequency'],'startDate'=>$source['start_date'],'status'=>'ACTIVE','immediateQrCode'=>['conciliationIdentifier'=>'revoke_'.$scenario,'expirationDate'=>'2030-01-01T12:00:00-03:00']];
 $deletes=0;$cancelCalls=0;$nested=null;$revision=$db->query('SELECT revision FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row()->revision;
 $transport=function($env,$method,$path,$payload,$secret)use($mock,$scenario,&$answer,&$deletes,&$cancelCalls,&$nested){$cancelCalls++;
  if($method==='DELETE'){$deletes++;if($nested){$call=$nested;$nested=null;$call();}if($scenario==='timeout')throw new RuntimeException('synthetic lost reply');if($scenario!=='active_after_delete')$answer['status']='CANCELLED';return ['success'=>true];}
  if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$answer],'hasMore'=>false,'totalCount'=>1];
  if($path==='pix/automatic/authorizations/'.$answer['id'])return $answer;return $mock($env,$method,$path,$payload,$secret);
 };
 $cancel=fn()=>$revoke->cancel($b,7,$origin,$revision,true,$dec,$transport);
 essentialThrows(fn()=>$revoke->cancel($a,7,$origin,$revision,true,$dec,$transport),'asaas_pix_reservation_denied','foreign cancellation denied');essentialCheck($cancelCalls===0,'foreign cancellation performs no remote call');
 essentialThrows(fn()=>$revoke->withdraw($portal,$sessions[$b],$b,$invoice,str_repeat('0',64),true),'asaas_pix_consent_stale','withdrawal requires exact accepted terms');
 if($scenario==='withdraw_paid'){
  $db->query('UPDATE '.$p.'invoices SET status=2 WHERE id=?',[$invoice]);$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=0 WHERE id=?',[$accounts[$b]]);
  essentialCheck(!$revoke->page($portal,$sessions[$b],$b,$invoice)['revoked'],'payer can review withdrawal after payment and staff/account disabled');
  $revoke->withdraw($portal,$sessions[$b],$b,$invoice,$review['revision'],true);$revoke->withdraw($portal,$sessions[$b],$b,$invoice,$review['revision'],true);
  essentialCheck($revoke->page($portal,$sessions[$b],$b,$invoice)['revoked'],'payer withdraws independently of former staff authority');
  essentialCheck($cancelCalls===0&&$deletes===0,'withdrawal is local, not a fictional remote cancellation');
  $db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');$db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=1 WHERE id=?',[$accounts[$b]]);$db->query('UPDATE '.$p.'invoices SET status=1 WHERE id=?',[$invoice]);
 }elseif($scenario==='audit_failure'){
  $db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($cancel,'issuer_storage_failed','withdrawal audit failure rolls back before transport');$db->failOn=null;
  essentialCheck($cancelCalls===0&&!$revoke->page($portal,$sessions[$b],$b,$invoice)['revoked'],'audit failure does not falsely revoke or send');$cancel();
 }else{
  $nested=function()use($cancel){essentialCheck($cancel()['state']==='cancellation_unconfirmed','concurrent reentry does not repeat claimed cancellation');};
  $out=$cancel();essentialCheck($out['state']===($scenario==='success'?'cancelled':($scenario==='timeout'?'cancellation_unconfirmed':'locally_revoked_remote_ACTIVE')),'canonical state not DELETE response determines cancellation');
  $cancel();essentialCheck($deletes===1,'retry never repeats DELETE');
  if($scenario!=='success'){$answer['status']='CANCELLED';essentialCheck($cancel()['state']==='cancelled','late canonical cancellation recovers unknown result');}
 }
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_revoke'])->row()->n===1,'one immutable revocation');
 essentialThrows(fn()=>$consent->accept($portal,$sessions[$b],$b,$invoice,$review['revision'],true),'asaas_pix_consent_revoked','old consent cannot reactivate');
 essentialThrows(fn()=>$consent->claim($portal,$sessions[$b],$b,$invoice,$review['revision']),'asaas_pix_consent_revoked','withdrawn consent cannot claim another send');
 essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row(),'revocation never writes financial receipt');
}
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-withdraw-http'));
$origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','pix-withdraw-http-key'),'MONTHLY',$input['date']);
$review=$consent->review($portal,$sessions[$b],$b,$invoice);$consent->accept($portal,$sessions[$b],$b,$invoice,$review['revision'],true);
$hash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->hash;
$detail->input->get=['issuer_id'=>(string)$b];$detail->input->verb='GET';$detail->input->post=[];
essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail),'withdrawal controller keeps private company scope');
$withdrawRender=function()use($detail,$invoice,$hash){ob_start();try{$detail->index((string)$invoice,$hash);return ob_get_contents();}finally{ob_end_clean();}};
$html=$withdrawRender();essentialCheck(str_contains($html,'asaas_pix_withdraw')&&str_contains($html,'pix_withdraw_confirm'),'native withdrawal action present only for accepted payer');
if(($file=getenv('ASAAS_PIX_WITHDRAW_HTML'))!==false&&$file!=='')file_put_contents($file,$html);
$detail->input->verb='POST';$detail->input->post=['native_payment_action'=>'asaas_pix_withdraw','pix_consent_revision'=>$review['revision'],'pix_withdraw_confirm'=>'1'];
essentialThrows($withdrawRender,'fixture_http_404','withdrawal mutation requires native CSRF');
$detail->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($detail);$detail->input->post['pix_withdraw_confirm']='false';essentialThrows($withdrawRender,'fixture_http_404','withdrawal needs exact confirmation');
$detail->input->post['pix_withdraw_confirm']='1';try{$withdrawRender();throw new RuntimeException('missing redirect');}catch(NativePortalRedirect $redirect){essentialCheck($redirect->status===303,'withdrawal redirects to private invoice');}
$detail->input->verb='GET';$detail->input->post=[];$html=$withdrawRender();essentialCheck(str_contains($html,'Consentimento revogado neste CRM')&&!str_contains($html,'value="asaas_pix_enroll"'),'revoked portal neither offers send nor claims bank cancellation');
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','pix-cancel-processes'));
$origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','pix-cancel-processes-key'),'MONTHLY',$input['date']);
$source=json_decode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_reserve'])->row()->result_json,true);
$answer=['id'=>'auth_process_cancel','customerId'=>'cus_b','contractId'=>$source['contract_id'],'frequency'=>$source['frequency'],'startDate'=>$source['start_date'],'status'=>'ACTIVE','immediateQrCode'=>['conciliationIdentifier'=>'process_cancel','expirationDate'=>'2030-01-01T12:00:00-03:00']];
$marker=tempnam('/tmp','pixcancel_');$workers=[];$in=['issuer'=>$b,'origin'=>$origin,'revision'=>$revision,'answer'=>$answer,'marker'=>$marker];
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'cancel-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'both cancellation workers wait on real native lock');$db->trans_commit();
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err===''&&in_array($out,['cancelled','cancellation_unconfirmed','stale'],true),'concurrent worker returns bounded canonical or uncertain result');}
 essentialCheck(file_get_contents($marker)==="sent\n",'two processes issue exactly one cancellation');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$origin,'pix_cancel_send'])->row()->n===1,'two processes share immutable cancel intent');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}unlink($marker);}
echo 'Pix revocation: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; simulated provider only.'.PHP_EOL;
