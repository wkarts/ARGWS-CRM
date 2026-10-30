<?php
/** Real PDO service/worker transactions; SMTP is not called by this worker. */
require __DIR__.'/auxiliary_contract.php';
require_once APPPATH.'services/NativeCompanyReminderWorker.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$db->query("ALTER TABLE {$p}staff ADD email varchar(191)");
$db->query("UPDATE {$p}staff SET email='synthetic@example.invalid'");
foreach([41,82]as$owner)foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,'invoices',?)",[$owner,$cap]);
if(!$db->table_exists($p.'invoices'))$db->query("CREATE TABLE {$p}invoices(id INT PRIMARY KEY,issuer_id INT,number varchar(64))");
if(!$db->field_exists('number',$p.'invoices'))$db->query("ALTER TABLE {$p}invoices ADD number varchar(64)");
$db->query("INSERT INTO {$p}invoices(id,issuer_id,number) VALUES(9401,41,'A-1'),(9402,82,'B-1')");
$auto=$db->driver()==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
$db->query("CREATE TABLE {$p}mail_queue(id {$auto},issuer_id INT,engine TEXT,email TEXT,cc TEXT,bcc TEXT,message TEXT,alt_message TEXT,headers TEXT,attachments TEXT,status TEXT,date TEXT)");
if($db->driver()==='sqlite')$db->query("CREATE TABLE {$p}company_native_mail_dispatch_fences(queue_id INTEGER PRIMARY KEY,issuer_id INT NOT NULL,kind TEXT NOT NULL,binding_hash TEXT NOT NULL,state TEXT NOT NULL,attempt_key TEXT,created_at TEXT NOT NULL,dispatched_at TEXT,completed_at TEXT,UNIQUE(issuer_id,queue_id))");
else NativeCompanyMailDispatchFence::ensure($db,$p);
// Notification actor lineage also references contacts. SQLite resolves even a NULL FK target at INSERT.
// This fixture is staff-only, but must still provide the real parent table before schema creation.
if(!$db->table_exists($p.'contacts'))$db->query("CREATE TABLE {$p}contacts(id INT PRIMARY KEY)");
if($db->driver()==='sqlite'){
foreach(NativeCompanyHeaderSchema::definitions($p)as$table=>$parts){
    $parts=array_values(array_filter($parts,static fn($s)=>!str_starts_with($s,'KEY ')));
    foreach($parts as&$part){$part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);$part=str_replace('int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);}unset($part);
    $db->query('CREATE TABLE '.$p.$table.' ('.implode(',',$parts).')');
}
}else NativeCompanyHeaderSchema::ensure($db,$p);
$db->query("CREATE TABLE {$p}emailtemplates(id {$auto},issuer_id INT,slug TEXT,language TEXT,active INT,subject TEXT,message TEXT,fromname TEXT,plaintext INT)");
$db->data_cache=[];
foreach(NativeCompanyReminderDelivery::tables()as$table)if(!$db->table_exists($p.$table)){$db->query('CREATE TABLE '.$p.$table.' (id INT PRIMARY KEY)');$db->data_cache=[];}
$a=new NativeCompanyAuxiliary($db,$p,41,12);$b=new NativeCompanyAuxiliary($db,$p,82,12);
$one=$a->saveReminder('invoice',9401,['description'=>'Reminder A','date'=>'2026-10-25 10:00:00','staff'=>12]);
$two=$b->saveReminder('invoice',9402,['description'=>'Reminder B','date'=>'2026-10-25 10:00:00','staff'=>12]);
$worker=new NativeCompanyReminderWorker($db,$p);$clock='2026-10-25 11:00:00';
$r=$worker->run($clock,'https://crm.example.invalid',static fn($v)=>$v);
essentialCheck($r===['notified'=>2,'deferred'=>0],'explicit issuer enumeration creates both real notifications');
$notes=$db->query("SELECT issuer_id,body FROM {$p}company_native_notifications ORDER BY issuer_id")->result_array();
essentialCheck(count($notes)===2&&$notes[0]['body']==='Reminder A'&&$notes[1]['body']==='Reminder B','notification source remains owned');
essentialCheck($worker->run($clock,'https://crm.example.invalid',static fn($v)=>$v)===['notified'=>0,'deferred'=>0],'replay creates no duplicate notifications');
$third=$a->saveReminder('invoice',9401,['description'=>'Atomic','date'=>'2026-10-25 10:00:00','staff'=>12]);
$db->failOn='INSERT INTO '.$p.'company_native_notifications';$r=$worker->run($clock,'https://crm.example.invalid',static fn($v)=>$v);$db->failOn=null;
essentialCheck($r['deferred']===1&&(int)$a->reminder($third)['isnotified']===0,'notification write failure rolls back source marker');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='invoices'");
essentialCheck($worker->run($clock,'https://crm.example.invalid',static fn($v)=>$v)['deferred']===1,'revoked source access prevents dispatch and marking');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===0,'notification-only reminders never queue synthetic email');
echo "Native reminder worker PDO ownership/replay/rollback: passed.\n";
foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'invoices',?)",[$cap]);
$db->query("INSERT INTO {$p}emailtemplates(issuer_id,slug,language,active,subject,message,fromname,plaintext) VALUES(41,'reminder-email-staff','portuguese_br',1,'Reminder {staff_firstname}','<p>{staff_reminder_description}</p><a href=\"{staff_reminder_relation_link}\">Open</a>','Synthetic',0)");
$db->data_cache=[];
$store->updateBatch(41,12,['mail_engine'=>'phpmailer','email_protocol'=>'smtp','smtp_host'=>'smtp.example.invalid','smtp_email'=>'sender@example.invalid','smtp_port'=>'587','smtp_encryption'=>'tls','smtp_email_charset'=>'utf-8','smtp_password'=>'fixture-only','active_language'=>'portuguese_br']);
$emailReminder=$a->saveReminder('invoice',9401,['description'=>'Mail <private>','date'=>'2026-10-25 10:00:00','staff'=>12,'notify_by_email'=>1]);
$r=$worker->run($clock,'https://crm.example.invalid/subpath',static fn($v)=>$v);
essentialCheck($r['notified']===2&&$r['deferred']===0,'retry rolled-back reminder and email reminder each committed once');
$email=$db->query("SELECT * FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row();$headers=unserialize($email->headers,['allowed_classes'=>false]);
essentialCheck(str_contains($email->message,'Mail &lt;private&gt;')&&str_contains($email->message,'/subpath/admin/invoices/list_invoices/9401'),'native template escapes content and preserves subpath/native route');
settingThrows(fn()=>NativeCompanyReminderDelivery::claimDispatch($db,$p,$email,$headers),'company_mail_dispatch_review_required','pending queue cannot dispatch without queue claim');
$db->query("UPDATE {$p}mail_queue SET status='sending' WHERE id=?",[$email->id]);
$permit=NativeCompanyReminderDelivery::claimDispatch($db,$p,$email,$headers);
essentialCheck(is_array($permit)&&!$db->pdo->inTransaction(),'mail permit returned only after independent commit');
NativeCompanyReminderDelivery::completeDispatch($db,$p,$permit,false);
settingThrows(fn()=>NativeCompanyReminderDelivery::claimDispatch($db,$p,$email,$headers),'company_mail_dispatch_review_required','unknown SMTP result cannot auto-resend');
$bad=$headers;unset($bad[NativeCompanyReminderDelivery::HEADER]);
settingThrows(fn()=>NativeCompanyReminderDelivery::assertQueueRouting($db,$p,$email,$bad),'native_reminder_delivery_denied','removing internal header cannot downgrade owned mail to global route');
$next=$a->saveReminder('invoice',9401,['description'=>'Config changes','date'=>'2026-10-25 10:00:00','staff'=>12,'notify_by_email'=>1]);$worker->run($clock,'https://crm.example.invalid',static fn($v)=>$v);
$email2=$db->query("SELECT * FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row();$headers2=unserialize($email2->headers,['allowed_classes'=>false]);$db->query("UPDATE {$p}mail_queue SET status='sending' WHERE id=?",[$email2->id]);
$store->updateBatch(41,12,['smtp_host'=>'changed.example.invalid']);
settingThrows(fn()=>NativeCompanyReminderDelivery::claimDispatch($db,$p,$email2,$headers2),'native_reminder_delivery_stale','configuration rotation rejects prepared old mail before SMTP');
essentialCheck($db->query("SELECT state FROM {$p}company_native_mail_dispatch_fences WHERE queue_id=?",[$email2->id])->row()->state==='prepared','rejected configuration never consumes a send permit');
echo "Native reminder mail envelope and durable fence: passed.\n";
require_once APPPATH.'services/NativeCompanyLegacyReminderScope.php';
essentialCheck(NativeCompanyLegacyReminderScope::permits($db,$p,['staff'=>7,'rel_type'=>'invoice','rel_id'=>42]),'legacy principal invoice still eligible for legacy worker');
essentialCheck(!NativeCompanyLegacyReminderScope::permits($db,$p,['staff'=>7,'rel_type'=>'invoice','rel_id'=>9401]),'global worker cannot hydrate named invoice even for installation master');
essentialCheck(!NativeCompanyLegacyReminderScope::permits($db,$p,['staff'=>7,'rel_type'=>'invoice','rel_id'=>NativeCompanyResourceId::encode('company_reminder',1)]),'encoded owned ID cannot enter global relation loader');
essentialCheck(!NativeCompanyLegacyReminderScope::permits($db,$p,['staff'=>12,'rel_type'=>'invoice','rel_id'=>42]),'named-only member cannot receive legacy principal reminder');
echo "Legacy cron relation ownership fence: passed.\n";
require_once APPPATH.'services/NativeCompanyDateInput.php';
$store->updateBatch(41,12,['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24']);
$dates=new NativeCompanyDateInput($db,$p,41);
essentialCheck($dates->parse('25/10/2026 13:45')==='2026-10-25 13:45:00'&&$dates->display('2026-10-25 13:45:00')==='25/10/2026 13:45','same native company date/time format round-trips');
settingThrows(fn()=>$dates->parse('31/02/2026 13:45'),'company_auxiliary_invalid_input','calendar overflow is rejected not silently normalized');
$store->updateBatch(41,12,['time_format'=>'12']);$dates=new NativeCompanyDateInput($db,$p,41);
essentialCheck($dates->parse('25/10/2026 1:45 PM')==='2026-10-25 13:45:00','native 12-hour UI preserves scheduled wall clock');
echo "Native reminder input date formats: passed.\n";
