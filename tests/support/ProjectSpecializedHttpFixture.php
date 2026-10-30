<?php
/** Real controllers/services/storage; only framework HTTP/session/context are synthetic. */
require dirname(__DIR__).'/companies/portal_operations_cleanup.php';require dirname(__DIR__).'/companies/projects_operations.php';require dirname(__DIR__).'/companies/portal_operations_schema_fixture.php';
class CompanyRequestScope
{
    public static bool $sealed=true;public static int $issuer;public static int $actor=7;
    public static function sealedFor($owner):bool{return self::$sealed;}public static function nativeFor($owner):bool{return self::$sealed;}
    public static function context(){return new class {public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}public function isPrimary(){return false;}public function available(){return [];}public function csrf(){return 'synthetic-switch';}};}
    public static function token(){return 'synthetic-project-context';}public static function invalidateCaches($owner):void{}
    public static function adminUrl($owner,string $path=''):string{return 'https://synthetic.invalid/admin/'.$path;}
}
class AdminController{public function __construct(){}public function __get($name){return $GLOBALS['CI']->$name;}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;}
function redirect($url,$method='location',$code=302){if($code!==303)throw new RuntimeException('unexpected_redirect');$GLOBALS['projectRedirect']=$url;$GLOBALS['CI']->output->status=$code;}
require APPPATH.'controllers/admin/Projects.php';require APPPATH.'controllers/admin/Tasks.php';
$GLOBALS['CI']->input=new class {public string $verb='GET';public array $gets=[],$posts=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function get($k,$filter=false){return $this->gets[$k]??null;}public function post($k,$filter=false){return $this->posts[$k]??null;}};
$GLOBALS['CI']->output=new class {public int $status=200;public function set_header($v){return $this;}public function set_status_header($v){$this->status=$v;}};
$GLOBALS['CI']->session=new class {public array $values=[];public function userdata($k){return $this->values[$k]??null;}public function set_userdata($k,$v){$this->values[$k]=$v;}};
$GLOBALS['CI']->load=new class {public array $views=[];public function helper($name){if($name!=="date")throw new RuntimeException("Unexpected helper");}public function view($n,$data){$this->views[]=[$n,$data];}};

function _l($key){return $key;}
function base_url($path=''){return 'https://synthetic.invalid/'.$path;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function form_open($url,$attributes=[]){return '<form method="post" action="'.e($url).'"><input type="hidden" name="synthetic_csrf" value="synthetic-native-csrf"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'">';}
function form_close(){return '</form>';}
class CompanySettingsRuntime{public static function active(){return true;}public static function can($cap){return false;}public static function canResource($feature,$cap){return true;}}
$http=$GLOBALS['CI']->input;
$harness=new class {public function __get($name){return $GLOBALS['CI']->$name;}public function render(string $name,array $data):string{extract($data);ob_start();include APPPATH.'views/admin/'.$name.'.php';return ob_get_clean();}};
