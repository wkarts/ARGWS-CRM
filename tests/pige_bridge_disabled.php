<?php
define('BASEPATH', __DIR__);
class App_Controller {public $output;}
require_once __DIR__.'/../modules/pige_bridge/controllers/Bridge.php';
class DisabledOutput {public $status;public $body;public function set_header($v){return $this;}public function set_status_header($v){$this->status=$v;return $this;}public function set_content_type($v){return $this;}public function set_output($v){$this->body=json_decode($v,true);return $this;}}
$c=new Bridge();$c->output=new DisabledOutput();$c->requests();
if($c->output->status!==503 || $c->output->body['error']!=='integration_disabled')throw new RuntimeException('default must be disabled before any input/database access');
echo "PIGE bridge disabled-by-default: passed\n";
