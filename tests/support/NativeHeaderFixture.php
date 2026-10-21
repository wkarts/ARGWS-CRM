<?php

declare(strict_types=1);

/**
 * Presentation-only CLI fixture. Outputs the actual native header fragment.
 *
 * The consumer supplies native CSS/JS and initializes selectpicker. No database,
 * HTTP server, authenticated session, provider request or CRM bootstrap is used.
 * CompanySettingsIdentity and IssuerResourceAccess are the shipped classes;
 * their read-only I/O is an exact synthetic query whitelist. Framework globals,
 * permissions and CompanyRequestScope are presentation stubs, not ACL evidence.
 * The quick-action catalog, notifications and timers are empty. Their actual
 * native views are retained. is_mobile() is false to match the real browser
 * test's desktop user agent at both its 390px and 1280px viewport widths.
 *
 * Usage: php tests/support/NativeHeaderFixture.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const NATIVE_HEADER_FIXTURE_ROOT = __DIR__ . '/../..';
define('BASEPATH', NATIVE_HEADER_FIXTURE_ROOT . '/system/');
define('APPPATH', NATIVE_HEADER_FIXTURE_ROOT . '/application/');
require APPPATH . 'language/portuguese_br/portuguese_br_lang.php';
require APPPATH . 'services/CompanySettingsIdentity.php';

final class NativeHeaderFixtureDatabase
{
    public function field_exists(string $field, string $table): bool
    {
        if ($field !== 'primary_issuer_id' || $table !== 'fixture_issuers_installation_identity') {
            throw new LogicException('Unexpected fixture field lookup');
        }
        return true;
    }

    public function query(string $sql, array $parameters = [])
    {
        if ($sql === 'SELECT primary_issuer_id FROM fixture_issuers_installation_identity WHERE id=1'
            && $parameters === []) {
            $rows = [['primary_issuer_id' => 1]];
        } elseif ($sql === 'SELECT * FROM fixture_issuers_registry WHERE id=? AND active=1'
            && $parameters === [1]) {
            $rows = [[
                'id' => 1,
                'active' => 1,
                'legal_name' => 'Empresa sintética CI',
                'identity_status' => 'complete',
                'settings_json' => '{}',
            ]];
        } elseif ($sql === 'SELECT value FROM fixture_options WHERE name=?'
            && $parameters === ['company_trade_name']) {
            $rows = [['value' => 'Empresa sintética CI']];
        } else {
            throw new LogicException('Unexpected fixture query');
        }
        return new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function result_array(): array { return $this->rows; }
        };
    }
}

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function _l(string $key, ...$arguments): string { global $lang; return $lang[$key] ?? $key; }
function db_prefix(): string { return 'fixture_'; }
function admin_url(string $path = ''): string { return '#fixture-' . rawurlencode($path); }
function form_open(string $url, array $attributes = []): string
{
    $html = '<form action="#" method="post"';
    foreach ($attributes as $key => $value) { $html .= ' ' . e($key) . '="' . e($value) . '"'; }
    return $html . '>';
}
function form_close(): string { return '</form>'; }
function get_admin_header_logo_url(): string { return ''; }
function crm_app_display_name_html(): string { return 'CRM sintético'; }
function is_mobile(): bool { return false; }
function is_staff_member(): bool { return true; }
function is_admin(): bool { return true; }
function staff_can(...$arguments): bool { return true; }
function staff_cant(...$arguments): bool { return false; }
function get_option(string $name): string { return '0'; }
function do_action_deprecated(...$arguments): void {}
function hooks()
{
    return new class {
        public function apply_filters(string $name, $value) { return $value; }
        public function do_action(...$arguments): void {}
    };
}
function collect(array $items)
{
    if ($items !== []) { throw new LogicException('Expected empty synthetic quick-action catalog'); }
    return new class implements IteratorAggregate {
        public function reject(callable $callback) { return $this; }
        public function isNotEmpty(): bool { return false; }
        public function getIterator(): Traversable { return new ArrayIterator([]); }
    };
}

/** Presentation context only: does not exercise the authenticated ACL service. */
final class CompanyRequestScope
{
    public static function context()
    {
        return new class {
            public function available(): array { return [['id' => 1, 'legal_name' => 'Empresa sintética CI']]; }
            public function issuerId(): int { return 1; }
            public function csrf(): string { return 'synthetic-fixture-only'; }
        };
    }
    public static function adminUrl($application, string $path): string { return admin_url($path); }
}

final class NativeHeaderFixtureApplication
{
    public NativeHeaderFixtureDatabase $db;
    public object $app;
    public object $misc_model;
    public object $load;

    public function __construct()
    {
        $this->db = new NativeHeaderFixtureDatabase();
        $this->app = new class {
            public function get_quick_actions_links(): array { return []; }
        };
        $this->misc_model = new class {
            public function get_user_notifications(): array { return []; }
        };
        $this->load = new class($this) {
            private NativeHeaderFixtureApplication $application;
            public function __construct(NativeHeaderFixtureApplication $application)
            {
                $this->application = $application;
            }
            public function view(string $view, array $data = []): void
            {
                if (!in_array($view, [
                    'admin/includes/header',
                    'admin/includes/company_selector',
                    'admin/includes/notifications',
                    'admin/tasks/started_timers',
                ], true)) {
                    throw new LogicException('Unexpected fixture view');
                }
                $render = function () use ($view, $data): void {
                    $current_user = (object) ['total_unfinished_todos' => 0, 'total_unread_notifications' => 0];
                    $startedTimers = [];
                    extract($data, EXTR_SKIP);
                    require APPPATH . 'views/' . $view . '.php';
                };
                $render->call($this->application);
            }
        };
    }
}

(new NativeHeaderFixtureApplication())->load->view('admin/includes/header');
