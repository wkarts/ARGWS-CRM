<?php
/** Presentation-only regression: real App_Loader include and inherited MX __get.
 * Extract the shipped route/menu fragment to avoid bootstrapping staff, hooks or DB.
 * No controller initialization, network, authentication or production data.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', __DIR__ . '/../../system/');
define('APPPATH', __DIR__ . '/../../application/');
define('VIEWPATH', APPPATH . 'views/');
require BASEPATH . 'core/Loader.php';
require APPPATH . 'core/App_Loader.php';
class CI { public static $APP; }
class CompanyRequestScope {
    public static function adminUrl($application, string $path = ''): string { return '/admin/' . $path; }
}
function log_message(...$arguments): void {}
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$source = file_get_contents(VIEWPATH . 'admin/companies/layout_head.php');
check((bool) preg_match('/\$companyLayoutMenus = \[.*?\n\];/s', $source, $menus), 'Shipped menu catalog must be available');
check((bool) preg_match('/\$companyLayoutRouter = .*?\n\?>/s', $source, $route), 'Shipped direct router resolution must be available');
$start = strpos($source, '<?php foreach ($companyLayoutMenus');
$end = strpos($source, '</aside>', $start ?: 0);
check($start !== false && $end !== false, 'Shipped menu rendering must be available');
$fragment = '<?php ' . $menus[0] . "\n" . $route[0] . substr($source, $start, $end - $start);
$file = tempnam(sys_get_temp_dir(), 'crm-shell-route-');
check($file !== false, 'Temporary fixture must be available');
try {
    check(file_put_contents($file, $fragment) !== false, 'Fixture fragment must be written');
    foreach (['company_client_imports' => 'Clientes', 'company_invoices' => 'Vendas', 'company_unknown_fixture' => null] as $controller => $expected) {
        $router = new class($controller) {
            private string $controller;
            public function __construct(string $controller) { $this->controller = $controller; }
            public function fetch_class(): string { return $this->controller; }
        };
        CI::$APP = (object) ['router' => $router, 'config' => new class {
            public function item(string $key): bool { return false; }
        }];
        $loader = (new ReflectionClass(App_Loader::class))->newInstanceWithoutConstructor();
        check(!isset($loader->router), 'Fixture must preserve actual missing __isset behavior');
        check($loader->router === $router, 'Actual MX getter must resolve controller router');
        $html = $loader->_ci_load([
            '_ci_path' => $file, '_ci_return' => true,
            '_ci_vars' => [
                'companyLayoutCan' => static fn(...$arguments): bool => true,
                'companyLayoutEscape' => static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            ],
        ]);
        preg_match_all('/<li class="company-menu-group active">\s*<details open><summary>.*?<span class="menu-text">([^<]+)<\/span>/s', $html, $active);
        check($active[1] === ($expected === null ? [] : [$expected]), 'Exactly the current controller group must be active/open: ' . $controller);
        check(substr_count($html, '<details open>') === ($expected === null ? 0 : 1), 'No unrelated disclosure may auto-open');
        check(str_contains($html, '/admin/company_client_imports'), 'Authorized importer link remains in menu');
    }
    echo "PASS: actual App_Loader/MX router resolution opens Clientes/Vendas and leaves unknown routes closed\n";
} finally { unlink($file); }
