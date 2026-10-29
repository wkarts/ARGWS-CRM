<?php
/** Actual SQLite adapter, namespaced original controller and original form/field helpers; framework/context/custom-field rendering are boundary doubles. */
namespace {
require __DIR__.'/native_project_form.php';
function html_escape($v){return is_array($v)?array_map('html_escape',$v):e($v);}
function config_item($k){return $k==='charset'?'UTF-8':null;}
function init_head(){} function init_tail(){}
function get_staff_user_id(){return 7;}
function html_purify($v){return strip_tags($v,'<p><b>');} // Framework sanitizer boundary, not a purifier test.
function render_custom_fields($type,$id){if($type!=='projects')throw new RuntimeException('Wrong fields scope');return '<div data-tested-custom-fields-boundary="'.e($id).'">Custom field renderer boundary</div>';}
function total_rows(){throw new RuntimeException('Global rows queried');}
function get_relation_data(){throw new RuntimeException('Global relation queried');}
function get_project_tabs_admin(){throw new RuntimeException('Global tabs queried');}
function is_email_template_active(){throw new RuntimeException('Global templates queried');}
function project_has_recurring_tasks(){throw new RuntimeException('Global recurring queried');}
function _d(){throw new RuntimeException('Global dates queried');}
function _dt(){throw new RuntimeException('Global dates queried');}
function get_tags_in(){throw new RuntimeException('Global tags queried');}
function admin_url($p=''){return'https://crm.invalid/sub/admin/'.$p;}
require APPPATH.'helpers/func_helper.php';$templateSource=file_get_contents(APPPATH.'helpers/template_helper.php');$begin=strpos($templateSource,'function clear_textarea_breaks(');$end=strpos($templateSource,'/**',$begin);eval(substr($templateSource,$begin,$end-$begin));require APPPATH.'../system/helpers/form_helper.php';require APPPATH.'helpers/fields_helper.php';
}
namespace NativeProjectFormHttpTest {
use \NativeCompanyProjectForm; use \NativeCompanySpecializedOperations; use \DomainException; use \RuntimeException; use \LogicException; use \Throwable; use \Closure;
function show_error($m,$s){throw new LogicException('HTTP '.$s);}
function set_alert($kind,$message){$GLOBALS['formAlert']=[$kind,$message];}
function html_purify($v){return strip_tags($v,'<p><b>');}
function redirect($url,$method,$status){$GLOBALS['formRedirect']=[$url,$method,$status];}
class CompanyRequestScope{
 public static int$issuer;public static int$actor=7;
 public static function nativeFor($ci){return true;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}function token(){return'synthetic-context';}};}
 public static function adminUrl($ci,$path){return admin_url($path);}
 public static function nativeCsrfToken($ci){return'synthetic-native-csrf';}
 public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='synthetic-native-csrf')throw new DomainException('native_mutation_denied');}
}
class AdminController{
 public $db,$input,$output,$load,$security,$uri,$config;
 function __construct(){
  $this->db=$GLOBALS['db'];$this->uri=new class{function uri_string(){return'admin/projects/project';}};
  $this->config=new class{function site_url($p){return'https://crm.invalid/sub/'.$p;}function item($k){return false;}};
  $this->input=new class{public string$verb='GET';public array$body=[],$query=[];function method($u=true){return$this->verb;}function post($k=null,$clean=false){return$k===null?$this->body:($this->body[$k]??null);}function get($k){return$this->query[$k]??null;}};
  $this->output=new class{public string$body='';public array$headers=[];function set_header($v){$this->headers[]=$v;return$this;}function set_content_type($v){$this->headers[]='Content-Type: '.$v;return$this;}function set_output($v){$this->body=$v;return$this;}};
  $this->security=new class{function get_csrf_token_name(){return'framework_csrf';}};
  $this->load=new class($this){function __construct(private$ci){}function model($name){throw new RuntimeException('Global model hydration');}function helper($name){if($name!=='date')throw new RuntimeException('Unexpected helper');}function view($path,$data,$return){if($path!=='admin/projects/project')throw new RuntimeException('Alternate view');return Closure::bind(function()use($path,$data){extract($data);ob_start();try{require APPPATH.'views/'.$path.'.php';return ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}},$this->ci)();}};
  $GLOBALS['CI']=$this;
 }
}
$projectsSource=file_get_contents(APPPATH.'controllers/admin/Projects.php');$projectsSource=str_replace('use NativeCompanySpecializedOperations;','use \\NativeCompanySpecializedOperations;',$projectsSource);eval('namespace NativeProjectFormHttpTest; use \\NativeCompanyProjectForm; use \\DomainException; use \\RuntimeException; '.substr($projectsSource,5));CompanyRequestScope::$issuer=$a;$controller=new Projects();$controller->project($saved);$html=$controller->output->body;
essentialCheck(str_contains($html,'<form')&&str_contains($html,'id="tab_project"')&&str_contains($html,'id="tab_settings"'),'Actual controller renders the same native form and tabs, not alternative markup');
essentialCheck(str_contains($html,'value="Original form updated project"')&&str_contains($html,'name="native_project_revision"')&&str_contains($html,'synthetic-context'),'Actual native form contains persisted source and sealed request fields');
essentialCheck(str_contains($html,'new_task')===false&&str_contains($html,'name="settings[view_tasks]"'),'Original settings controls remain present');
if(getenv('NATIVE_PROJECT_FORM_RENDER_OUTPUT'))file_put_contents(getenv('NATIVE_PROJECT_FORM_RENDER_OUTPUT'),$html);
$controller->input->verb='POST';$controller->input->body=$wire+['_native_company_csrf'=>'synthetic-native-csrf','_company_context'=>'synthetic-context','framework_csrf'=>'synthetic'];$controller->input->body['native_request_key']=$tk('controller-create');$controller->project();
essentialCheck(($GLOBALS['formRedirect'][2]??null)===303&&str_starts_with($GLOBALS['formRedirect'][0],admin_url('projects/view/')),'Actual original route POST redirects to canonical native project with 303');
unset($controller->input->body['_native_company_csrf']);try{$controller->project();throw new RuntimeException('Missing CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Missing native mutation proof is rejected');}
echo 'Native Projects original create/edit route and real form render: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

}
