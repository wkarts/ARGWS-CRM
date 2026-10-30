class NativeCompanyAuxiliaryUi {
 function __construct($db,$prefix,$issuer,$actor){check($issuer===2&&$actor===17,'todo header actor/issuer');}
 function todoCounts(){return [0=>5,1=>2];}
}
class IssuerResourceAccess {public static bool $administrative=true;function __construct(...$args){} function administrative($issuer,$actor){return $issuer===2&&self::$administrative;} function member($issuer,$actor){return $issuer===2&&$actor===17;} function can($issuer,$actor,$feature,$cap){return $issuer===2 && in_array($feature,['customers','invoices','projects','tasks','settings','tickets'],true);}}
class IssuerStorage {static function query($db,$sql,$params=[]){check(!str_contains($sql,'options'),'theme inactive must not fetch options');return new class($sql){private $sql;function __construct($sql){$this->sql=$sql;}function row_array(){return str_contains($this->sql,'staffid,firstname')?['staffid'=>17,'firstname'=>'<Alice>','lastname'=>'A','email'=>'synthetic@example.test']:['active'=>0];}};}}
class Fixture {
 public $config; public $security; public $load; public $db; public $router; public $agent; public $session; public $lang; public array $vars=[];
 function __construct(){ $this->config=new class {public bool $csrf=false;function item($key){check($key==='csrf_protection','only infrastructure CSRF config');return $this->csrf;}};$this->security=new class {function get_csrf_token_name(){return 'fixture_csrf';}function get_csrf_hash(){return 'fixture-framework-hash';}};$this->db=new stdClass; $this->session=new class {function has_userdata($key){check($key==='setup-menu-open','only actor UI session key');return true;}function userdata($key){return false;}}; $this->lang=(object)['language'=>[]];$this->load=$this; $this->router=new class {function fetch_class(){return 'dashboard';}function fetch_method(){return 'index';}}; }
 function vars($vars){$this->vars=$vars;}
 function library($name){check($name==='user_agent','only pure user-agent parser');$this->agent=new class {function is_mobile(){return false;}function browser(){return 'Chrome';}};}
 function view($name,$extra=[]){
  if($name==='admin/includes/selector'){echo '<div data-fixture-selector></div>';return;}
  if($name==='admin/includes/context_script'){echo '<script data-fixture-context></script>';return;}
  // MX_Loader includes templates in loader scope, not controller scope.
  $host=new class($this){private $owner;function __construct($owner){$this->owner=$owner;}function __get($key){return$this->owner->$key;}function __isset($key){return isset($this->owner->$key);}};
  check(!CompanyRequestScope::nativeFor($host),'a view loader cannot attest as the controller');
  (function($view,$extra,$variables){extract($variables);extract($extra);require APPPATH.'views/'.$view.'.php';})->call($host,$name,$extra,$this->vars);
 }
 function __get($name){throw new RuntimeException('Global dependency forbidden: '.$name);}
}
