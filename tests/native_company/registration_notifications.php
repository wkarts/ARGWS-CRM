<?php
declare(strict_types=1);
require __DIR__.'/header_storage.php';
require_once __DIR__.'/../../application/services/NativePortalRegistrationSchema.php';
foreach(['clients'=>'userid INTEGER PRIMARY KEY,registration_confirmed INTEGER','contacts'=>'id INTEGER PRIMARY KEY,userid INTEGER,active INTEGER','issuers_clients'=>'client_id INTEGER PRIMARY KEY,owner_issuer_id INTEGER']as$t=>$ddl)$db->query('CREATE TABLE tbl'.$t.'('.$ddl.')');
NativePortalRegistrationSchema::ensure($db,'tbl');
$db->query('UPDATE tblstaff SET admin=1 WHERE staffid IN(17,18)');
$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'core_legacy_administration','access')");
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=18');
$db->query('INSERT INTO tblclients VALUES(100,0),(200,1)');$db->query('INSERT INTO tblcontacts VALUES(101,100,0),(201,200,1)');$db->query('INSERT INTO tblissuers_clients VALUES(100,2),(200,3)');
$key=str_repeat('a',64);$hash=str_repeat('b',64);$nonce=str_repeat('c',64);
$db->query('INSERT INTO tblcompany_native_portal_registrations VALUES(?,?,?,?,?,?,?)',[2,$key,$hash,100,101,'2026-10-25 18:00:00',$nonce]);
$tables=NativeCompanyNotifications::tables('registration');$call=fn()=>$n->recordRegistrationInOwnedTransaction(2,$key);
try{$call();throw new LogicException('unowned accepted');}catch(RuntimeException$e){hc($e->getMessage()==='issuer_owned_transaction_required','registration requires owned transaction');}
$ids=IssuerTransaction::run($db,'tbl',$tables,$call);hc(count($ids)===1,'only current issuer admin notified');
hc(IssuerTransaction::run($db,'tbl',$tables,$call)===$ids,'registration notification durable replay');
$rows=$n->read(2,17)['items'];$row=array_values(array_filter($rows,fn($r)=>$r['source_kind']==='registration'))[0];
hc($row['actor_id']===null&&$row['actor_kind']==='contact'&&(int)$row['contact_actor_id']===101,'pending contact remains actual actor');hc($row['url']==='clients/client/100?contactid=101','derived native target');hc($n->read(3,18)['items']===[],'other company sees no registration');
try{IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordRegistrationInOwnedTransaction(3,$key));throw new LogicException('foreign receipt accepted');}catch(DomainException$e){}
$db->query('UPDATE tblissuers_clients SET owner_issuer_id=3 WHERE client_id=100');hc($n->read(2,17)['items']===[],'ownership revocation hides source');
try{IssuerTransaction::run($db,'tbl',$tables,$call);throw new LogicException('foreign owner accepted');}catch(DomainException$e){}$db->query('UPDATE tblissuers_clients SET owner_issuer_id=2 WHERE client_id=100');
$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');hc($n->read(2,17)['items']===[],'admin revocation hides event');$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');
$db->query('DELETE FROM tblcompany_native_notifications WHERE source_kind=?',['registration']);
try{IssuerTransaction::run($db,'tbl',$tables,function()use($call){$call();throw new DomainException('rollback_source');});}catch(DomainException$e){hc($e->getMessage()==='rollback_source','forced rollback');}
hc($db->query("SELECT id FROM tblcompany_native_notifications WHERE source_kind='registration'")->result_array()===[],'source rollback removes event');
$db->query("CREATE TRIGGER registration_admin_revocation AFTER INSERT ON tblcompany_native_notifications WHEN NEW.source_kind='registration' BEGIN UPDATE tblstaff SET admin=0 WHERE staffid=17; END");
try{IssuerTransaction::run($db,'tbl',$tables,$call);throw new LogicException('midwrite revocation accepted');}catch(DomainException$e){}
hc((int)$db->query('SELECT admin FROM tblstaff WHERE staffid=17')->row_array()['admin']===1,'midwrite revocation and event rolled back');hc($db->query("SELECT id FROM tblcompany_native_notifications WHERE source_kind='registration'")->result_array()===[],'no partial event');
$db->query('DROP TRIGGER registration_admin_revocation');
$db->query('UPDATE tblissuers_installation_identity SET primary_issuer_id=3,master_staff_id=17 WHERE id=1');
$namedIds=IssuerTransaction::run($db,'tbl',$tables,$call);hc(count($namedIds)===1,'named issuer notifies actual installation administrator');
$n->markRead(2,17,$namedIds[0],'all');$named=$n->read(2,17)['items'][0];hc($named['read_at']!==null&&$named['inline_read_at']!==null,'named registration read-state persists with dynamic source tables');
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL WHERE id=1');hc($n->read(2,17)['items']===[],'named administrator revocation hides event');
echo "Registration notification real SQLite contracts passed: actual inactive contact actor, issuer admin A/B, receipt replay, source/recipient revocation and rollback. No SMTP or MySQL claim.\n";
