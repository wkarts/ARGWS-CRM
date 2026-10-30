<?php
require __DIR__.'/primary_notes_reminders.php';
$forOther=$ui->saveReminder('invoice',42,['description'=>'Assigned to other','date'=>'2026-10-25 13:00:00','staff'=>14]);
$byOther=$other->saveReminder('invoice',42,['description'=>'Created for master','date'=>'2026-10-25 14:00:00','staff'=>7]);
$mine=$other->reminderTable(null,null,0,100,'',false,false);
$all=$other->reminderTable(null,null,0,100,'',false,true);
essentialCheck(in_array($forOther,array_column($mine['rows'],'id'),true)&&!in_array($byOther,array_column($mine['rows'],'id'),true),'my reminders only targets current actor');
essentialCheck(in_array($forOther,array_column($all['rows'],'id'),true)&&in_array($byOther,array_column($all['rows'],'id'),true),'all reminders preserves native recipient OR creator semantics');
essentialCheck(!in_array('1',array_column($all['rows'],'id'),true),'ordinary staff does not see unrelated master reminder');
$admin=$ui->reminderTable(null,null,0,100,'',false,true);
essentialCheck(in_array('1',array_column($admin['rows'],'id'),true)&&in_array($forOther,array_column($admin['rows'],'id'),true),'company administrator sees historical and owned reminders only after source authorization');
essentialCheck($named->reminderTable(null,null,0,100,'',false,true)['rows']===[],'named company never inherits principal all-reminders data');
echo "Native all/my reminders creator-recipient and issuer semantics: passed.\n";
