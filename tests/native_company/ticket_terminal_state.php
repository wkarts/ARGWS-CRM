<?php
/** Terminal-state rule applies to the actual writer, projection and original controller. */
require __DIR__.'/ticket_detail_page.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$updates=new NativeCompanyTicketUpdates($wrapped,'tbl');$reader=new NativeCompanyTicketRead($wrapped,'tbl');
$statuses=(new NativeCompanyTicketCatalog($wrapped,'tbl'))->list(2,17,'status');$byCode=array_column($statuses,'native_id','code');
$before=$reader->get(2,17,$id);$count=(int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_replies')->row_array()['n'];
$closed=$updates->changeStatus(2,17,$id,$byCode['closed'],$before['revision_token'],hash('sha256','close-ticket'));
$thread=(new NativeCompanyTicketThreadPresentation($wrapped,'tbl'))->read(2,17,$id);kc(!$thread['native_ticket_presentation']['can_reply'],'Closed ticket removes the reply control but retains its thread');
$ci->input->verb='POST';$ci->input->data=['_native_company_csrf'=>'synthetic-csrf','ticketid'=>$id,'revision'=>$closed['revision'],'request_nonce'=>hash('sha256','closed-reply'),'message'=>'Must not silently reopen','status'=>$byCode['open']];
try{$page->ticket($id);throw new LogicException('Closed reply accepted');}catch(DomainException $e){kc($e->getMessage()==='http_422','Original reply returns a specific validation rejection for a terminal ticket');}
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_replies')->row_array()['n']===$count&&$reader->get(2,17,$id)['revision_token']===$closed['revision'],'Denied terminal reply leaves body, status, revision and reply count unchanged');
$opened=$updates->changeStatus(2,17,$id,$byCode['open'],$closed['revision'],hash('sha256','reopen-ticket'));
$ci->input->data['revision']=$opened['revision'];$ci->input->data['request_nonce']=hash('sha256','opened-reply');$page->ticket($id);
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_ticket_replies')->row_array()['n']===$count+1,'Explicit original state transition enables the next authorized reply');
$ci->input->data['request_nonce']=hash('sha256','stale-after-reply');try{$page->ticket($id);throw new LogicException('Stale reply accepted');}catch(DomainException $e){kc($e->getMessage()==='http_409','Original obsolete reply revision is an HTTP 409, never generic success');}
echo "Ticket terminal states: original close/reopen/reply, HTTP 422/409 and unchanged denied state passed.\n";
