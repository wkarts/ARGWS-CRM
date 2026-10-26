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
    public function has_userdata($key): bool { return array_key_exists($key,$this->data); }
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
    public array $autoload = []; public array $trace = []; public array $views = []; public array $variables = [];
    public function database(): void { $this->trace[] = 'database'; }
    public function driver($name): void { $this->trace[] = 'driver:' . $name; }
    public function helper($name): void { $this->trace[] = 'helper:' . (is_array($name) ? implode(',', $name) : $name); }
    public function initialize(): void { include APPPATH . 'config/autoload.php'; $this->autoload = $autoload; $this->trace[] = 'autoload_returned'; }
    public function vars($variables): void { $this->variables = array_replace($this->variables,$variables); }
    public function library($name): void
    {
        $this->trace[] = 'library:' . $name;
        if (!in_array($name, ['encryption','user_agent'], true)) { throw new RuntimeException('unexpected library hydration'); }
        if ($name === 'user_agent') {
            $GLOBALS['CI']->agent = new class {
                public function browser(): string { return 'synthetic'; }
                public function is_mobile(): bool { return false; }
            };
        }
    }
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
require APPPATH . 'controllers/admin/Company_selection.php';
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
function is_admin($staffid = '') { return false; }
function init_head($aside = true) { echo '<!doctype html><html><head></head><body><div id="header"></div>'; }
function init_tail() { echo '<!-- synthetic CRM asset footer -->'; }
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
    catch (CompanyRequestDenied $error) { essentialCheck($error->getCode() === $status, 'HTTP status ' . $status . ' (received ' . $error->getCode() . ')'); }
    finally { CompanyRequestScope::finish(); }
}
function contextEnvironment($f, ContextSession $session, string $class = 'dashboard', string $method = 'index', string $verb = 'GET'): object
{
    CompanyRequestScope::finish(); $_POST = [];
    $ci = (object) ['db' => $f->db, 'session' => $session, 'router' => new ContextRouter(), 'input' => new ContextInput(), 'config' => new ContextConfig(), 'load' => new ContextLoader(), 'lang' => (object)['language'=>[]], 'encryption' => new class { public function encrypt($value) { return 'synthetic-cipher:' . base64_encode($value); } public function decrypt($value) { return str_starts_with((string) $value, 'synthetic-cipher:') ? base64_decode(substr($value, 17), true) : false; } }, 'output' => new class { public int $status=200; public function set_status_header($status) { $this->status = $status; } }];
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
$db->query("ALTER TABLE {$p}staff ADD COLUMN firstname VARCHAR(100) NOT NULL DEFAULT ''");
$db->query("ALTER TABLE {$p}staff ADD COLUMN lastname VARCHAR(100) NOT NULL DEFAULT ''");
$db->query("ALTER TABLE {$p}staff ADD COLUMN email VARCHAR(191) NOT NULL DEFAULT ''");
$db->query("ALTER TABLE {$p}staff ADD COLUMN profile_image VARCHAR(191) NULL");
$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Staff',email='synthetic@example.test'");
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
// Execute the one technical switch controller and real autoload.php, with CI I/O doubles only.
$db->queries = []; hooks()->trace = [];
$selectorContext = new CompanyContext($db, $p, $s, 8); $selectorContext->resolve();
$ci = contextEnvironment($f, $s, 'company_selection', 'select', 'POST');
$_POST = ['company_id'=>'54','_company_context'=>$selectorContext->token(),'_company_switch_csrf'=>$selectorContext->csrf()];
$controller = new Company_selection();
essentialCheck(CompanyRequestScope::sealedFor($controller), 'isolated attestation is bound to the real controller object');
essentialCheck(CompanyRequestScope::isolated()&&!CompanyRequestScope::nativeFor($controller), 'technical selector remains separate from company application routes');
essentialCheck(!in_array('library:user_agent',$controller->load->trace,true),'technical selector does not hydrate a presentation-only browser helper');
essentialCheck(!CompanyRequestScope::sealedFor(new stdClass()), 'HTTP-like/unrelated objects cannot attest isolated request');
essentialCheck($controller->load->autoload['libraries'] === [] && $controller->load->autoload['model'] === [], 'technical selection autoload has no App/email/gateways/models');
essentialCheck($controller->load->autoload['helper'] === ['url','form'], 'technical selection does not load optional module hooks/helpers');
essentialCheck(CompanySettingsRuntime::issuerId() === 54 && CompanySettingsRuntime::staffId() === 8, 'settings runtime exists before any hydration');
essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'B never inherits global SMTP secret');
essentialCheck(!array_filter($db->queries, static fn($query) => str_contains($query[0], 'FROM ' . $GLOBALS['essentialPrefix'] . 'options')), 'secondary bootstrap never hydrates global options');
essentialCheck(hooks()->trace === [], 'technical selection never dispatches module/app/language hooks');
$html = (new ContextViewRenderer())->render('admin/includes/company_selector');
essentialCheck(str_contains($html, 'method="post"') && str_contains($html, 'name="_company_switch_csrf"'), 'actual selector HTML submits POST plus independent CSRF');
essentialCheck(str_contains($html, 'name="_company_context" value="' . CompanyRequestScope::token() . '"'), 'actual form helper emits server-side original context token');
essentialCheck(strpos($html, 'value="41"') < strpos($html, 'value="54"'), 'actual selector HTML shows primary first');
essentialCheck(!str_contains(form_open('https://foreign.invalid/endpoint'), '_company_context'), 'form helper never transmits context token cross origin');
essentialCheck(!str_contains(form_open('https://crm.example.test/admin/read', ['method'=>'get']), '_company_context'), 'GET form does not put a token into URL');

$tokenB = CompanyRequestScope::token(); $csrfB = CompanyRequestScope::context()->csrf();
CompanyRequestScope::finish(); essentialCheck(!CompanySettingsRuntime::active(), 'request finalization clears settings runtime'); CompanyRequestScope::finish();
$ci = contextEnvironment($f, $s, 'company_context', 'index', 'GET');
expectStatus(fn() => CompanyRequestScope::beforeHydration($ci), 403);
essentialCheck(!file_exists(APPPATH . 'controllers/admin/Company_context.php'), 'parallel company_context controller is removed');
essentialCheck(!str_contains(file_get_contents(APPPATH . 'views/admin/companies/layout_head.php'), "'company_context'")
    && !str_contains(file_get_contents(APPPATH . 'views/admin/issuers/certificate_link.php'), "'company_context'"), 'active presentation links no longer target the retired company_context route');
$ci = contextEnvironment($f, $s, 'invoices', 'invoice');
expectStatus(fn() => $ci->load->initialize(), 403);
essentialCheck(!in_array('autoload_returned', $ci->load->trace, true), 'secondary finance denied before hydration/config autoload returns');
essentialCheck(!CompanySettingsRuntime::active(), 'rejected request never leaves settings context');
// Prove the existing Settings URL is explicitly attested before native hydration.
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(54,8,'settings','view'),(54,8,'settings','edit')");
$ci = contextEnvironment($f, $s, 'settings', 'index'); hooks()->trace = [];
CompanyRequestScope::beforeHydration($ci);
essentialCheck(CompanyRequestScope::nativeFor($ci) && CompanyRequestScope::sealedFor($ci), 'existing Settings index is native and sealed before controller hydration');
essentialCheck(CompanyRequestScope::nativeRouteAllowed($ci,'settings') && CompanyRequestScope::context()->issuerId()===54, 'native Settings route keeps the active company and uses its existing URL');
essentialCheck(CompanySettingsRuntime::active() && hooks()->trace===[], 'native Settings admission creates the scoped lease without global hooks');
CompanyRequestScope::finish();
$ci=contextEnvironment($f,$s,'settings','index','PUT');$_POST=['_company_context'=>$tokenB];expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),403);
essentialCheck(!CompanySettingsRuntime::active(),'unsupported Settings verb is denied before the legacy company form can hydrate');
$ci = contextEnvironment($f, $s, 'issuers', 'index');$companies=new Issuers();$companies->index(54);
$companyView=end($companies->load->views);
essentialCheck($companyView[0]==='admin/issuers/manage','secondary company registry uses the original CRM route and view');
essentialCheck($companyView[1]['can_manage'] && !$companyView[1]['can_create'],'company settings editor cannot create companies as implicit global administrator');
$companyHtml=(new ContextViewRenderer())->render($companyView[0],$companyView[1]);
essentialCheck(str_contains($companyHtml,'id="header"') && str_contains($companyHtml,'name="issuers_csrf"'),'company form retains layout and dedicated CSRF');
essentialCheck(!str_contains($companyHtml,'Forbidden synthetic'),'company catalog does not expose unauthorized company');
$dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML($companyHtml);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//form')->length===2 && $xpath->query('//form//form')->length===0,'company selector and status form are independent balanced forms');
essentialCheck($xpath->query('//*[@id="wrapper"]//form')->length===1,'company status form remains inside CRM content wrapper');
essentialCheck($xpath->query('//form[input[@name="action" and @value="issuer_status"] and input[@name="issuer_id" and @value="54"] and input[@name="issuers_csrf"]]')->length===1,'authorized secondary has dedicated CSRF-protected status action');
essentialCheck($xpath->query('//form[input[@name="action" and @value="issuer_status"]]/input[@name="_company_context" and @value="'.CompanyRequestScope::token().'"]')->length>=1,'status action retains original company context token');
essentialCheck($xpath->query('//form[input[@name="action" and @value="issuer_status"]]/input[@name="_native_company_csrf" and @value="'.CompanyRequestScope::nativeCsrfToken($ci).'"]')->length===1,'official registry POST form carries native context CSRF');
essentialCheck($xpath->query('//form/input[@name="issuer_id" and (@value="41" or @value="55")]')->length===0,'principal and unauthorized company have no status mutation control');
essentialCheck($xpath->query('//*[@data-company-identity or @data-new-company]')->length===0,'grid exposes neither parallel identity editor nor creation to non-master');
// The grid renders the authorized catalog projection, not the former selected/profile editor.
// Inject malicious stored values into the real rendering inputs so this remains an XSS regression test.
$escapedData=$companyView[1];
$escapedData['catalog'][0]['legal_name']='Synthetic <img src=x onerror=alert(1)>';
$escapedData['catalog'][0]['tax_id']='" autofocus onfocus=alert(1)';
$escapedHtml=(new ContextViewRenderer())->render($companyView[0],$escapedData);
essentialCheck(!str_contains($escapedHtml,'<img src=x') && str_contains($escapedHtml,'&lt;img'),'company registry escapes stored company name');
essentialCheck(str_contains($escapedHtml,'&quot; autofocus onfocus=alert(1)'),'company registry escapes stored tax identifier quotes');
$previous=libxml_use_internal_errors(true);$dom->loadHTML($escapedHtml);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//*[@onerror or @onfocus or @autofocus]')->length===0,'company registry field values cannot inject event attributes');

essentialCheck(!in_array('model:issuers_model',$companies->load->trace,true),'isolated registry does not hydrate legacy model hooks');
$companyToken=CompanyRequestScope::token();$registryCsrf=$s->userdata('issuers_csrf');
expectStatus(fn()=>$companies->index(55),403);
$ci=contextEnvironment($f,$s,'issuers','save','POST');
$nativeRegistryContext=new CompanyContext($db,$p,$s,8);$nativeRegistryContext->resolve();
$_POST=['_company_context'=>$companyToken,'_native_company_csrf'=>$nativeRegistryContext->nativeCsrf(),'issuers_csrf'=>$registryCsrf,'action'=>'issuer','issuer_id'=>'54','legal_name'=>'Company B edited in native registry','tax_id'=>'11222333000181','active'=>'1','company_profile'=>['invoice_company_city'=>'City B']];
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
// Group-level rejection uses the actual Settings controller fixture in
// tests/companies/native_settings_controller.php; this bootstrap fixture proves
// route admission and should not hydrate a second presentation shell.
$ci = contextEnvironment($f, $s, 'company_selection', 'select', 'POST'); $_POST = ['company_id' => '41', '_company_context' => $tokenA, '_company_switch_csrf' => $csrfB, 'amount' => '9999'];
expectStatus(fn() => new Company_selection(), 409);
essentialCheck($s->data[CompanyContext::SELECTION]['issuer_id'] === 54, 'stale A tab POST cannot switch/mutate B');
$ci = contextEnvironment($f, $s, 'company_selection', 'select', 'POST'); $_POST = ['company_id' => '41', '_company_context' => $tokenB, '_company_switch_csrf' => $csrfB, 'return_url' => 'https://evil.invalid/invoices', 'amount' => '9999'];
$controller = new Company_selection(); essentialCheck(!isset($_POST['_company_context']), 'native selection strips context metadata before its action reads input');
$controller->select(); essentialCheck(end($GLOBALS['redirects']) === ['https://crm.example.test/admin/', 'location', 303], 'native switch uses a fixed same-CRM GET redirect, ignoring original POST and return_url');
essentialCheck(!CompanySettingsRuntime::active(), 'switch finalizes old runtime without entering target in same request');
$nativeA=new CompanyContext($db,$p,$s,8);essentialCheck($nativeA->resolve()===41,'native switch is active only on the redirected request');
$ci=contextEnvironment($f,$s,'company_selection','select','POST');$_POST=['company_id'=>'54','_company_context'=>$nativeA->token(),'_company_switch_csrf'=>'forged'];
expectStatus(fn()=>(new Company_selection())->select(),403);
$ci=contextEnvironment($f,$s,'company_selection','select','POST');$_POST=['company_id'=>'55','_company_context'=>$nativeA->token(),'_company_switch_csrf'=>$nativeA->csrf()];
expectStatus(fn()=>(new Company_selection())->select(),403);
$ci=contextEnvironment($f,$s,'company_selection','select','POST');$_POST=['company_id'=>'54','_company_context'=>$nativeA->token(),'_company_switch_csrf'=>$nativeA->csrf()];
(new Company_selection())->select();essentialCheck(end($GLOBALS['redirects'])===['https://crm.example.test/admin/','location',303],'repeated native switch returns to the same dashboard after a safe 303');
essentialCheck((new CompanyContext($db,$p,$s,8))->resolve()===54,'repeated native switch changes only the next request company');
$returnToPrimary=new CompanyContext($db,$p,$s,8);$returnToPrimary->resolve();$ci=contextEnvironment($f,$s,'company_selection','select','POST');$_POST=['company_id'=>'41','_company_context'=>$returnToPrimary->token(),'_company_switch_csrf'=>$returnToPrimary->csrf()];(new Company_selection())->select();
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
essentialCheck($prefixDecision->invoke($loader,null,APPPATH.'views/admin/dashboard/dashboard.php')===false,'sealed native dashboard also refuses unreviewed my_ overrides');
CompanyRequestScope::finish();
essentialCheck($prefixDecision->invoke($loader,null,APPPATH.'views/admin/includes/head.php')===true,'ordinary legacy core view override behavior is preserved');
echo 'Company context/bootstrap: ' . $GLOBALS['essentialChecks'] . ' checks passed (' . $db->driver() . ").\n";
