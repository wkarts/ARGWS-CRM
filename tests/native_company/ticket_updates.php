<?php
declare(strict_types=1);
require __DIR__.'/ticket_create.php';
require_once __DIR__.'/../../application/services/NativeCompanyTicketUpdates.php';
$updates=new NativeCompanyTicketUpdates($wrapped,'tbl');$reader=new NativeCompanyTicketRead($wrapped,'tbl');
$status=$catalog->list(2,17,'status')[1]['native_id'];$before=$reader->get(2,17,$id);$key=str_repeat('f',64);
$result=$updates->changeStatus(2,17,$id,$status,$before['revision_token'],$key);$after=$reader->get(2,17,$id);
kc(!$result['replayed']&&$after['status']===$status&&$after['message']===$before['message']&&$result['revision']===$after['revision_token'],'real status CAS preserves message and returns revision');
kc($updates->changeStatus(2,17,$id,$status,$before['revision_token'],$key)['replayed'],'durable status replay');
$deny(fn()=>$updates->changeStatus(2,17,$id,$status,$before['revision_token'],str_repeat('1',64)));
$foreign=$catalog->list(3,17,'status')[0]['native_id'];$deny(fn()=>$updates->changeStatus(2,17,$id,$foreign,$after['revision_token'],str_repeat('2',64)));
$db->query("CREATE TRIGGER deny_status_audit BEFORE INSERT ON tblissuers_audit WHEN NEW.event='native_ticket_status_updated' BEGIN SELECT RAISE(IGNORE); END");
try{$updates->changeStatus(2,17,$id,$status,$after['revision_token'],str_repeat('3',64));throw new LogicException('audit failure accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_ticket_write_unconfirmed','audit readback failure');}
kc($reader->get(2,17,$id)===$after,'catalog reference and revision rolled back with failed audit');$db->query('DROP TRIGGER deny_status_audit');
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$deny(fn()=>$updates->changeStatus(2,17,$id,$status,$after['revision_token'],str_repeat('4',64)));$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$db->pdo->beginTransaction();try{$updates->changeStatus(2,17,$id,$status,$after['revision_token'],str_repeat('5',64));throw new LogicException('raw transaction accepted');}catch(RuntimeException$e){}finally{$db->pdo->rollBack();}
$db->query("INSERT INTO tblcompany_tickets(id,issuer_id,client_id,subject,body,status,priority,assignee_id,revision,created_at,updated_at) VALUES(90,2,2,'Historical owned','<not-html>literal</not-html>','open','normal',17,1,'2026-01-01 00:00:00','2026-01-01 00:00:00')");
$historical=NativeCompanyResourceId::encode('company_ticket',90);$old=$reader->get(2,17,$historical);
$updates->changeStatus(2,17,$historical,$status,$old['revision_token'],str_repeat('6',64));$oldAfter=$reader->get(2,17,$historical);
kc($oldAfter['status']===$status&&$oldAfter['message_format']==='plain_text'&&$oldAfter['message']===$old['message'],'historical owned text provenance is not rewritten by catalog change');
echo "Ticket lifecycle status actual SQLite: CAS, durable replay, stale/foreign/ACL negatives, audit rollback and independent transaction passed. No MySQL concurrency proof.\n";
