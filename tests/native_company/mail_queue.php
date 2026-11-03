<?php
require __DIR__.'/../settings/resume_439_support.php';
require_once APPPATH.'services/NativeCompanyMailQueue.php';
$db->resetInsertIdAfterRead=true; // Exercise real operations under strict mysqli-like ID lifetime.
$db->query('INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[12,'synthetic_queue_insert_id',41,gmdate('Y-m-d H:i:s')]);
essentialCheck($db->insert_id()>0,'strict queue fixture reads the generated audit id immediately');
$db->query('SELECT 1');essentialCheck($db->insert_id()===0,'strict queue fixture resets insert id after a read');
$db->query('CREATE TABLE '.$p.'mail_queue(id INTEGER PRIMARY KEY,issuer_id INTEGER NULL,email TEXT,headers TEXT,status TEXT,message TEXT)');
foreach([[1,41,'a@example.invalid'],[2,82,'b@example.invalid'],[3,null,'principal@example.invalid']]as[$id,$issuer,$email])$db->query('INSERT INTO '.$p.'mail_queue VALUES(?,?,?,?,?,?)',[$id,$issuer,$email,serialize(['subject'=>'Owned subject '.$id,'syntheticSecret'=>'must-not-enter-view']),'pending','private body']);
$queue=new NativeCompanyMailQueue($db,$p);$rows=$queue->page(41,12);
essentialCheck(count($rows)===1&&$rows[0]->id===1&&!isset($rows[0]->headers)&&!isset($rows[0]->message),'queue DTO selects only issuer-owned metadata, not envelope secrets/body');
settingThrows(fn()=>$queue->remove(41,12,2),'native_mail_queue_denied','cross-company deletion refused');
$db->query("CREATE TRIGGER native_queue_audit_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_mail_queue_removed' BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$queue->remove(41,12,1),'issuer_native_mail_queue_write_unconfirmed','late audit INSERT must be confirmed');$db->query('DROP TRIGGER native_queue_audit_failure');
essentialCheck(count($queue->page(41,12))===1,'late audit failure rolls queue deletion back');
$queue->remove(41,12,null);essentialCheck(count($queue->page(41,12))===0&&count($queue->page(82,12))===1&&count($db->query('SELECT id FROM '.$p.'mail_queue WHERE issuer_id IS NULL')->result_array())===1,'clear affects only issuer A, not B or historical principal');
$db->query('UPDATE '.$p.'mail_queue SET headers=? WHERE id=2',['O:8:"stdClass":0:{}']);settingThrows(fn()=>$queue->page(82,12),'native_mail_queue_invalid','object serialization cannot become queue view metadata');
echo "Native queue: actual SQLite ownership, safe projection, audit rollback and malformed-envelope negatives passed.\n";
