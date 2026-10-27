<?php
require __DIR__.'/native_clients_settings.php';
require_once APPPATH.'services/NativeCompanySettingsController.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
#[AllowDynamicProperties] class AdminController{public function __construct(){}}
class NativeClientSettingsHttpError extends RuntimeException{}
function show_error($message,$status=500){throw new NativeClientSettingsHttpError('http_error',$status);}
function _l($key){return$key;}
function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}
function redirect($url,$method='location',$status=302){$GLOBALS['settingsRedirect']=[$url,$status];}
class CompanyRequestScope{
 public static int$issuer=41;public static bool$sealed=true;
 public static function nativeFor($ci){return self::$sealed;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
 public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->body['_native_company_csrf']??'')!=='fixture-csrf')throw new DomainException('native_csrf_invalid');}
 public static function invalidateCaches($ci){}
}
require APPPATH.'controllers/admin/Settings.php';
$controller=new Settings();$controller->db=$db;$controller->input=new class{public string$verb='GET';public array$query=['group'=>'clients'],$body=[];function method($upper=true){return$this->verb;}function get($name){return$this->query[$name]??null;}function post($name=null,$filter=false){return$name===null?$this->body:($this->body[$name]??null);}};
$controller->security=new class{function get_csrf_token_name(){return'csrf_token';}};
$controller->load=new class{public array$views=[];function helper($names){}function model($name){throw new RuntimeException('Global settings model must not load');}function view($name,$data){$this->views[]=[$name,$data];}};
$start=$GLOBALS['essentialChecks'];
CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());$view=end($controller->load->views);
essentialCheck($view[0]==='admin/settings/all'&&$view[1]['group']['view']==='admin/settings/includes/clients','same native Clients settings route and original partial');
essentialCheck(array_column($view[1]['native_settings_clients']['estimate_request_forms'],'id')===[2],'controller supplies actual selected-company form catalog');
$controller->input->verb='POST';$controller->input->body=['settings'=>nativeSettingsPost($view[1]['native_settings_clients']['values']),'native_settings_revision'=>$view[1]['native_settings_revision']];
try{CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());throw new RuntimeException('Missing CSRF accepted');}catch(NativeClientSettingsHttpError$e){essentialCheck($e->getCode()===403,'missing CSRF rejected before settings write');}
$controller->input->body['_native_company_csrf']='fixture-csrf';$controller->input->body['settings']['allow_registration']='0';CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());
essentialCheck($GLOBALS['settingsRedirect']===['https://fixture.invalid/admin/settings?group=clients',303]&&(new NativePortalPreferences($db,$p))->publicValues(41)['allow_registration']==='0','native POST commits exact company then303 same route');
$committed=(new NativeCompanyClientsSettings($db,$p))->page(41,7);
$controller->input->body['settings']['allow_registration']='1';
try{CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());throw new RuntimeException('Stale form accepted');}catch(NativeClientSettingsHttpError$e){essentialCheck($e->getCode()===409,'native stale client settings returns conflict');}
essentialCheck((new NativeCompanyClientsSettings($db,$p))->page(41,7)===$committed,'stale HTTP form has no effects');
$controller->input->body['native_settings_revision']=$committed['revision'];$controller->input->body['settings']=nativeSettingsPost($committed['values']);$controller->input->body['settings']['allow_registration']='invalid';
try{CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());throw new RuntimeException('Invalid settings accepted');}catch(NativeClientSettingsHttpError$e){essentialCheck($e->getCode()===422,'invalid native client settings returns unprocessable entity');}
essentialCheck((new NativeCompanyClientsSettings($db,$p))->page(41,7)===$committed,'invalid HTTP form leaves company state intact');
$controller->input->body['settings']['allow_registration']='1';$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{CompanySettingsRuntime::run($db,$p,41,7,fn()=>$controller->index());throw new RuntimeException('Failed audit accepted');}catch(NativeClientSettingsHttpError$e){essentialCheck($e->getCode()===503,'unconfirmed transaction returns service unavailable');}finally{$db->failOn=null;}
essentialCheck((new NativeCompanyClientsSettings($db,$p))->page(41,7)===$committed,'failed audit rolls back actual HTTP settings changes');
$statusMethod=new ReflectionMethod(NativeCompanySettingsController::class,'domainStatus');$statusMethod->setAccessible(true);
foreach(['native_settings_stale','native_clients_settings_stale','native_google_settings_stale','native_calendar_preferences_stale','native_mail_settings_stale','native_ticket_preferences_stale','native_invoice_sequence_stale','company_task_preferences_stale','company_task_operation_preferences_stale','company_task_timer_preferences_stale','company_task_timer_stop_preferences_stale','company_ticket_preferences_stale'] as $reason)essentialCheck($statusMethod->invoke(null,$reason)===409,'known stale form status '.$reason);
foreach(['native_clients_settings_invalid','native_google_settings_invalid','native_calendar_preferences_invalid','native_mail_settings_invalid','native_misc_settings_invalid','native_task_settings_invalid','native_ticket_preferences_invalid','native_invoice_sequence_invalid'] as $reason)essentialCheck($statusMethod->invoke(null,$reason)===422,'known invalid form status '.$reason);
foreach(['native_clients_settings_denied','native_mail_settings_denied','native_csrf_invalid','unexpected_error'] as $reason)essentialCheck($statusMethod->invoke(null,$reason)===403,'denial and unknown form errors remain closed '.$reason);
$controller->input->verb='GET';$controller->input->body=[];CompanyRequestScope::$issuer=82;CompanySettingsRuntime::run($db,$p,82,7,fn()=>$controller->index());$other=end($controller->load->views);
essentialCheck(array_column($other[1]['native_settings_clients']['estimate_request_forms'],'id')===[3],'same controller changes only scoped data for B');
echo 'Native Clients settings actual controller: '.($GLOBALS['essentialChecks']-$start).' PHP/SQLite route/DTO/CSRF/303 checks passed.'.PHP_EOL;
