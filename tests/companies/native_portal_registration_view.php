<?php
require __DIR__.'/native_portal_authentication.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
require_once APPPATH.'helpers/custom_fields_helper.php';
function set_checkbox($key,$value){return'';}
function set_select($key,$value,$default=false){return$default?' selected="selected"':'';}
function is_client_logged_in(){return false;}
function is_admin(){throw new LogicException('public form cannot read staff admin');}
$auth->input->get=['issuer_id'=>(string)$a];$auth->input->post=[];$auth->input->verb='GET';$auth->router->method='register';PortalRequestScope::prepare($auth);PortalRequestScope::resolve($auth);$auth->__construct();
$start=$GLOBALS['essentialChecks'];essentialThrows(fn()=>$auth->register(),'native_portal_registration_denied','disabled public registration cannot render native form');
$preferences=new NativePortalPreferences($db,$p);$preferences->save($a,7,$preferences->revision($a),['allow_registration'=>'1'],fn($v)=>'cipher:'.$v);
NativeCompanyCustomFieldSchema::ensure($db,$p);$db->query('CREATE TABLE '.$p.'countries(country_id INTEGER PRIMARY KEY,short_name TEXT)');$db->query("INSERT INTO {$p}countries VALUES(32,'Synthetic country')");
// Real helper obtains the actual request object, not a separate staff fixture.
$f->ci=$auth;essentialUse($f);$auth->__construct();ob_start();$auth->register();$html=ob_get_clean();
essentialCheck(str_contains($html,'id="register-form"')&&str_contains($html,'register-contact-info-heading')&&str_contains($html,'register-company-info-heading'),'same native registration DOM renders through original view');
essentialCheck(str_contains($html,'name="native_registration_key"')&&str_contains($html,'name="issuer_id"')&&str_contains($html,'name="_native_portal_csrf"'),'native form binds request replay, issuer and public CSRF');
essentialCheck(str_contains($html,'Synthetic country')&&!str_contains($html,'company_portal')&&!str_contains($html,'company_context'),'native public form uses shared public country catalog and no parallel route');


essentialCheck(str_contains(NativePortalPresentation::css(true),'bootstrap-arabic')&&!str_contains(NativePortalPresentation::css(false),'bootstrap-arabic'),'RTL uses original product asset only when requested');
essentialCheck(str_contains(NativePortalPresentation::scripts(true),'https://www.google.com/recaptcha/api.js')&&!str_contains(NativePortalPresentation::scripts(false),'recaptcha/api.js'),'captcha library only included for explicitly configured policy');

echo 'Native public registration original view: '.($GLOBALS['essentialChecks']-$start).' actual controller/view checks passed.'.PHP_EOL;
