<?php
declare(strict_types=1);

// Run with: php tests/brand_assets_smoke.php
define('BASEPATH', __DIR__);

$brandOptions = [];

function get_option(string $name): string
{
    global $brandOptions;
    return $brandOptions[$name] ?? '';
}

function base_url(string $path): string
{
    return 'https://crm.example.test/' . $path;
}

function site_url(string $path = ''): string
{
    return base_url($path);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

class BrandHooks
{
    public function add_action(string $hook, string $callback): void {}
    public function apply_filters(string $hook, mixed $value): mixed { return $value; }
}

function hooks(): BrandHooks
{
    static $hooks;
    return $hooks ??= new BrandHooks();
}

class BrandCss
{
    public array $assets = [];
    public function add(string $name, mixed $asset, string $group = 'admin'): void
    {
        $this->assets[$name] = $asset;
    }
}

class BrandApplication
{
    public BrandCss $app_css;
    public function __construct() { $this->app_css = new BrandCss(); }
}

function get_instance(): BrandApplication
{
    static $application;
    return $application ??= new BrandApplication();
}

function expect_brand(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rendered_logo(bool $dark = false): string
{
    ob_start();
    $dark ? get_dark_company_logo() : get_company_logo();
    return (string) ob_get_clean();
}

require __DIR__ . '/../application/helpers/template_helper.php';
require __DIR__ . '/../application/helpers/assets_helper.php';

$brandOptions['companyname'] = 'Cliente Exemplo';
expect_brand(str_contains(rendered_logo(), '/assets/images/argws/logo-dark.png'), 'Logo padrão deve ser legível em fundo claro.');
expect_brand(str_contains(rendered_logo(true), '/assets/images/argws/logo-dark.png'), 'Login deve usar logo azul em fundo claro.');
expect_brand(str_contains(get_admin_header_logo_url(), '/assets/images/argws/logo-dark.png'), 'Cabeçalho branco deve usar logo azul.');
add_favicon_link_asset();
expect_brand(get_instance()->app_css->assets['favicon']['path'] === 'assets/images/argws/favicon.png', 'Favicon padrão ausente.');

$brandOptions['company_logo'] = 'logo-cliente.png';
expect_brand(str_contains(rendered_logo(), '/uploads/company/logo-cliente.png'), 'Logo configurado não substituiu o padrão.');
expect_brand(str_contains(rendered_logo(true), '/uploads/company/logo-cliente.png'), 'Login deve reutilizar logo do cliente se não houver variante.');
expect_brand(str_contains(get_admin_header_logo_url(), '/uploads/company/logo-cliente.png'), 'Cabeçalho não usou logo configurado.');

$brandOptions['company_logo_dark'] = 'logo-cliente-escuro.png';
expect_brand(str_contains(rendered_logo(true), '/uploads/company/logo-cliente-escuro.png'), 'Variante configurada não foi usada no login.');
expect_brand(str_contains(get_admin_header_logo_url(), '/uploads/company/logo-cliente-escuro.png'), 'Cabeçalho não usou variante configurada.');

$brandOptions['favicon'] = 'favicon-cliente.png';
add_favicon_link_asset('admin-auth');
expect_brand(get_instance()->app_css->assets['favicon']['path'] === 'uploads/company/favicon-cliente.png', 'Favicon configurado não substituiu o padrão.');
expect_brand(get_instance()->app_css->assets['favicon-apple-touch-icon']['attributes']['rel'] === 'apple-touch-icon', 'Rel do ícone Apple inválido.');

$setup = file_get_contents(__DIR__ . '/../docker/setup-web.php');
expect_brand(str_contains($setup, '/assets/images/argws/logo-light.png'), 'Setup deve usar logo branco em fundo escuro.');
echo "Branding padrão e personalizado: OK\n";
