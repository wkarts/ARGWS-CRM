<?php
define('BASEPATH', __DIR__);
class App_Controller {public $output;}
require_once __DIR__.'/../modules/pige_bridge/controllers/Bridge.php';
class DisabledOutput {public $status;public $body;public function set_header($v){return $this;}public function set_status_header($v){$this->status=$v;return $this;}public function set_content_type($v){return $this;}public function set_output($v){$this->body=json_decode($v,true);return $this;}}
$c=new Bridge();$c->output=new DisabledOutput();$c->requests();
if($c->output->status!==503 || $c->output->body['error']!=='integration_disabled')throw new RuntimeException('default must be disabled before any input/database access');
echo "PIGE bridge disabled-by-default: passed\n";

require __DIR__.'/../application/config/argws_resources.php';
if (!isset($config['argws_resources']['pige_bridge'])) throw new RuntimeException('Module must be explicitly available in the CRM resource catalog');
echo "PIGE bridge resource catalog: passed\n";

// Exercise the repository's actual HMVC route parser, not a mocked routing assumption.
define('APPPATH', dirname(__DIR__) . '/application/');
$CFG = new class { public function item($name) { return $name === 'modules_locations' ? [dirname(__DIR__) . '/modules/' => '../../modules/'] : null; } };
function log_message($level, $message) {}
define('FCPATH', dirname(__DIR__) . '/');
define('APP_MODULES_PATH', FCPATH . 'modules/');
define('ADMIN_URI', 'admin');
class CI_Router { public $config; public $directory = ''; }
require APPPATH . 'third_party/MX/Router.php';
$route = Modules::parse_routes('pige_bridge', 'pige_bridge/api/v1/requests');
if ($route !== ['pige_bridge', 'bridge', 'requests']) { throw new RuntimeException('Canonical endpoint must resolve without a duplicate module prefix'); }
if (!is_file(dirname(__DIR__) . '/modules/' . $route[0] . '/controllers/' . ucfirst($route[1]) . '.php') || !method_exists('Bridge', $route[2])) { throw new RuntimeException('Resolved controller/action must exist'); }
echo "PIGE bridge actual HMVC route: passed\n";

$router = new MX_Router();
$router->config = $CFG;
$located = $router->locate(explode('/', 'pige_bridge/api/v1/requests'));
if ($located !== ['bridge', 'requests'] || !str_ends_with($router->directory, '/pige_bridge/controllers/')) {
    throw new RuntimeException('Actual HMVC router must locate the bridge action');
}
echo "PIGE bridge actual HMVC controller location: passed\n";
