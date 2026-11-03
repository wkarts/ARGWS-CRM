<?php
require __DIR__.'/native_contact_mail.php';
require APPPATH.'services/NativeCompanyContactForm.php';
$start=$GLOBALS['essentialChecks'];
foreach(['title'=>'varchar(100)','direction'=>'varchar(3)','last_login'=>'datetime','profile_image'=>'varchar(191)']as$field=>$type)if(!$db->field_exists($field,$p.'contacts'))$db->query('ALTER TABLE '.$p.'contacts ADD '.$field.' '.$type);
foreach(['registration_confirmed'=>'INT NOT NULL DEFAULT 1','country'=>'INT NOT NULL DEFAULT 0']as$field=>$type)if(!$db->field_exists($field,$p.'clients'))$db->query('ALTER TABLE '.$p.'clients ADD '.$field.' '.$type);
if(!$db->table_exists($p.'contact_permissions'))$db->query("CREATE TABLE {$p}contact_permissions(id INTEGER PRIMARY KEY AUTOINCREMENT,userid INT NOT NULL,permission_id INT NOT NULL)");
unset($db->data_cache['table_names']);NativeCompanyCustomFieldSchema::ensure($db,$p);
$form=new NativeCompanyContactForm($db,$p,$a,7);$data=$form->data($client);essentialCheck($data['customer_id']===$client&&$data['native_contact_defaults']===[1,2,3,4,5,6],'actual native form data uses owner and proven defaults');
$input=['firstname'=>'Form','lastname'=>'Owner','email'=>'form@example.invalid','title'=>'Native form','phonenumber'=>'000000000','direction'=>'ltr','permissions'=>['1'],'invoice_emails'=>'invoice_emails','password'=>'Synthetic-native-form-123','is_primary'=>'on','donotsendwelcomeemail'=>'on','native_customer_id'=>(string)$client,'contactid'=>'','native_contact_revision'=>'','native_contact_fields_revision'=>$data['native_contact_fields_revision']];
$result=$form->save($client,null,$input,$portalSecret,$base,$decrypt,fn($v)=>password_hash($v,PASSWORD_DEFAULT));$id=$result['id'];essentialCheck($result['success']&&$result['has_primary_contact'],'native contact form persists all fields without parallel controller');
$existing=$form->data($client,$id);essentialCheck($existing['contact']->email===$input['email']&&$existing['native_contact_permissions']===[1],'native edit modal receives same real DTO');
$input['contactid']=(string)$id;$input['native_contact_revision']=$existing['native_contact_revision'];$input['native_contact_fields_revision']=$existing['native_contact_fields_revision'];$input['password']='';$input['title']='Updated title';$input['send_set_password_email']='on';
$result=$form->save($client,$id,$input,$portalSecret,$base,$decrypt,'password_hash');essentialCheck($result['success']&&$form->data($client,$id)['contact']->title==='Updated title','edit and owned password setup queue compose atomically');
essentialThrows(fn()=>$form->save($client,$id,$input,$portalSecret,$base,$decrypt,'password_hash'),'company_client_stale','native form rejects stale full contact revision');
$other=new NativeCompanyContactForm($db,$p,$b,7);essentialThrows(fn()=>$other->data($client,$id),'company_client_access_denied','B cannot open A native contact modal');
$existing=$form->data($client,$id);$input['native_contact_revision']=$existing['native_contact_revision'];$input['userid']='999';essentialThrows(fn()=>$form->save($client,$id,$input,$portalSecret,$base,$decrypt,'password_hash'),'company_contact_invalid_input','native contact form refuses ignored ownership input');
echo 'Native contact full form service: '.($GLOBALS['essentialChecks']-$start).' checks passed; PHP SQLite real, no browser.'.PHP_EOL;
