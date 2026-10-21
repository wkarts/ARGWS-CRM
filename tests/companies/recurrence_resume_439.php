<?php
/** Newly reconstructed service integration. Source recovered; prior missing tests are not claimed restored. */
declare(strict_types=1);
register_shutdown_function(static function():void{if(!isset($GLOBALS['db'],$GLOBALS['p']))return;foreach(['company_invoice_schedule_requests','company_invoice_schedule_runs','company_invoice_schedules']as$table)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$table);unset($GLOBALS['db']->data_cache['table_names']);});
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
if($db->driver()==='mysql')CompanyInvoiceRecurringSchema::ensure($db,$p);else{
 foreach(CompanyInvoiceRecurringSchema::definitions()as$table=>$fields){
  $parts=[];
  foreach($fields as$name=>$type){$nullable=str_ends_with($type,' NULL');$type=str_replace(' NULL','',$type);$type=str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type;$parts[]='`'.$name.'` '.$type.($nullable?' NULL':' NOT NULL');}
  foreach(CompanyInvoiceRecurringSchema::indexes($table)as$name=>[$cols,$non]){if($name==='PRIMARY'&&$table==='company_invoice_schedules')continue;if($non)continue;$parts[]=($name==='PRIMARY'?'PRIMARY KEY':'UNIQUE').'('.implode(',',$cols).')';}
  $db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');
 }
}
unset($db->data_cache['table_names']);
$series=new CompanyInvoiceRecurring($db,$p);$seed=['client_id'=>$ca,'currency'=>1,'date'=>'2024-01-31','due_date'=>'2024-02-05','items'=>[['description'=>'Recurring source','qty'=>'1.00','rate'=>'100.00']],'note'=>'Reconstructed synthetic proof'];$spec=['unit'=>'month','interval'=>1,'custom'=>false,'cycles'=>3];
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$sa=$series->create($a,7,$seed,$spec,hash('sha256','resume439-schedule-a'));
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before,'schedule creation does not emit or receive');
essentialCheck($series->create($a,7,$seed,$spec,hash('sha256','resume439-schedule-a'))===$sa,'schedule retry returns immutable same ID');
essentialThrows(fn()=>$series->create($a,7,$seed,array_replace($spec,['cycles'=>4]),hash('sha256','resume439-schedule-a')),'company_invoice_recurring_conflict','changed cycles conflict with existing request');
essentialThrows(fn()=>$series->document($b,7,$sa),'company_invoice_recurring_denied','cross-company schedule ID denied');
essentialThrows(fn()=>$series->create($b,7,$seed,$spec,hash('sha256','resume439-cross-client')),'company_sales_recipient_denied','cross-company client cannot be scheduled');
$pa=$series->run($a,7,$sa,'2024-01-31');$adoc=$s->document($a,7,$pa)->data();
essentialCheck($adoc['totals']['total']==='100.00'&&$adoc['status']===1,'first occurrence is canonical unpaid invoice');
essentialCheck($series->document($a,7,$sa)['completed_cycles']==1&&$series->document($a,7,$sa)['next_date']==='2024-02-29','monthly schedule clamps first February');
essentialCheck($series->run($a,7,$sa,'2024-01-31')===null,'retry before next occurrence does not duplicate invoice');
$pb=$series->run($a,7,$sa,'2024-02-29');$pc=$series->run($a,7,$sa,'2024-03-31');
essentialCheck($pa!==$pb&&$pb!==$pc&&$series->document($a,7,$sa)['state']==='completed','finite cycles emit distinct receipts and finish');
essentialCheck($series->run($a,7,$sa,'2024-12-31')===null&&count($series->document($a,7,$sa)['runs'])===3,'completed schedule remains bounded');
essentialCheck($db->query('SELECT duedate FROM '.$p.'invoices WHERE id=?',[$pb])->row()->duedate==='2024-03-05','due-date day interval follows occurrence civil date');
// A canonical invoice committed before final schedule advancement must be resumed, not reissued.
$resumeSeed=array_replace($seed,['client_id'=>$cb,'date'=>'2024-05-01','due_date'=>'2024-05-06']);
$sr=$series->create($b,7,$resumeSeed,['unit'=>'day','interval'=>1,'custom'=>true,'cycles'=>1],hash('sha256','resume439-interrupted'));
$db->failOn='UPDATE '.$p.'company_invoice_schedules SET `revision`';
essentialThrows(fn()=>$series->run($b,7,$sr,'2024-05-01'),'issuer_storage_failed','late finalize failure preserved');$db->failOn=null;
$processing=$series->document($b,7,$sr);$canonical=$processing['runs'][0]['canonical_invoice_id'];
essentialCheck($processing['state']==='processing'&&$canonical>0&&$processing['runs'][0]['invoice_id']===$canonical,'interrupted finalization retains canonical receipt and pending state');
$countAfterCrash=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$settings->saveIdentity($b,7,['legal_name'=>'Current B after canonical emission','tax_id'=>'52998224725','profile'=>['invoice_company_address'=>'Updated after durable invoice']]);
$rr=$series->run($b,7,$sr,'2024-05-01');
essentialCheck($rr===$canonical&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$countAfterCrash,'resume after company identity change returns same committed invoice without duplicate number');
essentialCheck($series->document($b,7,$sr)['completed_cycles']==1&&$series->document($b,7,$sr)['state']==='completed','resume advances exactly one cycle');
// Unlimited series stay unlimited but each bounded worker invocation emits at most one cycle for each selected row.
$su=$series->create($b,7,$resumeSeed,['unit'=>'day','interval'=>1,'custom'=>true,'cycles'=>0],hash('sha256','resume439-unlimited'));
$beforeWorker=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$out=$series->processPending('2024-05-10',1);
essentialCheck($out===['completed'=>1,'blocked'=>0]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeWorker+1,'bounded worker emits one, not all unlimited overdue cycles');
$unlimited=$series->document($b,7,$su);essentialCheck($unlimited['config']['cycles']===0&&$unlimited['completed_cycles']==1&&$unlimited['state']==='active','unlimited count preserved after worker');
$series->control($b,7,$su,$unlimited['revision'],'pause',hash('sha256','resume439-pause'));
essentialCheck($series->run($b,7,$su,'2024-05-10')===null,'paused schedule cannot emit');
$series->control($b,7,$su,$unlimited['revision'],'pause',hash('sha256','resume439-pause'));
essentialThrows(fn()=>$series->control($b,7,$su,$unlimited['revision'],'resume',hash('sha256','resume439-stale')),'company_invoice_recurring_stale','stale revision cannot resume');
$paused=$series->document($b,7,$su);$series->control($b,7,$su,$paused['revision'],'resume',hash('sha256','resume439-resume'));$series->run($b,7,$su,'2024-05-10');
$active=$series->document($b,7,$su);$series->control($b,7,$su,$active['revision'],'cancel',hash('sha256','resume439-cancel'));
essentialCheck($series->run($b,7,$su,'2024-05-10')===null,'cancelled recurrence retains receipts and cannot emit');
// Restricted collaborator and durable authority are rechecked by the worker.
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(37,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,37,1)",[$a]);
foreach(['view','create','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,37,'invoices',?)",[$a,$cap]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,37,'customers','view')",[$a]);
$restricted=$series->create($a,37,$seed,array_replace($spec,['cycles'=>1]),hash('sha256','resume439-restricted'));
essentialThrows(fn()=>$series->page($b,37),'company_sales_denied','restricted actor cannot list B');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=37 AND feature='invoices' AND capability='edit'",[$a]);
$beforeRevoke=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$out=$series->processPending('2024-12-31',100);
essentialCheck($out['blocked']===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeRevoke,'revoked actor blocks durable worker before emission');
$revoked=$series->document($a,7,$restricted);essentialCheck($revoked['state']==='paused'&&$revoked['last_error']==='origin_or_authority_review','revocation yields explicit resumable review state');
// A crash before the canonical receipt leaves an explicit processing claim that pause/resume preserves.
$sp=$series->create($b,7,array_replace($resumeSeed,['date'=>'2024-06-01','due_date'=>'2024-06-06']),['unit'=>'week','interval'=>1,'custom'=>true,'cycles'=>1],hash('sha256','resume439-before-receipt'));
$db->failOn='INSERT INTO '.$p.'company_invoice_requests';
essentialThrows(fn()=>$series->run($b,7,$sp,'2024-06-01'),'issuer_storage_failed','writer failure after schedule claim remains unconfirmed');$db->failOn=null;
$pending=$series->document($b,7,$sp);essentialCheck($pending['state']==='processing'&&count($pending['runs'])===1&&$pending['runs'][0]['canonical_invoice_id']===null,'processing claim has no invented financial receipt');
$series->control($b,7,$sp,$pending['revision'],'pause',hash('sha256','resume439-pause-processing'));
$pending=$series->document($b,7,$sp);essentialCheck($pending['state']==='paused_processing'&&$series->run($b,7,$sp,'2024-06-01')===null,'pause retains pending claim and blocks writer');
$series->control($b,7,$sp,$pending['revision'],'resume',hash('sha256','resume439-resume-processing'));
$completedPending=$series->run($b,7,$sp,'2024-06-01');$resumed=$series->document($b,7,$sp);
essentialCheck($completedPending>0&&count($resumed['runs'])===1&&$resumed['runs'][0]['canonical_invoice_id']===$completedPending,'resume reuses occurrence and writes exactly one canonical receipt');
$db->query('UPDATE '.$p.'company_invoice_schedule_runs SET invoice_id=? WHERE issuer_id=? AND schedule_id=?',[$ib,$b,$sp]);
essentialThrows(fn()=>$series->document($b,7,$sp),'issuer_invoice_recurring_corrupt','forged completed run cannot substitute another same-company invoice');
$db->query('UPDATE '.$p.'company_invoice_schedule_runs SET invoice_id=? WHERE issuer_id=? AND schedule_id=?',[$completedPending,$b,$sp]);
essentialThrows(fn()=>$series->run($a,7,$sa,(new DateTimeImmutable('tomorrow',new DateTimeZone('UTC')))->format('Y-m-d')),'company_invoice_recurring_invalid','future worker date rejected');
foreach([0,101]as$badLimit)essentialThrows(fn()=>$series->processPending('2024-12-31',$badLimit),'company_invoice_recurring_invalid','worker limit rejected outside explicit bound');
// Origin corruption is a hard veto; a failed creation audit leaves no orphan schedule.
$beforeSchedules=count($series->page($a,7));$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$series->create($a,7,$seed,$spec,hash('sha256','resume439-audit-fail')),'issuer_storage_failed','schedule/audit transaction rollback');$db->failOn=null;
essentialCheck(count($series->page($a,7))===$beforeSchedules,'failed audit does not persist schedule');
$db->query('UPDATE '.$p.'company_invoice_schedules SET payload_hash=? WHERE id=?',[str_repeat('0',64),$sa]);
essentialThrows(fn()=>$series->document($a,7,$sa),'issuer_invoice_recurring_corrupt','altered payload digest blocks reader');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'all recurrence operations preserve NULL legacy row');
echo 'Reconstructed439 recurring service A/B/idempotency/worker/revocation/resume: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No bank/SMTP transport.'.PHP_EOL;
