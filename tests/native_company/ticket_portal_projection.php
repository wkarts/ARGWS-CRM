<?php
/** Stored native content is projected as text only after portal ownership/publication. */
require __DIR__.'/ticket_detail_page.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$projection=new NativeCompanyTicketPortalProjection($wrapped,'tbl');
$raw=$db->query('SELECT * FROM tblcompany_tickets WHERE id=?',[$source])->row_array();
$record=$projection->ticket(2,$raw);kc($record['status']==='open'&&!str_contains($record['body'],'<p>'),'Original native status reference and stored HTML produce the public text projection');
$deny(fn()=>$projection->ticket(3,$raw));
$status=$db->query("SELECT * FROM tblcompany_ticket_catalog_references WHERE ticket_id=? AND kind='status'",[$source])->row_array();
$db->query('UPDATE tblcompany_ticket_catalog_references SET issuer_id=3 WHERE id=?',[$status['id']]);$deny(fn()=>$projection->ticket(2,$raw));$db->query('UPDATE tblcompany_ticket_catalog_references SET issuer_id=2 WHERE id=?',[$status['id']]);
$reader=new NativeCompanyTicketRead($wrapped,'tbl');$updates=new NativeCompanyTicketUpdates($wrapped,'tbl');$before=$reader->get(2,17,$id);$literal='Internal reply <script> & preserved text';
$updates->reply(2,17,$id,htmlspecialchars($literal,ENT_QUOTES,'UTF-8'),null,$before['revision_token'],hash('sha256','public-literal'));
$reply=$db->query('SELECT * FROM tblcompany_ticket_replies WHERE ticket_id=? ORDER BY id DESC LIMIT 1',[$source])->row_array();
kc($projection->reply(2,$source,$reply)['body']===$literal,'Public text preserves literal script characters without interpreting them as HTML');
$deny(fn()=>$projection->reply(3,$source,$reply));$deny(fn()=>$projection->reply(2,$source+1,$reply));
$statuses=array_column((new NativeCompanyTicketCatalog($wrapped,'tbl'))->list(2,17,'status'),'native_id','code');
$before=$reader->get(2,17,$id);$updates->changeStatus(2,17,$id,$statuses['closed'],$before['revision_token'],hash('sha256','public-closed'));
$closed=$projection->ticket(2,$db->query('SELECT * FROM tblcompany_tickets WHERE id=?',[$source])->row_array());
kc($closed['status']==='closed','Public lifecycle follows the authoritative native terminal status, retaining the stored native marker');
kc($db->query('SELECT status FROM tblcompany_tickets WHERE id=?',[$source])->row_array()['status']==='native','Public projection never rewrites the stored lifecycle provenance');
echo "Native ticket portal projection: actual catalog, typed content, literal text, foreign/mixed provenance and terminal state passed.\n";
