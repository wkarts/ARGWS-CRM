<?php
declare(strict_types=1);
require __DIR__.'/ticket_add_page.php';
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$ci->router=new class{function fetch_method(){return 'change_status_ajax';}};$page=new Tickets();
$reader=new NativeCompanyTicketRead($wrapped,'tbl');$before=$reader->get(2,17,$id);$status=$catalog->list(2,17,'status')[1]['native_id'];
$ci->input->verb='POST';$ci->input->data=['revision'=>$before['revision_token'],'request_nonce'=>str_repeat('9',64),'_native_company_csrf'=>'synthetic-csrf'];
$page->change_status_ajax($id,$status);$result=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);kc($result['success']&&$result['ticketid']===$id&&$result['revision']===$reader->get(2,17,$id)['revision_token'],'actual Tickets status controller writes owned source');
$page->change_status_ajax($id,$status);kc(json_decode($ci->output->body,true)['replayed'],'same endpoint durable replay');
$ci->input->verb='GET';$deny(fn()=>$page->change_status_ajax($id,$status));$ci->input->verb='POST';$ci->input->data['_native_company_csrf']='bad';$deny(fn()=>$page->change_status_ajax($id,$status));$ci->input->data['_native_company_csrf']='synthetic-csrf';$ci->input->data['issuer_id']=3;$deny(fn()=>$page->change_status_ajax($id,$status));unset($ci->input->data['issuer_id']);
CompanyRequestScope::$valid=false;$deny(fn()=>new Tickets());CompanyRequestScope::$valid=true;
echo "Actual Tickets status endpoint + real SQLite writer passed: POST/CSRF/context boundaries, typed input, replay and no global models. Bootstrap/browser not executed.\n";
