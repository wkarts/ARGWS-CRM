<?php
require __DIR__.'/native_task_form_writer.php';
require __DIR__.'/native_task_ticket_support.php';
$ticketFields=array_replace($standFields,['relation_type'=>'ticket','relation_source_kind'=>'company_ticket','relation_source_id'=>1]);
$ticketTask=$standalone->createNative($a,7,$standInput,$ticketFields,$key('ticket-task'));
$r=$standalone->get($a,7,$ticketTask);essentialCheck(json_decode($r['native_fields_json'],true)['relation_source_id']===1,'Existing canonical standalone writer preserves explicit ticket source');
essentialCheck($standalone->createNative($a,7,$standInput,$ticketFields,$key('ticket-task'))===$ticketTask,'Ticket-related task has durable create replay');
$bad=array_replace($ticketFields,['relation_source_id'=>2]);essentialThrows(fn()=>$standalone->createNative($a,7,$standInput,$bad,$key('ticket-cross')),'native_ticket_denied','Other company ticket cannot become task parent');
$db->query('UPDATE '.$p.'company_tickets SET client_id=? WHERE id=1',[$cb]);
$offset=count($db->queries);essentialThrows(fn()=>$standalone->get($a,7,$ticketTask),'native_ticket_denied','Canonical get denies ticket whose customer ownership changed');essentialCheck(!array_filter(array_slice($db->queries,$offset),fn($q)=>str_contains($q[0],'SELECT * FROM '.$p.'company_standalone_tasks')),'Canonical private task row is not hydrated before ticket parent proof');
$offset=count($db->queries);essentialThrows(fn()=>$standalone->page($a,7),'native_ticket_denied','Canonical page cannot leak a task through revoked parent');essentialCheck(!array_filter(array_slice($db->queries,$offset),fn($q)=>str_contains($q[0],'SELECT id,name,status')),'Page identifiers are authorized before names');
$db->query('UPDATE '.$p.'company_tickets SET client_id=? WHERE id=1',[$ca]);
$before=$standalone->get($a,7,$ticketTask);$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks')->row_array()['n'];
if($db->driver()==='sqlite'){
 $db->query('CREATE TRIGGER '.$p.'task_ticket_revoke AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_standalone_task_native_created' BEGIN UPDATE ".$p.'company_tickets SET client_id='.$cb.' WHERE id=1; END');
 essentialThrows(fn()=>$standalone->createNative($a,7,$standInput,$ticketFields,$key('ticket-audit-revoke')),'native_ticket_denied','Post-audit ticket ownership change rolls back task and journal');
 $db->query('DROP TRIGGER '.$p.'task_ticket_revoke');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks')->row_array()['n']===$count&&$standalone->get($a,7,$ticketTask)===$before,'Failed relation proof preserves existing task and ticket');
}
echo 'Ticket-related canonical task writer: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
