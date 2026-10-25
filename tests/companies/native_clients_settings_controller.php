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
$controller->input->verb='GET';$controller->input->body=[];CompanyRequestScope::$issuer=82;CompanySettingsRuntime::run($db,$p,82,7,fn()=>$controller->index());$other=end($controller->load->views);
essentialCheck(array_column($other[1]['native_settings_clients']['estimate_request_forms'],'id')===[3],'same controller changes only scoped data for B');
echo 'Native Clients settings actual controller: '.($GLOBALS['essentialChecks']-$start).' PHP/SQLite route/DTO/CSRF/303 checks passed.'.PHP_EOL;
