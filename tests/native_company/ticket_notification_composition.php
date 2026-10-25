<?php
declare(strict_types=1);
require __DIR__.'/ticket_read.php';require_once __DIR__.'/../../application/services/NativeCompanyNotifications.php';
$db->query('CREATE TABLE tblcompany_reminders(id INTEGER,issuer_id INTEGER,staff INTEGER,creator INTEGER,rel_type TEXT,rel_id TEXT,source_kind TEXT,source_id INTEGER,description TEXT,revision INTEGER)');
$db->query('CREATE TABLE tblcompany_native_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,recipient_id INTEGER,actor_kind TEXT,actor_id INTEGER,contact_actor_id INTEGER,source_kind TEXT,source_id INTEGER,resource_id TEXT,event_key TEXT,title TEXT,body TEXT,created_at TEXT,read_at TEXT,inline_read_at TEXT,UNIQUE(issuer_id,recipient_id,event_key))');
$db->query('INSERT INTO tblcompany_reminders VALUES(1,2,17,17,?,?,?,?,?,1)',['ticket',$id,'company_ticket',NativeCompanyResourceId::decode('ticket',$id)['source_id'],'Owned reminder']);
$n=new NativeCompanyNotifications($db,'tbl');$base=NativeCompanyNotifications::tables('reminder');kc(!in_array('company_ticket_catalog',$base,true),'base reminder transaction does not force unrelated ticket tables');
$tables=$n->transactionTables(2,17,'reminder',1);kc(array_diff($reader->tables(),$tables)===[],'ticket source metadata plans exact full reader dependencies');
$notification=IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(2,17,17,'reminder',1));
kc($n->read(2,17)['unread']===1,'ticket reminder visible through actual source delegate');
$r=$n->markRead(2,17,$notification,'all');kc($r['unread']===0&&count($r['items'])===1,'mark-read plans dependencies then joins owned transaction without nested fallback');
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');
$db->query('DELETE FROM tblcompany_department_members WHERE department_source_id=1');$deny(fn()=>$n->markRead(2,17,$notification));kc($n->read(2,17)['items']===[],'revoked department hides source and denies explicit mark-read');
$before=$db->query('SELECT * FROM tblcompany_native_notifications ORDER BY id')->result_array();$deny(fn()=>IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(2,17,17,'reminder',1)));kc($before===$db->query('SELECT * FROM tblcompany_native_notifications ORDER BY id')->result_array(),'recipient authority required before event write');
echo "Ticket notifications actual source/reader/PDO: finite metadata dependency plan, owned write/read/mark-read, no unrelated table demand, department revocation and no partial event passed.\n";
