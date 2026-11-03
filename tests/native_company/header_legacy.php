<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
require __DIR__.'/header_storage.php';
require_once __DIR__.'/../../application/services/NativeCompanyLegacyHeader.php';
$db->query('CREATE TABLE tbluser_meta(umeta_id INTEGER PRIMARY KEY,staff_id INTEGER,client_id INTEGER,contact_id INTEGER,meta_key TEXT,meta_value TEXT)');
$db->query('CREATE TABLE tblnotifications(id INTEGER PRIMARY KEY,touserid INTEGER,isread INTEGER,isread_inline INTEGER,date TEXT,description TEXT,additional_data TEXT,link TEXT)');
$db->query('INSERT INTO tbluser_meta VALUES(1,18,0,0,?,?)',['recent_searches','["old primary","second"]']);
$db->query("INSERT INTO tblnotifications VALUES(1,18,0,0,'2026-01-01 00:00:00','message',NULL,''),(2,17,0,0,'2026-01-01 00:00:00','other',NULL,'')");
$l=new NativeCompanyLegacyHeader($db,'tbl');$history=$l->history(2,18);hc(count($history)===2&&$history[0]['id']==='1','historical ID lineage');
foreach([fn()=>$l->history(3,18),fn()=>$l->notifications(3,18),fn()=>$l->markNotification(3,18,1,'all')] as $call){try{$call();throw new LogicException('named accessed legacy');}catch(DomainException $e){hc($e->getMessage()==='native_company_legacy_header_denied','named legacy denied');}}
$l->removeHistory(2,18,1,$history[0]['revision']);hc(count($l->history(2,18))===1,'primary history removal');try{$l->removeHistory(2,18,1,$history[0]['revision']);throw new LogicException('stale accepted');}catch(DomainException $e){hc($e->getMessage()==='issuer_search_stale','legacy revision checked');}
$r=$l->notifications(2,18);hc($r['unread']===1&&count($r['items'])===1,'recipient exact');$l->markNotification(2,18,1,'all');hc($l->notifications(2,18)['unread']===0,'legacy mark read');hc((int)$db->query('SELECT isread FROM tblnotifications WHERE id=2')->row_array()['isread']===0,'other recipient preserved');
try{$l->markNotification(2,18,2,'all');throw new LogicException('other recipient marked');}catch(DomainException $e){}
$db->query('CREATE TABLE tblprojects(id INTEGER PRIMARY KEY,clientid INTEGER,name TEXT)');
$db->query('CREATE TABLE tblissuers_clients(client_id INTEGER,owner_issuer_id INTEGER)');
$db->query("INSERT INTO tblprojects VALUES(99,200,'actual private B project')");$db->query('INSERT INTO tblissuers_clients VALUES(200,3)');
$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,18,'projects','view')");
$db->query("INSERT INTO tblnotifications VALUES(3,18,0,0,'2026-01-02 00:00:00','private B',NULL,'projects/view/99'),(4,18,0,0,'2026-01-02 00:00:00','unmapped target',NULL,'javascript:bad')");
$r=$l->notifications(2,18);hc($r['unread']===0&&count($r['items'])===1,'A has projects permission but real B client ownership still hides notification text/count');
try{$l->markNotification(2,18,3,'all');throw new LogicException('denied resource marked');}catch(DomainException $e){hc($e->getMessage()==='issuer_notification_source_denied','denied resource mutation blocked');}
$l->markNotification(2,18,null,'all');hc(count($db->query('SELECT id FROM tblnotifications WHERE id IN (3,4) AND isread=0')->result_array())===2,'mark-all does not mutate hidden resources');
$db->query('UPDATE tblnotifications SET additional_data=? WHERE id=1',[serialize(new stdClass)]);try{$l->notifications(2,18);throw new LogicException('object metadata accepted');}catch(DomainException $e){hc($e->getMessage()==='native_company_legacy_notification_invalid','serialized object denied');}
echo "Native historical header SQLite: primary-only lineage, exact recipient, stale history and serialized-object negatives passed\n";
