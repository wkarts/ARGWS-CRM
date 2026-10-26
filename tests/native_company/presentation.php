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
 static function exists($name){check(in_array($name,['dateformat','time_format','desktop_notifications','auto_dismiss_desktop_notifications_after','tables_pagination_limit','calendar_first_day'],true),'strict public option allowlist');return true;}
 static function get($name){return ['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24','desktop_notifications'=>'0','auto_dismiss_desktop_notifications_after'=>'0','tables_pagination_limit'=>'37','calendar_first_day'=>'6'][$name];}
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
class IssuerResourceAccess {public static bool $administrative=true;function __construct(...$args){} function administrative($issuer,$actor){return $issuer===2&&self::$administrative;} function can($issuer,$actor,$feature,$cap){return $issuer===2 && in_array($feature,['customers','invoices','projects','tasks','settings','tickets'],true);}}
class IssuerStorage {static function query($db,$sql,$params=[]){check(!str_contains($sql,'options'),'theme inactive must not fetch options');return new class($sql){function __construct(private $sql){}function row_array(){return str_contains($this->sql,'staffid,firstname')?['staffid'=>17,'firstname'=>'<Alice>','lastname'=>'A','email'=>'synthetic@example.test']:['active'=>0];}};}}
class Fixture {
 public $config; public $security; public $load; public $db; public $router; public $agent; public $session; public $lang; public array $vars=[];
 function __construct(){ $this->config=new class {public bool $csrf=false;function item($key){check($key==='csrf_protection','only infrastructure CSRF config');return $this->csrf;}};$this->security=new class {function get_csrf_token_name(){return 'fixture_csrf';}function get_csrf_hash(){return 'fixture-framework-hash';}};$this->db=new stdClass; $this->session=new class {function has_userdata($key){check($key==='setup-menu-open','only actor UI session key');return true;}function userdata($key){return false;}}; $this->lang=(object)['language'=>[]];$this->load=$this; $this->router=new class {function fetch_class(){return 'dashboard';}function fetch_method(){return 'index';}}; }
 function vars($vars){$this->vars=$vars;}
 function library($name){check($name==='user_agent','only pure user-agent parser');$this->agent=new class {function is_mobile(){return false;}function browser(){return 'Chrome';}};}
 function view($name,$extra=[]){
  if($name==='admin/includes/company_selector'){echo '<div data-fixture-selector></div>';return;}
  if($name==='admin/includes/company_context_script'){echo '<script data-fixture-context></script>';return;}
  extract($this->vars);extract($extra);require APPPATH.'views/'.$name.'.php';
 }
 function __get($name){throw new RuntimeException('Global dependency forbidden: '.$name);}
}
require APPPATH.'services/NativeCompanyAdminPresentation.php';
$ci=new Fixture;$GLOBALS['fixture']=$ci;CompanyRequestScope::$ci=$ci;
NativeCompanyAdminPresentation::prepare($ci);
check(($ci->lang->language['dashboard_string']??null)==='Painel','shared CI language object receives the trusted native PT-BR catalog');
$data=NativeCompanyAdminPresentation::current($ci);
$nativeBodyClasses=explode(' ',NativeCompanyAdminPresentation::bodyClasses($ci,'main-todo-page'));
check(in_array(strtolower((string)$ci->router->fetch_class()),$nativeBodyClasses,true)&&in_array('main-todo-page',$nativeBodyClasses,true),'same native body preserves controller and view selectors without global hooks');
try{NativeCompanyAdminPresentation::bodyClasses($ci,'bad" onclick="bad');throw new RuntimeException('unsafe body class accepted');}catch(DomainException $e){check($e->getMessage()==='native_body_class_invalid','body class projection rejects attribute injection');}

check($data['logo']==='data:image/png;base64,Qg=='&&$data['favicon']==='data:image/png;base64,Qw==','native dark-logo priority and issuer favicon');
$ci->config->csrf=true;$browser=NativeCompanyAdminPresentation::browserConfig($ci);check($browser['frameworkCsrf']===['name'=>'fixture_csrf','value'=>'fixture-framework-hash'],'framework token available without header providers for all native forms');$ci->config->csrf=false;
check(isset($data['sidebar_menu']['customers']) && isset($data['sidebar_menu']['sales']['children'][0]),'authorized native menu entries');
check(count(array_filter($data['setup_menu']['support']['children'],static fn($child)=>$child['slug']==='departments'&&str_ends_with($child['href'],'/admin/departments')))===1,'the original Departments setup route appears only for its administrator policy');
IssuerResourceAccess::$administrative=false;NativeCompanyAdminPresentation::prepare($ci);$limited=NativeCompanyAdminPresentation::current($ci);
check(count(array_filter($limited['setup_menu']['support']['children']??[],static fn($child)=>$child['slug']==='departments'))===0,'Departments is hidden when the actor is not an administrator');
IssuerResourceAccess::$administrative=true;NativeCompanyAdminPresentation::prepare($ci);$data=NativeCompanyAdminPresentation::current($ci);
check(!isset($data['sidebar_menu']['subscriptions']),'unauthorized/unimplemented entries absent');
$app=NativeCompanyAdminPresentation::appConfig($ci);
check($app['options']['tables_pagination_limit']==='37' && $app['options']['decimal_places']===2 && isset($app['lang']['datatables']['paginate']['next']),'real native tables payload without global hooks');
check($app['options']['date_format']==='d/m/Y' && $app['options']['has_permission_create_task']===true,'native app uses persisted formatting and actual create ACL');
check($app['lang']['search_tasks']==='Buscar Tarefas' && $app['tinymce_lang']==='pt_BR','native app trusted locale');
check($app['available_tags']===['<tag A>']&&$app['available_tags_ids']===['94489280513'],'tag catalog preserves actual labels and canonical IDs');
foreach(['user_is_admin'] as $unknown)check(!array_key_exists($unknown,$app),'no fabricated or global app field '.$unknown);
check($app['options']['allowed_files']==='.pdf' && (int)$app['max_php_ini_upload_size_bytes']>0,'upload preferences and actual PHP cap projected');check(!array_key_exists('google_api',$app['options']),'no provider settings');
check($data['total_unfinished_todos']===5,'actual todo provider count not fabricated');
check(count($data['quick_actions'])===2,'quick actions use explicit create ACL and native route catalog');
check($data['quick_actions'][1]['href_attributes']['onclick']==='new_task();return false;','native task handler preserved');
ob_start();foreach(['head','header','setup_menu','aside'] as $name)$ci->view('admin/includes/'.$name);echo '<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body"><form><label for="native-field">Nome</label><input id="native-field" name="name" class="form-control" value="Sintético"><button type="button" class="btn btn-primary">Salvar</button></form></div></div></main></div>'; $ci->view('admin/includes/scripts');echo '</body></html>';$html=ob_get_clean();
if(in_array('--render',$argv??[],true)){echo $html;exit;}
foreach(['id="header"','id="menu"','id="side-menu"','id="setup-menu"','native-company-admin','&lt;Alice&gt;','/sub/admin/clients','/sub/admin/invoices'] as $needle)check(str_contains($html,$needle),'native render '.$needle);
foreach(['company_context','company_clients','company-shell.js','company-workspace.js','assets/js/main.js','admin/projects/pinned'] as $needle)check(!str_contains($html,$needle),'no parallel/global hydration '.$needle);
check(!str_contains($html,'synthetic@example.test</script>'),'escaped identity');
check(str_contains($html,'rel="icon" href="data:image/png;base64,Qw=="')&&str_contains($html,'src="data:image/png;base64,Qg=="'),'same header/head render scoped branding');
$reflection=new ReflectionProperty(NativeCompanyAdminPresentation::class,'data');$original=$reflection->getValue();$withProviders=$original;
$withProviders['notifications']=['unread'=>1,'items'=>[['id'=>'171798691841','url'=>'tasks/view/8589934593','read_at'=>null,'inline_read_at'=>null,'created_at'=>'2026-01-01 12:00:00','title'=>'<script>bad</script>','body'=>'A & B']]];
$reflection->setValue(null,$withProviders);ob_start();$ci->view('admin/includes/header');$providerHtml=ob_get_clean();
check(str_contains($providerHtml,'notification-wrapper')&&str_contains($providerHtml,'&lt;script&gt;bad&lt;/script&gt;'),'same notification DOM escapes owned content');check(!str_contains($providerHtml,'<script>bad</script>'),'no raw event markup');
ob_start();$ci->view('admin/search',['result'=>[['type'=>'tasks','search_heading'=>'tasks','native_owned'=>true,'result'=>[['relative_url'=>'tasks/view/8589934593','label'=>'<owned task>']]]]]);$searchHtml=ob_get_clean();
check(str_contains($searchHtml,'&lt;owned task&gt;')&&str_contains($searchHtml,'/admin/tasks/view/8589934593'),'same search DOM owned route and escaped label');
ob_start();$ci->view('admin/search',['result'=>[['type'=>'custom_fields','search_heading'=>'custom_fields','native_owned'=>true,'result'=>[['relative_url'=>'settings?group=company','label'=>'<company field>','relation_type'=>'company']]],['type'=>'invoice_items','search_heading'=>'invoice_items','native_owned'=>true,'result'=>[['relative_url'=>'invoices/list_invoices/42','label'=>'INV42','secondary_label'=>'<line item>']]]]]);$expandedSearch=ob_get_clean();
check(str_contains($expandedSearch,'class="pull-left"')&&str_contains($expandedSearch,'class="pull-right"')&&str_contains($expandedSearch,'&lt;company field&gt;'),'native custom field search DOM and escaping');
check(str_contains($expandedSearch,'<br /><small>&lt;line item&gt;</small>'),'native invoice line item DOM and escaping');
require_once APPPATH.'helpers/fields_helper.php';require_once APPPATH.'helpers/template_helper.php';
function set_value($name,$value=''){return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$policy=['can_edit'=>true,'can_view_all'=>true,'can_start_blank'=>false,'auto_stop'=>true,'policy_hash'=>str_repeat('a',64)];
$timers=[['id'=>'47244640257','task_id'=>'8589934593','task_subject'=>'<task>','start_time'=>1760000000,'started_at_label'=>'09/10/2025 08:53','total_seconds'=>3661,'revision'=>3,'note_revision'=>2]];
ob_start();$ci->view('admin/tasks/started_timers',['startedTimers'=>$timers,'nativeTimerPolicy'=>$policy]);$timerHtml=ob_get_clean();
check(str_contains($timerHtml,'01:01')&&str_contains($timerHtml,'&lt;task&gt;')&&str_contains($timerHtml,'data-timer-revision="3"')&&str_contains($timerHtml,'data-note-revision="2"'),'same timer partial uses scoped elapsed/name/revision');
check(str_contains($timerHtml,'init_task_modal(8589934593)')&&str_contains($timerHtml,'timer_action(this,8589934593,47244640257)'),'same task handlers retained');
check(!str_contains($timerHtml,'started-timers-button'),'persisted timer policy suppresses conflicting blank start');
$policy['can_edit']=false;$policy['can_view_all']=false;
ob_start();$ci->view('admin/tasks/started_timers',['startedTimers'=>$timers,'nativeTimerPolicy'=>$policy]);$readOnlyTimer=ob_get_clean();
check(!str_contains($readOnlyTimer,'unfinished-timesheet-delete')&&!str_contains($readOnlyTimer,'top-stop-timer')&&!str_contains($readOnlyTimer,'view-all-timesheets'),'read-only timer ACL does not advertise mutations');
unset($timers[0]['started_at_label']);$level=ob_get_level();try{ob_start();$ci->view('admin/tasks/started_timers',['startedTimers'=>$timers,'nativeTimerPolicy'=>$policy]);throw new RuntimeException('missing time basis accepted');}catch(LogicException $error){check($error->getMessage()==='native_timer_projection_incomplete','missing time basis rejected');}finally{while(ob_get_level()>$level)ob_end_clean();}
$reflection->setValue(null,$original);

CompanyRequestScope::$token='changed';try{NativeCompanyAdminPresentation::current($ci);throw new RuntimeException('stale accepted');}catch(LogicException $e){check($e->getMessage()==='native_company_presentation_stale','stale token blocked');}
CompanyRequestScope::$issuer=3;CompanyRequestScope::$token='3:revision';NativeCompanyAdminPresentation::prepare($ci);$data=NativeCompanyAdminPresentation::current($ci);check(!isset($data['sidebar_menu']['customers']),'A grants not reused for B');check(NativeCompanyAdminPresentation::appConfig($ci)['available_tags']===['B'],'A tags not reused for B');check(!isset($data['total_unfinished_todos']),'A todo data not reused for B');check($data['quick_actions']===[],'A quick actions not reused for B');check(NativeCompanyAdminPresentation::appConfig($ci)['options']['has_permission_create_task']===false,'A app permission not reused for B');check(NativeCompanyAdminPresentation::appConfig($ci)['options']['allowed_files']==='.txt','A upload policy not reused for B');
check($data['logo']==='data:image/png;base64,QQ=='&&$data['favicon']===base_url('assets/images/argws/favicon.png'),'B uses its own logo and public favicon fallback');
CompanyAssets::$invalid=true;try{NativeCompanyAdminPresentation::prepare($ci);throw new RuntimeException('remote branding accepted');}catch(LogicException $e){check($e->getMessage()==='native_branding_projection_invalid','remote URL rejected');}CompanyAssets::$invalid=false;$brandingCalls=CompanyAssets::$calls;
CompanyRequestScope::$issuer=null;CompanyRequestScope::$token='17:0';NativeCompanyAdminPresentation::prepare($ci);$data=NativeCompanyAdminPresentation::current($ci);check($data['sidebar_menu']===[] && $data['setup_menu']===[],'no selection has no resource links');
check(CompanyAssets::$calls===$brandingCalls&&$data['logo']===base_url('assets/images/argws/logo-dark.png'),'no selection does not read company branding');
ob_start();foreach(['head','header','setup_menu','aside','scripts'] as $name)$ci->view('admin/includes/'.$name);$empty=ob_get_clean();check(str_contains($empty,'data-fixture-selector'),'selection remains available');
CompanyRequestScope::$native=false;check(NativeCompanyAdminPresentation::current($ci)===null,'legacy path remains separate');
echo "Native presentation real templates and A/B/null/stale negative dependencies passed.\n";
