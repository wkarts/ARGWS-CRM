<?php
/** Native cancellation with real SQL and simulated provider; never real charges. */
require __DIR__.'/asaas_remote_operations.php';
require_once APPPATH.'services/CompanyAsaasInvoiceCancellation.php';
$start=$GLOBALS['essentialChecks'];$cases=[];
foreach(['success','timeout','rollback','revoked','stale','restored','race'] as $name)$cases[$name]=asaasUnpaid($b,$customerB,'cus_b','native-cancel-'.$name);
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);unset($db->data_cache['table_names']);
$db->query("UPDATE {$p}modules SET installed_version='1.6.3' WHERE module_name='asaas'");
$cancel=new CompanyAsaasInvoiceCancellation($db,$p);$invoiceService=new CompanyInvoices($db,$p);
[$id,$origin,$remote]=$cases['success'];$revision=$invoiceService->revision($b,7,$id);$deleteMode='normal';$before=$deletes;
$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport);
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5&&$deletes===$before+1,'canonical remote deletion precedes native invoice cancellation');
$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport);
essentialCheck($deletes===$before+1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE subject_id=? AND event='company_asaas_invoice_cancelled'",[$id])->row()->n===1,'retry preserves one remote request and one cancellation receipt');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row()->n===1,'origin remains immutable history after cancellation');
essentialThrows(fn()=>$cancel->cancel($a,7,$id,$revision,$dec,$removeTransport),'asaas_company_origin_denied','other company cannot cancel or recover this invoice');
[$id,$origin,$remote]=$cases['timeout'];$revision=$invoiceService->revision($b,7,$id);$deleteMode='timeout';$before=$deletes;
essentialThrows(fn()=>$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport),'asaas_invoice_cancellation_pending','unknown DELETE cannot become native success');
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status!==5,'unknown remote result leaves local invoice active');
essentialThrows(fn()=>$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport),'asaas_invoice_cancellation_pending','unknown retry remains pending');essentialCheck($deletes===$before+1,'unknown retry never repeats DELETE');
$responses[$remote]['deleted']=true;$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport);essentialCheck($deletes===$before+1&&(int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'unknown result recovered by canonical GET without repeating DELETE');
[$id,$origin,$remote]=$cases['rollback'];$revision=$invoiceService->revision($b,7,$id);$deleteMode='normal';$db->failOn='UPDATE '.$p.'invoices SET status=5';$before=$deletes;
essentialThrows(fn()=>$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport),'issuer_storage_failed','native write failure remains an error after remote deletion');$db->failOn=null;
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status!==5&&$removal->inspect($b,7,$origin)['state']==='deleted','remote success remains durable while local transaction rolls back');
$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport);essentialCheck($deletes===$before+1&&(int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'recovery completes local cancellation without another DELETE');
[$id,$origin,$remote]=$cases['stale'];$before=$deletes;
essentialThrows(fn()=>$cancel->cancel($b,7,$id,str_repeat('f',64),$dec,$removeTransport),'company_invoice_stale','stale native form is refused before remote mutation');essentialCheck($before===$deletes,'stale form emits no DELETE');
[$id,$origin,$remote]=$cases['revoked'];$revision=$invoiceService->revision($b,7,$id);$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$before=$deletes;
essentialThrows(fn()=>$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport),'asaas_company_access_denied','revoked actor cannot cancel');essentialCheck($before===$deletes,'revocation emits no DELETE');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
[$id,$origin,$remote]=$cases['restored'];$revision=$invoiceService->revision($b,7,$id);$key=hash('sha256','company-asaas-delete:'.$b.':'.$origin);$removal->remove($b,7,$origin,$key,$dec,$removeTransport);$responses[$remote]['deleted']=false;$before=$deletes;
essentialThrows(fn()=>$cancel->cancel($b,7,$id,$revision,$dec,$removeTransport),'asaas_invoice_cancellation_unconfirmed','old deletion ledger cannot authorize cancellation after provider restoration');essentialCheck($before===$deletes&&(int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status!==5,'restored charge stays available for explicit review, with no blind deletion retry');
echo 'Native Asaas cancellation: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().'). Provider simulated.'.PHP_EOL;

if($db->driver()==='mysql'){
 [$id,$origin,$remote]=$cases['race'];$revision=$invoiceService->revision($b,7,$id);
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$evidence='/tmp/asaas-remote-test-'.bin2hex(random_bytes(10)).'.jsonl';
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_remote_concurrency.php','worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['operation'=>'cancel','issuer'=>$b,'invoice'=>$id,'origin'=>$origin,'revision'=>$revision,'evidence'=>$evidence],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two native cancellation workers wait on the actual financial lock');$db->trans_commit();$outputs=[];
  foreach($workers as [$proc,$pipes]){$outputs[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','independent cancellation worker exits without diagnostics');}
  essentialCheck(count(is_file($evidence)?file($evidence,FILE_IGNORE_NEW_LINES):[])===1,'concurrent native cancellation sends exactly one DELETE');
  essentialCheck(in_array('cancelled',$outputs,true)&&(int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'one confirmed cancellation completes while the competitor observes success or pending');
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE subject_id=? AND event='company_asaas_invoice_cancelled'",[$id])->row()->n===1,'concurrent cancellation has one durable local receipt');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_file($evidence))unlink($evidence);}
 echo 'Native Asaas cancellation concurrency: six additional real MySQL checks passed.'.PHP_EOL;
}
