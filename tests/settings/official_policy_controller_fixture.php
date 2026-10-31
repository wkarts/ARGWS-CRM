<?php
/** Current Settings controller + original policy partial. Framework transport boundaries are explicit. */
require __DIR__.'/native_settings.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
require_once dirname(__DIR__).'/support/StrictViewFixture.php';
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id IN (41,82)");
class AdminController{public function __construct(){}public function __get($name){return$GLOBALS['CI']->$name;}}
class CompanyRequestScope{
 public static function nativeFor($ci){return true;}
 public static function context(){return new class{function issuerId(){return CompanySettingsRuntime::issuerId();}function staffId(){return CompanySettingsRuntime::staffId();}};}
 public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='fixture-proof')throw new DomainException('native_settings_denied');}
 public static function invalidateCaches($ci){}
}
function _l($key){return$key;}function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;}
function redirect($url,$method='location',$status=302){$GLOBALS['officialSettingsRedirect']=[$url,$status];}
function set_alert($type,$message){}
$GLOBALS['CI']->input=new class{public string $group='';public array $payload=[];function get($key,$filter=false){return$key==='group'?$this->group:null;}function method($upper=true){return$this->payload===[]?'GET':'POST';}function post($key=null,$filter=false){return$key===null?$this->payload:($this->payload[$key]??null);}};
$GLOBALS['CI']->security=new class{function get_csrf_token_name(){return'framework_csrf';}};
$GLOBALS['CI']->output=new class{public int $status=200;function set_header($h){return$this;}function set_status_header($s){$this->status=$s;return$this;}};
$GLOBALS['CI']->load=new class{public array $views=[];function helper($names){}function view($view,$data){$this->views[]=[$view,$data];}function model($name){throw new RuntimeException('Global model fallback');}};
require APPPATH.'controllers/admin/Settings.php';
function officialSettingsRequest($controller):void{
 $ci=$GLOBALS['CI'];$ci->output->status=200;$GLOBALS['officialSettingsSaved']=false;$GLOBALS['officialSettingsRedirect']=null;$original=$ci->input->payload;
 if($original!==[])$ci->input->payload['_native_company_csrf']='fixture-proof';
 $controller->index();
 if($GLOBALS['officialSettingsRedirect']!==null){essentialCheck($GLOBALS['officialSettingsRedirect']===[admin_url('settings?group='.$ci->input->group),303],'policy POST uses original route and explicit303');$GLOBALS['officialSettingsSaved']=true;$ci->input->payload=[];$controller->index();}
 $ci->input->payload=$original;
}
$viewHarness=new class{function render(array $data):string{return strictFixtureRender(function()use($data){extract($data,EXTR_SKIP);include APPPATH.'views/admin/settings/includes/policy.php';});}};
