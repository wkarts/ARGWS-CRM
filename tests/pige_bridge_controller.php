<?php
// Exercise the actual controller action with synthetic framework dependencies; no HTTP/provider calls.
define('BASEPATH', __DIR__);
define('APP_PIGE_BRIDGE_ENABLED', true);
$secret=str_repeat('test-only-',4);
$scope=['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>'7','account_id'=>'wallet_test','environment'=>'sandbox'];
define('APP_PIGE_BRIDGE_CONNECTIONS',['test'=>['enabled'=>true,'scope'=>$scope,'secret'=>$secret]]);
class App_Controller { public $input; public $app_modules; public $output; public $uri; public $load; public $pige_bridge_model; }
function log_activity($message) {}
require_once __DIR__.'/../modules/pige_bridge/controllers/Bridge.php';
class ModulesFake {public $active=true;public function is_active($name){return $this->active;}}
class InputFake { public $raw_input_stream; public $headers=[]; public $verb='POST'; public function method($uppercase=false){return $this->verb;} public function get_request_header($name,$filter=false){return $this->headers[$name]??null;} }
class OutputFake { public $status; public $body; public $headers=[]; public function set_header($h){$this->headers[]=$h;return $this;} public function set_status_header($s){$this->status=$s;return $this;} public function set_content_type($t){return $this;} public function set_output($b){$this->body=json_decode($b,true);return $this;} }
class UriFake {public $path='pige_bridge/api/v1/requests'; public function uri_string(){return $this->path;} }
class LoadFake {public $calls=0; public function model($name){$this->calls++;} }
class ModelFake {public $calls=0;public $fail=null; public function accept_request($key,$event,$raw){$this->calls++;if($this->fail)throw $this->fail;return ['event_id'=>$event['event_id'],'request_id'=>'req_test'];} }
$event=['schema_version'=>1,'event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','event_type'=>'charge.requested','scope'=>$scope,'data'=>['charge_id'=>'22222222-2222-4222-8222-222222222222','amount_cents'=>100,'currency'=>'BRL','due_date'=>'2028-02-29','description'=>'Synthetic']];
$body=json_encode($event);
class TestBridge extends Bridge { protected function openRequestStream(){ $stream=fopen('php://temp','w+b');fwrite($stream,$this->input->raw_input_stream);rewind($stream);return $stream; } }
$c=new TestBridge();$c->app_modules=new ModulesFake();$c->input=new InputFake();$c->output=new OutputFake();$c->uri=new UriFake();$c->load=new LoadFake();$c->pige_bridge_model=new ModelFake();$c->input->raw_input_stream=$body;
$timestamp=(string)time();
$headers=['X-Pige-Timestamp'=>$timestamp,'X-Pige-Event-Id'=>$event['event_id'],'X-Pige-Signature'=>PigeBridgeProtocol::signature($secret,$timestamp,$event['event_id'],'POST',PigeBridgeProtocol::REQUEST_PATH,$body)];
$checks=0;function check($yes,$msg){global $checks;$checks++;if(!$yes)throw new RuntimeException($msg);}
$c->app_modules->active=false;$c->requests();check($c->output->status===503,'inactive module protected');$c->app_modules->active=true;
$c->requests();check($c->output->status===401 && $c->load->calls===0,'auth required before persistence');
$c->input->verb='GET';$c->requests();check($c->output->status===405,'method protected');
$c->input->verb='POST';$c->uri->path='pige_bridge/bridge/requests';$c->requests();check($c->output->status===404,'route alias blocked');
$c->uri->path='pige_bridge/api/v1/requests';$c->input->raw_input_stream=str_repeat('x',16385);$c->requests();check($c->output->status===413,'body limit');
$c->input->raw_input_stream=str_repeat('x', 1024*1024);$c->requests();check($c->output->status===413 && $c->load->calls===0,'large stream rejected before persistence');
$c->input->raw_input_stream=$body;$c->input->headers=$headers;$c->requests();
check($c->output->status===202,'receipt accepted');check($c->output->body['status']==='blocked' && $c->output->body['capabilities']['issue_charge']===false,'accepted is never issued');
check($c->pige_bridge_model->calls===1,'single persistence call');
$c->pige_bridge_model->fail=new DomainException('idempotency_conflict');$c->requests();check($c->output->status===409,'conflict status');
$c->pige_bridge_model->fail=new RuntimeException('secret sql details');$c->requests();check($c->output->status===503 && strpos(json_encode($c->output->body),'secret')===false,'storage failure sanitized');
check(in_array('Cache-Control: no-store',$c->output->headers,true),'no cache');
echo "PIGE bridge controller: {$checks} checks passed (framework stubs)\n";
