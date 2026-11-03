<?php
/** Exercise the real application/MX routers with the real legacy api wildcard routes. */
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
define('ADMIN_URI', 'admin');
function log_message($level, $message) {}
final class RouterFixtureConfig
{
    public function item($key)
    {
        return $key === 'modules_locations' ? [APP_MODULES_PATH => '../modules/'] : '';
    }
}
class CI_Router
{
    public $directory = '';
    public $routes = [];
    public $config;
    public function __construct()
    {
        $this->config = new RouterFixtureConfig();
        require APPPATH . 'config/routes.php';
        $this->routes = $route;
    }
}
$GLOBALS['CFG'] = new RouterFixtureConfig();
require $argv[1] ?? APPPATH . 'core/App_Router.php';
$checks = 0;
function checkRoute(array $segments, bool $native): void
{
    global $checks;
    $router = new App_Router();
    $resolved = $router->locate($segments);
    if ($native) {
        if ($resolved !== $segments || $router->fetch_module() !== null || $router->directory !== '') {
            throw new RuntimeException('Exact machine route was captured by the legacy module: ' . implode('/', $segments));
        }
    } elseif ($router->fetch_module() !== 'api' || $router->directory !== '../modules/api/controllers/') {
        throw new RuntimeException('Legacy module route changed: ' . implode('/', $segments));
    }
    ++$checks;
}
$routes = (new App_Router())->routes;
foreach ($routes as $key => $target) {
    if (!preg_match('#^company_api/([a-z_]+)$#D', $key, $match)) { continue; }
    checkRoute(['api', $match[1]], true);
    checkRoute(['api', $match[1], '17'], true);
}
checkRoute(['api', 'expense_files', '17', str_repeat('a', 64)], true);
foreach ([['login', 'auth'], ['invoices', 'search', 'synthetic'], ['invoices', '0'],
    ['invoices', '-1'], ['invoices', '17', 'extra'], ['expense_files', '17', 'invalid']] as $tail) {
    checkRoute(array_merge(['api'], $tail), false);
}
echo "Native API router: {$checks} core/legacy dispatch scenarios passed; no authorization bypass.\n";
