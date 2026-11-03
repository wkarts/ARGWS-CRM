<?php
declare(strict_types=1);
require __DIR__.'/ticket_edit_endpoint.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketDeletion.php';
foreach(array_merge(NativeCompanyTicketLifecycleSchema::definitions('tbl'),NativeCompanyTicketMergeSchema::definitions('tbl'))as$t=>$parts)ticketSqliteDdl($t,$parts,$db);
$db->query('UPDATE tblmigrations SET version=474');$wrapped=new TicketCreateDb($db,array_merge(NativeCompanyTicketReplySchema::definitions('tbl'),NativeCompanyTicketLifecycleSchema::definitions('tbl'),NativeCompanyTicketMergeSchema::definitions('tbl')));$reader=new NativeCompanyTicketRead($wrapped,'tbl');$ci->db=$wrapped;
$current=$reader->get(2,17,$id);$r=$reader->replies(2,17,$id)[0];$ci->router=new class{function fetch_method(){return 'delete_ticket_reply';}};$page=new Tickets();
$ci->input->data=['revision'=>$current['revision_token'],'reply_revision'=>$r['revision_token'],'request_nonce'=>hash('sha256','http-delete-reply'),'_native_company_csrf'=>'synthetic-csrf'];
$page->delete_ticket_reply($id,$r['id']);kc(json_decode($ci->output->body,true)['deleted']&&$reader->replies(2,17,$id)===[],'original reply deletion endpoint retains source but hides reply');$page->delete_ticket_reply($id,$r['id']);kc(json_decode($ci->output->body,true)['replayed'],'HTTP reply deletion replay');
$ci->router=new class{function fetch_method(){return 'delete';}};$current=$reader->get(2,17,$id);$ci->input->data=['revision'=>$current['revision_token'],'request_nonce'=>hash('sha256','http-delete-ticket'),'_native_company_csrf'=>'synthetic-csrf'];
$ci->input->verb='GET';$deny(fn()=>$page->delete($id));$ci->input->verb='POST';$ci->input->data['_native_company_csrf']='wrong';$deny(fn()=>$page->delete($id));$ci->input->data['_native_company_csrf']='synthetic-csrf';$page->delete($id);kc(json_decode($ci->output->body,true)['deleted'],'original parent delete endpoint uses retained tombstone');$deny(fn()=>$reader->get(2,17,$id));
echo "Actual Tickets delete/delete_ticket_reply endpoints passed with real SQLite: separate CAS, POST/CSRF, replay, retained content and hidden reads. Browser/admission pending.\n";
