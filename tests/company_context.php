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
require APPPATH . 'controllers/admin/Company_context.php';
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
function base_