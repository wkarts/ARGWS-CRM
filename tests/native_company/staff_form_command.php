<?php
require __DIR__.'/staff_native_creation.php';require_once APPPATH.'services/NativeCompanyStaffFormCommand.php';
$reader=new NativeCompanyStaffFormRead($db,$p);$dto=$reader->page(41,7,$id);$command=new NativeCompanyStaffFormCommand($db,$p);
$input=['native_staff_revision'=>$dto['native_staff_revision'],'native_staff_form_revision'=>$dto['native_staff_form_revision'],'native_staff_roles_revision'=>$dto['native_staff_roles_revision'],'native_staff_rate_revision'=>(string)$dto['native_staff_rate_revision'],'request_key'=>hash('sha256','staff-original-form-command'),'firstname'=>'Original form','departments'=>[$wire],'role'=>(string)$dto['native_staff_selected_role'],'permissions'=>['tasks'=>['view']],'custom_fields'=>['staff'=>[$field=>'Original field']],'hourly_rate'=>'18.25'];
essentialCheck($command->save(41,7,$id,$input,'https://example.test/',str_repeat('x',32),fn($x)=>$x)===$id,'original form fields update real identity plus owned metadata');
essentialCheck($reader->page(41,7,$id)['member']->hourly_rate==='18.25'&&$custom->values(41,7,'staff',$id)===[$field=>'Original field'],'form wage and custom fields commit together');
essentialCheck($command->save(41,7,$id,$input,'https://example.test/',str_repeat('x',32),fn($x)=>$x)===$id,'whole native form command replays without second mutation');
settingThrows(fn()=>$command->save(82,7,$id,$input,'https://example.test/',str_repeat('x',32),fn($x)=>$x),'issuer_staff_denied','foreign identity never selected by form transport');
settingThrows(fn()=>$command->save(41,7,$id,$input+['administrator'=>'1'],'https://example.test/',str_repeat('x',32),fn($x)=>$x),'issuer_staff_profile_field_denied','administrator input is not a tenant identity capability');
settingThrows(fn()=>$command->save(41,7,$id,array_replace($input,['hourly_rate'=>['1']]),'https://example.test/',str_repeat('x',32),fn($x)=>$x),'native_staff_rate_invalid','array rate fails before write');
$db->query('UPDATE '.$p.'staff SET is_not_staff=1 WHERE staffid=?',[$id]);
$next=$reader->page(41,7,$id);$clear=array_replace($input,['native_staff_revision'=>$next['native_staff_revision'],'native_staff_form_revision'=>$next['native_staff_form_revision'],'native_staff_roles_revision'=>$next['native_staff_roles_revision'],'native_staff_rate_revision'=>(string)$next['native_staff_rate_revision'],'request_key'=>hash('sha256','staff-checkbox-unchecked')]);
$command->save(41,7,$id,$clear,'https://example.test/',str_repeat('x',32),fn($x)=>$x);
essentialCheck((int)$db->query('SELECT is_not_staff FROM '.$p.'staff WHERE staffid=?',[$id])->row_array()['is_not_staff']===0,'unchecked original checkbox clears only editable identity');
echo "Original staff form command: seven actual SQLite checks passed.\n";
