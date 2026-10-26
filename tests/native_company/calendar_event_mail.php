<?php
require __DIR__.'/../settings/company_settings.php';require_once APPPATH.'services/NativeCompanyEventWorker.php';require_once APPPATH.'services/NativeCompanyEvents.php';
NativeCompanyEventSchema::ensure($db,$p);NativeCompanyEventMailSchema::ensure($db,$p);NativeCompanyEventMailSchema::ensure($db,$p);
foreach(['firstname'=>'TEXT','lastname'=>'TEXT','email'=>'TEXT','is_not_staff'=>'INT']as$column=>$type)if(!IssuerStorage::fieldExists($db,$column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column.' '.$type);
$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Staff',email='staff@example.test',is_not_staff=0");$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=82 AND staff_id=12");$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'calendar','view')");
$db->query('CREATE TABLE '.$p.'mail_queue(id INTEGER PRIMARY KEY AUTOINCREMENT,engine TEXT,email TEXT,cc TEXT,bcc TEXT,message TEXT,alt_message TEXT,headers TEXT,attachments TEXT,status TEXT,date TEXT,issuer_id INT)');
$db->query('CREATE TABLE '.$p.'company_native_mail_dispatch_fences(queue_id INTEGER PRIMARY KEY,issuer_id INT,kind TEXT,binding_hash TEXT,state TEXT,attempt_key TEXT,created_at TEXT,dispatched_at TEXT,completed_at TEXT)');
foreach(CompanyPortalMailSchema::definitions()as$table=>$fields){$parts=[];foreach($fields as$key=>$type)$parts[]=$key.' '.$type.' NOT NULL';$parts[]='PRIMARY KEY('.array_key_first($fields).')';$db->query('CREATE TABLE '.$p.$table.' ('.implode(',',$parts).')');}
$db->query('CREATE TABLE '.$p.'emailtemplates(emailtemplateid INTEGER PRIMARY KEY,issuer_id INT,slug TEXT,language TEXT,active INT,message TEXT,subject TEXT,fromname TEXT,plaintext INT)');
$db->query('INSERT INTO '.$p.'emailtemplates VALUES(1,41,?,?,?,?,?,?,?)',['event-notification-to-staff','portuguese_br',1,'{staff_firstname}: {event_title} {event_start_date} {event_link}','Event {event_title}','Synthetic',0]);
$db->query('INSERT INTO '.$p.'emailtemplates VALUES(2,NULL,?,?,?,?,?,?,?)',['installation-only','portuguese_br',1,'Synthetic installation template','Installation','Synthetic',0]);
$db->query('INSERT INTO '.$p.'emailtemplates VALUES(3,82,?,?,?,?,?,?,?)',['other-company-only','portuguese_br',1,'Synthetic company template','Other company','Synthetic',0]);
require_once APPPATH.'helpers/email_templates_helper.php';
CompanySettingsRuntime::run($db,$p,41,12,function(){
essentialCheck(is_email_template_active('event-notification-to-staff'),'official email template helper sees the active company template');
essentialCheck(!is_email_template_active('installation-only')&&!is_email_template_active('other-company-only'),'official email template helper does not borrow installation or another company template');
});
CompanySettingsRuntime::run($db,$p,82,12,function(){
essentialCheck(is_email_template_active('other-company-only')&&!is_email_template_active('event-notification-to-staff'),'official email template helper changes only its company-scoped availability');
});
CompanySettingsRuntime::run($db,$p,$primary,7,function(){
essentialCheck(is_email_template_active('installation-only')&&!is_email_template_active('event-notification-to-staff'),'principal uses its legacy template source without exposing secondary templates');
});
if(!$db->table_exists($p.'contacts'))$db->query('CREATE TABLE '.$p.'contacts(id INT PRIMARY KEY)');
$parts=NativeCompanyHeaderSchema::definitions($p)[NativeCompanyHeaderSchema::NOTIFICATIONS];$parts=array_values(array_filter($parts,static fn($x)=>!str_starts_with($x,'KEY ')&&$x!=='PRIMARY KEY (`id`)'));foreach($parts as&$part){$part=preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);if(str_starts_with($part,'`id`'))$part='`id` INTEGER PRIMARY KEY AUTOINCREMENT';}unset($part);$db->query('CREATE TABLE '.$p.NativeCompanyHeaderSchema::NOTIFICATIONS.' ('.implode(',',$parts).')');
$store=new CompanySettingsStorage($db,$p);$store->updateBatch(41,7,['mail_engine'=>'codeigniter','email_protocol'=>'smtp','smtp_host'=>'smtp.example.test','smtp_port'=>'587','smtp_email'=>'from@example.test','dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24','default_timezone'=>'America/Sao_Paulo']);
$events=new NativeCompanyEvents($db,$p);$input=['title'=>'Private event','description'=>'Synthetic','start'=>'2026-10-25 10:00:00','end'=>'','public'=>0,'color'=>'#112233','reminder_before'=>'30','reminder_before_type'=>'minutes'];$event=$events->save(41,7,$input);$id=NativeCompanyResourceId::decode('event',$event['eventid'])['source_id'];
$worker=new NativeCompanyEventWorker($db,$p);$decrypt=fn($v)=>$v;
essentialCheck($worker->run('2026-10-25 12:29:00','https://example.test/crm',$decrypt)['notified']===0,'calendar local timezone threshold not prematurely due');
$result=$worker->run('2026-10-25 12:30:00','https://example.test/crm',$decrypt);essentialCheck($result===['notified'=>1,'deferred'=>0],'due event atomically queues template and real notification');
essentialCheck($worker->run('2026-10-25 12:31:00','https://example.test/crm',$decrypt)['notified']===0&&count($db->query('SELECT id FROM '.$p.'mail_queue')->result_array())===1,'cron replay cannot queue event twice');
$mail=$db->query('SELECT * FROM '.$p.'mail_queue')->row();$headers=unserialize($mail->headers,['allowed_classes'=>false]);essentialCheck(str_contains($mail->message,'/crm/admin/utilities/calendar?eventid=')&&!str_contains($mail->message,'{event_'),'existing event merge fields and original subpath route rendered');
$missing=$headers;unset($missing[NativeCompanyEventMail::HEADER]);settingThrows(fn()=>NativeCompanyEventMail::assertQueueRouting($db,$p,$mail,$missing),'native_event_mail_denied','stripped header cannot downgrade event queue');
$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=?',['sending',$mail->id]);$mail=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$mail->id])->row();$permit=NativeCompanyEventMail::claimDispatch($db,$p,$mail,$headers);NativeCompanyEventMail::completeDispatch($db,$p,$permit,false);
settingThrows(fn()=>NativeCompanyEventMail::claimDispatch($db,$p,$mail,$headers),'native_event_mail_stale','uncertain accepted SMTP cannot be retried');
$public=$events->save(41,7,array_replace($input,['title'=>'Public event','public'=>1]));$result=$worker->run('2026-10-25 13:00:00','https://example.test/crm',$decrypt);essentialCheck($result['notified']===1&&count($db->query('SELECT id FROM '.$p.'mail_queue')->result_array())===3,'public event queues only current authorized company members');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='calendar'");$second=$db->query('SELECT * FROM '.$p.'mail_queue ORDER BY id DESC LIMIT 1')->row();$headers2=unserialize($second->headers,['allowed_classes'=>false]);settingThrows(fn()=>(new NativeCompanyEventMail($db,$p))->state($second,$headers2),'native_event_mail_denied','recipient revocation denies queued public event before credentials');
echo "Calendar event job/mail: 8 actual SQLite checks, timezone/public audiences/template/receipts/retry/revocation; SMTP not executed.\n";

$before=count($db->query('SELECT id FROM '.$p.'mail_queue')->result_array());$pending=$events->save(41,7,array_replace($input,['title'=>'Rollback event']));$pendingId=NativeCompanyResourceId::decode('event',$pending['eventid'])['source_id'];
if($db->driver()==='sqlite'){
$db->query('CREATE TRIGGER event_receipt_ignored BEFORE INSERT ON '.$p.'company_event_mail_deliveries BEGIN SELECT RAISE(IGNORE); END');$failed=$worker->run('2026-10-25 13:00:00','https://example.test/crm',$decrypt);$db->query('DROP TRIGGER event_receipt_ignored');
essentialCheck($failed['deferred']===1&&count($db->query('SELECT id FROM '.$p.'mail_queue')->result_array())===$before,'ignored receipt INSERT rolls back queued message instead of reusing prior insert id');
essentialCheck((int)$db->query('SELECT isstartnotified FROM '.$p.'company_events WHERE eventid=?',[$pendingId])->row()->isstartnotified===0,'failed transaction never marks event notified');
}
$db->query('UPDATE '.$p.'company_events SET isstartnotified=1 WHERE eventid=?',[$pendingId]); // isolate the intentional failure fixture, not a product write.
echo "Calendar receipt rollback: source remains pending after failed atomic queue; fixture isolated.\n";
