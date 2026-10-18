<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('FCPATH', dirname(__DIR__).'/');
define('APPPATH', FCPATH.'application/');
define('APP_MODULES_PATH',FCPATH.'modules/');
define('ADMIN_URI','admin');
class CI_Router { public $config; public $directory=''; }
$CFG=new class { function item($key){return $key==='modules_locations'?[APP_MODULES_PATH=>'../../modules/']:'';} };
function log_message(...$x){}
require APPPATH.'third_party/MX/Router.php';
$checks=0;
function check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function route($path):array {global $CFG;$r=new MX_Router();$r->config=$CFG;return [$r->locate(explode('/',$path)),$r->directory];}
[$parts,$dir]=route('asaas/gateways/callback');
check($parts===['callback','index'],'legacy callback resolves to correct controller/method');
check(str_ends_with($dir,'/asaas/controllers/gateways/'),'callback resolves in gateways subdirectory');
class APP_Controller {public $input;public $output;public $load;public $asaas_gateway;public $app_modules;public function __construct(){} }
class RouteOutput { public $status;public array $headers=[];public $body;public function set_content_type(...$x){return $this;}public function set_status_header($x){$this->status=$x;return $this;}public function set_header($x){$this->headers[]=$x;return $this;}public function set_output($x){$this->body=$x;return $this;} }
require FCPATH.'modules/asaas/controllers/gateways/Callback.php';
foreach(['GET','HEAD','OPTIONS','PUT','DELETE'] as $method){
    $c=new Callback();$c->input=new class($method){public function __construct(private $verb){}public function method($x){return $this->verb;}public function request_headers(){throw new RuntimeException('headers not needed');}};
    $c->load=new class{public function library(...$x){throw new RuntimeException('GET must not initialize gateway');}};$c->output=new RouteOutput();
    $c->index();
    check($c->output->status===405,'non-POST is 405: '.$method);
    check($c->output->body==='Método não permitido.','response fixed no data: '.$method);
    check(in_array('Allow: POST',$c->output->headers)&&in_array('Cache-Control: no-store',$c->output->headers),'noncacheable method policy: '.$method);
}
$c=new Callback();$c->input=new class{public function method($x){return 'POST';}public function request_headers(){return [];}};
$c->app_modules=new class{public function is_active($name){return true;}};
$c->load=new class{public function library(...$x){throw new RuntimeException('sensitive initialization detail');}};$c->output=new RouteOutput();$c->index();
check($c->output->status===503&&$c->output->body==='Falha temporária ao receber o evento.','gateway init failure sanitized');
$c->load=new class{public array $names=[];public function library($name){$this->names[]=$name;}};
$c->asaas_gateway=new class {public array $calls=[];public array $result=['processed'=>true,'http_status'=>200,'message'=>'sensitive synthetic data'];public function handle_webhook($headers,$body){$this->calls[]=[$headers,$body];return $this->result;}};
$c->input=new class{public function method($x){return 'POST';}public function request_headers(){return ['Asaas-Access-Token'=>'synthetic-test-value'];}};
$c->output=new RouteOutput();$c->index();
check($c->output->status===200&&$c->output->body==='OK','successful POST keeps existing processed/HTTP semantics without echoing gateway details');
check($c->load->names===['asaas/asaas_gateway'],'explicit HMVC library resolution');
check(count($c->asaas_gateway->calls)===1&&$c->asaas_gateway->calls[0][0]===['asaas-access-token'=>'synthetic-test-value'],'headers passed unchanged except existing lowercase normalization');
foreach([400,401,403,409,413,503] as $status){$c->asaas_gateway->result=['processed'=>false,'http_status'=>$status,'message'=>'sensitive synthetic data'];$c->output=new RouteOutput();$c->index();check($c->output->status===$status&&strpos($c->output->body,'sensitive')===false,'gateway failure status preserved and body sanitized');}
$c->asaas_gateway->result=['processed'=>false,'http_status'=>200];$c->output=new RouteOutput();$c->index();check($c->output->status===503,'no false success introduced');
check(!str_contains(file_get_contents(FCPATH.'modules/asaas/controllers/gateways/Callback.php'),'PAYMENT_CONFIRMED'),'no financial event policy in controller');
echo "OK: {$checks} callback routing/controller assertions; real HMVC routes, synthetic requests only.\n";
