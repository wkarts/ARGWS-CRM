<?php
require __DIR__.'/native_invoice_issue_commands.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
$preferences=$draftInput+['native_preferences'=>['cancel_overdue_reminders'=>'0']];$key=hash('sha256','native-form-explicit-reminder-preference');
$issued=$commands->createIssued($a,12,$preferences,$metadata,$key);$invoice=$issued['invoice_id'];
$get=fn($id)=>(int)$db->query('SELECT cancel_overdue_reminders FROM '.$p.'invoices WHERE id=?',[$id])->row_array()['cancel_overdue_reminders'];
essentialCheck($get($invoice)===0,'create-only full native form applies explicit reminder flag in the same canonical transaction');
$preferences['native_preferences']['cancel_overdue_reminders']=0;essentialCheck($commands->createIssued($a,12,$preferences,$metadata,$key)===$issued,'native checkbox integer/string normalizes to the same receipt');
$changed=$preferences;$changed['native_preferences']['cancel_overdue_reminders']=1;essentialThrows(fn()=>$commands->createIssued($a,12,$changed,$metadata,$key),'native_invoice_command_conflict','same request cannot replay a divergent reminder preference');
$without=$draftInput;essentialThrows(fn()=>$commands->createIssued($a,12,$without,$metadata,$key),'native_invoice_command_conflict','omission cannot adopt explicit preference receipt');
$historicalKey=hash('sha256','native-preferences-omitted-preserved');$historical=$commands->createIssued($a,12,$draftInput,$metadata,$historicalKey);essentialCheck($get($historical['invoice_id'])===1,'omission preserves the historical canonical reminder default');
$state=new NativeCompanyInvoiceCommunicationState($db,$p);$before=$state->state($a,7,$invoice);$state->change($a,7,$invoice,'pause_overdue_reminders',$before['revision'],hash('sha256','later-native-pause'));
$rowsBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$auditBefore=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();
essentialCheck($commands->createIssued($a,12,$preferences,$metadata,$key)===$issued&&$get($invoice)===1,'creation replay does not overwrite a later authorized communication change');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$rowsBefore&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$auditBefore,'creation replay performs no metadata repair or extra audit');
$writer=new CompanyInvoices($db,$p);essentialThrows(fn()=>$writer->change($a,7,$invoice,$writer->revision($a,7,$invoice),$preferences),'company_invoice_native_preferences_creation_only','manual invoice edit cannot bypass dedicated communication CAS');
$draft=$commands->createDraft($a,12,$preferences,$metadata,hash('sha256','native-draft-reminder-preference'));$draftWriter=new CompanyInvoiceDrafts($db,$p);$document=$draftWriter->document($a,12,$draft['draft_id']);
essentialCheck($document['payload']['native_preferences']===['cancel_overdue_reminders'=>0],'draft stores the explicit preference in its authenticated canonical payload');
$draftIssueKey=hash('sha256','native-draft-preference-issued');$draftInvoice=$draftWriter->issue($a,12,$draft['draft_id'],1,$draftIssueKey);
essentialCheck($get($draftInvoice)===0&&$draftWriter->issue($a,12,$draft['draft_id'],1,$draftIssueKey)===$draftInvoice,'draft issue transfers preference atomically and replays');
foreach([null,[],['cancel_overdue_reminders'=>true],['cancel_overdue_reminders'=>2],['cancel_overdue_reminders'=>0,'other'=>1]]as$invalid){$bad=$draftInput;$bad['native_preferences']=$invalid;essentialThrows(fn()=>$commands->createIssued($a,12,$bad,$metadata,hash('sha256',serialize($invalid))),'company_invoice_native_preferences_invalid','malformed preferences cannot be dropped or coerced');}
if($db->driver()==='sqlite'){
 $snapshot=[];foreach(['invoices','issuers_sequences','issuers_audit','company_invoice_requests','company_invoice_native_commands']as$table)$snapshot[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
 $db->query('CREATE TRIGGER ignore_native_preference BEFORE UPDATE OF cancel_overdue_reminders ON '.$p.'invoices BEGIN SELECT RAISE(IGNORE); END');$failedKey=hash('sha256','native-preference-readback-rollback');
 essentialThrows(fn()=>$commands->createIssued($a,12,$preferences,$metadata,$failedKey),'issuer_native_invoice_communication_unconfirmed','unconfirmed checkbox persistence rolls back the entire issue');$db->query('DROP TRIGGER ignore_native_preference');
 foreach($snapshot as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'failed preference leaves '.$table.' unchanged');
 $retry=$commands->createIssued($a,12,$preferences,$metadata,$failedKey);essentialCheck($get($retry['invoice_id'])===0,'exact failed preference request retries after complete rollback');
}
echo 'Native creation preference composition: '.$GLOBALS['essentialChecks']." cumulative PASS; no cron or SMTP claim.\n";
