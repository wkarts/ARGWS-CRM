<?php
/** Exercise the original contact controller against real issuer-owned storage. */
require __DIR__.'/native_contact_controller.php';
$input=['firstname'=>'Deletable','lastname'=>'Fixture','email'=>'deletable-controller@example.invalid','is_primary'=>0,'permissions'=>[],'password'=>'Synthetic-delete-controller-123'];
$subject=$service->save($a,7,$client,$input,fn($value)=>password_hash($value,PASSWORD_DEFAULT));
$revision=$service->get($a,7,$client,$subject)['revision'];
$controller->input->verb='POST';$controller->input->body=['revision'=>$revision];$controller->output->status=200;
$controller->delete_contact($client,$subject);
essentialCheck($controller->output->status>=400&&$service->get($a,7,$client,$subject)['contact']['id']===$subject,'contact delete rejects absent native CSRF without mutation');
$controller->input->body['_native_company_csrf']='synthetic-native-csrf';CompanyRequestScope::$issuer=$b;$controller->output->status=200;
$controller->delete_contact($client,$subject);
essentialCheck($controller->output->status===403&&$service->get($a,7,$client,$subject)['contact']['id']===$subject,'foreign issuer cannot delete another company contact');
CompanyRequestScope::$issuer=$a;$controller->input->body['revision']=str_repeat('0',64);$controller->output->status=200;
$controller->delete_contact($client,$subject);
essentialCheck($controller->output->status===409&&$service->get($a,7,$client,$subject)['contact']['id']===$subject,'contact delete rejects stale revision');
$controller->input->body['revision']=$revision;$controller->output->status=200;
$controller->delete_contact($client,$subject);
essentialCheck($controller->output->status===200&&(json_decode($controller->output->body,true)['success']??false),'owner contact deletion returns success only after scoped deletion');
essentialThrows(fn()=>$service->get($a,7,$client,$subject),'company_client_access_denied','deleted owner contact cannot be reopened');
echo "Native contact deletion controller isolation and CSRF passed.\n";
