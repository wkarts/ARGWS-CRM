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
if (array_diff($fixtureFlags, ['--full-shell', '--long-name', '--mobile-user', '--selector-only', '--selected-two', '--company-google', '--company-microsoft', '--company-efi'])) {
    throw new InvalidArgumentException('Unknown native-header fixture flag');
}
define('NATIVE_HEADER_FIXTURE_COMPANY', in_array('--company-google', $fixtureFlags, true) ? 'google_mail_oauth' : (in_array('--company-microsoft', $fixtureFlags, true) ? 'microsoft_mail_oauth' : (in_array('--company-efi', $fixtureFlags, true) ? 'efi' : '')));
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
        if (NATIVE_HEADER_FIXTURE_COMPANY && $table === 'fixture_staff') { return false; }
        if ($field !== 'primary_issuer_id' || $table !== 'fixture_issuers_installation_identity') {
            throw new LogicException('Unexpected fixture field lookup');
        }
        return true;
    }

    public function query(string $sql, array $parameters = [])
    {
        if ($sql === 'SELECT active FROM fixture_modules WHERE module_name=?' && $parameters === ['theme_style']) {
            $rows = [['active' => 1]];
        } elseif ($sql === 'SELECT value FROM fixture_options WHERE name=?' && $parameters === ['theme_style']) {
            $rows = [['value' => '[{"id":"btn-primary","color":"#32c977"},{"id":"admin-menu-active-item","color":"#32c977"}]']];
        } elseif ($sql === 'SELECT primary_issuer_id FROM fixture_issuers_installation_identity WHERE id=1'
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
            public function row_array(): array { return $this->rows[0] ?? []; }
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
    $html = '<form action="' . e((NATIVE_HEADER_FIXTURE_SELECTOR_ONLY || NATIVE_HEADER_FIXTURE_COMPANY) ? $url : '#') . '" method="post"';
    foreach ($attributes as $key => $value) { $html .= ' ' . e($key) . '="' . e($value) . '"'; }
    return $html . '>';
}
function form_open_multipart(string $url,array $attributes=[]):string { return form_open($url,['enctype'=>'multipart/form-data']+$attributes); }
function form_close(): string { return '</form>'; }
function get_admin_header_logo_url(): string { return base_url('assets/images/argws/logo-dark.png'); }
putenv('APP_DISPLAY_NAME=CRM sintético');
require APPPATH.'helpers/app_branding_helper.php';
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
    public static function token(): ?string { return null; }
    public static function context()
    {
        return new class {
            public function isPrimary(): bool { return true; }
            public function staffId(): int { return 1; }
            public function available(): array {
                $companies = [['id' => 1, 'legal_name' => native_header_fixture_company_name()]];
                if (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY) { $companies[] = ['id' => 2, 'legal_name' => 'Outra empresa de teste']; }
                return $companies;
            }
            public function issuerId(): int { return NATIVE_HEADER_FIXTURE_SELECTED_TWO ? 2 : 1; }
            public function csrf(): string { return 'synthetic-fixture-only'; }
        };
    }
    public static function adminUrl($application, string $path = ''): string { return NATIVE_HEADER_FIXTURE_COMPANY ? '/admin/' . $path : admin_url($path); }
}

final class CompanySettingsRuntime { public static function active(): bool { return true; } public static function canResource($feature, $cap = 'view'): bool { return !in_array($feature, ['pusher', 'connect-api-connector', 'calendar'], true); } public static function can($cap): bool { return true; } }

final class NativeHeaderFixtureApplication
{
    public NativeHeaderFixtureDatabase $db;
    public object $app;
    public object $misc_model;
    public object $load;
    public object $session;
    public object $app_modules;
    public object $router;

    public function __construct()
    {
        $this->db = new NativeHeaderFixtureDatabase();
        $this->router = new class { public function fetch_class(): string { return 'company_' . NATIVE_HEADER_FIXTURE_COMPANY; } };
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
                    'admin/companies/google_mail_oauth',
                    'admin/companies/microsoft_mail_oauth',
                    'admin/companies/efi',
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
if (NATIVE_HEADER_FIXTURE_COMPANY) {
    if(NATIVE_HEADER_FIXTURE_COMPANY==='efi')set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
    ob_start();
    $fixtureApplication->load->view('admin/companies/' . NATIVE_HEADER_FIXTURE_COMPANY, [
        'profiles'=>[['id'=>1,'environment'=>'sandbox','revision'=>str_repeat('a',64),'enabled'=>1]], 'choices'=>[['id'=>1,'environment'=>'sandbox','revision'=>str_repeat('a',64),'enabled'=>1]],
        'activity'=>['origins'=>[['id'=>1,'invoice_id'=>42,'environment'=>'sandbox','kind'=>'pix','state'=>'bound','proof'=>['id'=>2,'state'=>'received_observed'],'receipt'=>null]]], 'instrument'=>['pix_copy_paste'=>'000201'.str_repeat('1',120)], 'can_configure'=>true,'can_issue'=>true,'can_settle'=>true,'saved'=>null,
        'csrf' => 'synthetic-form-csrf', 'can_edit' => true, 'error' => '', 'result' => '',
        'trusted_redirect' => 'https://fixture.test/admin/callback', 'portal_mail' => ['mode' => 'native', 'revision' => 1],
        'document_mail' => ['mode' => 'native', 'revision' => 1],
        'profile' => ['revision' => 1, 'status' => 'unconfigured', 'sender_email' => 'sender@example.invalid', 'client_id' => '', 'secret_configured' => false, 'tenant_id' => ''],
    ]);
    $rendered = ob_get_clean();
    // Explicitly synthetic table supplements the actual OAuth views for DOM ownership tests.
    $tableFixture = '<form id="synthetic-table-owner" data-synthetic-table-fixture action="/synthetic-table" method="post"><input type="hidden" name="synthetic_csrf" value="unchanged"><table id="synthetic-table"><thead><tr><th>Coluna sintética A</th><th>Coluna sintética B</th><th>Coluna sintética C</th></tr></thead><tbody><tr id="synthetic-row"><td style="white-space:nowrap">Conteúdo sintético largo para testar rolagem horizontal local</td><td><input type="checkbox" name="synthetic_choice" value="1" checked></td><td>Mesma linha e mesmos nós</td></tr></tbody></table></form>';
    echo str_replace('</section>', $tableFixture . '</section>', $rendered);
    exit;
}
if (NATIVE_HEADER_FIXTURE_SELECTOR_ONLY) {
    $fixtureApplication->load->view('admin/includes/company_selector');
    exit;
}
$fixtureApplication->load->view('admin/includes/header');
if (NATIVE_HEADER_FIXTURE_FULL_SHELL) {
    $fixtureApplication->load->view('admin/includes/setup_menu');
    $fixtureApplication->load->view('admin/includes/aside');
}
