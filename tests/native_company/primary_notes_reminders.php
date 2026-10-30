<?php
require __DIR__.'/primary_auxiliary.php';
require_once APPPATH.'services/NativeCompanyReminderDelivery.php';
$db->query("ALTER TABLE {$p}invoices ADD number varchar(64)");$db->query("UPDATE {$p}invoices SET number='Legacy-42' WHERE id=42");
$db->query("CREATE TABLE {$p}notes(id {$auto},rel_type varchar(40),rel_id INT,description TEXT,addedfrom INT,dateadded DATETIME,date_contacted DATETIME NULL)");
$db->query("CREATE TABLE {$p}reminders(id {$auto},rel_type varchar(40),rel_id INT,description TEXT,staff INT,creator INT,date DATETIME,isnotified INT,notify_by_email INT)");
$db->query("CREATE TABLE {$p}activity_log(id {$auto},description TEXT,date DATETIME,staffid varchar(100))");
$db->query("INSERT INTO {$p}notes(id,rel_type,rel_id,description,addedfrom,dateadded) VALUES(1,'invoice',42,'Historical<br />note',7,'2020-01-01 00:00:00')");
$db->query("INSERT INTO {$p}reminders(id,rel_type,rel_id,description,staff,creator,date,isnotified,notify_by_email) VALUES(1,'invoice',42,'Historical reminder',7,7,'2026-10-25 10:00:00',0,0)");
$db->data_cache=[];foreach(NativeCompanyReminderDelivery::tables()as$table)if(!$db->table_exists($p.$table)){$db->query('CREATE TABLE '.$p.$table.' (id INT PRIMARY KEY)');$db->data_cache=[];}
$ui=new NativeCompanyAuxiliaryUi($db,$p,$principal,7);
$note=$ui->saveNote('invoice',42,['description'=>'Owned note']);$reminder=$ui->saveReminder('invoice',42,['description'=>'Owned reminder','date'=>'2026-10-25 11:00:00','staff'=>7]);
essentialCheck(count($ui->notes('invoice',42))===2&&count($ui->reminders('invoice',42))===2&&$ui->remindersCount()===2,'principal relation readers union historical and owned notes/reminders');
$legacy=$ui->note('1');$ui->saveNote('invoice',42,['description'=>'Updated historical note'],'1',$legacy['revision']);
settingThrows(fn()=>$ui->deleteNote('1',$legacy['revision']),'company_auxiliary_stale','legacy note stale fingerprint cannot delete newer content');
$legacy=$ui->reminder('1');$ui->saveReminder('invoice',42,['description'=>'Updated historical reminder','date'=>'2026-10-25 12:00:00','staff'=>7,'notify_by_email'=>0],'1',$legacy['revision']);
essentialCheck($ui->reminder('1')['date']==='2026-10-25 12:00:00'&&$ui->reminder($reminder)['date']==='2026-10-25 11:00:00','legacy edit does not mutate owned reminder with overlapping source ID');
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,14,1)",[$principal]);foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,14,'invoices',?)",[$principal,$cap]);
$other=new NativeCompanyAuxiliaryUi($db,$p,$principal,14);
settingThrows(fn()=>$other->saveNote('invoice',42,['description'=>'Not author'],'1',$ui->note('1')['revision']),'company_auxiliary_access_denied','resource editor cannot modify another author historical note');
settingThrows(fn()=>$other->deleteNote($note,$ui->note($note)['revision']),'company_auxiliary_access_denied','same author guard also protects owned notes');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'invoices',?)",[$cap]);
$db->query("INSERT INTO {$p}invoices(id,issuer_id,number) VALUES(99,41,'Named-99')");$db->query("INSERT INTO {$p}notes(rel_type,rel_id,description,addedfrom,dateadded) VALUES('invoice',99,'Foreign historical-looking record',7,'2020-01-01 00:00:00')");$foreign=(int)$db->insert_id();
settingThrows(fn()=>$ui->note((string)$foreign),'native_resource_reference_denied','principal cannot use global notes table to hydrate a named invoice');
$named=new NativeCompanyAuxiliaryUi($db,$p,41,12);settingThrows(fn()=>$named->note('1'),'company_auxiliary_access_denied','named cannot fall through to global note with colliding ID');
$db->query("INSERT INTO {$p}activity_log(description,date,staffid) VALUES('Historical event','2020-01-01 00:00:00','Synthetic')");
essentialCheck(in_array('Historical event',array_column($ui->activity(100),'description'),true),'principal activity preserves native historical events');
echo "Primary notes/reminders lineage, author guards and activity: passed.\n";
