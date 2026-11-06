<?php
/** Complete disposable ownership schemas and explicit framework/context boundary for contract forms. */
require __DIR__.'/portal_operations_cleanup.php';
require __DIR__.'/operation_files_setup.php';
require __DIR__.'/portal_operations_schema_fixture.php';
require_once dirname(__DIR__).'/helpers/native_auxiliary_schema_fixture.php';
require __DIR__.'/native_contract_metadata_schema_fixture.php';
class CompanyRequestScope
{
    public static bool $sealed=true,$csrfValid=true;public static int $issuer,$actor=7;
    public static function sealedFor($ci):bool{return self::$sealed;}
    public static function nativeFor($ci):bool{return true;}
    public static function assertNativeMutation($ci):void{if(!self::$sealed||!self::$csrfValid||strtoupper($ci->input->method(true))!=='POST')throw new DomainException('native_contract_csrf_invalid');}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}public function isPrimary(){return false;}public function available(){return [];}public function csrf(){return'synthetic-switch';}};}
    public static function token(){return'synthetic-contract-context';}
    public static function invalidateCaches($ci):void{}
    public static function adminUrl($ci,string$path=''):string{return'https://synthetic.invalid/admin/'.$path;}
}
class AdminController{public function __construct(){}public function __get($name){return$GLOBALS['CI']->$name;}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->body=$message;}
function redirect($url,$method='location',$code=302){$GLOBALS['contractRedirect']=$url;$GLOBALS['CI']->output->status=$code;}
function _l($key){return$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($path=''){return'https://synthetic.invalid/admin/'.$path;}
function base_url($path=''){return'https://synthetic.invalid/'.$path;}
function form_open($url,$attributes=[]){$extra='';foreach($attributes as$key=>$value)$extra.=' '.e($key).'="'.e($value).'"';return'<form method="post" action="'.e($url).'"'.$extra.'><input type="hidden" name="synthetic_csrf" value="synthetic-native-csrf"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'">';}
function form_close(){return'</form>';}
function init_head(){}function init_tail(){}
$GLOBALS['CI']->input=new class{public string$verb='GET';public array$gets=[],$posts=[];public function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}public function get($k,$filter=false){return$this->gets[$k]??null;}public function post($k=null,$filter=false){return$k===null?$this->posts:($this->posts[$k]??null);}public function is_ajax_request(){return true;}};
$GLOBALS['CI']->output=new class{public int$status=200;public string$body='',$mime='';public array$headers=[];public function set_header($v){$this->headers[]=$v;return$this;}public function set_status_header($v){$this->status=$v;return$this;}public function set_content_type($v){$this->mime=$v;return$this;}public function set_output($v){$this->body=$v;return$this;}};
$GLOBALS['CI']->session=new class{public array$values=[];public function userdata($k){return$this->values[$k]??null;}public function set_userdata($k,$v){$this->values[$k]=$v;}};
$GLOBALS['CI']->load=new class{public array$views=[];public function view($name,$data=[],$return=false){$this->views[]=[$name,$data];if($return){$render=function()use($name,$data){extract($data,EXTR_SKIP);include APPPATH.'views/'.$name.'.php';};ob_start();try{$render->bindTo($GLOBALS['CI'],null)();return ob_get_clean();}catch(Throwable$error){ob_end_clean();throw$error;}}}};
require APPPATH.'controllers/admin/Contracts.php';
CompanyRequestScope::$issuer=$b;$cc=new Contracts();
