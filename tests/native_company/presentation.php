<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
if(array_diff(array_slice($argv,1),['--render'])){throw new InvalidArgumentException('Unknown presentation fixture flag');}
// Dependency-negative real view renderer: models/hooks/options are forbidden.
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__,2).'/application/');define('ARGWS_VERSION','test');
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($p=''){return 'https://crm.example.test/sub/'.$p;}
function admin_url($p=''){return base_url('admin/'.$p);}
function db_prefix(){return 'tbl';}
function crm_app_page_title($t=null){return (string)($t??'CRM');}
function crm_app_display_name_html(){return 'CRM';}
function _attributes_to_string($attrs){check($attrs===[],'no arbitrary native attributes');return '';}
function hooks(){throw new RuntimeException('Global hooks forbidden');}
function _l(...$args){throw new RuntimeException('Global translation hooks forbidden');}
function get_option(...$args){throw new RuntimeException('Global options forbidden');}
function get_instance(){return $GLOBALS['fixture'];}
class CompanyRequestScope {
 static $ci; static $issuer=2; static $token='2:revision'; static $native=true;
 static function nativeFor($ci){return self::$native && $ci===self::$ci;}
 static function sealedFor($ci){return $ci===self::$ci;}
 static function context(){return new class {function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 17;}};}
 static function token(){return self::$token;}
 static function nativeCsrfToken($ci){return 'synthetic-native-csrf';}
 static function nativeRouteAllowed($ci,$route){return in_array($route,['','clients','clients/client','invoices','projects','tasks','tasks/task','settings','todo','departments'],true);}
 static function adminUrl($ci,$path=''){return admin_url($path);}
}
class CompanySettingsCatalog {static function defaultValue($group,$field){check($group==='localization','only public localization defaults');return ['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24'][$field];}}
class CompanySettingsRuntime {
 static function isPrimary(){return false;}static function active(){return CompanyRequestScope::$issuer!==null;}static function issuerId(){return CompanyRequestScope::$issuer;}static function staffId(){return 17;}
 static function exists($name){check(in_array($name,['dateformat','time_format','desktop_notifications','auto_dismiss_desktop_notifications_after','tables_pagination_limit','task_modal_class','calendar_first_day'],true),'strict public option allowlist');return true;}
 static function get($name){return ['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24','desktop_notifications'=>'0','auto_dismiss_desktop_notifications_after'=>'0','tables_pagination_limit'=>'37','task_modal_class'=>'modal-lg','calendar_first_day'=>'6'][$name];}
}
// Real upload provider is covered by native_company_upload_preferences.php SQLite suite.
class NativeCompanyUploadPreferences {
 static function read($db,$prefix,$issuer,$actor){check($issuer===CompanyRequestScope::$issuer&&$actor===17,'upload projection bound to current context');return ['allowed_files'=>$issuer===2?'.pdf':'.txt','media_max_file_size_upload'=>'10'];}
 static function maxBytes($values){return (int)$values['media_max_file_size_upload']*1024*1024;}
}
class NativeCompanyCalendarPresentation {
 static function read($db,$p,$issuer,$actor){check($issuer===CompanyRequestScope::$issuer&&$actor===17,'calendar presentation binding');return ['calendar_first_day'=>'6','revision'=>3,'source'=>'owned_override'];}
}
class CompanyAssets {
 static $calls=0;static $invalid=false;
 function __construct($db,$p){}
 function brandingForStaff($issuer,$actor){check($issuer===CompanyRequestScope::$issuer&&$actor===17,'branding bound to current identity');self::$calls++;return self::$invalid?['logo'=>'https://external.invalid/logo','logo_dark'=>'','favicon'=>'']:['logo'=>'data:image/png;base64,QQ==','logo_dark'=>$issuer===2?'data:image/png;base64,Qg==':'','favicon'=>$issuer===2?'data:image/png;base64,Qw==':''];}
}
class NativeCompanyWorkTags {
 function __construct($db,$prefix){}
 function catalog($issuer,$actor){check($issuer===CompanyRequestScope::$issuer&&$actor===17,'tag catalog bound to active scope');return ['tags'=>[],'available_tags'=>[$issuer===2?'<tag A>':'B'],'available_tags_ids'=>[$issuer===2?'94489280513':'94489280514']];}
}
class NativeCompanyAuxiliaryUi {
 function __construct($db,$prefix,$issuer,$actor){check($issuer===2&&$actor===17,'todo header actor/issuer');}
 function todoCounts(){return [0=>5,1=>2];}
}
class IssuerResourceAccess {public static bool $administrative=true;function __construct(...$args){} function administrative($issuer,$actor){return $issuer===2&&self::$administrative;} function member($issuer,$actor){return $issuer===2&&$actor===17;} function can($issuer,$actor,$feature,$cap){return $issuer===2 && in_array($feature,['customers','invoices','projects','tasks','settings','tickets'],true);}}
class IssuerStorage {static function query($db,$sql,$params=[]){check(!str_contains($sql,'options'),'theme inactive must not fetch options');return new class($sql){function __construct(private $sql){}function row_array(){return str_contains($this->sql,'staffid,firstname')?['staffid'=>17,'firstname'=>'<Alice>','lastname'=>'A','email'=>'synthetic@example.test']:['active'=>0];}};}}
class Fixture {
 public $config; public $security; public $load; public $db; public $router; public $agent; public $session; public $lang; public array $vars=[];
 function __construct(){ $this->config=new class {public bool $csrf=false;function item($key){check($key==='csrf_protection','only infrastructure CSRF config');return $this->csrf;}};$this->security=new class {function get_csrf_token_name(){return 'fixture_csrf';}function get_csrf_hash(){return 'fixture-framework-hash';}};$this->db=new stdClass; $this->session=new class {function has_userdata($key){check($key==='setup-menu-open','only actor UI session key');return true;}function userdata($key){return false;}}; $this->lang=(object)['language'=>[]];$this->load=$this; $this->router=new class {function fetch_class(){return 'dashboard';}function fetch_method(){return 'index';}}; }
