<?php
/** Real writer and original view; multipart parsing is covered in operation_files_browser. */
require __DIR__.'/ticket_detail_page.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$reader=new NativeCompanyTicketRead($wrapped,'tbl');$updates=new NativeCompanyTicketUpdates($wrapped,'tbl');
$before=$reader->get(2,17,$id);$nonce=hash('sha256','private-independent-upload');
$bytes="%PDF-1.4\nPrivate attachment without a manufactured reply\n%%EOF\n";$files=[['name'=>'private.pdf','bytes'=>$bytes]];
$replyCount=(int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_replies')->row_array()['n'];
$result=$updates->uploadFiles(2,17,$id,$files,$before['revision_token'],$nonce);
$fileKey=hash('sha256','native-ticket-file|2|'.$nonce.'|0');
$saved=(new NativeCompanyTicketFiles($wrapped,'tbl'))->read(2,17,$id,$fileKey);
kc($saved['content']===$bytes&&$result['revision']!==$before['revision_token'],'Private file preserves actual bytes and advances the parent revision');
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_replies')->row_array()['n']===$replyCount,'Independent upload does not create a fake reply');
kc($updates->uploadFiles(2,17,$id,$files,$before['revision_token'],$nonce)['replayed'],'Durable retry preserves one file');
$deny(fn()=>$updates->uploadFiles(2,17,$id,[['name'=>'changed.pdf','bytes'=>$bytes]],$before['revision_token'],$nonce));
$deny(fn()=>$updates->uploadFiles(2,17,$id,$files,$before['revision_token'],hash('sha256','stale-upload')));
$deny(fn()=>$updates->uploadFiles(3,17,$id,$files,$result['revision'],hash('sha256','foreign-upload')));
$current=$reader->get(2,17,$id);$fileCount=(int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_files')->row_array()['n'];
$db->query("CREATE TRIGGER private_upload_audit BEFORE INSERT ON tblissuers_audit WHEN NEW.event='native_ticket_files_uploaded' BEGIN SELECT RAISE(IGNORE); END");
try{$updates->uploadFiles(2,17,$id,$files,$current['revision_token'],hash('sha256','failed-upload'));throw new LogicException('Unconfirmed audit accepted');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_ticket_write_unconfirmed','Ignored audit fails explicitly');}
kc($current===$reader->get(2,17,$id)&&(int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_files')->row_array()['n']===$fileCount,'Audit failure rolls back bytes, revision and request receipt');
$db->query('DROP TRIGGER private_upload_audit');
$statuses=array_column((new NativeCompanyTicketCatalog($wrapped,'tbl'))->list(2,17,'status'),'native_id','code');
$closed=$updates->changeStatus(2,17,$id,$statuses['closed'],$current['revision_token'],hash('sha256','close-upload'));
$deny(fn()=>$updates->uploadFiles(2,17,$id,$files,$closed['revision'],hash('sha256','closed-upload')));
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_files')->row_array()['n']===$fileCount,'Terminal status cannot accept an independent upload');
echo "Ticket private upload: real bytes, independent history, receipt, CAS, foreign parent, closed state and rollback passed.\n";
