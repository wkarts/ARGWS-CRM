<?php
require __DIR__.'/asaas_recreation_intents.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyOriginGenerations.php';
if($db->driver()!=='mysql'){echo "Generation DDL requires MySQL; not exercised on SQLite.\n";return;}
$start=$GLOBALS['essentialChecks'];
$before=$db->query("SELECT * FROM {$p}asaas_payment_origins ORDER BY id")->result_array();
\Asaas\CompanyOriginGenerations::ensure($db,$p);
\Asaas\CompanyOriginGenerations::ensure($db,$p);
\Asaas\CompanyOriginGenerations::ensure($db,$p,false);
$after=$db->query("SELECT * FROM {$p}asaas_payment_origins ORDER BY id")->result_array();
foreach($after as &$row){essentialCheck((int)$row['active_slot']===1,'existing identity remains active');unset($row['active_slot']);}unset($row);
essentialCheck($before===$after,'upgrade preserves every historical identity byte');
$db->trans_begin();
try{
 $original=$before[0];$fields=array_keys($original);$fields=array_values(array_diff($fields,['id']));
 $sql='INSERT INTO '.$p.'asaas_payment_origins('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')';
 $copy=$original;$copy['remote_id']=null;$copy['state']='issuing';
 $values=array_map(fn($field)=>$copy[$field],$fields);
 try{$db->query($sql,$values);$duplicateBlocked=false;}catch(PDOException $error){$duplicateBlocked=$error->getCode()==='23000';}essentialCheck($duplicateBlocked,'second active identity denied by real unique key');
 $db->query("UPDATE {$p}asaas_payment_origins SET active_slot=NULL WHERE id=?",[$original['id']]);
 $db->query($sql,$values);$successor=(int)$db->insert_id();
 essentialCheck($successor!==(int)$original['id'],'successor gets distinct identity');
 $old=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$original['id']])->row_array();
 essentialThrows(fn()=>\Asaas\CompanyOriginGenerations::assertActive($old),'asaas_company_origin_retired','retired origin denied for active writes');
 unset($old['active_slot']);essentialCheck($old===$original,'retirement never overwrites original remote scope');
 $db->trans_rollback();
}catch(Throwable $error){if($db->pdo->inTransaction())$db->trans_rollback();throw $error;}
$restored=$db->query("SELECT * FROM {$p}asaas_payment_origins ORDER BY id")->result_array();
essentialCheck(count($restored)===count($before)&&(int)$restored[0]['active_slot']===1,'rollback restores current origin without deleting history');
echo 'Asaas generations: '.($GLOBALS['essentialChecks']-$start).' MySQL checks.'.PHP_EOL;
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRecreationSuccessors.php';
$db->query("UPDATE {$p}modules SET installed_version='1.6.5' WHERE module_name='asaas'");
$asaas->query($b,7,$origin,$dec,$removeTransport);
$successors=new \Asaas\CompanyRecreationSuccessors($db,$p);
$original=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array();
$key=hash('sha256','recreation-intent-'.$origin);
$db->failOn='INSERT INTO '.$p.'asaas_company_origin_meta';
essentialThrows(fn()=>$successors->prepare($b,7,$id,$origin,$revision,$key),'issuer_storage_failed','failed successor metadata rolls back retirement and creation');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array()===$original,'old origin remains current after failed successor');
$next=$successors->prepare($b,7,$id,$origin,$revision,$key);
essentialCheck($next!==$origin&&$successors->prepare($b,7,$id,$origin,$revision,$key)===$next,'successor is distinct and repeated preparation is idempotent');
$old=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array();$original['active_slot']=null;
essentialCheck($old===$original,'only active slot changes in historical origin');
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'preparation does not reopen cancelled invoice');
essentialThrows(fn()=>$asaas->query($b,7,$origin,$dec,$removeTransport),'asaas_company_origin_retired','retired source cannot perform current operations');
essentialThrows(fn()=>$successors->prepare($a,7,$id,$origin,$revision,$key),'asaas_recreation_conflict','successor replay cannot cross companies');
echo 'Asaas successor preparation: '.($GLOBALS['essentialChecks']-$start).' cumulative generation checks.'.PHP_EOL;
require_once APPPATH.'services/CompanyAsaasRecreationSource.php';
$oldProof=$db->query("SELECT proof_id FROM {$p}asaas_company_recreations WHERE origin_id=?",[$origin])->row()->proof_id;
$proofTime=$db->query("SELECT created_at FROM {$p}asaas_company_proofs WHERE id=?",[$oldProof])->row()->created_at;
$db->query("UPDATE {$p}asaas_company_proofs SET created_at=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-601),$oldProof]);
essentialThrows(fn()=>$asaas->dispatchRecreation($b,7,$origin,$key,$dec,$mock),'asaas_recreation_fresh_query_required','unsent preparation cannot dispatch on expired deletion proof');
$db->query("UPDATE {$p}asaas_company_proofs SET created_at=? WHERE id=?",[$proofTime,$oldProof]);
$recreatePosts=0;$recoveredRemote=null;
$recreateTransport=function($environment,$method,$path,$payload,$secret)use($mock,&$recreatePosts,&$recoveredRemote,$db,$p,$origin){
 if($method==='POST'){
  $recreatePosts++;essentialCheck($db->query("SELECT state FROM {$p}asaas_company_recreations WHERE origin_id=?",[$origin])->row()->state==='sending','durable sending state precedes provider request');
  $response=$mock($environment,$method,$path,$payload,$secret);$recoveredRemote=$response['id'];throw new RuntimeException('synthetic response lost after provider accepted');
 }
 return $mock($environment,$method,$path,$payload,$secret);
};
essentialThrows(fn()=>$asaas->dispatchRecreation($b,7,$origin,$key,$dec,$recreateTransport),'asaas_recreation_post_unconfirmed','lost creation response remains uncertain');
essentialThrows(fn()=>$asaas->dispatchRecreation($b,7,$origin,$key,$dec,$recreateTransport),'asaas_recreation_recovery_required','retry requires canonical ID without another POST');
essentialCheck($recreatePosts===1&&(int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'uncertain result never reopens invoice or repeats POST');
essentialCheck($asaas->dispatchRecreation($b,7,$origin,$key,$dec,$recreateTransport,$recoveredRemote)===$next,'uncertain result recovered by canonical GET');
$source=new CompanyAsaasRecreationSource($db,$p,$b,7,$origin,$key);
$db->failOn='INSERT INTO '.$p.'company_financial_events';
essentialThrows(fn()=>$invoiceService->reopen($b,7,$id,$revision,$source),'issuer_storage_failed','journal failure rolls back reopening');$db->failOn=null;
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5&&$db->query("SELECT state FROM {$p}asaas_company_recreations WHERE origin_id=?",[$origin])->row()->state==='uncertain','failed journal keeps cancelled invoice and durable remote success');
$invoiceService->reopen($b,7,$id,$revision,$source);$invoiceService->reopen($b,7,$id,$revision,$source);
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status!==5&&$db->query("SELECT state FROM {$p}asaas_company_recreations WHERE origin_id=?",[$origin])->row()->state==='reopened','native status and recreation receipt commit together');
essentialCheck($recreatePosts===1&&(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_invoice_reopened' AND subject_id=?",[$id])->row()->n===1,'replay has one POST and one native reopening');
echo 'Asaas recreation dispatch/reopening: '.($GLOBALS['essentialChecks']-$start).' cumulative generation checks.'.PHP_EOL;
// Separate processes contend on the same actual financial lock and perform exactly one simulated POST.
[$raceInvoice,$raceOrigin]=$cases['rollback'];$asaas->query($b,7,$raceOrigin,$dec,$removeTransport);
$raceRevision=$invoiceService->revision($b,7,$raceInvoice);$raceKey=hash('sha256','concurrent-recreation-intent');
$raceSuccessor=$successors->prepare($b,7,$raceInvoice,$raceOrigin,$raceRevision,$raceKey);
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$evidence='/tmp/asaas-remote-test-'.bin2hex(random_bytes(10)).'.jsonl';$workers=[];
$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
try{
 for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_remote_concurrency.php','worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['operation'=>'recreate','issuer'=>$b,'origin'=>$raceOrigin,'key'=>$raceKey,'evidence'=>$evidence],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two recreation POST contenders wait on real native lock');$db->trans_commit();$out=[];
 foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','independent recreation worker has no diagnostics: '.$err);}
 essentialCheck(count(is_file($evidence)?file($evidence,FILE_IGNORE_NEW_LINES):[])===1,'concurrent recreation sends exactly one POST');
 essentialCheck(in_array((string)$raceSuccessor,$out,true),'one contender resolves successor and competitor observes current result or pending');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_file($evidence))unlink($evidence);}
echo 'Asaas recreation: '.($GLOBALS['essentialChecks']-$start).' cumulative MySQL generation/failure/concurrency checks.'.PHP_EOL;
require_once APPPATH.'services/CompanyAsaasInvoiceRecreation.php';
$coordinator=new CompanyAsaasInvoiceRecreation($db,$p);
$cancel->cancel($b,7,$id,$invoiceService->revision($b,7,$id),$dec,$removeTransport);
$secondRevision=$invoiceService->revision($b,7,$id);$postCount=count(array_filter($calls,fn($r)=>$r[1]==='POST'));
$coordinator->reopen($b,7,$id,$secondRevision,$dec,$mock);$coordinator->reopen($b,7,$id,$secondRevision,$dec,$mock);
essentialCheck(count(array_filter($calls,fn($r)=>$r[1]==='POST'))===$postCount+1,'second complete native cycle creates exactly one new charge including replay');
$chain=$db->query("SELECT id,remote_id,active_slot FROM {$p}asaas_payment_origins WHERE invoice_id=? ORDER BY id",[$id])->result_array();
essentialCheck(count($chain)===3&&count(array_filter($chain,fn($r)=>(int)$r['active_slot']===1))===1,'multiple recreation cycles preserve three distinct identities and one active origin');
essentialCheck(count(array_unique(array_column($chain,'remote_id')))===3,'historical remote identifiers are never overwritten');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>$coordinator->reopen($b,7,$id,$secondRevision,$dec,$mock),'asaas_company_access_denied','completed recreation replay still requires current permission');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
echo 'Asaas complete native cycle: '.($GLOBALS['essentialChecks']-$start).' cumulative checks.'.PHP_EOL;
$latest=end($chain);$responses[$latest['remote_id']]['status']='RECEIVED';$responses[$latest['remote_id']]['paymentDate']='2026-10-19';$responses[$latest['remote_id']]['creditDate']='2026-10-19';
$receivedProof=$asaas->query($b,7,(int)$latest['id'],$dec,$mock);
$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt((int)$latest['id'],$receivedProof));
essentialCheck($payment>0&&$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt((int)$latest['id'],$receivedProof))===$payment,'active successor posts one native receipt despite retained predecessors');
$oldMeta=$db->query("SELECT account_row FROM {$p}asaas_company_origin_meta WHERE origin_id=?",[$origin])->row_array();
$db->query("INSERT INTO {$p}asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,'observed',?)",[$oldMeta['account_row'],'evt_retired_received','PAYMENT_RECEIVED',$chain[0]['remote_id'],hash('sha256','retired-received'),gmdate('Y-m-d H:i:s')]);
essentialThrows(fn()=>$asaas->query($b,7,(int)$latest['id'],$dec,$mock),'asaas_recreation_historical_event_review','late financial event of predecessor fences current origin for review');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$id])->row()->n===1,'late predecessor event cannot duplicate native payment');
echo 'Asaas successor receipt/history: '.($GLOBALS['essentialChecks']-$start).' cumulative MySQL checks.'.PHP_EOL;
