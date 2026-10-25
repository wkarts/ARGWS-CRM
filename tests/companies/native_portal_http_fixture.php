<?php
/** Actual controllers/template renderer; explicit HTTP transport seams, no network. */
require_once APPPATH.'services/PortalRequestScope.php';
require_once APPPATH.'services/NativePortalPreferences.php';
if(!defined('PHPASS_HASH_STRENGTH'))define('PHPASS_HASH_STRENGTH',8);if(!defined('PHPASS_HASH_PORTABLE'))define('PHPASS_HASH_PORTABLE',true);
#[AllowDynamicProperties] class App_Controller{public function __construct(){}}
class NativePortalRedirect extends RuntimeException{public function __construct(public string$url,public int$status){parent::__construct('redirect');}}
function redirect($url,$method='auto',$code=null){throw new NativePortalRedirect($url,(int)$code);}
$GLOBALS['essentialHooks']=new class{public $trace=null,$observer=null;function __call($method,$args){throw new LogicException('global hooks forbidden');}};
function get_option($key){throw new LogicException('global options forbidden: '.$key);}
function _l($key,$args='',$log=true){return$key;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($path=''){return'https://fixture.test/crm/'.$path;}
function site_url($path=''){return base_url($path);}
function form_open($path='',$attrs=[]){$s='<form action="'.e(site_url($path)).'" method="post"';foreach($attrs as$k=>$v)$s.=' '.e($k).'="'.e($v).'"';return$s.'>';}
function form_close(){return'</form>';}
function form_hidden($name,$value){return'<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_error($name){return'';}
function validation_errors(...$args){return'';}
function set_value($name,$value=''){return e($value);}
require APPPATH.'helpers/fields_helper.php';require APPPATH.'core/ClientsController.php';require APPPATH.'controllers/Authentication.php';
$auth=(new ReflectionClass(Authentication::class))->newInstanceWithoutConstructor();$auth->db=$db;
$auth->router=new class{public string$method='login',$class='authentication';function fetch_directory(){return'';}function fetch_module(){return null;}function fetch_class(){return$this->class;}function fetch_method(){return$this->method;}};
$auth->input=new class{public array$get=[],$post=[],$cookies=[];public string$verb='GET';function get($key,$filter=false){return$this->get[$key]??null;}function post($key=null,$filter=false){return$key===null?$this->post:($this->post[$key]??null);}function cookie($key,$filter=false){return$this->cookies[$key]??null;}function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}function is_cli_request(){return false;}function ip_address(){return'127.0.0.88';}};
$auth->session=new class{public array$data=['staff_logged_in'=>true,'staff_user_id'=>7];public int$rotations=0;function userdata($k){return$this->data[$k]??null;}function set_userdata($data){$this->data=array_replace($this->data,$data);}function unset_userdata($keys){foreach((array)$keys as$k)unset($this->data[$k]);}function sess_regenerate($destroy){$this->rotations++;}function flashdata($key){return null;}};
$auth->config=new class{function item($key){return match($key){'encryption_key'=>str_repeat('synthetic-',4),'base_url'=>'https://fixture.test/crm/',default=>null};}};
$auth->uri=new class($auth){function __construct(private$ci){}function uri_string(){return'authentication/'.$this->ci->router->method;}};
$auth->form_validation=new class{function set_error_delimiters(...$args){}};
$auth->lang=new class{public array$language=[];function load(...$args){return[];}function set_last_loaded_language($v){}};
$auth->output=new class{public array$headers=[];function set_header($v,$replace=true){$this->headers[]=$v;return$this;}};
$auth->load=new class($auth){public $trace=null,$observer=null;public string$html='';function __construct(private$ci){}function helper($names){}function library($name){if(!in_array($name,['form_validation'],true))throw new LogicException('unexpected library');}function view($path,$data=[],$return=false){$render=function()use($path,$data){extract($data);include APPPATH.'views/'.$path.'.php';};$render=$render->bindTo($this->ci,$this->ci);ob_start();try{$render();$html=ob_get_contents();}finally{ob_end_clean();}if($return)return$html;$this->html.=$html;echo$html;}};
