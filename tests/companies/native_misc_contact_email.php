<?php
/** Actual Misc action and contact service; explicit framework admission marker, no legacy model. */
require __DIR__.'/native_contact_controller.php';
// The bootstrap admission is independently tested centrally. This marker only
// selects the admitted branch; no Auxiliary service behavior is substituted.
if(!class_exists('NativeCompanyAuxiliaryUi')){final class NativeCompanyAuxiliaryUi{}}
require APPPATH.'controllers/admin/Misc.php';
$misc=(new ReflectionClass(Misc::class))->newInstanceWithoutConstructor();
$marker=(new ReflectionClass(NativeCompanyAuxiliaryUi::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Misc::class,'companyAuxiliary'))->setValue($misc,$marker);
$misc->db=$db;$misc->input=$controller->input;$misc->output=$controller->output;
$misc->input->verb='POST';$misc->input->body=['client_id'=>(string)$client,'userid'=>(string)$id,'email'=>'unused@example.invalid','_native_company_csrf'=>'synthetic-native-csrf'];
$start=$GLOBALS['essentialChecks'];
$misc->contact_email_exists();essentialCheck(json_decode($misc->output->body,true)===true&&in_array('Cache-Control: no-store',$misc->output->headers,true),'original Misc URL returns owner-bound no-store native validation result');
CompanyRequestScope::$issuer=$b;essentialThrows(fn()=>$misc->contact_email_exists(),'company_client_access_denied','original URL cannot probe another issuer client');CompanyRequestScope::$issuer=$a;
unset($misc->input->body['_native_company_csrf']);essentialThrows(fn()=>$misc->contact_email_exists(),'native_csrf_denied','original URL rejects missing native CSRF');
$misc->input->body['_native_company_csrf']='synthetic-native-csrf';$misc->input->verb='GET';essentialThrows(fn()=>$misc->contact_email_exists(),'native_csrf_denied','availability probe requires POST');
$misc->input->verb='POST';$misc->input->body['email']=['invalid'];essentialThrows(fn()=>$misc->contact_email_exists(),'company_contact_invalid_input','non-scalar email denied without global lookup');
echo 'Native Misc contact email: '.($GLOBALS['essentialChecks']-$start).' actual action checks passed.'.PHP_EOL;
