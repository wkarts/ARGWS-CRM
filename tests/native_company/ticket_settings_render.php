<?php
declare(strict_types=1);
require __DIR__.'/ticket_add_page.php';require_once APPPATH.'services/NativeCompanyTicketSettingsPresentation.php';
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$projection=new NativeCompanyTicketSettingsPresentation($wrapped,'tbl');$data=$projection->read(2,17,$id);
$render=function($data){extract($data);ob_start();include APPPATH.'views/admin/tickets/partials/ticket-settings.php';return ob_get_clean();};$html=$render($data);
kc(str_contains($html,'id="settings"')&&str_contains($html,'name="subject"')&&str_contains($html,'name="custom_fields[tickets]['.$customId.']"'),'original settings partial and actual custom-field renderer');
kc(str_contains($html,'name="tags_revision"')&&str_contains($html,$data['native_ticket_presentation']['tags_revision'])&&str_contains($html,'name="custom_fields_revision"'),'actual CAS sources are form fields');
kc(!str_contains($html,'company_tickets')&&str_contains($html,'name="department"'),'same native form contract, no parallel route');
$db->query('UPDATE tblcompany_tickets SET assignee_id=18 WHERE issuer_id=2 AND id=?',[NativeCompanyResourceId::decode('ticket',$id)['source_id']]);$db->query('UPDATE tblstaff SET active=0 WHERE staffid=18');$historical=$projection->read(2,17,$id);$selected=array_values(array_filter($historical['staff'],fn($s)=>(int)$s['staffid']===18));kc(count($selected)===1&&(int)$selected[0]['active']===0&&str_contains($render($historical),'value="18"'),'existing inactive assignment retained explicitly rather than silently blanked');$db->query('UPDATE tblstaff SET active=1 WHERE staffid=18');
$deny(fn()=>$projection->read(3,17,$id));
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=18');$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$projection->read(2,17,$id));
echo "Original ticket-settings partial + real projection/custom fields/SQLite passed with global options/hooks throwing; A/B and revoked ACL denied. Merge field explicitly unavailable pending its implementation, not final parity. Full detail page/browser remain pending.\n";
