<?php
require __DIR__.'/staff_member_page.php';
$writer=new CompanyStaff($db,$p);$before=$writer->get(41,7,$id);$writer->setNativeStatus(41,7,$id,$before['revision'],0,hash('sha256','inactive-form-test'));
$dto=(new NativeCompanyStaffFormRead($db,$p))->page(41,7,$id);essentialCheck((int)$dto['member']->active===0,'administrative form reads inactive linked identity');
$ref=NativeCompanyResourceReferences::resolve($db,$p,41,7,'staff',$id);essentialCheck((int)$ref['source_id']===$id,'custom field resource authority accepts inactive administrative target only');
$input=['native_staff_revision'=>$dto['native_staff_revision'],'native_staff_form_revision'=>$dto['native_staff_form_revision'],'native_staff_roles_revision'=>$dto['native_staff_roles_revision'],'native_staff_rate_revision'=>(string)$dto['native_staff_rate_revision'],'request_key'=>hash('sha256','inactive-edit-test'),'firstname'=>'Inactive edited','departments'=>[$wire],'role'=>(string)$dto['native_staff_selected_role'],'permissions'=>['tasks'=>['view']],'custom_fields'=>['staff'=>[$field=>'Inactive custom field']],'hourly_rate'=>'24.00'];
(new NativeCompanyStaffFormCommand($db,$p))->save(41,7,$id,$input,'https://example.test/',str_repeat('x',32),fn($x)=>$x);$after=(new NativeCompanyStaffFormRead($db,$p))->page(41,7,$id);essentialCheck($after['member']->firstname==='Inactive edited'&&(int)$after['member']->active===0&&$after['member']->hourly_rate==='24.00','identity fields, grants, role and rate update without reactivating login');
settingThrows(fn()=>(new NativeCompanyStaffRates($db,$p))->forOwnTimer(41,$id),'native_staff_rate_denied','inactive target cannot use own timer authority');
$history=(new NativeCompanyStaffTimesheets($db,$p,'UTC'))->read(41,7,$id);essentialCheck($history['timesheets']===[],'administrative historical reader accepts inactive linked target');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=?',[$id]);settingThrows(fn()=>(new NativeCompanyStaffFormRead($db,$p))->page(41,7,$id),'issuer_staff_denied','unlinked identity remains denied before form hydration');
echo "Inactive staff administration: six actual SQLite checks.\n";
