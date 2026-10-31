<?php
require __DIR__.'/native_sales_settings.php';
// The real Settings controller and runtime lease; only framework transport is doubled.
#[AllowDynamicProperties] class AdminController { public function __construct(){} }
class PrimarySalesSettingsHttpError extends RuntimeException {}
function show_error($message,$status=500){throw new PrimarySalesSettingsHttpError('http_error',$status);}
function _l($key){return $key;}
function admin_url($path=''){return 'https://fixture.invalid/admin/'.$path;}
function redirect($url,$method='location',$status=302){$GLOBALS['primarySettingsRedirect']=[$url,$status];}
function set_alert($type,$message){}
class CompanyRequestScope {
    public static int $issuer;
    public static function nativeFor($ci){return true;}
    public static function context(){return new class {function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
    public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->body['_native_company_csrf']??'')!=='fixture-csrf')throw new DomainException('native_csrf_invalid');}
    public static function invalidateCaches($ci){}
}
require_once APPPATH.'controllers/admin/Settings.php';
$controller=new Settings();$controller->db=$db;CompanyRequestScope::$issuer=$principal;
$controller->input=new class {public string $verb='GET';public array $body=[];function method($upper=true){return $this->verb;}function get($key,$filter=false){return $key==='group'?'estimates':null;}function post($key=null,$filter=false){return $key===null?$this->body:($this->body[$key]??null);}};
$controller->load=new class {public array $views=[];function helper($names){}function model($name){throw new RuntimeException('global_model_forbidden');}function view($name,$data){$this->views[]=[$name,$data];}};
$controller->security=new class {function get_csrf_token_name(){return 'csrf_token';}};
CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->index());
[$viewName,$viewData]=end($controller->load->views);
essentialCheck($viewName==='admin/settings/all'&&$viewData['group']['view']==='admin/settings/includes/estimates','principal HTTP controller selects the same original estimate settings view');
essentialCheck($viewData['native_settings_estimate_values']['next_estimate_number']==='90','principal HTTP form retains its original counter');
$controller->input->verb='POST';$controller->input->body=['settings'=>$viewData['native_settings_estimate_values'],'native_settings_revision'=>$viewData['native_settings_revision']];
try{CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->index());throw new RuntimeException('missing_csrf_accepted');}catch(PrimarySalesSettingsHttpError $e){essentialCheck($e->getCode()===403,'principal original settings POST still requires CSRF');}
$controller->input->body['_native_company_csrf']='fixture-csrf';$controller->input->body['settings']['estimate_due_after']='4';
CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->index());
essentialCheck($GLOBALS['primarySettingsRedirect']===[admin_url('settings?group=estimates'),303],'principal POST redirects back to original Settings route');
essentialCheck((new NativeCompanySalesDates($db,$p))->dueDate($principal,7,'estimates','2026-01-31')==='2026-02-04','principal date consumer reads the setting saved by the real controller');
try{CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->index());throw new RuntimeException('stale_post_accepted');}catch(PrimarySalesSettingsHttpError $e){essentialCheck($e->getCode()===409,'principal stale POST returns conflict');}
CompanyRequestScope::$issuer=$a;
try{CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->index());throw new RuntimeException('mismatched_context_accepted');}catch(PrimarySalesSettingsHttpError $e){essentialCheck($e->getCode()===403,'principal settings cannot execute under a secondary request context');}
echo "Principal Settings actual controller: original form, original counter, due-date consumer, CSRF, stale and mismatched-context guards passed.\n";
