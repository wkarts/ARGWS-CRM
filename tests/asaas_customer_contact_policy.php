<?php

declare(strict_types=1);

// Real gateway, adapter, provider and vendored SDK/Guzzle. Every HTTP handler is
// replaced before use; only synthetic data and in-memory storage are exercised.
// Existing PHP 8.2+ utf8_encode deprecations are outside this contact policy.
set_error_handler(static function ($level, $message, $file, $line) {
    if ($level === E_DEPRECATED) return true;
    throw new ErrorException($message, 0, $level, $file, $line);
});
define('BASEPATH', __DIR__);
define('FCPATH', dirname(__DIR__) . '/');
require FCPATH . 'application/libraries/gateways/App_gateway.php';
require FCPATH . 'modules/asaas/libraries/Asaas_gateway.php';
require FCPATH . 'modules/asaas/vendor/autoload.php';

use Asaas\AsaasAdapter;
use Asaas\EnvironmentSettings;
use Asaas\Sdk\Http\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

final class ContactResult
{
    public function __construct(private array $rows) {}
    public function row_array() { return $this->rows[0] ?? null; }
    public function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : null; }
    public function result_array() { return $this->rows; }
    public function num_rows() { return count($this->rows); }
}
final class ContactDb
{
    public bool $db_debug = true;
    public array $tables = [];
    private array $where = [];
    private string $from = '';
    private int $affected = 0;
    public function table_exists($table) { return isset($this->tables[$table]); }
    public function field_exists($field, $table) { return true; }
    public function select($columns) { return $this; }
    public function from($table) { $this->from = $table; return $this; }
    public function limit($n) { return $this; }
    public function join(...$args) { return $this; }
    public function where($key, $value = null, ...$args) { $this->where[$key] = $value; return $this; }
    public function get_where($table, $where) { foreach ($where as $key => $value) $this->where($key, $value); return $this->get($table); }
    private function matches(array $row): bool {
        foreach ($this->where as $key => $value) {
            if ($key === 'remote_id IS NULL') { if (($row['remote_id'] ?? null) !== null) return false; }
            elseif (($row[preg_replace('/^c\./', '', $key)] ?? null) != $value) return false;
        }
        return true;
    }
    public function get($table = null) {
        $table = preg_replace('/ c$/', '', $table ?? $this->from);
        $rows = array_values(array_filter($this->tables[$table] ?? [], fn($row) => $this->matches($row)));
        $this->where = [];
        return new ContactResult($rows);
    }
    public function insert($table, $row) { $row['id'] ??= count($this->tables[$table] ?? []) + 1; $this->tables[$table][] = $row; $this->affected = 1; return true; }
    public function replace($table, $row) { return $this->insert($table, $row); }
    public function update($table, $data) {
        $this->affected = 0;
        foreach ($this->tables[$table] as &$row) if ($this->matches($row)) { $row = array_replace($row, $data); $this->affected++; }
        unset($row); $this->where = []; return true;
    }
    public function affected_rows() { return $this->affected; }
    public function error() { return ['message' => 'SYNTHETIC_DATABASE_SECRET']; }
    public function query($sql, $args = []) {
        if ($sql === 'SELECT installed_version FROM tblmodules WHERE module_name=?') return $this->get_where('tblmodules', ['module_name' => $args[0]]);
        if ($sql === 'SHOW COLUMNS FROM `tblasaas_logs`') return new ContactResult(array_map(fn($field) => ['Field' => $field], ['level', 'message', 'context_json', 'correlation_id', 'created_at']));
        throw new RuntimeException('Unexpected synthetic database query');
    }
}
$ci = (object) [];
$options = [];
$logs = [];
$checks = 0;
function &get_instance() { global $ci; return $ci; }
function get_option($key) { global $options; return $options[$key] ?? ''; }
function db_prefix() { return 'tbl'; }
function hooks() { static $hooks; return $hooks ??= new class { public function add_action(...$args) {} }; }
function log_message(...$args) { $GLOBALS['logs'][] = $args; }
function log_activity(...$args) { $GLOBALS['logs'][] = $args; }
function get_staff_user_id() { return 1; }
function format_invoice_number($id) { return 'INV-000001'; }
function get_invoice_total_left_to_pay($id, $total) { return (float) $total; }
function site_url($path) { return 'https://crm.invalid/' . $path; }
function redirect($url) { $GLOBALS['redirects'][] = $url; }
function set_alert(...$args) { $GLOBALS['alerts'][] = $args; }
function get_currency($id) { return (object) ['name' => 'BRL']; }
function assertContact($ok, string $message): void { if (!$ok) throw new RuntimeException($message); $GLOBALS['checks']++; }
function profile(string $environment, string $field, string $value): void {
    global $ci;
    $name = EnvironmentSettings::option($field, $environment);
    foreach ($ci->db->tables['tbloptions'] as &$row) if ($row['name'] === $name) $row['value'] = $value;
}
final class ContactTransportProvider extends AsaasSdkProvider
{
    public MockHandler $transport;
    public array $history = [];
    public function __construct(App_gateway $gateway) { parent::__construct($gateway); $this->transport = new MockHandler(); }
    public function intercept(Client $client): Client {
        $guzzle = (new ReflectionProperty(Client::class, 'client'))->getValue($client);
        $stack = $guzzle->getConfig('handler');
        // Keep the SDK's middleware (including its actual retry semantics), but
        // replace the socket transport. An exhausted queue cannot reach a host.
        $stack->setHandler($this->transport);
        $stack->push(Middleware::history($this->history));
        // Do not sleep on synthetic retries; retain the production retry decider.
        $stack->push(static fn($next) => static function ($request, $options) use ($next) { unset($options['delay']); return $next($request, $options); });
        return $client;
    }
    public function client(?int $companyId = null): Client { return $this->intercept(parent::client($companyId)); }
    public function clientForEnvironment(string $environment, ?int $issuerId = null): Client { return $this->intercept(parent::clientForEnvironment($environment, $issuerId)); }
    public function responses(array ...$values): void { foreach ($values as $value) $this->transport->append(new Response(200, ['Content-Type' => 'application/json'], json_encode($value))); }
}
function contactFixture(string $environment, string $email = '0', string $mobile = '0', string $notifications = '1'): array {
    global $ci, $options, $logs;
    $options = ['paymentmethod_asaas_profile_schema' => '1', 'paymentmethod_asaas_sandbox' => $environment === 'sandbox' ? '1' : '0', 'paymentmethod_asaas_api_key' => 'SYNTHETIC_PRODUCTION_KEY', 'paymentmethod_asaas_api_key_sandbox' => 'SYNTHETIC_SANDBOX_KEY'];
    $logs = []; $ci->db = new ContactDb(); $ci->load = new class { public function library(...$args) {} };
    $ci->encryption = new class { public function decrypt($value) { return $value; } };
    foreach (['options', 'asaas_customers_map', 'asaas_payment_origins', 'asaas_payment_origin_events', 'asaas_payments_map', 'invoicepaymentrecords', 'asaas_logs'] as $table) $ci->db->tables['tbl' . $table] = [];
    $ci->db->tables['tblmodules'] = [['module_name' => 'asaas', 'installed_version' => '1.5.5']];
    $ci->db->tables['tblcurrencies'] = [['id' => 1, 'name' => 'BRL']];
    $ci->db->tables['tblclients'] = [['userid' => 10, 'company' => 'Synthetic Customer', 'vat' => '11144477735', 'email' => 'synthetic-contact@example.invalid', 'phonenumber' => '11999990001', 'mobilePhone' => '11999990002', 'zip' => '01001000', 'address' => 'Synthetic Street', 'numero' => '1']];
    $ci->db->tables['tblinvoices'] = [['id' => 1, 'hash' => 'synthetic_invoice_hash', 'prefix' => 'INV-', 'number' => 1, 'total' => '100.00', 'subtotal' => '100.00', 'adjustment' => '0.00', 'duedate' => '2026-11-01', 'status' => 1, 'issuer_id' => null, 'clientid' => 10, 'currency' => 1, 'adminnote' => '', 'allowed_payment_modes' => serialize(['asaas'])]];
    foreach (EnvironmentSettings::ENVIRONMENTS as $env) foreach (EnvironmentSettings::DEFAULTS as $field => $value) $ci->db->tables['tbloptions'][] = ['name' => EnvironmentSettings::option($field, $env), 'value' => $field === 'active' ? '1' : $value];
    profile($environment, 'send_customer_email', $email); profile($environment, 'send_customer_mobile', $mobile); profile($environment, 'disable_charge_notification', $notifications);
    profile($environment === 'sandbox' ? 'production' : 'sandbox', 'send_customer_email', $email === '1' ? '0' : '1');
    profile($environment === 'sandbox' ? 'production' : 'sandbox', 'send_customer_mobile', $mobile === '1' ? '0' : '1');
    $gateway = new Asaas_gateway(); $provider = new ContactTransportProvider($gateway); $adapter = new AsaasAdapter($provider, $gateway);
    (new ReflectionProperty(Asaas_gateway::class, 'provider'))->setValue($gateway, $provider);
    (new ReflectionProperty(Asaas_gateway::class, 'adapter'))->setValue($gateway, $adapter);
    $ci->asaas_gateway = $gateway;
    return [$gateway, $adapter, $provider, $ci->db];
}
function sentBody(array $entry): array { return json_decode((string) $entry['request']->getBody(), true) ?? []; }
function sentQuery(array $entry): array { parse_str($entry['request']->getUri()->getQuery(), $query); return $query; }
function remoteCustomer(): array { return ['id' => 'cus_synthetic', 'cpfCnpj' => '11144477735']; }
function remotePayment(): array { return ['id' => 'pay_synthetic', 'customer' => 'cus_synthetic', 'externalReference' => 'synthetic_invoice_hash', 'value' => 100, 'status' => 'PENDING', 'dueDate' => '2026-11-01']; }
function assertFields(array $values, string $email, string $mobile, string $context): void {
    foreach (['email', 'additionalEmails'] as $field) assertContact(array_key_exists($field, $values) === ($email === '1'), $context . ': e-mail flag controls ' . $field);
    foreach (['phone', 'mobilePhone'] as $field) assertContact(array_key_exists($field, $values) === ($mobile === '1'), $context . ': mobile flag controls ' . $field);
    foreach ($GLOBALS['contacts'] as $field => $expected) if (array_key_exists($field, $values)) assertContact($values[$field] === $expected, $context . ': enabled contact value is unchanged');
}
if (defined('ASAAS_CONTACT_FIXTURES_ONLY')) return;

$contacts = ['email' => 'synthetic-contact@example.invalid', 'additionalEmails' => 'synthetic-extra@example.invalid', 'phone' => '11999990001', 'mobilePhone' => '11999990002'];
foreach (EnvironmentSettings::ENVIRONMENTS as $environment) foreach (['0', '1'] as $email) foreach (['0', '1'] as $mobile) foreach (['0', '1'] as $notifications) {
    [$gateway, $adapter, $provider, $db] = contactFixture($environment, $email, $mobile, $notifications);
    foreach (['adapter_create', 'adapter_update', 'legacy_create', 'gateway_request', 'adapter_request', 'structured_create', 'structured_update', 'adapter_list', 'url_query', 'structured_query'] as $entrypoint) {
        $blockedLookup = in_array($entrypoint, ['adapter_list', 'url_query', 'structured_query'], true) && ($email === '0' || $mobile === '0');
        $before = count($provider->history);
        if (!$blockedLookup) $provider->responses(remoteCustomer());
        $payload = $contacts + ['name' => 'Synthetic Customer', 'notificationDisabled' => $notifications !== '1'];
        $blocked = false;
        try { switch ($entrypoint) {
            case 'adapter_create': $adapter->createCustomer($payload); break;
            case 'adapter_update': $adapter->updateCustomer('cus_synthetic', $payload); break;
            case 'legacy_create': $gateway->create_customer('ignored', 'ignored', json_encode($payload)); break;
            case 'gateway_request': $gateway->request('POST', '/v3/customers', [], [], $payload); break;
            case 'adapter_request': $adapter->request('PATCH', 'customers/cus_synthetic', [], [], $payload); break;
            case 'structured_create': $adapter->requestStructured('POST', 'v3/customers/', [], [], $payload); break;
            case 'structured_update': $adapter->requestStructured('PUT', '/customers/cus_synthetic', [], [], $payload); break;
            case 'adapter_list': $adapter->listCustomers($contacts + ['limit' => 50]); break;
            case 'url_query': $adapter->request('GET', '/v3/customers?' . http_build_query($contacts), ['limit' => 50]); break;
            case 'structured_query': $adapter->requestStructured('GET', '/customers?' . http_build_query($contacts), ['limit' => 50]); break;
        }
        } catch (DomainException $error) {
            if ($error->getMessage() !== 'asaas_customer_contact_filter_disabled') throw $error;
            $blocked = true;
        }
        if ($blockedLookup) {
            assertContact($blocked && count($provider->history) === $before, 'disabled contact lookup fails closed without broadening a customer match');
            continue;
        }
        assertContact(!$blocked, 'enabled lookup or customer write proceeds');
        $last = end($provider->history); $body = sentBody($last); $query = sentQuery($last);
        assertFields($last['request']->getMethod() === 'GET' ? $query : $body, $email, $mobile, $environment . '/' . $entrypoint);
        if ($last['request']->getMethod() !== 'GET') assertContact($body['notificationDisabled'] === ($notifications === '1'), 'customer-level notification boolean follows profile');
        assertContact($last['request']->getUri()->getHost() === ($environment === 'sandbox' ? 'api-sandbox.asaas.com' : 'api.asaas.com'), 'actual SDK URI uses selected environment');
        assertContact($last['request']->getHeaderLine('access_token') === ($environment === 'sandbox' ? 'SYNTHETIC_SANDBOX_KEY' : 'SYNTHETIC_PRODUCTION_KEY'), 'actual SDK credentials remain in selected environment');
    }
    // Updating unrelated fields must not flip an existing customer's notifications.
    $provider->responses(remoteCustomer()); $adapter->updateCustomer('cus_synthetic', ['name' => 'Renamed Synthetic']);
    assertContact(!array_key_exists('notificationDisabled', sentBody(end($provider->history))), 'unrelated update never injects remote notification change');
    $before = count($provider->history); profile($environment, 'send_customer_email', $email === '1' ? '0' : '1'); profile($environment, 'disable_charge_notification', $notifications === '1' ? '0' : '1');
    assertContact(count($provider->history) === $before, 'preference toggle has no remote side effects');
}

// Public raw clients and generated SDK services cannot bypass the same policy.
foreach (EnvironmentSettings::ENVIRONMENTS as $environment) foreach (['0', '1'] as $email) foreach (['0', '1'] as $mobile) foreach (['gateway_client', 'environment_client', 'sdk_service'] as $entrypoint) {
    [$gateway, $adapter, $provider] = contactFixture($environment, $email, $mobile, $mobile);
    if ($entrypoint === 'sdk_service') {
        $sdk = $provider->sdk();
        $provider->intercept((new ReflectionProperty($sdk, 'client'))->getValue($sdk));
    } else $client = $entrypoint === 'gateway_client' ? $gateway->client() : $provider->clientForEnvironment($environment);
    foreach (['POST', 'PUT'] as $method) {
        $provider->responses(remoteCustomer()); $payload = $contacts + ['name' => 'Synthetic Customer', 'notificationDisabled' => $mobile !== '1'];
        if ($entrypoint === 'sdk_service') {
            if ($method === 'POST') $sdk->customer->createNewCustomer([], [], [], $payload);
            else $sdk->customer->updateExistingCustomer(['id' => 'cus_synthetic'], [], [], $payload);
        } else $client->request($method, 'v3/customers' . ($method === 'PUT' ? '/cus_synthetic' : ''), [], [], $payload);
        $entry = end($provider->history); $body = sentBody($entry);
        assertFields($body, $email, $mobile, $entrypoint . '/' . $method);
        assertContact($body['notificationDisabled'] === ($mobile === '1'), 'raw transport enforces notification preference');
        assertContact((int) $entry['request']->getHeaderLine('Content-Length') === strlen((string) $entry['request']->getBody()), 'rewritten JSON has correct HTTP body length');
    }
    $before = count($provider->history); $blocked = false;
    if ($email === '1' && $mobile === '1') $provider->responses(['data' => []]);
    try {
        if ($entrypoint === 'sdk_service') $sdk->customer->listCustomers([], $contacts);
        else $client->request('GET', 'v3/customers', $contacts);
    } catch (Asaas\Sdk\Exception\TransportException $error) { $blocked = $error->getPrevious() instanceof DomainException; }
    assertContact($blocked === ($email === '0' || $mobile === '0'), 'raw customer lookup blocks disabled contact filters');
    assertContact(count($provider->history) === $before + ($blocked ? 0 : 1), 'raw blocked lookup never reaches transport or retries');
}

// Every customer-producing entrypoint is exercised with real SDK encoded bodies,
// including the three legacy charge flows and the hosted-card compatibility path.
foreach (EnvironmentSettings::ENVIRONMENTS as $environment) foreach (['0', '1'] as $flag) foreach (['ensure_adapter', 'ensure_gateway', 'ensure_legacy', 'ensure_deprecated', 'process_payment', 'charge_billet', 'charge_pix', 'charge_credit_card'] as $entrypoint) {
    [$gateway, $adapter, $provider, $db] = contactFixture($environment, $flag, $flag, $flag);
    profile($environment, 'debug', '1'); profile($environment, 'file_log_enabled', '1');
    $client = (object) $db->tables['tblclients'][0]; $invoice = (object) $db->tables['tblinvoices'][0]; $invoice->client = $client;
    $empty = ['data' => [], 'totalCount' => 0];
    if ($entrypoint === 'process_payment') $provider->responses($empty);
    $provider->responses($empty);
    if (in_array($entrypoint, ['ensure_legacy', 'ensure_deprecated', 'charge_credit_card'], true) && $flag === '1') $provider->responses($empty);
    $provider->responses(remoteCustomer());
    if (in_array($entrypoint, ['process_payment', 'charge_billet', 'charge_pix', 'charge_credit_card'], true)) $provider->responses(['data' => [['id' => 'wallet_synthetic']], 'hasMore' => false], remoteCustomer(), remotePayment() + ['billingType' => 'CREDIT_CARD', 'invoiceUrl' => 'https://sandbox.asaas.com/i/synthetic']);
    ob_start();
    switch ($entrypoint) {
        case 'ensure_adapter': $result = $adapter->ensureCustomerFromClient($client); break;
        case 'ensure_gateway': $result = $gateway->ensure_customer_from_client($client); break;
        case 'ensure_legacy': $result = $gateway->ensure_customer_for_client(10, $client); break;
        case 'ensure_deprecated': $result = $gateway->ensure_customer_for_perfex_client(10, $client); break;
        case 'process_payment': $result = $gateway->process_payment(['invoice' => $invoice]); break;
        case 'charge_billet': $result = $gateway->charge_billet($invoice); break;
        case 'charge_pix': $result = $gateway->charge_pix(['invoice' => $invoice]); break;
        case 'charge_credit_card': $result = $gateway->charge_credit_card(['invoice' => $invoice]); break;
    }
    $output = ob_get_clean();
    assertContact($output === '', 'debug never prints customer/provider/charge data: ' . $entrypoint);
    $creates = array_values(array_filter($provider->history, fn($entry) => $entry['request']->getMethod() === 'POST' && $entry['request']->getUri()->getPath() === '/v3/customers'));
    assertContact(count($creates) === 1, 'entrypoint actually reaches customer creation: ' . $entrypoint);
    $body = sentBody($creates[0]);
    assertContact(isset($body['email']) === ($flag === '1') && isset($body['phone']) === ($flag === '1') && isset($body['mobilePhone']) === ($flag === '1'), 'legacy creation enforces both flags: ' . $entrypoint);
    assertContact($body['notificationDisabled'] === ($flag === '1'), 'legacy notification semantics: ' . $entrypoint);
    if ($entrypoint === 'charge_billet') assertContact($body['externalReference'] === '1', 'customer reference contains only invoice ID, never full invoice/client object');
    foreach ($provider->history as $entry) {
        $wire = (string) $entry['request']->getUri() . (string) $entry['request']->getBody();
        if ($flag === '0') foreach (['synthetic-contact@example.invalid', '11999990001', '11999990002'] as $private) assertContact(!str_contains(urldecode($wire), $private), 'no disabled contact bytes in URL/body: ' . $entrypoint);
    }
    foreach (['synthetic-contact', '11999990001', '11999990002', 'SYNTHETIC_PRODUCTION_KEY', 'SYNTHETIC_SANDBOX_KEY'] as $private) assertContact(!str_contains(json_encode([$GLOBALS['logs'], $db->tables['tblasaas_logs'], $output]), $private), 'diagnostics exclude contacts and keys');
    assertContact($provider->transport->count() === 0, 'all expected calls consumed; no early swallowed exception: ' . $entrypoint);
}

// Existing local or remote matches are read-only even after preference changes.
foreach (['map', 'remote'] as $source) {
    [$gateway, $adapter, $provider, $db] = contactFixture('sandbox', '0', '0', '1');
    $client = (object) $db->tables['tblclients'][0];
    if ($source === 'map') $db->tables['tblasaas_customers_map'][] = ['id' => 1, 'client_id' => 10, 'asaas_customer_id' => 'cus_synthetic'];
    else $provider->responses(['data' => [remoteCustomer()], 'totalCount' => 1]);
    $gateway->ensure_customer_for_client(10, $client);
    assertContact(count(array_filter($provider->history, fn($entry) => $entry['request']->getMethod() !== 'GET')) === 0, 'existing customer never remotely updated from preferences: ' . $source);
    assertContact(count($provider->history) === ($source === 'map' ? 0 : 1), 'existing lookup preserves read-only behavior: ' . $source);
}

// Diagnostics with enabled contacts still exclude URL query, arbitrary IDs,
// request/response bodies, headers and provider error details in both log stores.
foreach ([200, 400] as $status) {
    [$gateway, $adapter, $provider, $db] = contactFixture('sandbox', '1', '1', '1'); profile('sandbox', 'file_log_enabled', '1');
    $provider->transport->append(new Response($status, [], json_encode(['errors' => [['description' => 'SYNTHETIC_PROVIDER_SECRET']], 'email' => 'synthetic-response@example.invalid'])));
    try { $adapter->request('GET', '/customers/SYNTHETIC_PRIVATE_ID?email=synthetic-contact%40example.invalid&access_token=SYNTHETIC_QUERY_SECRET', [], ['X-Correlation-Id' => 'SYNTHETIC_CORRELATION_SECRET']); } catch (Asaas\Sdk\Exception\ApiException $expected) {}
    $diagnostics = json_encode([$GLOBALS['logs'], $db->tables['tblasaas_logs']]);
    foreach (['synthetic-contact', 'synthetic-response', 'SYNTHETIC_PROVIDER_SECRET', 'SYNTHETIC_QUERY_SECRET', 'SYNTHETIC_PRIVATE_ID', 'SYNTHETIC_CORRELATION_SECRET', 'SYNTHETIC_SANDBOX_KEY'] as $private) assertContact(!str_contains($diagnostics, $private), 'file/database diagnostics redact ' . $private);
    assertContact(count($db->tables['tblasaas_logs']) === 1 && count($GLOBALS['logs']) === 1, 'redaction is verified with actual logs written');
}

// Encoded/normalized routes, bracketed keys and nested compatibility data cannot
// bypass the transport policy. Disabled contacts are omitted, never cleared.
foreach (['/customers', '//customers', '///customers', '////v3/customers', '/v3/%63ustomers', '/v3/payments/../customers', '/v3/customers/'] as $path) {
    [$gateway, $adapter, $provider] = contactFixture('sandbox'); $provider->responses(remoteCustomer());
    $adapter->request('POST', $path . '?email=synthetic-url%40example.invalid&cpfCnpj=11144477735', ['mobilePhone' => '11999990002'], [], [
        'email' => 'synthetic-contact@example.invalid', 'additionalEmails' => ['synthetic-extra@example.invalid'],
        'phone' => '11999990001', 'mobilePhone' => '11999990002',
        'nested' => (object) ['Email' => 'synthetic-nested@example.invalid', 'phonenumber' => '11999990003'],
        'contact[email]' => 'synthetic-bracket@example.invalid', 'name' => 'Synthetic Customer',
    ]);
    $entry = end($provider->history); $body = sentBody($entry); $query = sentQuery($entry);
    assertContact(!str_contains(json_encode([$body, $query]), 'example.invalid') && !str_contains(json_encode([$body, $query]), '1199999'), 'normalized paths and nested contact keys are filtered');
    assertContact($query === ['cpfCnpj' => '11144477735'] && $body['name'] === 'Synthetic Customer', 'non-contact identity fields are retained');
    assertContact($body['notificationDisabled'] === true, 'normalized creation receives notification preference');
    foreach (['request', 'requestStructured'] as $method) {
        $before = count($provider->history); $blocked = false;
        try { $adapter->$method('GET', $path . '?email=synthetic-url%40example.invalid'); }
        catch (DomainException $error) { $blocked = $error->getMessage() === 'asaas_customer_contact_filter_disabled'; }
        assertContact($blocked && count($provider->history) === $before, 'normalized URL cannot bypass disabled contact lookup');
    }
}

// Rewriting a customer body keeps framing valid and leaves an unread stream.
[$gateway, $adapter, $provider] = contactFixture('sandbox'); $provider->responses(remoteCustomer());
$gateway->client()->request('POST', 'v3/customers', [], ['Transfer-Encoding' => 'chunked'], $contacts + ['name' => 'Synthetic Customer']);
$entry = end($provider->history);
assertContact(!$entry['request']->hasHeader('Transfer-Encoding'), 'filtered JSON does not retain conflicting chunked transfer header');
assertContact($entry['request']->getBody()->tell() === 0, 'filtered body stream starts unread for the socket handler');
assertContact((int) $entry['request']->getHeaderLine('Content-Length') === strlen((string) $entry['request']->getBody()), 'filtered body length matches wire bytes');
[$gateway, $adapter, $provider] = contactFixture('sandbox', '1', '1', '1'); $provider->responses(remoteCustomer());
$gateway->client()->request('POST', 'v3/customers', [], [], $contacts + ['name' => 'Synthetic Customer', 'notificationDisabled' => true]);
assertContact(end($provider->history)['request']->getBody()->tell() === 0, 'inspection restores an unchanged JSON stream position');

// A bounded invoice-origin context wins over the selector and is restored.
[$gateway, $adapter, $provider, $db] = contactFixture('production', '1', '1', '0');
profile('sandbox', 'send_customer_email', '0'); profile('sandbox', 'send_customer_mobile', '0'); profile('sandbox', 'disable_charge_notification', '1');
$db->tables['tblasaas_payment_origins'][] = ['invoice_id' => 1, 'environment' => 'sandbox', 'issuer_id' => null, 'provider' => 'asaas', 'channel' => 'api'];
$provider->responses(remoteCustomer());
(new ReflectionMethod($gateway, 'withInvoiceProfile'))->invoke($gateway, 1, fn() => $adapter->createCustomer($contacts + ['name' => 'Synthetic Customer']));
$entry = end($provider->history);
assertContact($entry['request']->getUri()->getHost() === 'api-sandbox.asaas.com' && $gateway->getProvider()->env() === 'production', 'request uses saved origin environment and restores selector');
assertFields(sentBody($entry), '0', '0', 'origin profile');
assertContact(sentBody($entry)['notificationDisabled'] === true, 'origin notification preference wins');

// Customer policy is scoped: unrelated endpoints retain their documented fields.
[$gateway, $adapter, $provider] = contactFixture('sandbox'); $provider->responses(['id' => 'hook_synthetic']);
$adapter->request('POST', '/webhooks', [], [], ['email' => 'synthetic-webhook@example.invalid']);
assertContact(sentBody(end($provider->history)) === ['email' => 'synthetic-webhook@example.invalid'], 'customer flags do not change webhook contracts');

echo "Asaas customer contact policy: {$checks} assertions passed; real SDK/Guzzle, synthetic transport/storage only.\n";
