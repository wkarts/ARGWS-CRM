<?php
/** Real denial boundary: revoked OAuth callbacks scrub their query without dispatch. */
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
require APPPATH . 'services/CompanyRequestScope.php';
final class CallbackFixtureExit extends RuntimeException
{
    public function __construct(public string $destination, public int $status) { parent::__construct('fixture response'); }
}
function redirect($url, $method, $status) { throw new CallbackFixtureExit($url, $status); }
function show_error($message, $status) { throw new CallbackFixtureExit($message, $status); }
$deny = new ReflectionMethod(CompanyRequestScope::class, 'deny');
$checks = 0;
function rejectedCallback(string $action, string $verb, array $query, bool $scrub, string $module = '', string $directory = 'admin/'): void
{
    global $deny, $checks;
    $ci = (object) [
        'router' => new class($action, $module, $directory) {
            public function __construct(private string $action, private string $module, private string $directory) {}
            public function fetch_class() { return 'settings'; }
            public function fetch_method() { return $this->action; }
            public function fetch_module() { return $this->module; }
            public function fetch_directory() { return $this->directory; }
        },
        'input' => new class($verb, $query) {
            public function __construct(private string $verb, private array $query) {}
            public function method($upper) { return $this->verb; }
            public function get($key, $filter) { return $this->query[$key] ?? null; }
        },
        'config' => new class { public function site_url($path) { return 'https://fixture.invalid/' . $path; } },
        'load' => new class {
            public array $helpers = [];
            public function helper($name) { $this->helpers[] = $name; }
        },
        'output' => new class {
            public array $headers = [];
            public function set_header($header) { $this->headers[] = $header; return $this; }
        },
    ];
    try { $deny->invoke(null, $ci, 'company_context_unavailable', 403); throw new RuntimeException('denial returned'); }
    catch (CallbackFixtureExit $response) {
        if ($response->status !== ($scrub ? 303 : 403) || str_contains($response->destination, 'private-protocol-value')) {
            throw new RuntimeException('denial response did not preserve status/query contract');
        }
        if ($scrub && ($response->destination !== 'https://fixture.invalid/admin/settings/' . $action
            || $ci->load->helpers !== ['url'] || $ci->output->headers !== ['Cache-Control: no-store', 'Referrer-Policy: no-referrer'])) {
            throw new RuntimeException('callback must redirect only to its clean trusted endpoint');
        }
        if (!$scrub && ($ci->load->helpers !== [] || $ci->output->headers !== [])) {
            throw new RuntimeException('unrelated denial changed');
        }
        ++$checks;
    }
}
foreach (['google_mail_oauth', 'microsoft_mail_oauth'] as $provider) {
    foreach (['GET', 'HEAD'] as $verb) {
        foreach (['state', 'code', 'error'] as $key) { rejectedCallback($provider, $verb, [$key => 'private-protocol-value'], true); }
    }
    rejectedCallback($provider, 'POST', ['code' => 'private-protocol-value'], false);
    rejectedCallback($provider, 'GET', [], false);
    rejectedCallback($provider, 'GET', ['code' => 'private-protocol-value'], false, 'foreign');
    rejectedCallback($provider, 'GET', ['code' => 'private-protocol-value'], false, '', 'foreign/');
}
rejectedCallback('index', 'GET', ['code' => 'private-protocol-value'], false);
echo "Native mail denial: {$checks} clean-callback/unrelated-denial scenarios passed; no credential exchange.\n";
