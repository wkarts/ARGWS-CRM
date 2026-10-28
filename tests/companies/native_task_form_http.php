<?php
namespace {
require __DIR__.'/native_task_form_payload.php';require_once APPPATH.'services/NativeCompanyTaskChecklistTemplates.php';NativeCompanyTaskChecklistSchema::ensure($db,$p);
// The current detail reads recurring rows; this older form fixture has none.
$db->query('CREATE TABLE '.$p.'company_task_schedules(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id int unsigned NOT NULL,kind varchar(16) NOT NULL,source_id int unsigned NOT NULL)');
unset($db->data_cache['table_names']);
require_once APPPATH.'services/NativeCompanyTaskItemSchema.php';NativeCompanyTaskItemSchema::ensure($db,$p);
$db->query('CREATE TABLE '.$p.'user_meta(umeta_id INTEGER PRIMARY KEY AUTOINCREMENT,staff_id int NOT NULL,meta_key varchar(191) NOT NULL,meta_value text NULL)');
unset($db->data_cache['table_names']);
require_once APPPATH.'services/NativeCompanyBlankTimerSchema.php';NativeCompanyBlankTimerSchema::ensure($db,$p);
$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tbltasks_checklist_templates` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$m);$sql=str_replace('`tbltasks_checklist_templates`','`'.$p.'tasks_checklist_templates`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);
function _l($key,...$args){return$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function html_escape($value){return is_array($value)?array_map('html_escape',$value):e($value);}
function config_item($key){return$key==='charset'?'UTF-8':null;}
function admin_url($path=''){return'https://crm.invalid/sub/admin/'.$path;}
function site_url($path=''){return'https://crm.invalid/sub/'.ltrim($path,'/');}
function get_staff_user_id(){return 7;}
function is_mobile(){return false;}
function file_upload_max_size(){return 10485760;} // PHP upload cap boundary; actual upload gate is separate.
function html_purify($value){return strip_tags((string)$value,'<p><b>');}
function get_option(){throw new \RuntimeException('Unexpected global option');}
function total_rows(){throw new \RuntimeException('Unexpected global row query');}
function staff_can(){throw new \RuntimeException('Unexpected global permission');}
function get_relation_data(){throw new \RuntimeException('Unexpected global relation');}
function get_tags_in(){throw new \RuntimeException('Unexpected global tags');}
function _d(){throw new \RuntimeException('Unexpected global date');}
function render_custom_fields($type,$id){if($type!=='tasks')throw new \RuntimeException('Wrong field scope');return'<div data-custom-field-renderer-boundary></div>';}
require APPPATH.'helpers/func_helper.php';$src=file_get_contents(APPPATH.'helpers/template_helper.php');$begin=strpos($src,'function clear_textarea_breaks(');$end=strpos($src,'/**',$begin);eval(substr($src,$begin,$end-$begin));require BASEPATH.'helpers/form_helper.php';require APPPATH.'helpers/fields_helper.php';
}
namespace NativeTaskHttpTest {
use \DomainException;use \RuntimeException;use \Throwable;use \NativeCompanyTaskForm;use \RecursiveIteratorIterator;use \RecursiveArrayIterator;
function show_error($m,$s){throw new \LogicException('HTTP '.$s);}
class CompanyRequestScope {
 public static int$issuer;public static int$actor=92;
 public static function nativeFor($ci){return true;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}function token(){return'synthetic-context';}};}
 public static function nativeCsrfToken($ci){return'synthetic-csrf';}
 public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='synthetic-csrf')throw new DomainException('native_mutation_denied');}
}
class AdminController {
 public $db,$input,$output,$load,$config,$uri,$security;
 function __construct(){
 $this->db=$GLOBALS['db'];$this->input=new class{public string$verb='GET';public array$body=[],$query=[];function method($v=true){return$this->verb;}function get($k){return$this->query[$k]??null;}function post($k=null,$clean=false){return$k===null?$this->body:($this->body[$k]??null);}};
 $this->output=new class{public string$body='';function set_header($h){return$this;}function set_content_type($type){return$this;}function set_output($v){$this->body=$v;return$this;}};
 $this->config=new class{function site_url($p){return'https://crm.invalid/sub/'.$p;}function item($k){return false;}};$this->uri=new class{function uri_string(){return'admin/tasks/task';}};$this->security=new class{function get_csrf_token_name(){return'framework_csrf';}};
 $this->load=new class($this){function __construct(private$ci){}function model($n){throw new RuntimeException('Global model hydration');}function is_loaded($n){return false;}function view($path,$data,$return=false){if(!in_array($path,['admin/tasks/task','admin/tasks/view_task_template','admin/tasks/checklist_items_template'],true))throw new RuntimeException('Alternate modal');$html=\Closure::bind(function()use($path,$data){extract($data);ob_start();try{require APPPATH.'views/'.$path.'.php';return ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}},$this->ci)();if($return)return$html;echo$html;return null;}};$GLOBALS['CI']=$this;
 }
}
class_alias(CompanyRequestScope::class,'CompanyRequestScope');eval('namespace NativeTaskHttpTest;use \\DomainException;use \\RuntimeException;use \\Throwable;use \\NativeCompanyTaskForm;use \\RecursiveIteratorIterator;use \\RecursiveArrayIterator;'.substr(file_get_contents(APPPATH.'controllers/admin/Tasks.php'),5));
CompanyRequestScope::$issuer=$a;$controller=new Tasks();$controller->task();$html=$controller->output->body;
essentialCheck(str_contains($html,'id="task-form"')&&str_contains($html,'id="_task_modal"')&&str_contains($html,'name="native_request_key"'),'Original GET renders real native modal and durable request identity');
essentialCheck(str_contains($html,'name="assignees[]"')&&str_contains($html,'name="followers[]"')&&str_contains($html,'name="repeat_every"'),'Original controls preserved, not alternative view');
$controller->input->verb='POST';$controller->input->body=$wire+['_native_company_csrf'=>'synthetic-csrf','_company_context'=>'synthetic-context'];$controller->input->body['native_request_key']=$key('http-task-save');$_FILES=['attachments'=>['error'=>[UPLOAD_ERR_NO_FILE]]];$controller->task();$result=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($result['success']===true&&$tasks->get($a,7,\NativeCompanyResourceId::decode('task',$result['id'])['source_id'])['name']===$wire['name'],'Original POST commits actual canonical writer and original response shape');
unset($controller->input->body['_native_company_csrf']);try{$controller->task();throw new RuntimeException('CSRF accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original POST refuses missing proof');}
echo 'Original Tasks GET/modal and POST writer: '.$GLOBALS['essentialChecks']." cumulative checks passed; no authenticated HTTP/browser claim.\n";
}
