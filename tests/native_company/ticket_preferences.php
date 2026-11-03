<?php
declare(strict_types=1);
require __DIR__.'/ticket_catalog.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketPreferences.php';
$db->query('DROP TRIGGER fail_catalog_audit');$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$db->query('CREATE TABLE tbloptions(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT UNIQUE,value TEXT,autoload INTEGER DEFAULT 1)');
$db->query("INSERT INTO tbltickets_status VALUES(3,'Answered',1,'#123456',3)");$db->query("INSERT INTO tbltickets_priorities VALUES(2,'Medium')");
$db->query("INSERT INTO tbloptions(name,value) VALUES('services','0'),('ticket_replies_order','desc')");
$prefs=new NativeCompanyTicketPreferences($db,'tbl');$db->queries=[];$a=$prefs->read(2,17);
kc($a['values']['services']==='1'&&$a['values']['ticket_replies_order']==='asc','named public factory and existing owned reply default retained');
kc(!preg_grep('/FROM tbloptions/',$db->queries),'named never reads global option rows');
kc($a['sources']['default_ticket_reply_status']==='owned_public_catalog'&&$a['values']['default_ticket_reply_status']!=='3','default status resolves real owned catalog, not foreign ID');
$b=$prefs->read(3,18);$primary=$prefs->read(1,17);kc($primary['values']['services']==='0'&&$primary['values']['ticket_replies_order']==='desc','proven principal values retained');
$save=fn($issuer,$actor,$rev,$values)=>IssuerTransaction::run($db,'tbl',$prefs->tables(),fn()=>$prefs->saveInOwnedTransaction($issuer,$actor,$rev,$values));
$changed=$save(2,17,$a['revision'],['services'=>'0','ticket_replies_order'=>'desc']);kc($changed['values']['services']==='0'&&$changed['values']['ticket_replies_order']==='desc','same atomic form writes option and existing owned order node');
kc($prefs->read(3,18)['revision']===$b['revision'],'company B unchanged');$deny(fn()=>$save(2,17,$a['revision'],['services'=>'1']));
$deny(fn()=>$save(2,17,$changed['revision'],['default_ticket_reply_status'=>$b['values']['default_ticket_reply_status']]));
$deny(fn()=>$save(2,17,$changed['revision'],['ai_enable_ticket_reply_suggestions'=>'1']));kc(!$changed['ai_available']&&$changed['ai_unavailable_reason']==='company_scoped_provider_not_implemented','AI gap explicit, not a provider success');
foreach([['maximum_allowed_ticket_attachments'=>'2147483648'],['services'=>true],['ticket_attachments_file_extensions'=>'.php'],['unknown'=>'1'],['ticket_replies_order'=>'DESC']]as$bad)$deny(fn()=>NativeCompanyTicketPreferences::prepare($bad));
$before=$db->query('SELECT settings_json FROM tblissuers_registry WHERE id=2')->row_array();$db->query("CREATE TRIGGER fail_prefs_audit BEFORE INSERT ON tblissuers_audit BEGIN SELECT RAISE(FAIL,'synthetic failure'); END");
try{$save(2,17,$changed['revision'],['services'=>'1','ticket_replies_order'=>'asc']);throw new LogicException('audit failure accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_storage_failed','audit failure surfaced');}
kc($before===$db->query('SELECT settings_json FROM tblissuers_registry WHERE id=2')->row_array(),'option and existing order node rolled back together');$db->query('DROP TRIGGER fail_prefs_audit');
$pchanged=$save(1,17,$primary['revision'],['services'=>'1']);kc($pchanged['values']['services']==='1','same primary option storage retained');
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$prefs->read(2,17));
$seed=file_get_contents(__DIR__.'/../../install/database.sql');foreach(NativeCompanyTicketPreferences::DEFAULTS as$key=>$value){$pattern='/\\(\\d+, '.preg_quote("'".$key."', '".$value."'",'/').'/';kc(preg_match($pattern,$seed)===1,'public seed provenance '.$key);}
echo "Native Tickets preferences real SQLite: 27 native fields, A/B/principal, canonical catalogs, shared reply-order node, stale/foreign/revoked guards, atomic rollback and explicit AI gap passed.\n";
