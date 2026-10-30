<?php
require __DIR__.'/native_contact_controller.php';
require APPPATH.'services/NativeCompanyCustomerRegistration.php';
function set_alert($type,$message){$GLOBALS['registration_alert']=[$type,$message];}
function redirect($url,$method='auto',$status=null){$GLOBALS['registration_redirect']=[$url,$status];}
$db->query("UPDATE {$p}clients SET registration_confirmed=0,active=0 WHERE userid=?",[$client]);$db->query("UPDATE {$p}contacts SET is_primary=CASE WHEN id=? THEN 1 ELSE 0 END WHERE userid=?",[$id,$client]);
$registration=new NativeCompanyCustomerRegistration($db,$p,$portalSecret);$revision=$registration->revision($a,7,$client);$start=$GLOBALS['essentialChecks'];$controller->input->verb='GET';
try{$controller->confirm_registration((string)$client);throw new RuntimeException('GET accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===405,'native registration confirmation rejects GET');}
$controller->input->verb='POST';$controller->input->body=['revision'=>$revision];try{$controller->confirm_registration((string)$client);throw new RuntimeException('missing CSRF accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'native registration confirmation requires mutation token');}
$controller->input->body['_native_company_csrf']='synthetic-native-csrf';$controller->confirm_registration((string)$client);essentialCheck($GLOBALS['registration_redirect']===['https://fixture.test/admin/clients/client/'.$client,303],'native confirmation returns to original customer route');
essentialCheck((int)$db->query("SELECT registration_confirmed FROM {$p}clients WHERE userid=?",[$client])->row()->registration_confirmed===1,'actual controller commits exact issuer confirmation');
try{$controller->confirm_registration((string)$client);throw new RuntimeException('replay accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===409,'actual repeated confirmation cannot duplicate notification');}
echo 'Native registration actual controller: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
