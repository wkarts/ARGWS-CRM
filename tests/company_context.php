<?php
/** Real context/ACL/bootstrap/controller + synthetic PDO and CI I/O; no transport or production data. */
require __DIR__ . '/issuers_core_support.php';
require APPPATH . 'services/CompanyRequestScope.php';
require APPPATH . 'services/CompanySettingsRuntime.php';
define('ADMIN_URI', 'admin');
define('ENVIRONMENT', 'testing');

final class ContextSession
{
    public array $data = [];
    public int $reads = 0;
    public function userdata($key) { ++$this->reads; return $this->data[$key] ?? null; }
    public function set_userdata($key, $value = null): void { if (is_array($key)) { $this->data = array_replace($this->data, $key); } else { $this->data[$key] = $value; } }
    public function unset_userdata($key): void { unset($this->data[$key]); }
    public function flashdata($key){$value=$this->data[$key]??null;unset($this->data[$key]);return $value;}
    public function set_flashdata($key,$value){$this->data[$key]=$value;}
}
final class ContextInput
{
    public string $verb = 'GET'; public array $headers = []; public bool $cli = false; public string $group = 'company';
    public function method($upper = false) { return $upper ? $this->verb : strtolower($this->verb); }
    public function post($name = null, $clean = false) { return $name === null ? $_POST : ($_POST[$name] ?? null); }
    public function get($name) { return $name === 'group' ? $this->group : null; }
    public function get_request_header($name, $clean = false) { return $this->headers[$name] ?? null; }
    public function is_cli_request() { return $this->cli; }
    public bool $ajax = false;
    public function is_ajax_request() { return $this->ajax; }
}
final class ContextRouter
{
    public string $controller = 'company_context'; public string $method = 'index'; public string $directory = 'admin/'; public ?string $module = null;
    public function fetch_directory() { return $this->directory; }
    public function fetch_class() { return $this->controller; }
    public function fetch_method() { return $this->method; }
    public function fetch_module() { return $this->module; }
}
final class ContextConfig
{
    public function site_url($path) { return 'https://crm.example.test/' . $path; }
    public function base_url() { return 'https://crm.example.test/'; }
    public function item($key) { return $key === 'csrf_protection' ? false : 'UTF-8'; }
}
final class ContextLoader
{
    public array $autoload = []; public array $trace = []; public array $views = [];
    public function database(): void { $this->trace[] = 'database'; }
    public function driver($name): void { $this->trace[] = 'driver:' . $name; }
    public function helper($name): void { $this->trace[] = 'helper:' . (is_array($name) ? implode(',', $name) : $name); }
    public function initialize(): void { include APPPATH . 'config/autoload.php'; $this->autoload = $autoload; $this->trace[] = 'autoload_returned'; }
    public function library($name): void { $this->trace[] = 'library:' . $name; if ($name !== 'encryption') { throw new RuntimeException('unexpected library hydration'); } }
    public function model($name): void { $this->trace[] = 'model:' . $name; if (!in_array($name,['settings_model','emails_model'],true)) { throw new RuntimeException('unexpected model hydration'); } $GLOBALS['CI']->{$name} = $name==='settings_model'?new Settings_model():new Emails_model(); }
    public function view($name, $data = []): void { $this->views[] = [$name, $data]; }
}
#[AllowDynamicProperties]
class CI_Controller
{
    public function __construct()
    {
        foreach (get_object_vars($GLOBALS['contextEnvironment']) as $key => $value) { $this->$key = $value; }
        $GLOBALS['CI'] = $this;
        $this->load->initialize();
    }
}
require APPPATH . 'core/App_Controller.php';
require APPPATH . 'core/AdminController.php';
require APPPATH . 'controllers/admin/Company_context.php';
#[AllowDynamicProperties]
class CI_Model { public function __construct() {} public function __get($key) { return $GLOBALS['CI']->{$key}; } }
require APPPATH . 'core/App_Model.php';
require APPPATH . 'models/Settings_model.php';
require APPPATH . 'controllers/admin/Settings.php';
require APPPATH . 'controllers/admin/Issuers.php';
function redirect($url, $method = '', $code = 302) { $GLOBALS['redirects'][] = [$url, $method, $code]; }
function show_error($message, $status = 500) { throw new CompanyRequestDenied(strip_tags($message), $status); }
function list_files($path) { return []; }
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function html_escape($value) { return e($value); }
function base_url($path) { return 'https://crm.example.test/' . $path; }
function config_item($name) { return $name === 'charset' ? 'UTF-8' : null; }
require APPPATH . 'helpers/App_form_helper.php';
require BASEPATH . 'helpers/form_helper.php';
final class ContextViewRenderer
{
    public function __get($key) { return $GLOBALS['CI']->{$key}; }
    public function render($view, $data = []): string { extract($data); ob_start(); require APPPATH . 'views/' . $view . '.php'; return ob_get_clean(); }
}
function expectContext(callable $call, string $reason): void
{
    try { $call(); throw new LogicException('missing expected rejection: ' . $reason); }
    catch (DomainException $error) { essentialCheck($error->getMessage() === $reason, $reason); }
}
function expectStatus(callable $call, int $status): void
{
    try { $call(); throw new LogicException('missing expected HTTP rejection: ' . $status); }
    catch (CompanyRequestDenied $error) { essentialCheck($error->getCode() === $status, 'HTTP status ' . $status); }
    finally { CompanyRequestScope::finish(); }
}
function contextEnvironment($f, ContextSession $session, string $class = 'company_context', string $method = 'index', string $verb = 'GET'): object
{
    CompanyRequestScope::finish(); $_POST = [];
    $ci = (object) ['db' => $f->db, 'session' => $session, 'router' => new ContextRouter(), 'input' => new ContextInput(), 'config' => new ContextConfig(), 'load' => new ContextLoader(), 'encryption' => new class { public function encrypt($value) { return 'synthetic-cipher:' . base64_encode($value); } public function decrypt($value) { return str_starts_with((string) $value, 'synthetic-cipher:') ? base64_decode(substr($value, 17), true) : false; } }, 'output' => new class { public int $status=200; public function set_status_header($status) { $this->status = $status; } }];
    $ci->router->controller = $class; $ci->router->method = $method; $ci->input->verb = $verb;
    $GLOBALS['CI'] = $GLOBALS['contextEnvironment'] = $ci;
    return $ci;
}
$f = essentialFixture(); $db = $f->db; $p = $f->prefix;
essentialSeedSchema($f);
$db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at) VALUES(41,'Principal synthetic','12345678000195',1,'{}',7,'2026-10-18')");
$db->query("INSERT INTO {$p}options VALUES('company_vat','12345678000195')");
IssuerCore::reconcile($db, $p); IssuerCore::bindInstallationMaster($db, $p, 7);
$db->query("INSERT INTO {$p}staff VALUES(8,1,0,NULL),(1,1,1,NULL),(9,0,1,NULL)");
$db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(54,'Secondary synthetic','11222333000181',1,'{}',7,'2026-10-18','complete'),(55,'Forbidden synthetic','11444777000161',1,'{}',7,'2026-10-18','complete')");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(41,8,1),(54,8,1),(54,1,0)");
$db->query("INSERT INTO {$p}issuers_staff_defaults(staff_id,issuer_id) VALUES(8,41),(1,54)");
$s = new ContextSession(); $s->data = ['staff_user_id' => 8, 'staff_logged_in' => true];
$context = new CompanyContext($db, $p, $s, 8);
essentialCheck($context->resolve() === 41, 'valid server default selected, real primary ID 41');
essentialCheck(array_column($context->available(), 'id') === [41,54], 'primary first and only authorized companies');
$tokenA = $context->token(); $csrfA = $context->csrf();
expectContext(fn() => $context->select(55, $csrfA, $tokenA), 'company_context_access_denied');
expectContext(fn() => $context->select(54, 'forged', $tokenA), 'company_context_invalid_csrf');
expectContext(fn() => $context->select(54, $csrfA, 'forged'), 'company_context_mismatch');
essentialCheck($context->select(54, $csrfA, $tokenA) === false, 'authorized switch chooses next request secondary');
essentialCheck($context->issuerId() === 41, 'selection never mutates in-flight scope');
$contextB = new CompanyContext($db, $p, $s, 8); essentialCheck($contextB->resolve() === 54, 'next request resolves B');
expectContext(fn() => $contextB->assertToken($tokenA), 'company_context_mismatch');
$tokenB = $contextB->token(); $contextB->assertToken($tokenB);
$forged = new CompanyContext($db, $p, new ContextSession(), 1);
expectContext(fn() => $forged->resolve(), 'company_context_selection_required');
essentialCheck($forged->resolve(true) === null, 'forged default and ordinary admin cannot imply primary/master');
$master = new CompanyContext($db, $p, new ContextSession(), 7);
essentialCheck($master->resolve() === 41 && count($master->available()) === 3, 'proven master has primary default and every current company');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=54 AND staff_id=8");
expectContext(fn() => (new CompanyContext($db, $p, $s, 8))->resolve(), 'company_context_membership_revoked');
expectContext(fn() => $contextB->assertToken($tokenB), 'company_context_membership_revoked');
$recovery = new CompanyContext($db, $p, $s, 8);
essentialCheck($recovery->resolve(true) === 41, 'GET recovery selects only validated default');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=54 AND staff_id=8");
$recovery->select(54, $recovery->csrf(), $recovery->token());
// Execute real App_Controller and real autoload.php, with CI I/O doubles only.
$db->queries = []; hooks()->trace = [];
$ci = contextEnvironment($f, $s); $controller = new Company_context();
essentialCheck(CompanyRequestScope::sealedFor($controller), 'isolated attestation is bound to the real controller object');
essentialCheck(!CompanyRequestScope::sealedFor(new stdClass()), 'HTTP-like/unrelated objects cannot attest isolated request');
essentialCheck($controller->load->autoload['libraries'] === [] && $controller->load->autoload['model'] === [], 'isolated real autoload has no App/email/gateways/models');
essentialCheck($controller->load->autoload['helper'] === ['url','form'], 'isolated real autoload does not load optional module hooks/helpers');
essentialCheck(CompanySettingsRuntime::issuerId() === 54 && CompanySettingsRuntime::staffId() === 8, 'settings runtime exists before any hydration');
essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'B never inherits global SMTP secret');
essentialCheck(!array_filter($db->queries, static fn($query) => str_contains($query[0], 'FROM ' . $GLOBALS['essentialPrefix'] . 'options')), 'secondary bootstrap never hydrates global options');
essentialCheck(hooks()->trace === [], 'secondary App_Controller never dispatches module/app/language hooks');
$html = (new ContextViewRenderer())->render('admin/includes/company_selector');
essentialCheck(str_contains($html, 'method="post"') && str_contains($html, 'name="_company_switch_csrf"'), 'actual selector HTML submits POST plus independent CSRF');
essentialCheck(str_contains($html, 'name="_company_context" value="' . CompanyRequestScope::token() . '"'), 'actual form helper emits server-side original context token');
essentialCheck(strpos($html, 'value="41"') < strpos($html, 'value="54"'), 'actual selector HTML shows primary first');
essentialCheck(!str_contains(form_open('https://foreign.invalid/endpoint'), '_company_context'), 'form helper never transmits context token cross origin');
essentialCheck(!str_contains(form_open('https://crm.example.test/admin/read', ['method'=>'get']), '_company_context'), 'GET form does not put a token into URL');
$controller->index(); essentialCheck($controller->load->views[0][0] === 'admin/companies/context', 'real selector controller renders isolated view');
// Native recovery feedback is delivered only by the existing attested document.
require_once APPPATH.'services/AdminDatabaseUpgrade.php';
$createdFeedbackVersionTable = !$db->table_exists($p.'migrations');
if($createdFeedbackVersionTable){$db->query('CREATE TABLE '.$p.'migrations(version integer NOT NULL)');$db->query('INSERT INTO '.$p.'migrations(version) VALUES(436)');}
$feedbackVersion=(string)$db->query('SELECT version FROM '.$p.'migrations LIMIT 1')->row()->version;
AdminDatabaseUpgrade::recordSuccess($s,8,$feedbackVersion);
$ci->input->ajax=true;$controller->index();$feedbackView=end($controller->load->views);essentialCheck($feedbackView[1]['native_database_upgrade_success']===false&&isset($s->data['admin_database_upgrade_success']),'real context XHR preserves pending feedback');
$ci->input->ajax=false;$ci->input->verb='HEAD';$controller->index();$feedbackView=end($controller->load->views);essentialCheck($feedbackView[1]['native_database_upgrade_success']===false&&isset($s->data['admin_database_upgrade_success']),'real context HEAD preserves pending feedback');
$ci->input->verb='GET';$db->failOn='SELECT version FROM `'.$p.'migrations`';expectStatus(fn()=>$controller->index(),503);essentialCheck(isset($s->data['admin_database_upgrade_success']),'native version read failure preserves receipt for retry');$db->failOn=null;
$ci=contextEnvironment($f,$s);$controller=new Company_context();
$controller->index();$feedbackView=end($controller->load->views);essentialCheck($feedbackView[1]['native_database_upgrade_success']===true&&!isset($s->data['admin_database_upgrade_success']),'real scoped GET consumes exact actor/version feedback');essentialCheck(CompanyRequestScope::context()->issuerId()===54,'feedback never changes the active secondary company');
$controller->index();$feedbackView=end($controller->load->views);essentialCheck($feedbackView[1]['native_database_upgrade_success']===false,'real context feedback is one-shot');
AdminDatabaseUpgrade::recordSuccess($s,7,$feedbackVersion);$controller->index();$feedbackView=end($controller->load->views);essentialCheck($feedbackView[1]['native_database_upgrade_success']===false,'real context cannot display another staff receipt');
if($createdFeedbackVersionTable){$db->query('DROP TABLE '.$p.'migrations');unset($db->data_cache['table_names']);}

$tokenB = CompanyRequestScope::token(); $csrfB = CompanyRequestScope::context()->csrf();
CompanyRequestScope::finish(); essentialCheck(!CompanySettingsRuntime::active(), 'request finalization clears settings runtime'); CompanyRequestScope::finish();
$ci = contextEnvironment($f, $s, 'invoices', 'invoice');
expectStatus(fn() => $ci->load->initialize(), 403);
essentialCheck(!in_array('autoload_returned', $ci->load->trace, true), 'secondary finance denied before hydration/config autoload returns');
essentialCheck(!CompanySettingsRuntime::active(), 'rejected request never leaves settings context');
// Compose the actual native Settings -> AdminController -> App_Controller chain.
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(54,8,'settings','view'),(54,8,'settings','edit')");
$ci = contextEnvironment($f, $s, 'settings', 'index'); hooks()->trace = [];
$settings = new Settings();
essentialCheck(CompanyRequestScope::sealedFor($settings), 'native Settings index secondary is isolated by server attestation');
essentialCheck($settings->load->autoload['libraries'] === [] && $settings->load->autoload['model'] === [], 'native secondary Settings has minimum autoload');
essentialCheck(hooks()->trace === [] && in_array('model:settings_model', $settings->load->trace, true), 'real AdminController and Settings_model bypass all global initialization hooks');
$settings->index(); $view = end($settings->load->views);
essentialCheck($view[0] === 'admin/settings/company_scoped' && $view[1]['company_id'] === 54, 'real Settings renders selected B identity');
$settingsToken = CompanyRequestScope::token();
$ci = contextEnvironment($f, $s, 'settings', 'index', 'POST'); $ci->input->group = 'email';
$_POST = ['_company_context' => $settingsToken, 'settings' => ['smtp_host' => 'b.example.invalid', 'smtp_port' => '587', 'smtp_encryption' => 'tls', 'smtp_email' => 'sender@example.invalid', 'smtp_password' => 'synthetic-private']]; $_FILES = [];
$settings = new Settings(); $settings->index();
essentialCheck(CompanySettingsRuntime::get('smtp_host') === 'b.example.invalid', 'composed HTTP bootstrap and real controller save only B SMTP');
essentialCheck($db->query("SELECT value FROM {$p}options WHERE name='smtp_password'")->row()->value === 'synthetic-test-only', 'B settings POST leaves primary SMTP unchanged');
$view = end($settings->load->views); $settingsHtml = (new ContextViewRenderer())->render($view[0], $view[1]);
essentialCheck(str_contains($settingsHtml, 'id="header"') && str_contains($settingsHtml, 'id="menu"') && str_contains($settingsHtml, 'id="wrapper"'), 'selected company settings retain CRM header sidebar and wrapper');
essentialCheck(str_contains($settingsHtml, 'bootstrap.min.css') && str_contains($settingsHtml, 'style.min.css'), 'scoped layout loads native public styles');
essentialCheck(!str_contains($settingsHtml, 'synthetic-private') && !str_contains($settingsHtml, 'synthetic-cipher:'), 'composed B view never discloses password/ciphertext');
$ci = contextEnvironment($f, $s, 'issuers', 'index');$companies=new Issuers();$companies->index(54);
$companyView=end($companies->load->views);
essentialCheck($companyView[0]==='admin/companies/registry','secondary company registry uses isolated native renderer');
essentialCheck($companyView[1]['can_manage'] && !$companyView[1]['can_create'],'company editor cannot create companies as implicit global administrator');
$companyHtml=(new ContextViewRenderer())->render($companyView[0],$companyView[1]);
essentialCheck(str_contains($companyHtml,'id="header"') && str_contains($companyHtml,'name="issuers_csrf"'),'company form retains layout and dedicated CSRF');
essentialCheck(!str_contains($companyHtml,'Forbidden synthetic'),'company catalog does not expose unauthorized company');
$dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML($companyHtml);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//form')->length===2 && $xpath->query('//form//form')->length===0,'company selector and edit form are independent balanced forms');
essentialCheck($xpath->query('//*[@id="wrapper"]//main//form')->length===1,'company edit form remains inside CRM content wrapper');
$escapedData=$companyView[1];$escapedData['selected']['legal_name']='Synthetic <img src=x onerror=alert(1)>';$escapedData['profile']['invoice_company_city']='" autofocus onfocus=alert(1)';
$escapedHtml=(new ContextViewRenderer())->render($companyView[0],$escapedData);
essentialCheck(!str_contains($escapedHtml,'<img src=x') && str_contains($escapedHtml,'&lt;img'),'company registry escapes stored company name');
$previous=libxml_use_internal_errors(true);$dom->loadHTML($escapedHtml);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//*[@onerror or @onfocus or @autofocus]')->length===0,'company registry field values cannot inject event attributes');

essentialCheck(!in_array('model:issuers_model',$companies->load->trace,true),'isolated registry does not hydrate legacy model hooks');
$companyToken=CompanyRequestScope::token();$registryCsrf=$s->userdata('issuers_csrf');
expectStatus(fn()=>$companies->index(55),403);
$ci=contextEnvironment($f,$s,'issuers','save','POST');
$_POST=['_company_context'=>$companyToken,'issuers_csrf'=>$registryCsrf,'action'=>'issuer','issuer_id'=>'54','legal_name'=>'Company B edited in native registry','tax_id'=>'11222333000181','active'=>'1','company_profile'=>['invoice_company_city'=>'City B']];
$companies=new Issuers();$companies->save();
essentialCheck(CompanySettingsRuntime::get('invoice_company_name')==='Company B edited in native registry','secondary registry write is immediately visible in Settings source');
essentialCheck(CompanySettingsRuntime::get('invoice_company_city')==='City B','secondary registry profile uses shared identity writer');
essentialCheck(($s->data['company_registry_notice']['type']??null)==='success','native company save produces explicit success feedback');
// Banco do Brasil is admitted only as an explicitly company-scoped native module route.
$ci=contextEnvironment($f,$s,'banco_brasil','index');$ci->router->module='banco_brasil';
CompanyRequestScope::beforeHydration($ci);
essentialCheck(CompanyRequestScope::isolated()&&CompanyRequestScope::context()->issuerId()===54&&CompanySettingsRuntime::issuerId()===54,'BB GET is sealed to selected company B before hydration');
$bbContextToken=CompanyRequestScope::token();
$bbImportForm=form_open_multipart('https://crm.example.test/admin/banco_brasil/import_return');
essentialCheck(str_contains($bbImportForm,'name="_company_context" value="'.$bbContextToken.'"'),'BB multipart POST form carries the selected company token');
CompanyRequestScope::finish();
$ci=contextEnvironment($f,$s,'banco_brasil','register_connection','POST');$ci->router->module='banco_brasil';$_POST=['_company_context'=>$bbContextToken];
CompanyRequestScope::beforeHydration($ci);
essentialCheck(CompanyRequestScope::isolated()&&CompanyRequestScope::context()->issuerId()===54&&!isset($_POST['_company_context']),'BB POST validates and strips company metadata before controller hydration');
CompanyRequestScope::finish();
$ci=contextEnvironment($f,$s,'banco_brasil','register_connection','POST');$ci->router->module='banco_brasil';$_POST=['_company_context'=>$tokenA];
expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),409);
$ci=contextEnvironment($f,$s,'banco_brasil','index');$ci->router->module='banco_brasil';$ci->router->directory='../../modules/banco_brasil/controllers/';
CompanyRequestScope::bootstrap($ci);
essentialCheck(CompanyRequestScope::isolated()&&CompanyRequestScope::context()->issuerId()===54,'actual MX module controller directory is sealed before autoload');
CompanyRequestScope::finish();
$ci=contextEnvironment($f,$s,'banco_brasil','index');$ci->router->module='banco_brasil';$ci->router->directory='../../modules/banco_brasil/controllers/public/';
CompanyRequestScope::beforeHydration($ci);
essentialCheck(!CompanyRequestScope::isolated(),'unreviewed module directory cannot opt into the isolated route set');
CompanyRequestScope::finish();
$ci=contextEnvironment($f,$s,'banco_brasil','send_to_bank','POST');$ci->router->module='banco_brasil';$_POST=['_company_context'=>$bbContextToken];
expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),403);
$ci = contextEnvironment($f, $s, 'settings', 'email_test'); expectStatus(fn() => new Settings(), 403);
$ci = contextEnvironment($f, $s, 'settings', 'index'); $ci->input->group = 'payment_gateways'; $settings = new Settings(); expectStatus(fn() => $settings->index(), 403);
$ci = contextEnvironment($f, $s, 'company_context', 'select', 'POST'); $_POST = ['company_id' => '41', '_company_context' => $tokenA, '_company_switch_csrf' => $csrfB, 'amount' => '9999'];
expectStatus(fn() => new Company_context(), 409);
essentialCheck($s->data[CompanyContext::SELECTION]['issuer_id'] === 54, 'stale A tab POST cannot switch/mutate B');
$ci = contextEnvironment($f, $s, 'company_context', 'select', 'POST'); $_POST = ['company_id' => '41', '_company_context' => $tokenB, '_company_switch_csrf' => $csrfB, 'return_url' => 'https://evil.invalid/invoices', 'amount' => '9999'];
$controller = new Company_context(); essentialCheck(!isset($_POST['_company_context']), 'scope metadata removed before legacy model POST reads');
$controller->select(); essentialCheck(end($GLOBALS['redirects']) === ['https://crm.example.test/admin/', 'location', 303], 'switch response is fixed safe GET redirect, ignoring original POST and return_url');
essentialCheck(!CompanySettingsRuntime::active(), 'switch finalizes old runtime without entering target in same request');
$ci = contextEnvironment($f, $s, 'invoices', 'index'); $ci->load->initialize();
essentialCheck(CompanySettingsRuntime::issuerId() === 41 && !CompanyRequestScope::isolated(), 'primary legacy request resolves before standard libraries');
essentialCheck(in_array('app', $ci->load->autoload['libraries'], true), 'primary preserves legacy autoload');
require APPPATH . 'libraries/App.php';
class ContextObservedApp extends App
{
    protected function init() { essentialCheck(CompanySettingsRuntime::issuerId() === 41, 'real App constructor enters hydration only with primary runtime resolved'); }
}
$ci->app = new ContextObservedApp();
$ref = new ReflectionClass(App::class); $ref->getProperty('options')->setValue($ci->app, ['smtp_password'=>'synthetic']);
$ci->app->add_quick_actions_link(['name'=>'synthetic']);
$ci->app_object_cache = new class { public int $flushes = 0; public function flush() { ++$this->flushes; } };
$GLOBALS['current_user'] = (object) ['permissions' => ['synthetic']]; $s->data['staff_permissions'] = ['synthetic'];
CompanyRequestScope::invalidateCaches($ci);
essentialCheck($ref->getProperty('options')->getValue($ci->app) === [] && $ref->getProperty('quick_actions')->getValue($ci->app) === [], 'real App cache reset clears options and permission-bearing quick actions');
essentialCheck($ci->app_object_cache->flushes === 1 && !isset($GLOBALS['current_user']) && !isset($s->data['staff_permissions']), 'switch clears global/object/session permission caches');
CompanyRequestScope::finish();
$ci = contextEnvironment($f, $s, 'cron' , 'index'); $ci->router->directory = ''; $beforeReads = $s->reads;
CompanyRequestScope::bootstrap($ci); essentialCheck($s->reads === $beforeReads && !CompanySettingsRuntime::active(), 'job/webhook public route does not read browser session');
$ci = contextEnvironment($f, $s, 'invoices', 'index'); $ci->input->cli = true; $beforeReads = $s->reads;
CompanyRequestScope::bootstrap($ci); essentialCheck($s->reads === $beforeReads, 'CLI ignores browser selection');
essentialCheck((int)$db->query("SELECT total FROM {$p}invoices WHERE id=42")->row()->total === 125000, 'all tests preserve financial rows');
CompanyRequestScope::finish();
// Actual loader decision, deliberately without modules_helper/app_modules hydration.
if(!class_exists('CI_Loader',false)){class CI_Loader {}}
if(!class_exists('CI',false)){class CI { public static $APP; }}
require_once APPPATH.'core/App_Loader.php';
$loader=(new ReflectionClass(App_Loader::class))->newInstanceWithoutConstructor();$prefixDecision=new ReflectionMethod(App_Loader::class,'canMyPrefixViewFile');
$ci=contextEnvironment($f,$s,'banco_brasil','index');$ci->router->module='banco_brasil';$ci->router->directory='../../modules/banco_brasil/controllers/';CompanyRequestScope::beforeHydration($ci);
essentialCheck($prefixDecision->invoke($loader,'banco_brasil',APP_MODULES_PATH.'banco_brasil/views/index.php')===false,'sealed BB renderer does not call unavailable module_supports/app_modules');
essentialCheck($prefixDecision->invoke($loader,null,APPPATH.'views/admin/companies/context.php')===false,'sealed core views also refuse unreviewed my_ overrides');
CompanyRequestScope::finish();
essentialCheck($prefixDecision->invoke($loader,null,APPPATH.'views/admin/includes/head.php')===true,'ordinary legacy core view override behavior is preserved');
echo 'Company context/bootstrap: ' . $GLOBALS['essentialChecks'] . ' checks passed (' . $db->driver() . ").\n";
