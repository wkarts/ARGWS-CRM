<?php
/** Durable intent only; no charge creation or native reactivation is claimed. */
require __DIR__.'/asaas_invoice_cancellation.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRecreationIntents.php';
$start=$GLOBALS['essentialChecks'];
\Asaas\CompanyRecreationSchema::ensure($db,$p);\Asaas\CompanyRecreationSchema::ensure($db,$p,false);
$db->query("UPDATE {$p}modules SET installed_version='1.6.4' WHERE module_name='asaas'");
[$id,$origin,$remote]=$cases['success'];$asaas->query($b,7,$origin,$dec,$removeTransport);
$revision=$invoiceService->revision($b,7,$id);$key=hash('sha256','recreation-intent-'.$origin);
$intents=new \Asaas\CompanyRecreationIntents($db,$p);
$beforeOrigin=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array();
$beforeEvents=$db->query("SELECT * FROM {$p}asaas_company_events ORDER BY id")->result_array();$beforeDeletes=$deletes;
$intent=$intents->reserve($b,7,$id,$origin,$revision,$key);
essentialCheck($intent===['origin_id'=>$origin,'invoice_id'=>$id,'state'=>'reserved','charge_created'=>false,'invoice_reopened'=>false],'intent explicitly distinguishes reservation from recreation');
essentialCheck($intents->reserve($b,7,$id,$origin,$revision,$key)===$intent,'same command is idempotent');
essentialCheck($db->query("SELECT * FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row_array()===$beforeOrigin&&$db->query("SELECT * FROM {$p}asaas_company_events ORDER BY id")->result_array()===$beforeEvents,'original identity and historical events unchanged');
essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5&&$deletes===$beforeDeletes,'reservation never reopens invoice or deletes charge');
essentialThrows(fn()=>$intents->reserve($b,7,$id,$origin,$revision,hash('sha256','another-command')),'asaas_recreation_conflict','another key cannot reserve another recreation for same origin');
essentialThrows(fn()=>$intents->reserve($a,7,$id,$origin,$revision,$key),'asaas_recreation_stale','foreign invoice not visible');
essentialThrows(fn()=>$intents->reserve($b,7,$id,$origin,str_repeat('a',64),$key),'asaas_recreation_stale','stale cancelled invoice rejected');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>$intents->reserve($b,7,$id,$origin,$revision,$key),'asaas_company_access_denied','replay revalidates current permission');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
[$nextId,$nextOrigin,$nextRemote]=$cases['rollback'];$asaas->query($b,7,$nextOrigin,$dec,$removeTransport);$nextRevision=$invoiceService->revision($b,7,$nextId);
$db->failOn='INSERT INTO '.$p.'asaas_company_recreations';essentialThrows(fn()=>$intents->reserve($b,7,$nextId,$nextOrigin,$nextRevision,hash('sha256','intent-rollback')),'issuer_storage_failed','failed intent write rolls back');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}asaas_company_recreations WHERE origin_id=?",[$nextOrigin])->result_array()===[],'failed write leaves no partial intent');
$proof=$db->query("SELECT id FROM {$p}asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1",[$origin])->row()->id;
$db->query("UPDATE {$p}asaas_company_proofs SET created_at=? WHERE id=?",[gmdate('Y-m-d H:i:s',time()-601),$proof]);
essentialThrows(fn()=>$intents->reserve($b,7,$id,$origin,$revision,$key),'asaas_company_remote_fresh_query_required','even reserved replay needs a fresh deletion proof');
echo 'Asaas recreation intents: '.($GLOBALS['essentialChecks']-$start).' additional SQL checks. No replacement charge or reactivation.'.PHP_EOL;

if($db->driver()==='mysql'){
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$evidence='/tmp/asaas-remote-test-'.bin2hex(random_bytes(10)).'.jsonl';$workers=[];$key=hash('sha256','concurrent-recreation-intent');
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_remote_concurrency.php','worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['operation'=>'recreation_intent','issuer'=>$b,'invoice'=>$nextId,'origin'=>$nextOrigin,'revision'=>$nextRevision,'key'=>$key,'evidence'=>$evidence],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'recreation contenders wait on actual native lock');$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','recreation intent worker has no diagnostics');}
  essentialCheck($out[0]===$out[1]&&json_decode($out[0],true)['state']==='reserved','concurrent commands return one identical durable intent');
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_recreations WHERE origin_id=?",[$nextOrigin])->row()->n===1&&!is_file($evidence),'one intent and no external mutation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_file($evidence))unlink($evidence);}
 echo 'Asaas recreation intents: five additional real MySQL concurrency checks.'.PHP_EOL;
}
