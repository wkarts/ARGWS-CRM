<?php

declare(strict_types=1);

// Execute the real controllers with inert dependencies. No provider, credential,
// production database or HTTP server is used by this authorization regression.
define('BASEPATH', __DIR__);
define('WEBHOOKS_MODULE', 'webhooks');

#[AllowDynamicProperties]
class AdminController {}

$permissions = [];
$administrator = false;
$denials = 0;
$effects = [];
$assertions = 0;

function checkIntegration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
}
function is_admin(): bool { return $GLOBALS['administrator']; }
function staff_can($capability, $feature): bool
{
    return is_admin() || in_array($capability, $GLOBALS['permissions'][$feature] ?? [], true);
}
function access_denied($feature): void { $GLOBALS['denials']++; }
function _l($key, ...$args): string { return (string)$key; }
function admin_url($path = ''): string { return '/admin/' . $path; }
function module_views_path($module, $view): string { return $module . '/' . $view; }
function set_alert(...$args): void { $GLOBALS['effects'][] = ['alert', $args]; }
final class IntegrationRedirect extends RuntimeException {}
function redirect($url): void { throw new IntegrationRedirect($url); }
function validate_request_url($url) { return $url === 'https://example.invalid/event' ? $url : false; }
function remove_blank_value($rows, $key) { return $rows; }

final class IntegrationInput
{
    public bool $ajax = false;
    public array $posted = [];
    public function is_ajax_request(): bool { return $this->ajax; }
    public function post() { return $this->posted; }
}
final class IntegrationLoad
{
    public function view(...$args): void { $GLOBALS['effects'][] = ['view', $args]; }
}
final class IntegrationApp
{
    public function get_table_data($path): void { $GLOBALS['effects'][] = ['table', $path]; }
}
final class IntegrationModel
{
    public function __call($method, $args)
    {
        $GLOBALS['effects'][] = [$method, $args];
        if ($method === 'get_user') { return [['id' => 1, 'token' => 'synthetic-fixture-token']]; }
        if ($method === 'get') { return (object)['id' => 7, 'name' => 'Fixture webhook']; }
        if ($method === 'get_log_info') { return (object)['id' => 3]; }
        return true;
    }
}

require __DIR__ . '/../modules/api/controllers/Api.php';
require __DIR__ . '/../modules/webhooks/controllers/Webhooks.php';

function integrationController(string $class)
{
    $controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $controller->input = new IntegrationInput();
    $controller->load = new IntegrationLoad();
    $controller->app = new IntegrationApp();
    $controller->api_model = new IntegrationModel();
    $controller->webhooks_model = new IntegrationModel();
    return $controller;
}
function integrationInvoke($controller, string $method, array $arguments = []): void
{
    ob_start();
    try { $controller->$method(...$arguments); }
    catch (IntegrationRedirect $expected) {}
    finally { ob_end_clean(); }
}
function integrationDenied($controller, string $method, array $arguments = []): void
{
    $GLOBALS['effects'] = [];
    $before = $GLOBALS['denials'];
    integrationInvoke($controller, $method, $arguments);
    checkIntegration($GLOBALS['denials'] === $before + 1, $method . ' denies the missing capability');
    checkIntegration($GLOBALS['effects'] === [], $method . ' stops before any model, table, view, alert or redirect');
}

$api = integrationController(Api::class);
integrationDenied($api, 'api_management');
$administrator = true;
$effects = [];
integrationInvoke($api, 'api_management');
checkIntegration(array_column($effects, 0) === ['get_user', 'view'], 'authorized API admin retains credential listing and original view');
$administrator = false;

$webhooks = integrationController(Webhooks::class);
$actions = [
    ['index', []], ['logs', []], ['get_webhook_log_info', [3]],
    ['webhooks_cron_job_queue_table', []], ['webhook', []], ['webhook', [7]],
    ['change_status_hook', [7, 0]], ['change_debug_status_hook', [7, 1]],
    ['delete_webhook', [7]], ['clear_webhook_log', []],
    ['copy_webhook', [7]], ['delete_scheduled_call', [9]],
];
foreach ($actions as [$method, $args]) { integrationDenied($webhooks, $method, $args); }

// Preserve the two historical feature spellings without inheriting any other
// capability or translating the persisted grants to a different namespace.
foreach (['WEBHOOKS', 'webhooks'] as $feature) {
    $permissions = [$feature => ['view']];
    $webhooks->input->ajax = true;
    $effects = [];
    integrationInvoke($webhooks, 'index');
    checkIntegration(array_column($effects, 0) === ['table', 'view'], $feature . ' view permits the existing list/table');
    foreach (['change_status_hook', 'change_debug_status_hook', 'delete_webhook', 'delete_scheduled_call', 'copy_webhook', 'webhook'] as $method) {
        integrationDenied($webhooks, $method, $method === 'webhook' ? [] : ($method === 'change_status_hook' || $method === 'change_debug_status_hook' ? [7, 1] : [7]));
    }
    integrationDenied($webhooks, 'clear_webhook_log');

    $permissions = [$feature => ['edit']];
    $effects = [];
    integrationInvoke($webhooks, 'change_status_hook', [7, 0]);
    checkIntegration($effects[0] === ['change_webhook_status', [['active' => 0], ['id' => 7]]], $feature . ' edit preserves the existing mutation payload');
    integrationDenied($webhooks, 'delete_webhook', [7]);

    $permissions = [$feature => ['delete']];
    $effects = [];
    integrationInvoke($webhooks, 'delete_scheduled_call', [9]);
    checkIntegration($effects[0] === ['delete_scheduled_call', [['id' => 9]]], $feature . ' delete retains scheduled-call deletion');
    integrationDenied($webhooks, 'index');

    $permissions = [$feature => ['create']];
    $webhooks->input->posted = [
        'webhook_name' => 'Fixture', 'request_url' => 'https://example.invalid/event',
        'request_method' => 'POST', 'request_format' => 'json', 'webhook_for' => 'invoice',
        'webhook_action' => ['add'], 'header' => [], 'body' => [],
    ];
    $effects = [];
    integrationInvoke($webhooks, 'webhook');
    checkIntegration($effects[0][0] === 'add' && $effects[0][1][0]['name'] === 'Fixture', $feature . ' create keeps the original creation flow');
    integrationDenied($webhooks, 'webhook', [7]);
    integrationDenied($webhooks, 'copy_webhook', [7]);
    $permissions[$feature][] = 'view';
    $effects = [];
    integrationInvoke($webhooks, 'copy_webhook', [7]);
    checkIntegration(array_slice(array_column($effects, 0), 0, 2) === ['get', 'copy_webhook'], $feature . ' copy requires read and create before touching source or destination');
    $webhooks->input->posted = [];
}

echo 'Integration admin authorization: ' . $assertions . " checks passed; real controllers, no external providers.\n";
