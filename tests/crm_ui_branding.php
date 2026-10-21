<?php
declare(strict_types=1);

// Standalone helpers must work before CI, the database, session or app-config.
require __DIR__ . '/../application/helpers/app_branding_helper.php';
require __DIR__ . '/../application/helpers/installer_branding_helper.php';
$checks = 0;
function check_branding(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (!$ok) {
        throw new RuntimeException($label);
    }
}
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_rtl(): bool { return false; }
function _l(string $key): string { return 'Entrar'; }
class BrandingRenderComplete extends RuntimeException {}
function app_compile_css($group = ''): string { throw new BrandingRenderComplete(); }
function compile_theme_css(): string { throw new BrandingRenderComplete(); }

putenv('APP_DISPLAY_NAME');
putenv('INSTALLER_DISPLAY_NAME');
check_branding(crm_app_display_name() === 'CRM', 'Default product name');
check_branding(crm_installer_display_name() === 'ARGWS CRM', 'Default installer name');
check_branding(!defined('BASEPATH'), 'Standalone bootstrap does not need CI');
check_branding(crm_resolve_app_display_name(['app_display_name' => 'Configuração Parceiro'], false) === 'Configuração Parceiro', 'Central public config fallback');
check_branding(crm_resolve_app_display_name(['app_display_name' => 'Configuração Parceiro'], ' Ambiente ') === 'Ambiente', 'Environment precedence and trim');
foreach ([[], ['app_display_name' => ''], ['app_display_name' => '  '], ['app_display_name' => []], ['app_display_name' => null]] as $config) {
    check_branding(crm_resolve_app_display_name($config, false) === 'CRM', 'Invalid/empty config default');
}
foreach (['', '   '] as $empty) {
    putenv('APP_DISPLAY_NAME=' . $empty);
    putenv('INSTALLER_DISPLAY_NAME=' . $empty);
    check_branding(crm_app_display_name() === 'CRM', 'Blank product environment default');
    check_branding(crm_installer_display_name() === 'ARGWS CRM', 'Blank installer environment default');
}

define('BASEPATH', __DIR__);
define('APPPATH', __DIR__ . '/../application/');
$_POST['base_url'] = 'https://crm.example.test/';

function render_install_fragment(string $path): string
{
    $cwd = getcwd();
    $debug = '';
    $error = '';
    $current_step = 0;
    $steps = [];
    ob_start();
    try {
        chdir(__DIR__ . '/../install');
        include $path;
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
        chdir($cwd);
    }
}

function render_runtime_title(string $path, ?string $title = null): string
{
    $locale = 'pt-BR';
    $controller = new class {
        public $load;
        public function __construct()
        {
            $this->load = new class {
                public function view(string $path, array $data = []): void {}
            };
        }
    };
    $render = function (string $relativePath): void {
        include __DIR__ . '/../' . $relativePath;
    };
    ob_start();
    try {
        $render->call($controller, $path);
        throw new RuntimeException('Expected interception before assets/session-dependent rendering');
    } catch (BrandingRenderComplete $complete) {
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

foreach ([false, '', '  ', 'Parceiro Exemplo', '<script>alert("nome")</script> & Parceiro', '0'] as $name) {
    $name === false ? putenv('INSTALLER_DISPLAY_NAME') : putenv('INSTALLER_DISPLAY_NAME=' . $name);
    $expected = $name === false || trim($name) === '' ? 'ARGWS CRM' : trim($name);
    check_branding(crm_installer_display_name() === $expected, 'Installer custom/default name');
    $html = render_install_fragment('finish.php');
    check_branding(str_contains($html, 'pacotes oficiais do ' . e($expected)), 'Finish renders escaped name');
    check_branding(str_contains($html, 'canal de distribuição ARGWS'), 'Provider distribution brand preserved');
    check_branding(!str_contains($html, 'github.com') && !str_contains($html, '<script>alert'), 'No repository link or HTML injection');
    check_branding(str_contains(render_install_fragment('html.php'), '<title>' . e($expected) . ' — Instalação</title>'), 'Installer title uses same escaped source');
}

putenv('APP_DISPLAY_NAME=Parceiro <Exemplo> & "Produto"');
putenv('INSTALLER_DISPLAY_NAME=Instalador Separado');
check_branding(crm_app_display_name() === 'Parceiro <Exemplo> & "Produto"', 'Separate product name');
check_branding(crm_installer_display_name() === 'Instalador Separado', 'Separate installer name');
check_branding(crm_app_display_name_html() === e(crm_app_display_name()), 'Product HTML encoding');
check_branding(crm_app_page_title('Faturas <teste>') === 'Faturas <teste> | ' . crm_app_display_name(), 'Page title composition');
check_branding(crm_app_page_title(crm_app_display_name()) === crm_app_display_name(), 'Avoid duplicate app title');
foreach (['application/views/admin/includes/head.php', 'application/views/authentication/includes/head.php', 'application/views/themes/perfex/head.php'] as $path) {
    $html = render_runtime_title($path, 'Página <teste>');
    check_branding(str_contains($html, e(crm_app_display_name())), 'Real title partial uses escaped central name');
    check_branding(!str_contains($html, 'Parceiro <Exemplo>'), 'Raw markup absent from title partial');
}
foreach (['app_branding_helper.php', 'installer_branding_helper.php'] as $helper) {
    $code = file_get_contents(APPPATH . 'helpers/' . $helper);
    check_branding(!preg_match('/get_option\s*\(|get_instance\s*\(|session_start\s*\(|app-config\.php|Dotenv|\$_SERVER/', $code), 'Bootstrap helper has no runtime/fiscal/credential/Host dependencies');
}
foreach (['install/index.php', 'install/install.class.php', 'install/requirements.php', 'install/file_permissions.php', 'docker/setup-web.php'] as $path) {
    check_branding(str_contains(file_get_contents(__DIR__ . '/../' . $path), 'crm_installer_display_name_html()'), 'Installer consumer uses escaped helper: ' . $path);
}
$autoload = file_get_contents(APPPATH . 'config/autoload.php');
check_branding(str_contains($autoload, "'app_branding'"), 'Product helper autoloaded');
check_branding(str_contains(file_get_contents(APPPATH . 'helpers/pdf_helper.php'), "get_option('company_logo')"), 'Fiscal logo source unchanged');

// Execute the real pre-install config last: its intentional exit is checked at shutdown.
check_branding(!is_file(APPPATH . 'config/app-config.php'), 'Synthetic test requires no real installed app-config');
putenv('INSTALLER_DISPLAY_NAME=Pré-instalação <Parceiro>');
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'crm.example.test"><script>alert("host")</script><a href="';
$_SERVER['SCRIPT_NAME'] = '/tenant"><svg onload="alert(1)">/index.php';
ob_start();
register_shutdown_function(static function (): void {
    $html = (string) ob_get_clean();
    check_branding(str_contains($html, '<h1>' . e('Pré-instalação <Parceiro>') . ' ainda não foi instalado</h1>'), 'Actual pre-install config uses escaped installer identity');
    check_branding(!str_contains($html, 'Parceiro <Exemplo>') && !str_contains($html, 'companyname'), 'Runtime/fiscal identity does not leak into pre-install notice');
    $url = 'https://' . $_SERVER['HTTP_HOST'] . '/tenant"><svg onload="alert(1)">/install';
    check_branding(substr_count($html, e($url)) === 2, 'Host/path-derived URL escaped in both href and link text');
    check_branding(!str_contains($html, '<script>') && !str_contains($html, '<svg'), 'Malicious Host/path cannot inject tags');
    echo 'CRM branding: ' . $GLOBALS['checks'] . " checks passed\n";
});
require APPPATH . 'config/config.php';
