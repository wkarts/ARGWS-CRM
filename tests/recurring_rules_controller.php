<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line); return false; });
define('BASEPATH', dirname(__DIR__) . '/system/');
$checks = 0; $staff = 7; $viewAllowed = true;
function check($condition, $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function staff_cant($permission, $feature): bool { global $viewAllowed; return !$viewAllowed; }
function get_option($name): string { return '0'; }
function get_staff_user_id(): int { global $staff; return $staff; }
function access_denied($feature): void { throw new RuntimeException('denied', 403); }
function show_error($message, $code): void { throw new RuntimeException($message, $code); }
function set_alert($type, $message): void {}
function admin_url($path): string { return '/admin/' . $path; }
function redirect($url): void { throw new RuntimeException('redirect', 302); }
class TestSession
{
    public array $data = [];
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function set_userdata($key, $value): void { $this->data[$key] = $value; }
}
class TestInput
{
    public string $verb = 'get'; public array $data = [];
    public function method(): string { return $this->verb; }
    public function post($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
    public function get($key) { return null; }
}
class TestRules
{
    public int $writes = 0;
    public function create_from_catalogue($input): int { $this->writes++; return 1; }
    public function preview(...$args): array { $this->writes++; return []; }
    public function apply(...$args): array { $this->writes++; return []; }
}
class TestLoader
{
    public array $data = []; public int $modelLoads = 0;
    public function vars($data): void { $this->data = $data; }
    public function model($model): void { $this->modelLoads++; }
    public function view($view, $data): void {}
}
$session = new TestSession(); $input = new TestInput(); $rules = new TestRules(); $loader = new TestLoader();
class AdminController
{
    public $session; public $input; public $load; public $recurring_rules_model;
    public function __construct() { global $session, $input, $rules, $loader; $this->session = $session; $this->input = $input; $this->recurring_rules_model = $rules; $this->load = $loader; }
}
require dirname(__DIR__) . '/application/controllers/admin/Recurring_rules.php';
new Recurring_rules(); $token = $loader->data['recurring_rules_csrf'];
check((bool) preg_match('/^[a-f0-9]{64}$/D', $token), 'cryptographically generated session token');
new Recurring_rules(); check($loader->data['recurring_rules_csrf'] === $token, 'multiple tabs share valid session token');
$input->verb = 'post';
foreach (['create', 'preview', 'apply'] as $action) {
    foreach ([null, '', str_repeat('f', 64), [$token]] as $bad) {
        $input->data = ['recurring_rules_csrf' => $bad]; $before = $rules->writes; $loads = $loader->modelLoads;
        try { $controller = new Recurring_rules(); $controller->$action(); throw new LogicException('Expected CSRF rejection'); }
        catch (RuntimeException $e) { check($e->getCode() === 403 && $rules->writes === $before && $loads === $loader->modelLoads, 'invalid CSRF rejected before loading model or writing: ' . $action); }
    }
}
$input->data = ['recurring_rules_csrf' => $token, 'source' => 'rules', 'ids' => [1], 'action' => 'pause', 'token' => str_repeat('a', 64), 'confirm_future' => '1'];
foreach (['create', 'preview', 'apply'] as $action) {
    $before = $rules->writes; $controller = new Recurring_rules();
    try { $controller->$action(); } catch (RuntimeException $e) { if ($e->getCode() !== 302) throw $e; }
    check($rules->writes === $before + 1, 'valid CSRF reaches action: ' . $action);
}
$staff = 8;
try { new Recurring_rules(); throw new LogicException('Expected actor rejection'); }
catch (RuntimeException $e) { check($e->getCode() === 403, 'another actor cannot reuse prior session token'); }
$staff = 7; $session = new TestSession();
try { new Recurring_rules(); throw new LogicException('Expected session rejection'); }
catch (RuntimeException $e) { check($e->getCode() === 403, 'another session cannot reuse token'); }
foreach (['form', 'list', 'view', 'preview'] as $view) {
    $source = file_get_contents(dirname(__DIR__) . '/application/views/admin/recurring_rules/' . $view . '.php');
    check(str_contains($source, "form_hidden('recurring_rules_csrf', \$recurring_rules_csrf)"), 'POST form carries own token: ' . $view);
}
echo "Recurring rule controller: $checks checks passed (real controller with local session/input collaborators; global CSRF not used).\n";
