<?php
declare(strict_types=1);
require __DIR__.'/ticket_reply.php';
// An older plain-text body has no sender/department provenance fabricated by editing.
$db->query("UPDATE tblcompany_tickets SET status='open',body='<literal>old text</literal>' WHERE id=?",[$source]);
$before=$reader->get(2,17,$id);$key=str_repeat('3',64);$metadata=$db->query('SELECT * FROM tblcompany_ticket_native_metadata WHERE ticket_id=?',[$source])->row_array();
$result=$updates->editMessage(2,17,$id,'<p>Edited <strong>body</strong></p><script>x()</script>',$before['revision_token'],$key);
$after=$reader->get(2,17,$id);kc($after['message_format']==='native_html'&&str_contains($after['message'],'<strong>')&&!str_contains($after['message'],'script'),'real edit records explicit purified HTML provenance');
kc($db->query('SELECT status FROM tblcompany_tickets WHERE id=?',[$source])->row_array()['status']==='open','historic status marker unchanged');kc($db->query('SELECT * FROM tblcompany_ticket_native_metadata WHERE ticket_id=?',[$source])->row_array()===$metadata,'edit does not fabricate sender or alter department metadata');
kc($updates->editMessage(2,17,$id,'<p>Edited <strong>body</strong></p><script>x()</script>',$before['revision_token'],$key)['replayed'],'same edit durable replay');
$deny(fn()=>$updates->editMessage(3,17,$id,'foreign',$after['revision_token'],str_repeat('4',64)));$deny(fn()=>$updates->editMessage(2,17,$id,'changed',$before['revision_token'],str_repeat('4',64)));
$proof=$db->query('SELECT * FROM tblcompany_ticket_body_native_metadata')->result_array();
$db->query("CREATE TRIGGER fail_edit_audit BEFORE INSERT ON tblissuers_audit WHEN NEW.event='native_ticket_message_edited' BEGIN SELECT RAISE(IGNORE); END");try{$updates->editMessage(2,17,$id,'<p>Rollback</p>',$after['revision_token'],str_repeat('4',64));throw new LogicException('missing audit accepted');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_ticket_write_unconfirmed','audit readback enforced');}
kc($reader->get(2,17,$id)===$after&&$db->query('SELECT * FROM tblcompany_ticket_body_native_metadata')->result_array()===$proof,'body/provenance/revision rollback together');$db->query('DROP TRIGGER fail_edit_audit');
$db->query('UPDATE tblcompany_ticket_body_native_metadata SET issuer_id=3 WHERE ticket_id=?',[$source]);$deny(fn()=>$reader->get(2,17,$id));$db->query('UPDATE tblcompany_ticket_body_native_metadata SET issuer_id=2 WHERE ticket_id=?',[$source]);
echo "Ticket body editing actual SQLite passed: explicit richtext provenance, legacy marker/sender preservation, CAS/replay, cross-company and audit rollback. Reply editing/detail form not yet connected.\n";
