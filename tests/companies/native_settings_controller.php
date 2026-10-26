<?php
require __DIR__.'/native_settings_forms.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
#[AllowDynamicProperties] class AdminController{public function __construct(){}}
class NativeSettingsHttpError extends RuntimeException{}
function show_error($message,$status=500){throw new NativeSettingsHttpError('http_error',$status);}
function _l($key){return$key;}
function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}
function redirect($url,$method='location',$status=302){$GLOBALS['settingsRedirect']=[$url,$status];}
class CompanyRequestScope{
    public static int $issuer;public static bool $sealed=true;
    public static function nativeFor($ci){return self::$sealed;}
    public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
    public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->body['_native_company_csrf']??'')!=='fixture-csrf')throw new DomainException('native_csrf_invalid');}
    public static function invalidateCaches($ci){}
}
require APPPATH.'controllers/admin/Settings.php';
CompanyRequestScope::$issuer=$a;$controller=new Settings();$controller->db=$db;
$controller->input=new class{public string $verb='GET';public array $query=['group'=>'localization'],$body=[];function method($upper=true){return$this->verb;}function get($name){return$this->query[$name]??null;}function post($name=null,$filter=false){return$name===null?$this->body:($this->body[$name]??null);}};
$controller->security=new class{function get_csrf_token_name(){return'csrf_token';}};
$controller->load=new class{public array $views=[];function helper($names){}function model($name){throw new RuntimeException('Global settings model must not load');}function view($name,$data){$this->views[]=[$name,$data];}};
CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());
$view=end($controller->load->views);essentialCheck($view[0]==='admin/settings/all'&&$view[1]['group']['view']==='admin/settings/includes/localization','same existing native form and shell selected');
$visible=[];foreach($view[1]['sections']as$section)foreach($section['children']as$child)$visible[]=$child['id'];sort($visible);$supported=NativeCompanySettingsForms::availableGroups();sort($supported);essentialCheck($visible===$supported,'native Settings navigation exposes exactly groups with owned native form adapters');
essentialCheck(!in_array('proposals',$visible,true)&&!in_array('payment_gateways',$visible,true),'unadapted native Settings links are not rendered as dead forms');
$controller->input->verb='POST';$controller->input->body=['settings'=>$locale,'native_settings_revision'=>$view[1]['native_settings_revision']];
try{CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());throw new RuntimeException('Missing CSRF accepted');}catch(NativeSettingsHttpError$e){essentialCheck($e->getCode()===403,'native controller rejects missing CSRF');}
$controller->input->body['_native_company_csrf']='fixture-csrf';$controller->input->body['settings']['time_format']='12';
CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());
essentialCheck($GLOBALS['settingsRedirect']===['https://fixture.invalid/admin/settings?group=localization',303]&&$store->get($a,'time_format')==='12','original native POST commits then303 same route');
try{CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());throw new RuntimeException('Stale accepted');}catch(NativeSettingsHttpError$e){essentialCheck($e->getCode()===409,'stale browser form rejected');}
$controller->input->verb='GET';$controller->input->query=['group'=>'company'];
CompanyRequestScope::$issuer=$a;CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());$companyA=end($controller->load->views)[1];
CompanyRequestScope::$issuer=$b;CompanySettingsRuntime::run($db,$p,$b,7,fn()=>$controller->index());$companyB=end($controller->load->views)[1];
$pageA=$forms->page($a,7,'company');$pageB=$forms->page($b,7,'company');
essentialCheck($companyA['native_settings_company_values']===$pageA['values']&&$companyB['native_settings_company_values']===$pageB['values'],'same native company form receives only the selected company identity DTO');
essentialCheck($companyA['native_settings_company_field_definitions']===$pageA['custom_field_definitions']&&$companyB['native_settings_company_field_definitions']===$pageB['custom_field_definitions']&&$companyB['native_settings_company_field_definitions']===[],'company custom field definitions stay with their owner');
CompanyRequestScope::$issuer=$a;
$controller->input->verb='GET';$controller->input->query=['group'=>'payment_gateways'];try{CompanySettingsRuntime::run($db,$p,$a,7,fn()=>$controller->index());throw new RuntimeException('Unadapted group rendered');}catch(NativeSettingsHttpError$e){essentialCheck($e->getCode()===403,'unadapted gateway group is denied before rendering a global provider form');}
CompanyReques