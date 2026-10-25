<?php
declare(strict_types=1);
require __DIR__.'/ticket_reply.php';
$replyDto=$reader->replies(2,17,$contactId)[0];$ticketDto=$reader->get(2,17,$contactId);$nonce=hash('sha256','reply-edit-positive');
$original=$db->query('SELECT * FROM tblcompany_portal_support_replies WHERE reply_id=?',[$replyDto['source_id']])->row_array();
$result=$updates->editReply(2,17,$contactId,$replyDto['id'],'<p>Edited <strong>contact</strong> message</p>',$ticketDto['revision_token'],$replyDto['revision_token'],$nonce);
$after=$reader->replies(2,17,$contactId)[0];kc($after['message_format']==='native_html'&&$after['admin']===null&&$after['contactid']===$replyDto['contactid']&&$after['submitter']===$replyDto['submitter'],'editing contact text does not impersonate author');
kc($db->query('SELECT * FROM tblcompany_portal_support_replies WHERE reply_id=?',[$replyDto['source_id']])->row_array()===$original,'original contact source receipt unchanged');
kc($updates->editReply(2,17,$contactId,$replyDto['id'],'<p>Edited <strong>contact</strong> message</p>',$ticketDto['revision_token'],$replyDto['revision_token'],$nonce)['replayed'],'exact reply edit replay');
$deny(fn()=>$updates->editReply(2,17,$id,$replyDto['id'],'Foreign ticket',$reader->get(2,17,$id)['revision_token'],$after['revision_token'],hash('sha256','reply-edit-negative')));
$before=$reader->get(2,17,$contactId);$proof=$db->query('SELECT * FROM tblcompany_ticket_reply_edit_metadata')->result_array();
$db->query("CREATE TRIGGER failed_reply_edit BEFORE INSERT ON tblissuers_audit WHEN NEW.event='native_ticket_reply_edited' BEGIN SELECT RAISE(IGNORE); END");try{$updates->editReply(2,17,$contactId,$replyDto['id'],'Rollback',$before['revision_token'],$after['revision_token'],hash('sha256','reply-edit-negative'));throw new LogicException('unconfirmed accepted');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_ticket_write_unconfirmed','audit failure fatal');}
kc($reader->replies(2,17,$contactId)[0]===$after&&$reader->get(2,17,$contactId)===$before&&$db->query('SELECT * FROM tblcompany_ticket_reply_edit_metadata')->result_array()===$proof,'body/provenance/parent revision rollback');
$db->query('DROP TRIGGER failed_reply_edit');$db->query('UPDATE tblcompany_ticket_reply_edit_metadata SET issuer_id=3 WHERE reply_id=?',[$replyDto['source_id']]);$deny(fn()=>$reader->replies(2,17,$contactId));
echo "Reply edits real SQLite passed: authenticated editor separate from original contact author, CAS/replay, parent mismatch, foreign proof and atomic rollback. No browser/MySQL claim.\n";
