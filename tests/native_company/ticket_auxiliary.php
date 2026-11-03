<?php
require __DIR__.'/ticket_read.php';
require_once __DIR__.'/../../application/services/NativeCompanyAuxiliary.php';
foreach(NativeCompanyAuxiliarySchema::definitions('tbl')as$table=>$parts){
 $parts=array_values(array_filter($parts,static fn($part)=>!str_starts_with($part,'KEY ')));$auto=null;
 foreach(NativeCompanyAuxiliarySchema::fields()[$table]as$name=>[$type])if(str_contains($type,'AUTO_INCREMENT'))$auto=$name;
 foreach($parts as&$part){$part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);if($auto!==null&&str_starts_with($part,'`'.$auto.'`'))$part='`'.$auto.'` INTEGER PRIMARY KEY AUTOINCREMENT';}unset($part);
 if($auto!==null)$parts=array_values(array_filter($parts,static fn($part)=>$part!=='PRIMARY KEY (`'.$auto.'`)'));
 $db->query('CREATE TABLE tbl'.$table.' ('.implode(',',$parts).')');
}
if(!$db->field_exists('metadata_json','tblissuers_audit'))$db->query('ALTER TABLE tblissuers_audit ADD COLUMN metadata_json TEXT');
foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,'tblstaff'))$db->query('ALTER TABLE tblstaff ADD COLUMN '.$field.' TEXT');
$aux=new NativeCompanyAuxiliary($db,'tbl',2,17);
$note=$aux->saveNote('ticket',$id,['description'=>'Ticket-scoped note']);
kc(count($aux->notes('ticket',$id))===1,'ticket note persisted through concrete department boundary');
$aux->saveNote('ticket',$id,['description'=>'Edited ticket note'],$note,1);$aux->deleteNote($note,2);kc($aux->notes('ticket',$id)===[],'ticket note update and delete retain owned boundary');
$reminder=$aux->saveReminder('ticket',$id,['description'=>'Ticket reminder','date'=>'2026-10-25 20:00:00','staff'=>17,'notify_by_email'=>0]);
kc($aux->reminder($reminder)['source_kind']==='company_ticket','ticket reminder stores canonical owned source');$aux->deleteReminder($reminder,1);
// Actor17 remains master; actor18 is a company member with feature view but no department membership.
$db->query('INSERT INTO tblissuers_staff(issuer_id,staff_id,can_view) VALUES(2,18,1)');$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,18,'tickets','view')");
$before=$db->query('SELECT COUNT(*) n FROM tblcompany_reminders')->row_array()['n'];$audit=$db->query('SELECT COUNT(*) n FROM tblissuers_audit')->row_array()['n'];
$deny(fn()=>$aux->saveReminder('ticket',$id,['description'=>'Must not persist','date'=>'2026-10-25 20:00:00','staff'=>18,'notify_by_email'=>0]));
kc($before===$db->query('SELECT COUNT(*) n FROM tblcompany_reminders')->row_array()['n']&&$audit===$db->query('SELECT COUNT(*) n FROM tblissuers_audit')->row_array()['n'],'recipient without department creates no reminder or audit');
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');
$deny(fn()=>$aux->saveNote('ticket',$id,['description'=>'Revoked department']));
$deny(fn()=>$aux->notes('ticket',$id));
echo "Ticket auxiliary actual SQLite: note/reminder owned transactions, revision, department recipient and rollback passed.\n";
// Cron uses persisted issuer/actor and the same declared ticket authority; no SMTP path.
$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$db->query('ALTER TABLE tblstaff ADD COLUMN email TEXT');$db->query("UPDATE tblstaff SET email='synthetic@example.invalid'");
if(!defined('BASEPATH'))define('BASEPATH',__DIR__.'/../../system/');if(!defined('APPPATH'))define('APPPATH',__DIR__.'/../../application/');
require_once __DIR__.'/../../application/services/NativeCompanyReminderWorker.php';
foreach(NativeCompanyHeaderSchema::definitions('tbl')as$table=>$parts){$parts=array_values(array_filter($parts,static fn($s)=>!str_starts_with($s,'KEY ')));foreach($parts as&$part){$part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);$part=str_replace('int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);}unset($part);$db->query('CREATE TABLE tbl'.$table.' ('.implode(',',$parts).')');}
$pending=$aux->saveReminder('ticket',$id,['description'=>'Cron real ticket','date'=>'2026-10-25 20:00:00','staff'=>17,'notify_by_email'=>0]);
$worker=new NativeCompanyReminderWorker($db,'tbl');$result=$worker->run('2026-10-25 21:00:00','https://example.invalid/',static fn($x)=>$x);
kc($result['notified']===1&&$result['deferred']===0,'ticket cron revalidates persisted authority and creates notification');
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_native_notifications')->row_array()['n']===1,'one actual notification persists');
kc($worker->run('2026-10-25 21:00:00','https://example.invalid/',static fn($x)=>$x)['notified']===0,'ticket cron replay creates no duplicate notification');
echo "Ticket reminder cron actual SQLite: explicit issuer, department, durable notification and replay passed; no email enabled.\n";
$blockedReminder=$aux->saveReminder('ticket',$id,['description'=>'Revoked before cron','date'=>'2026-10-25 20:00:00','staff'=>17,'notify_by_email'=>0]);$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');
$blocked=$worker->run('2026-10-25 21:00:00','https://example.invalid/',static fn($x)=>$x);kc($blocked['notified']===0&&$blocked['deferred']===1,'revoked department defers actual cron source');kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_native_notifications')->row_array()['n']===1&&(int)$db->query('SELECT isnotified FROM tblcompany_reminders WHERE id=?',[$blockedReminder])->row_array()['isnotified']===0,'revocation produces neither notification nor false notified flag');
echo "Ticket cron revocation: no partial source or notification writes passed.\n";
