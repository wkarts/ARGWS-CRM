<?php
require __DIR__.'/pige_bridge_controller.php';
function encodedInvoice(array $event):string{return json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
$event=['schema_version'=>2,'event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','event_type'=>'invoice.requested','scope'=>$scope,'data'=>['charge_id'=>'22222222-2222-4222-8222-222222222222','client_id'=>10,'amount_cents'=>100,'currency'=>'BRL','due_date'=>'2030-02-28','description'=>'Synthetic']];
check(PigeBridgeInvoiceProtocol::decode(encodedInvoice($event))===$event,'strict invoice contract');
foreach(['0','1.5',0,-1,true,2147483648] as $id){$bad=$event;$bad['data']['client_id']=$id;$failed=false;try{PigeBridgeInvoiceProtocol::decode(encodedInvoice($bad));}catch(Throwable $e){$failed=true;}check($failed,'invalid client denied');}
$bad=$event;$bad['data']['issuer_id']='9';$failed=false;try{PigeBridgeInvoiceProtocol::decode(encodedInvoice($bad));}catch(Throwable $e){$failed=true;}check($failed,'payload cannot override issuer');
$permuted=$event;$permuted['scope']=array_reverse($event['scope'],true);$permuted['data']=array_reverse($event['data'],true);check(PigeBridgeInvoiceProtocol::semanticHash($permuted)===PigeBridgeInvoiceProtocol::semanticHash($event),'semantic key order stable');
class InvoiceServiceFake {public $creates=0;public $reads=0;public function create(...$args){$this->creates++;return ['status'=>'invoice_created'];}public function status(...$args){$this->reads++;return ['status'=>'pending'];}}
class InvoiceBridgeFake extends TestBridge {public $service;protected function invoiceService(){return $this->service;}}
$c=new InvoiceBridgeFake();$c->service=new InvoiceServiceFake();$c->app_modules=new ModulesFake();$c->input=new InputFake();$c->output=new OutputFake();$c->uri=new UriFake();$c->load=new LoadFake();
function requestInvoice($c,$event,$path,$secret){$body=encodedInvoice($event);$timestamp=(string)time();$c->uri->path=trim($path,'/');$c->input->raw_input_stream=$body;$c->input->headers=['X-Pige-Timestamp'=>$timestamp,'X-Pige-Event-Id'=>$event['event_id'],'X-Pige-Signature'=>PigeBridgeProtocol::signature($secret,$timestamp,$event['event_id'],'POST',$path,$body)];}
requestInvoice($c,$event,PigeBridgeInvoiceProtocol::PATH,$secret);$c->invoices();check($c->output->status===503&&$c->service->creates===0,'invoice gate defaults closed');
$status=$event;$status['event_type']='invoice.status.requested';$status['data']=['charge_id'=>$event['data']['charge_id']];requestInvoice($c,$status,PigeBridgeInvoiceProtocol::STATUS_PATH,$secret);$c->invoice_status();check($c->output->status===200&&$c->service->reads===1&&$c->service->creates===0,'status works without mutating create gate');
$c->input->verb='GET';$c->invoice_status();check($c->output->status===405&&$c->service->reads===1,'GET does not execute service');$c->input->verb='POST';
requestInvoice($c,$status,PigeBridgeInvoiceProtocol::PATH,$secret);$c->uri->path=trim(PigeBridgeInvoiceProtocol::STATUS_PATH,'/');$c->invoice_status();check($c->output->status===401&&$c->service->reads===1,'signature cannot transfer between create/status paths');
define('APP_PIGE_BRIDGE_INVOICES_ENABLED',true);requestInvoice($c,$event,PigeBridgeInvoiceProtocol::PATH,$secret);$c->invoices();check($c->output->status===200&&$c->service->creates===1,'explicitly gated create dispatch');
echo "PIGE invoice protocol/controller: {$checks} cumulative checks; service fixture only.\n";
