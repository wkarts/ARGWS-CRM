<?php
declare(strict_types=1);
require __DIR__.'/header_storage.php';
foreach(['clients'=>'userid INTEGER PRIMARY KEY','contacts'=>'id INTEGER PRIMARY KEY,userid INTEGER','issuers_clients'=>'client_id INTEGER PRIMARY KEY,owner_issuer_id INTEGER','gdpr_requests'=>'id INTEGER PRIMARY KEY,clientid INTEGER,contact_id INTEGER,lead_id INTEGER,request_type TEXT','company_native_gdpr_request_owners'=>'request_id INTEGER PRIMARY KEY,issuer_id INTEGER,source_kind TEXT,source_id INTEGER']as$t=>$ddl)$db->query('CREATE TABLE tbl'.$t.' ('.$ddl.')');
$db->query('INSERT INTO tblclients VALUES(10),(20)');$db->query('INSERT INTO tblcontacts VALUES(100,10),(200,20)');$db->query('INSERT INTO tblissuers_clients VALUES(10,2),(20,3)');
$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'core_legacy_administration','access')");
$db->query("INSERT INTO tblgdpr_requests VALUES(1,10,100,0,'account_removal'),(2,20,200,0,'account_removal')");$db->query("INSERT INTO tblcompany_native_gdpr_request_owners VALUES(1,2,'contact',100),(2,3,'contact',200)");
$tables=NativeCompanyNotifications::tables('privacy_request');$work=fn()=>IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordPrivacyRequestInOwnedTransaction(2,1));
$ids=$work();hc(count($ids)===1,'only actual company administrator notified');hc($work()===$ids,'real request event idempotent');
$row=$db->query('SELECT * FROM tblcompany_native_notifications WHERE id=?',[$ids[0]])->row_array();hc($row['actor_kind']==='contact'&&$row['actor_id']===null&&$row['contact_actor_id']===100,'contact actor explicit, never forged staff');
$visible=$n->read(2,17)['items'];$p=array_values(array_filter($visible,fn($r)=>$r['source_kind']==='privacy_request'));hc(count($p)===1&&$p[0]['url']==='gdpr/index?page=forgotten','same native privacy destination');hc(!str_contains($p[0]['body'],'@'),'body has no requester address or description');
$deny=static function(callable$f):void{try{$f();}catch(DomainException$e){return;}throw new LogicException('expected source denied');};
$deny(fn()=>IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordPrivacyRequestInOwnedTransaction(2,2)));
$db->query('UPDATE tblissuers_clients SET owner_issuer_id=3 WHERE client_id=10');hc(!array_filter($n->read(2,17)['items'],fn($r)=>$r['source_kind']==='privacy_request'),'ownership change removes disclosure');$db->query('UPDATE tblissuers_clients SET owner_issuer_id=2 WHERE client_id=10');
$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');hc(!array_filter($n->read(2,17)['items'],fn($r)=>$r['source_kind']==='privacy_request'),'admin revocation removes disclosure');$db->query('UPDATE tblstaff SET admin=1 WHERE staffid=17');
$db->query('UPDATE tblcompany_native_notifications SET actor_id=17 WHERE id=?',[$ids[0]]);$deny(fn()=>$n->read(2,17));$db->query('UPDATE tblcompany_native_notifications SET actor_id=NULL WHERE id=?',[$ids[0]]);
$before=count($db->query('SELECT id FROM tblcompany_native_notifications')->result_array());
try{IssuerTransaction::run($db,'tbl',$tables,function()use($db,$n){$db->query("INSERT INTO tblgdpr_requests VALUES(3,10,100,0,'account_removal')");$db->query("INSERT INTO tblcompany_native_gdpr_request_owners VALUES(3,2,'contact',100)");$n->recordPrivacyRequestInOwnedTransaction(2,3);throw new DomainException('fixture_request_rollback');});}catch(DomainException$e){hc($e->getMessage()==='fixture_request_rollback','source rollback');}
hc(!$db->query('SELECT id FROM tblgdpr_requests WHERE id=3')->result_array()&&count($db->query('SELECT id FROM tblcompany_native_notifications')->result_array())===$before,'request/notification commit atomically, never notify rolled-back request');
echo "Actual SQLite privacy notification: source ownership, real contact actor, recipient administration, replay, revocation, dual-actor corruption and source rollback passed. No SMTP claim.\n";
