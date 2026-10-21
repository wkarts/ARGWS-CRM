<?php
/** Reconstructed real row-lock tests. No SQLite fallback or banking/SMTP calls. */
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL/MariaDB DSN is required.\n");exit(2);}
function recurrenceResumeConnection(string $prefix):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$db=recurrenceResumeConnection($p);$service=new CompanyInvoiceRecurring($db,$p);
 try{
  if($in['op']==='create')echo $service->create($in['issuer'],$in['actor'],$in['invoice'],$in['spec'],$in['key']);
  elseif($in['op']==='run')echo $service->run($in['issuer'],$in['actor'],$in['schedule'],$in['through'])??'none';
  elseif($in['op']==='pause'){$service->control($in['issuer'],$in['actor'],$in['schedule'],$in['revision'],'pause',$in['key']);echo 'controlled';}
  else throw new RuntimeException('unknown operation');
 }catch(DomainException $e){if(in_array($e->getMessage(),['company_sales_denied','company_sales_origin_changed','company_invoice_recurring_denied','company_invoice_recurring_stale'],true))echo 'denied';else throw $e;}
 exit(0);
}
require __DIR__.'/recurrence_resume_439.php';
$observer=recurrenceResumeConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function recurrenceResumeRace(array $input,?callable $whileLocked=null):array
{
 global $db,$p,$observer,$blocker;
 $workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{
  for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([getenv('ISSUERS_TEST_PHP')?:PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
  $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'both recurrence workers observed waiting on actual installation lock');if($whileLocked)$whileLocked();$db->trans_commit();$out=[];
  foreach($workers as[$process,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);essentialCheck($code===0&&$stderr==='','recurrence child completed without diagnostic leakage: '.$stderr);$out[]=$stdout;}return$out;
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$raceSeed=['client_id'=>$ca,'currency'=>1,'date'=>'2024-07-01','due_date'=>'2024-07-06','items'=>[['description'=>'Real lock recurrence','qty'=>'1.00','rate'=>'25.00']],'note'=>'Synthetic concurrency'];$raceSpec=['unit'=>'month','interval'=>1,'custom'=>false,'cycles'=>2];$raceKey=hash('sha256','resume439-race-create');
$out=recurrenceResumeRace(['op'=>'create','issuer'=>$a,'actor'=>7,'invoice'=>$raceSeed,'spec'=>$raceSpec,'key'=>$raceKey]);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent create returns one schedule');$raceId=(int)$out[0];
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_schedules WHERE request_key=?',[$raceKey])->row()->n===1,'concurrent schedule key stored once');
$beforeRace=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$out=recurrenceResumeRace(['op'=>'run','issuer'=>$a,'actor'=>7,'schedule'=>$raceId,'through'=>'2024-07-01']);sort($out);
$emitted=array_values(array_filter($out,'ctype_digit'));essentialCheck(count($emitted)>=1&&count(array_unique($emitted))===1&&count($emitted)+count(array_filter($out,fn($v)=>$v==='none'))===2,'concurrent callers return the same committed invoice or a next-date no-op');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeRace+1&&count($series->document($a,7,$raceId)['runs'])===1&&$series->document($a,7,$raceId)['completed_cycles']==1&&$series->document($a,7,$raceId)['next_date']==='2024-08-01','real race emits one invoice and one durable occurrence and advances once');
$ready=$series->document($a,7,$raceId);$pauseKey=hash('sha256','resume439-race-pause');
$out=recurrenceResumeRace(['op'=>'pause','issuer'=>$a,'actor'=>7,'schedule'=>$raceId,'revision'=>$ready['revision'],'key'=>$pauseKey]);
essentialCheck($out===['controlled','controlled'],'same pause request remains idempotent under two connections');
essentialCheck($series->document($a,7,$raceId)['revision']===$ready['revision']+1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_schedule_requests WHERE request_key=?',[$pauseKey])->row()->n===1,'concurrent control advances exactly one revision');
// Regrant only synthetic collaborator A, then revoke while both workers are blocked.
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,37,'invoices','edit')",[$a]);
$restrictedRace=$series->create($a,37,$raceSeed,$raceSpec,hash('sha256','resume439-race-restricted'));$beforeRevoke=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$out=recurrenceResumeRace(['op'=>'run','issuer'=>$a,'actor'=>37,'schedule'=>$restrictedRace,'through'=>'2024-07-01'],fn()=>$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=37',[$a]));
essentialCheck($out===['denied','denied'],'authority revoked during observed lock wait denies both workers');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeRevoke&&$series->document($a,7,$restrictedRace)['runs']===[],'revocation race leaves no canonical invoice or fake run');
// A frozen origin changed during the wait must be reviewed; never silently emit with new identity.
$originRace=$series->create($a,7,$raceSeed,$raceSpec,hash('sha256','resume439-race-origin'));$beforeOrigin=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$out=recurrenceResumeRace(['op'=>'run','issuer'=>$a,'actor'=>7,'schedule'=>$originRace,'through'=>'2024-07-01'],fn()=>$db->query('UPDATE '.$p.'issuers_registry SET legal_name=? WHERE id=?',['Changed during synthetic lock wait',$a]));
essentialCheck($out===['denied','denied'],'origin modified during actual lock wait blocks both emissions');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeOrigin&&$series->document($a,7,$originRace)['runs']===[],'origin race has no partial persisted financial operation');
echo 'Reconstructed439 recurring real concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks passed, two observed waiters per scenario. No external transport.'.PHP_EOL;
