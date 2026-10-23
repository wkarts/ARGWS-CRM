<?php

declare(strict_types=1);

/**
 * Presentation-only CLI fixture. Outputs the actual native header fragment.
 *
 * The consumer supplies native CSS/JS. The selector loads its shipped assets. No database,
 * HTTP server, authenticated session, provider request or CRM bootstrap is used.
 * CompanySettingsIdentity and IssuerResourceAccess are the shipped classes;
 * their read-only I/O is an exact synthetic query whitelist. Framework globals,
 * permissions and CompanyRequestScope are presentation stubs, not ACL evidence.
 * The quick-action catalog, notifications and timers are empty. Their actual
 * native views are retained. Full-shell mode also renders actual setup/aside/
 * pinned-project views with empty menus, languages and projects plus a synthetic
 * staff identity. The caller can request the server's mobile header branch.
 *
 * Usage: php tests/support/NativeHeaderFixture.php [--full-shell] [--long-name] [--mobile-user]
 * Selector-only POST fixture: --selector-only [--selected-two]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$fixtureFlags = array_slice($argv, 1);
if (array_diff($fixtureFlags, ['--full-shell', '--long-name', '--mobile-user', '--selector-only', '--selected-two'])) {
    throw new InvalidArgumentException('Unknown native-header fixture flag');
}
define('NATIVE_HEADER_FIXTURE_FULL_SHELL', in_array('--full-shell', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_LONG_NAME', in_array('--long-name', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_MOBILE_USER', in_array('--mobile-user', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_SELECTOR_ONLY', in_array('--selector-only', $fixtureFlags, true));
define('NATIVE_HEADER_FIXTURE_SELECTED_TWO', in_array('--selected-two', $fixtureFlags, true));
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
            && ($parameters === [1] || (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY && $parameters === [2]))) {
            $rows = [[
                'id' => $parameters[0],
                'active' => 1,
                'legal_name' => $parameters[0] === 1 ? native_header_fixture_company_name() : 'Outra empresa de teste',
                'identity_status' => 'complete',
                'settings_json' => '{}',
            ]];
        } elseif ($sql === 'SELECT value FROM fixture_options WHERE name=?'
            && $parameters === ['company_trade_name']) {
            $rows = [['value' => native_header_fixture_company_name()]];
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

function native_header_fixture_company_name(): string
{
    return NATIVE_HEADER_FIXTURE_LONG_NAME
        ? 'Empresa sintética CI de demonstração com razão social e nome fantasia muito longos para validação de seleção responsiva UnidadeRegionalOperacionalSemSeparadoresParaValidacaoDeQuebraDeLinha'
        : 'Empresa sintética CI';
}
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function _l(string $key, ...$arguments): string { global $lang; return $lang[$key] ?? $key; }
function db_prefix(): string { return 'fixture_'; }
function base_url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function admin_url(string $path = ''): string { return NATIVE_HEADER_FIXTURE_SELECTOR_ONLY ? '/admin/' . $path : '#fixture-' . rawurlencode($path); }
function form_open(string $url, array $attributes = []): string
{
    $html = '<form action="' . e(NATIVE_HEADER_FIXTURE_SELECTOR_ONLY ? $url : '#') . '" method="post"';
    foreach ($attributes as $key => $value) { $html .= ' ' . e($key) . '="' . e($value) . '"'; }
    return $html . '>';
}
function form_close(): string { return '</form>'; }
function get_admin_header_logo_url(): string { return ''; }
function crm_app_display_name_html(): string { return 'CRM sintético'; }
function is_mobile(): bool { return NATIVE_HEADER_FIXTURE_MOBILE_USER; }
function is_staff_member(): bool { return true; }
function is_admin(): bool { return true; }
function staff_can(...$arguments): bool { return true; }
function staff_cant(...$arguments): bool { return false; }
function get_option(string $name): string { return '0'; }
function is_language_disabled(): bool { return false; }
function get_staff_full_name(): string { return 'Usuário sintético CI'; }
function get_staff(): object { return (object) ['email' => 'synthetic-staff@example.invalid']; }
function get_user_pinned_projects(): array { return []; }
function support_contact_destination(): ?array { return null; }
function staff_profile_image($staffId, array $classes): string
{
    // Local synthetic avatar keeps the native image box without fetching a file.
    return '<svg class="' . e(implode(' ', $classes)) . '" width="32" height="32" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" fill="#94a3b8" /></svg>';
}
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
            public function available(): array {
                $companies = [['id' => 1, 'legal_name' => native_header_fixture_company_name()]];
                if (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY) { $companies[] = ['id' => 2, 'legal_name' => 'Outra empresa de teste']; }
                return $companies;
            }
            public function issuerId(): int { return NATIVE_HEADER_FIXTURE_SELECTED_TWO ? 2 : 1; }
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
    public object $session;
    public object $app_modules;

    public function __construct()
    {
        $this->db = new NativeHeaderFixtureDatabase();
        $this->app = new class {
            public function get_quick_actions_links(): array { return []; }
            public function get_available_languages(): array { return []; }
            public function show_setup_menu(): bool { return true; }
            public function set_setup_menu_visibility(int $count): void {}
        };
        $this->session = new class {
            public function has_userdata(string $key): bool { return false; }
            public function userdata(string $key): bool { return false; }
        };
        $this->app_modules = new class {
            public function number_of_modules_that_require_database_upgrade(): int { return 0; }
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
                    'admin/includes/setup_menu',
                    'admin/includes/aside',
                    'admin/projects/pinned',
                ], true)) {
                    throw new LogicException('Unexpected fixture view');
                }
                $render = function () use ($view, $data): void {
                    $current_user = (object) [
                        'staffid' => 1,
                        'default_language' => '',
                        'total_unfinished_todos' => 0,
                        'total_unread_notifications' => 0,
                    ];
                    $startedTimers = [];
                    $sidebar_menu = [];
                    $setup_menu = [];
                    extract($data, EXTR_SKIP);
                    require APPPATH . 'views/' . $view . '.php';
                };
                $render->call($this->application);
            }
        };
    }
}

$fixtureApplication = new NativeHeaderFixtureApplication();
if (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY) {
    $fixtureApplication->load->view('admin/includes/company_selector');
    exit;
}
$fixtureApplication->load->view('admin/includes/header');
if (NATIVE_HEADER_FIXTURE_FULL_SHELL) {
    $fixtureApplication->load->view('admin/includes/setup_menu');
    $fixtureApplication->load->view('admin/includes/aside');
}
