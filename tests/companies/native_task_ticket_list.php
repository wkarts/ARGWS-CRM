<?php
require __DIR__.'/native_task_ticket_form.php';require_once APPPATH.'services/NativeCompanyRelatedTaskRead.php';
$list=new NativeCompanyRelatedTaskRead($db,$p);$page=$list->read($a,7,'ticket',$ticketCanonical);
essentialCheck(count($page['items'])===1&&$page['items'][0]['id']===$new,'Related task rows query actual canonical source without mixing unrelated standalone tasks');
essentialCheck($page['items'][0]['row']['name']==='Updated ticket-linked task'&&$page['items'][0]['fields']['hourly_rate']===number_format((float)$ticketWire['hourly_rate'],2,'.',''),'Real name and explicit native values projected');
essentialCheck(count($page['items'][0]['tags'])===1&&$page['items'][0]['assignees']!==[],'Native tags and assignees are actual source data, not placeholders');
$other=NativeCompanyResourceId::encode('company_ticket',2);essentialThrows(fn()=>$list->read($a,7,'ticket',$other),'native_ticket_denied','Cross-company parent rejected before list');
$db->query('UPDATE '.$p.'company_tickets SET client_id=? WHERE id=1',[$cb]);$offset=count($db->queries);essentialThrows(fn()=>$list->read($a,7,'ticket',$ticketCanonical),'native_ticket_denied','Parent client drift prevents related list');essentialCheck(!array_filter(array_slice($db->queries,$offset),fn($q)=>str_contains($q[0],'SELECT id,native_fields_json')),'Parent authority established before candidate enumeration');
echo 'Ticket-related task list actual SQLite: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
