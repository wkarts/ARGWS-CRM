<?php
require __DIR__.'/staff_form_transaction.php';require_once APPPATH.'services/NativeCompanyStaffFormRead.php';
$reader=new NativeCompanyStaffFormRead($db,$p);$page=$reader->page(41,7,14);
essentialCheck($page['member']->staffid===14&&$page['native_staff_selected_role']===NativeCompanyResourceId::encode('company_staff_role',$role),'form projects real identity and scoped role separately');
essentialCheck($page['staff_departments']===[['departmentid'=>$wire]]&&$page['departments'][0]['departmentid']===$wire,'original department fields preserve explicit owned identity');
essentialCheck($page['native_staff_revision']===$service->get(41,7,14)['revision']&&$page['native_staff_form_revision']===hash('sha256',json_encode($form->state(14),JSON_THROW_ON_ERROR)),'form revisions match actual atomic writer snapshots');
essentialCheck(!isset($page['member']->password)&&!isset($page['departments'][0]['password_cipher']),'form data excludes authentication and IMAP secrets');
settingThrows(fn()=>$reader->page(82,7,14),'issuer_staff_denied','foreign member denied before form hydration');
$create=$reader->page(41,7);essentialCheck(!isset($create['member'])&&$create['native_staff_form_revision']===null&&$create['native_staff_selected_role']==='','creation has no invented existing identity or role assignment');
echo "Native original staff form DTO: six SQLite metadata/identity/secret/CAS checks passed.\n";
