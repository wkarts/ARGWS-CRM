<?php
/** Actual two-process durable traversal and canonical draft races; no simulated lock inventory. */
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
require_once APPPATH.'services/CompanyInvoiceRecurringDiscovery.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL/MariaDB DSN is required.\n");exit(2);}
function recurringDiscoveryConnection(string$p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=recurringDiscoveryConnection($p);$GLOBALS['CI']=(object)['db'=>$db];
 try{echo json_encode((new CompanyInvoiceRecurring($db,$p))->processPending('2026-10-19',1),JSON_THROW_ON_ERROR);}catch(Throwable$e){fwrite(STDERR,$e->getMessage());exit(1);}exit(0);
}
require __DIR__.'/invoice_recurring_discovery_440.php';
$observer=recurringDiscoveryConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function recurringDiscoveryRace(?callable$whileLocked=null):array
{
 global$db,$p,$observer,$blocker;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker unavailable');fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+20;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'two real worker discovery transactions observed waiting on owned mutex');if($whileLocked)$whileLocked();$db->trans_commit();$out=[];
  foreach($workers as[$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$stderr==='','durable discovery child completes without hidden failure '.$stderr);$out[]=json_decode($stdout,true,32,JSON_THROW_ON_ERROR);}return$out;
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$waitingIds=[];for($n=0;$n<4;$n++)$waitingIds[]=$ir->create($a,7,$baseInput,array_replace($ispec,['cycles'=>0,'mode'=>'issue','principal_invoice_id'=>$parent]),$mk('discovery-race-wait-'.$n));
$eligibleIds=[];for($n=0;$n<2;$n++)$eligibleIds[]=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('discovery-race-ready-'.$n));
$revision=(int)$db->query('SELECT revision FROM '.$p.'company_invoice_schedule_discovery WHERE id=1')->row()->revision;$draftsBefore=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;$invoicesBefore=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
for($batch=0;$batch<3;$batch++){
 $out=recurringDiscoveryRace();$revision+=2;essentialCheck((int)$db->query('SELECT revision FROM '.$p.'company_invoice_schedule_discovery WHERE id=1')->row()->revision===$revision,'concurrent bounded reservations advance two distinct cursor revisions');
 essentialCheck(array_sum(array_column($out,'blocked'))===0&&array_sum(array_column($out,'completed'))===($batch===2?2:0),'stable cursor advances across four unpaid A rows then services both B rows');
}
foreach($waitingIds as$id)essentialCheck($ir->document($a,7,$id)['state']==='active'&&$ir->document($a,7,$id)['completed_cycles']==0,'concurrent discovery never mutates unpaid series '.$id);
foreach($eligibleIds as$id)essentialCheck($ir->document($b,7,$id)['state']==='completed'&&$ir->document($b,7,$id)['completed_cycles']==1,'each selected eligible B schedule completes once');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$draftsBefore+2&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$invoicesBefore,'two concurrent traversals create only two private drafts and zero native financial side effects');
foreach($waitingIds as$id){$r=$ir->document($a,7,$id);$ir->control($a,7,$id,$r['revision'],'cancel',$mk('discovery-race-remove-'.$id));}
// Two reservations can revisit a lone eligible row, but canonical occurrence identity still deduplicates.
$single=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('discovery-race-single'));$beforeSingle=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;$out=recurringDiscoveryRace();
essentialCheck(array_sum(array_column($out,'completed'))>=1&&array_sum(array_column($out,'blocked'))===0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$beforeSingle+1,'concurrent wraparound uses the same canonical draft key without duplication');
essentialCheck($ir->document($b,7,$single)['completed_cycles']==1&&count($ir->document($b,7,$single)['runs'])===1,'lone-row concurrent replay advances one cycle and one receipt');
// Discovering IDs does not grant the stored actor authority to write after revocation.
$revoke=[];for($n=0;$n<2;$n++)$revoke[]=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('discovery-race-revoked-'.$n));$beforeRevocation=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;
try{$out=recurringDiscoveryRace(fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7'));essentialCheck(array_sum(array_column($out,'completed'))===0&&array_sum(array_column($out,'blocked'))===2&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$beforeRevocation,'actor revoked during discovery lock wait cannot produce drafts from selected IDs');}finally{$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
foreach($revoke as$id)essentialCheck($ir->document($b,7,$id)['state']==='paused','revoked discovered schedule has explicit safety review state');
echo 'Invoice recurring discovery real concurrency/fairness/canonical retry/revocation: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'). No external calls.'.PHP_EOL;
