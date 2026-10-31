<?php
declare(strict_types=1);
require __DIR__.'/ticket_status_endpoint.php';
foreach(NativeCompanyTicketReplySchema::definitions('tbl')as$table=>$parts)if($table!==NativeCompanyTicketReplySchema::BODY)ticketSqliteDdl($table,$parts,$db);
$wrapped=new TicketCreateDb($db,NativeCompanyTicketReplySchema::definitions('tbl'));$ci->db=$wrapped;$reader=new NativeCompanyTicketRead($wrapped,'tbl');
$ci->router=new class{function fetch_method(){return 'edit_message';}};$page=new Tickets();$before=$reader->get(2,17,$id);
$ci->input->data=['type'=>'ticket','id'=>$id,'main_ticket'=>$id,'data'=>'<p>Native edited body</p>','revision'=>$before['revision_token'],'request_nonce'=>str_repeat('2',64),'_native_company_csrf'=>'synthetic-csrf'];
$page->edit_message();$result=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);kc($result['success']&&$result['revision']===$reader->get(2,17,$id)['revision_token'],'actual original Tickets edit endpoint uses scoped writer');$page->edit_message();kc(json_decode($ci->output->body,true)['replayed'],'HTTP edit replay');
$ci->input->verb='GET';$deny(fn()=>$page->edit_message());$ci->input->verb='POST';$ci->input->data['_native_company_csrf']='wrong';$deny(fn()=>$page->edit_message());$ci->input->data['_native_company_csrf']='synthetic-csrf';$ci->input->data['type']='reply';$deny(fn()=>$page->edit_message());$ci->input->data['type']='ticket';$ci->input->data['main_ticket']='1';$deny(fn()=>$page->edit_message());
echo "Actual ticket edit endpoint passed: original controller, real SQLite, typed POST/context/CSRF, replay and no global hydration. Reply input requires its separate provenance revision; route not admitted.\n";

$db->query('CREATE TABLE tblcompany_portal_support_replies(issuer_id INTEGER,ticket_id INTEGER,client_id INTEGER,contact_id INTEGER,reply_id INTEGER PRIMARY KEY,author_name TEXT,created_at TEXT)');
$updates=new NativeCompanyTicketUpdates($wrapped,'tbl');$current=$reader->get(2,17,$id);$updates->reply(2,17,$id,'<p>Staff original</p>',null,$current['revision_token'],hash('sha256','http-reply-create'));
$reply=$reader->replies(2,17,$id)[0];$current=$reader->get(2,17,$id);
$ci->input->data=['type'=>'reply','id'=>$reply['id'],'main_ticket'=>$id,'data'=>'<p>Actual reply edit</p>','revision'=>$current['revision_token'],'reply_revision'=>$reply['revision_token'],'request_nonce'=>hash('sha256','http-reply-edit'),'_native_company_csrf'=>'synthetic-csrf'];
$page->edit_message();kc(json_decode($ci->output->body,true)['success']&&$reader->replies(2,17,$id)[0]['message']==='<p>Actual reply edit</p>','actual edit_message reply branch uses same scoped transactional source');
echo "Original Tickets edit_message handles both body and reply with distinct source CAS; real controller/PDO passed. Detail form/browser still pending.\n";
