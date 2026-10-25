<?php
require __DIR__.'/invoices.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
NativeCompanyInvoiceCommandSchema::ensure($db,$p);
$service=new NativeCompanyInvoiceCommunicationState($db,$p);
$invoice=$db->query('SELECT id FROM '.$p.'invoices WHERE issuer_id=? AND status<>5 AND status<>6 ORDER BY id DESC LIMIT 1',[$a])->row_array()['id'];
$invoice=(int)$invoice;$original=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array();
$state=$service->state($a,7,$invoice);$key=hash('sha256','native-communication-resume');
$out=$service->change($a,7,$invoice,'resume_overdue_reminders',$state['revision'],$key);
essentialCheck($out['state']['cancel_overdue_reminders']===0&&!$out['replayed'],'native resume commits actual metadata');
$count=count($db->query("SELECT id FROM {$p}issuers_audit WHERE event='native_invoice_communication_updated'")->result_array());
essentialCheck($service->change($a,7,$invoice,'resume_overdue_reminders',$state['revision'],$key)['replayed'],'exact replay performs no second mutation');
essentialCheck(count($db->query("SELECT id FROM {$p}issuers_audit WHERE event='native_invoice_communication_updated'")->result_array())===$count,'replay does not append a second audit');
essentialThrows(fn()=>$service->change($a,7,$invoice,'pause_overdue_reminders',$state['revision'],$key),'native_invoice_communication_conflict','request key cannot change operation');
essentialThrows(fn()=>$service->change($a,7,$invoice,'pause_overdue_reminders',$state['revision'],hash('sha256','stale')),'native_invoice_communication_stale','stale metadata cannot overwrite a newer command');
$paused=$service->change($a,7,$invoice,'pause_overdue_reminders',$out['state']['revision'],hash('sha256','pause'));
essentialCheck($paused['state']['cancel_overdue_reminders']===1,'pause restores actual reminder preference');
$sent=$service->change($a,7,$invoice,'mark_as_sent',$paused['state']['revision'],hash('sha256','mark'));
essentialCheck($sent['state']['sent']===1&&is_string($sent['state']['datesend']),'explicit manual sent records metadata, not transport receipt');
$after=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array();
foreach(['sent','datesend','cancel_overdue_reminders']as$field){unset($original[$field],$after[$field]);}
essentialCheck($after===$original,'communication commands preserve every other financial/header byte');
essentialThrows(fn()=>$service->state($b,7,$invoice),'company_invoice_access_denied','B cannot inspect A communication metadata');
$revision=$service->state($a,7,$invoice)['revision'];$failureKey=hash('sha256','audit-failure');
if($db->driver()==='sqlite'){
 $db->query("CREATE TRIGGER native_comm_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_invoice_communication_updated' BEGIN SELECT RAISE(ABORT,'synthetic audit failure'); END");
 essentialThrows(fn()=>$service->change($a,7,$invoice,'resume_overdue_reminders',$revision,$failureKey),'issuer_storage_failed','audit failure rolls back native flag');
 $db->query('DROP TRIGGER native_comm_failure');
 essentialCheck($service->state($a,7,$invoice)['revision']===$revision&&!$db->query('SELECT request_key FROM '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$failureKey])->row_array(),'failed command leaves metadata and receipt unchanged');
}
echo 'Native invoice communication metadata: '.$GLOBALS['essentialChecks']." cumulative actual SQLite checks passed; no email/network.\n";
