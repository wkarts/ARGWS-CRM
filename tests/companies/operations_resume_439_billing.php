<?php
/** Rebuilt 437–439 operation proof: synthetic data, production writers, no external transport. */
require __DIR__.'/operations_resume_439_schema.php';
operationsResumeCleanup(['CompanyStandaloneBillingSchema', 'CompanyTaskTimeBillingSchema', 'CompanyTaskTimeBatchSchema']);
require __DIR__.'/task_billing.php';
require_once APPPATH.'services/CompanyStandaloneBilling.php';
require_once APPPATH.'services/CompanyStandaloneTasks.php';
require_once APPPATH.'services/CompanyTaskTimeBilling.php';
require_once APPPATH.'services/CompanyTaskTimeBatch.php';
require_once APPPATH.'services/CompanyTaskTimeBatchSchema.php';
foreach (['CompanyStandaloneBillingSchema', 'CompanyTaskTimeBillingSchema', 'CompanyTaskTimeBatchSchema'] as $schema) operationsResumeSchema($db, $p, $schema);
$resumeKey = static fn(string $label): string => hash('sha256', 'operations-resume439-'.$label);
$resumeProfile = ['client_id'=>$ca, 'currency'=>1, 'date'=>'2026-10-19', 'due_date'=>'2026-11-19', 'rate'=>'100.00', 'round_mode'=>'none', 'round_minutes'=>0, 'taxes'=>[], 'note'=>'Synthetic recovery validation'];
$resumeStandalone = new CompanyStandaloneTasks($db, $p);
$resumeWhole = new CompanyStandaloneBilling($db, $p);
$resumeTimes = new CompanyTaskTimeBilling($db, $p);
$resumeBatch = new CompanyTaskTimeBatch($db, $p);
$resumeTask = ['assignee_id'=>7, 'name'=>'Recovery standalone', 'description'=>'Closed native timer', 'due_date'=>'2026-11-19', 'checklist'=>[]];
$resumeMakeStandalone = static function (int $issuer, string $label) use ($db, $p, $resumeStandalone, $resumeTask, $resumeKey): array {
    $id = $resumeStandalone->create($issuer, 7, $resumeTask, $resumeKey($label.'create'));
    $resumeStandalone->activity($issuer, 7, $id, 1, 'start', $resumeKey($label.'start'));
    $db->query('UPDATE '.$p.'company_standalone_timers SET started_at=? WHERE issuer_id=? AND task_id=?', [date('Y-m-d H:i:s', time()-3600), $issuer, $id]);
    $resumeStandalone->activity($issuer, 7, $id, 2, 'stop', $resumeKey($label.'stop'));
    $resumeStandalone->update($issuer, 7, $id, 3, array_replace($resumeTask, ['status'=>'done']), $resumeKey($label.'done'));
    $timer = $db->query('SELECT id FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=?', [$issuer, $id])->row_array();
    return [$id, (int)$timer['id']];
};
$resumeBeforeCash = $db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
foreach ([[$a,$ca],[$b,$cb]] as [$owner,$client]) {
    [$task,$timer] = $resumeMakeStandalone($owner, 'whole-'.$owner);
    $profile = array_replace($resumeProfile, ['client_id'=>$client]);
    $origin = $resumeWhole->prepare($owner,7,$task,4,$profile,$resumeKey('whole-prepare-'.$owner));
    essentialCheck($resumeWhole->prepare($owner,7,$task,4,$profile,$resumeKey('whole-prepare-'.$owner))===$origin,'recovered whole-task preparation retries own durable origin A/B');
    $invoice = $resumeWhole->convert($owner,7,$task,$origin);
    essentialCheck($resumeWhole->convert($owner,7,$task,$origin)===$invoice,'whole-task conversion retries canonical invoice A/B');
    essentialCheck($s->document($owner,7,$invoice)->data()['totals']['total']==='100.00','one closed hour becomes own 100.00 receivable A/B');
    essentialThrows(fn()=>$resumeWhole->status($owner===$a?$b:$a,7,$task),'company_standalone_billing_denied','whole task status rejects crossed owner');
    $s->change($owner,7,$invoice,$s->revision($owner,7,$invoice));
    essentialCheck($resumeWhole->status($owner,7,$task)['invoice_current']['status']===5,'source reader exposes cancelled canonical invoice');
    essentialThrows(fn()=>$resumeWhole->disable($owner,7,$task,$origin),'company_standalone_billing_immutable','cancelled canonical invoice never releases billed whole task');
}
// A partial whole-timer selection remains claimed after invoice cancellation.
$resumeProject = $ps->create($a,7,array_replace($pi,['name'=>'Recovery timer selection']),$resumeKey('project'));
$resumeProjectTask = array_replace($taskInput,['project_id'=>$resumeProject,'name'=>'Partial timer task','checklist'=>[]]);
$resumeId = $ts->create($a,7,$resumeProjectTask,$resumeKey('project-task'));
$resumeTimerIds = [];
foreach ([1,2] as $index) {
    $revision = (int)$ts->get($a,7,$resumeId)['revision'];
    $ts->activity($a,7,$resumeId,$revision,'start',$resumeKey('project-start-'.$index));
    $db->query('UPDATE '.$p.'company_task_timers SET started_at=? WHERE issuer_id=? AND task_id=? AND stopped_at IS NULL',[date('Y-m-d H:i:s',time()-1800),$a,$resumeId]);
    $ts->activity($a,7,$resumeId,$revision+1,'stop',$resumeKey('project-stop-'.$index));
    $resumeTimerIds[]=(int)$db->query('SELECT id FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=? ORDER BY id DESC LIMIT 1',[$a,$resumeId])->row()->id;
}
$resumeSelection = array_replace(array_diff_key($resumeProfile,['client_id'=>true]),['selection_mode'=>'selected_timers','timer_ids'=>[$resumeTimerIds[0]],'line_mode'=>'task_per_item']);
$resumeOrigin = $resumeTimes->prepare($a,7,$resumeProject,1,$resumeSelection,$resumeKey('selected-prepare'));
essentialCheck($resumeTimes->prepare($a,7,$resumeProject,1,$resumeSelection,$resumeKey('selected-prepare'))===$resumeOrigin,'selected timer preparation idempotent');
$resumeInvoice = $resumeTimes->convert($a,7,$resumeOrigin);
essentialCheck($resumeTimes->convert($a,7,$resumeOrigin)===$resumeInvoice,'selected timer canonical conversion idempotent');
essentialCheck($s->document($a,7,$resumeInvoice)->data()['totals']['total']==='50.00','selected half hour excludes second native timer');
$s->change($a,7,$resumeInvoice,$s->revision($a,7,$resumeInvoice));
essentialThrows(fn()=>$resumeTimes->disable($a,7,$resumeOrigin),'company_time_billing_immutable','invoice cancellation never releases converted timer');
essentialThrows(fn()=>$resumeTimes->prepare($a,7,$resumeProject,1,$resumeSelection,$resumeKey('selected-overlap')),'company_time_billing_overlap','selected timer cannot be billed a second time after cancellation');
essentialThrows(fn()=>$resumeTimes->status($b,7,$resumeOrigin),'company_time_billing_denied','timer source rejects cross-company ID');
// A cross-project batch uses the remaining timer; its source and receipt remain immutable.
$resumeBatchInput=array_replace($resumeProfile,['source_kind'=>'projects','timers'=>[['id'=>$resumeTimerIds[1],'task_revision'=>(int)$ts->get($a,7,$resumeId)['revision'],'project_revision'=>1]],'rate_source'=>'explicit','line_mode'=>'single_line']);
$resumeBatchOrigin=$resumeBatch->prepare($a,7,$resumeBatchInput,$resumeKey('batch-prepare'));
essentialCheck($resumeBatch->prepare($a,7,$resumeBatchInput,$resumeKey('batch-prepare'))===$resumeBatchOrigin,'cross-project batch has own idempotent declaration');
$resumeBatchInvoice=$resumeBatch->convert($a,7,$resumeBatchOrigin);
essentialCheck($resumeBatch->convert($a,7,$resumeBatchOrigin)===$resumeBatchInvoice,'batch retry returns original canonical invoice');
essentialCheck($s->document($a,7,$resumeBatchInvoice)->data()['totals']['total']==='50.00','batch consumes only selected remaining timer');
$s->change($a,7,$resumeBatchInvoice,$s->revision($a,7,$resumeBatchInvoice));
essentialThrows(fn()=>$resumeBatch->disable($a,7,$resumeBatchOrigin),'company_time_batch_immutable','cancelled batch invoice never unbills its native timer');
// Failed declaration/claim audits cannot leave partial billing authority or an orphan invoice.
[$resumeRollbackTask] = $resumeMakeStandalone($a,'audit-rollback');
essentialThrows(fn()=>$resumeWhole->prepare($a,7,$resumeRollbackTask,4,array_replace($resumeProfile,['client_id'=>$cb]),$resumeKey('cross-client')),'company_standalone_billing_reference_denied','standalone work cannot bill another private company customer');
$resumeOriginsBefore=$db->query('SELECT * FROM '.$p.'company_standalone_billing_origins ORDER BY id')->result_array();
$resumeLinksBefore=$db->query('SELECT * FROM '.$p.'company_standalone_billing_links ORDER BY task_id')->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$resumeWhole->prepare($a,7,$resumeRollbackTask,4,$resumeProfile,$resumeKey('audit-prepare')),'issuer_storage_failed','failed declaration audit must roll back immutable source and link');
$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_billing_origins ORDER BY id')->result_array()===$resumeOriginsBefore&&$db->query('SELECT * FROM '.$p.'company_standalone_billing_links ORDER BY task_id')->result_array()===$resumeLinksBefore,'failed declaration leaves original billing evidence unchanged');
$resumeRollbackOrigin=$resumeWhole->prepare($a,7,$resumeRollbackTask,4,$resumeProfile,$resumeKey('audit-prepare'));
$resumeInvoicesBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$resumeWhole->convert($a,7,$resumeRollbackTask,$resumeRollbackOrigin),'issuer_storage_failed','failed claim audit must not issue an invoice');
$db->failOn=null;
essentialCheck($resumeWhole->status($a,7,$resumeRollbackTask)['state']==='ready'&&$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$resumeInvoicesBefore,'failed claim preserves ready origin and original invoice rows');
$resumeRetriedInvoice=$resumeWhole->convert($a,7,$resumeRollbackTask,$resumeRollbackOrigin);
essentialCheck($resumeWhole->convert($a,7,$resumeRollbackTask,$resumeRollbackOrigin)===$resumeRetriedInvoice,'explicit retry after failed audit creates one canonical invoice');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$resumeBeforeCash,'all recovered billing paths preserve payment ledger exactly');
echo 'Rebuilt recovery439 standalone/selected-timer/batch A+B/idempotency/cancellation/ledger: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
