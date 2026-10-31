<?php
declare(strict_types=1);
require __DIR__.'/knowledge_read.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketContacts.php';
$db->query('ALTER TABLE tblstaff ADD COLUMN is_not_staff INTEGER NOT NULL DEFAULT 0');
$db->query("ALTER TABLE tblissuers_registry ADD COLUMN settings_json TEXT NOT NULL DEFAULT '{}'");
foreach(['options'=>'id INTEGER PRIMARY KEY,name TEXT,value TEXT','clients'=>'userid INTEGER PRIMARY KEY,active INTEGER','contacts'=>'id INTEGER PRIMARY KEY,userid INTEGER,active INTEGER,firstname TEXT,lastname TEXT,email TEXT,ticket_emails INTEGER NOT NULL DEFAULT 1','issuers_clients'=>'client_id INTEGER PRIMARY KEY,owner_issuer_id INTEGER','company_customer_admins'=>'issuer_id INTEGER,client_id INTEGER,staff_id INTEGER','customer_admins'=>'customer_id INTEGER,staff_id INTEGER']as$t=>$ddl)$db->query('CREATE TABLE tbl'.$t.'('.$ddl.')');
TestPdo::registerSqliteFunction($db->pdo,'CONCAT',static fn(...$parts)=>implode('',$parts));
$db->query("INSERT INTO tblclients VALUES(1,1),(2,1),(3,1),(4,0)");$db->query('INSERT INTO tblissuers_clients VALUES(2,2),(3,3),(4,2)');
$db->query("INSERT INTO tblcontacts(id,userid,active,firstname,lastname,email) VALUES(1,1,1,'Historical','One','primary@example.invalid'),(2,2,1,'Own','Two','a@example.invalid'),(3,3,1,'Private','Three','b@example.invalid'),(4,2,0,'Inactive','Contact','off@example.invalid'),(5,4,1,'Inactive','Client','off-client@example.invalid')");
foreach([1,2]as$i)foreach(['view','create']as$cap)$db->query("INSERT INTO tblissuers_resource_permissions VALUES(?,17,'tickets',?)",[$i,$cap]);
$lookup=new NativeCompanyTicketContacts($db,'tbl');$db->queries=[];$rows=$lookup->options(2,17);kc($rows===[['id'=>'2','name'=>'Own Two','subtext'=>'a@example.invalid']],'ticket-only actor sees own active contact without customer profile permission');kc(!preg_grep('/FROM tbloptions/',$db->queries),'named lookup never reads global option rows');
$deny(fn()=>$lookup->options(2,17,'','3'));$deny(fn()=>$lookup->options(2,17,'','1'));$deny(fn()=>$lookup->options(2,17,'','2','3'));$deny(fn()=>$lookup->options(2,17,'','4'));kc(count($lookup->options(1,17))===1,'principal only proven historical contacts');
kc(count($lookup->options(2,17,'Own Two'))===1,'native full name query');kc($lookup->options(2,17,'%')===[],'wildcard escaped');
$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=2',[json_encode(['company_options'=>['staff_members_open_tickets_to_all_contacts'=>'0']])]);kc($lookup->options(2,17)===[],'option0 requires customer authority');
$db->query('INSERT INTO tblcompany_customer_admins VALUES(2,2,17),(2,3,17)');kc(count($lookup->options(2,17))===1,'assigned owned client permitted; corrupt foreign assignment not a grant');$deny(fn()=>$lookup->options(2,17,'','3'));
$db->query('DELETE FROM tblcompany_customer_admins');$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'customers','view')");kc(count($lookup->options(2,17))===1,'customer view remains scoped');
$db->query("DELETE FROM tblissuers_resource_permissions WHERE issuer_id=2 AND staff_id=17 AND feature='tickets' AND capability='create'");$deny(fn()=>$lookup->options(2,17));
echo "Ticket contact lookup real SQLite: native ticket-only capability, scoped option0/1, A/B/principal, assigned-client boundary, inactive/revoked negatives, full-name and escaped search passed; no portal/profile grant.\n";
