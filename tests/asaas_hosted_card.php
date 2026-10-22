<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require __DIR__.'/../modules/asaas/libraries/Asaas/HostedCardCheckout.php';
use Asaas\HostedCardCheckout as Card;
$checks=0;
function check($condition,$message){global $checks;++$checks;if(!$condition)throw new RuntimeException($message);}
function fails($action,$expected=null){try{$action();}catch(Throwable $e){check($expected===null||$e->getMessage()===$expected,'Unexpected failure: '.$e->getMessage());return;}check(false,'Expected rejection');}
check(Card::allowsInvoice(serialize(['asaas','other'])),'serialized explicit allowed mode');
check(Card::allowsInvoice(['asaas']),'array allowed mode');
foreach ([null,'garbage',serialize(['stripe']),serialize(['Asaas']),serialize((object)['asaas'])] as $modes) check(!Card::allowsInvoice($modes),'invalid or unrelated modes fail closed');
foreach ([2,'2',0,null,'1e0',true,1.0,[],['1']] as $count) fails(fn()=>Card::installments($count));
check(Card::installments(1)===1 && Card::installments('1')===1,'explicit single installment only');
$invoice=['id'=>1,'hash'=>'invoice_test_hash','duedate'=>'2026-10-20'];
$payload=Card::payload($invoice,'cus_test',19.99,1);
check(array_keys($payload)===['customer','billingType','dueDate','value','externalReference'],'hosted payload exact no card data');
check($payload['billingType']==='CREDIT_CARD'&&$payload['value']===19.99,'hosted amount and type');
foreach ([0,-1,'NaN',INF,1.001,[]] as $amount) fails(fn()=>Card::payload($invoice,'cus_test',$amount,1));
fails(fn()=>Card::payload(array_replace($invoice,['duedate'=>'2026-02-31']),'cus_test',1,1));
fails(fn()=>Card::payload($invoice,'',1,1));
$payment=['id'=>'pay_test','billingType'=>'CREDIT_CARD','externalReference'=>$invoice['hash'],'invoiceUrl'=>'https://sandbox.asaas.com/i/test_checkout'];
check(Card::verifiedUrl($payment,'sandbox',$invoice['hash'])===$payment['invoiceUrl'],'canonical hosted link accepted');
check(Card::verifiedUrl(array_replace($payment,['billingType'=>'UNDEFINED']),'sandbox',$invoice['hash'])===$payment['invoiceUrl'],'canonical UNDEFINED supports card');
foreach (['BOLETO','PIX','CREDIT_CARD_FAKE'] as $type) fails(fn()=>Card::verifiedUrl(array_replace($payment,['billingType'=>$type]),'sandbox',$invoice['hash']));
fails(fn()=>Card::verifiedUrl($payment,'production',$invoice['hash']));
fails(fn()=>Card::verifiedUrl($payment,'invalid',$invoice['hash']));
fails(fn()=>Card::verifiedUrl($payment,'sandbox','another_invoice'));
fails(fn()=>Card::verifiedUrl($payment+['deleted'=>true],'sandbox',$invoice['hash']));
foreach (['http://sandbox.asaas.com/i/a','https://sandbox.asaas.com.attacker.test/i/a','https://user@sandbox.asaas.com/i/a','https://sandbox.asaas.com:443/i/a','https://sandbox.asaas.com/i/a?token=bad','https://sandbox.asaas.com/i/a#bad','https://sandbox.asaas.com/i/../bad','javascript:bad','https://sandbox.asaas.com/other/a'] as $url) fails(fn()=>Card::verifiedUrl(array_replace($payment,['invoiceUrl'=>$url]),'sandbox',$invoice['hash']));
check(Card::verifiedUrl(array_replace($payment,['invoiceUrl'=>'https://www.asaas.com/i/test']),'production',$invoice['hash'])==='https://www.asaas.com/i/test','production host isolated');

class ClientsController {public $db,$input,$session,$asaas_gateway,$rendered=[],$viewName;function data($data){$this->rendered=$data;}function view($name){$this->viewName=$name;}function layout(){}}
class InputStub {public $method='GET',$post=[];function method($upper=false){return $this->method;}function post($key){return $this->post[$key]??null;}}
class SessionStub {public $values=[];function userdata($key){return $this->values[$key]??null;}function set_userdata($key,$value){$this->values[$key]=$value;}}
class DbStub {public $invoice;function where($key,$value){return $this;}function get($table){return $this;}function row(){return $this->invoice;}}
class OriginStub {public $origin=['state'=>'issued','remote_id'=>'pay_test','environment'=>'sandbox'];function byInvoice($id){return $this->origin;}}
class AdapterStub {public $store;function __construct(){$this->store=new OriginStub();}function originStore(){return $this->store;}}
class GatewayStub {public $calls=[],$settings=['active'=>'1','card_only'=>'1'],$adapter,$response;function __construct(){$this->adapter=new AdapterStub();$this->response=['id'=>'pay_test','billingType'=>'CREDIT_CARD','invoiceUrl'=>'https://sandbox.asaas.com/i/test'];}function getSetting($key){return $this->settings[$key]??'';}function getSettingForOrigin($key,$origin){return $this->getSetting($key);}function getAdapter(){return $this->adapter;}function charge_credit_card($data){$this->calls[]=$data;return json_encode($this->response);}}
function db_prefix(){return 'tbl';}function check_invoice_restrictions($id,$hash){$GLOBALS['restrictions'][]=[$id,$hash];}function show_error($text,$status){throw new RuntimeException((string)$status);}function show_404(){throw new RuntimeException('404');}function redirect($url){$GLOBALS['redirect']=$url;}function site_url($path){return 'https://crm.example.test/'.$path;}
require __DIR__.'/../modules/asaas/controllers/Checkout.php';
function controller(){ $c=(new ReflectionClass(Checkout::class))->newInstanceWithoutConstructor();$c->db=new DbStub();$c->db->invoice=(object)['id'=>1,'hash'=>'invoice_test_hash','status'=>1,'allowed_payment_modes'=>serialize(['asaas'])];$c->input=new InputStub();$c->session=new SessionStub();$c->asaas_gateway=new GatewayStub();return $c; }
$c=controller();$c->cartao('invoice_test_hash');check($c->viewName==='asaas/cartao','hosted view GET');check($c->asaas_gateway->calls===[],'GET never creates charge');check(strlen($c->rendered['csrf_token'])===64,'own CSRF generated');check(count($GLOBALS['restrictions'])===1,'invoice restrictions verified');
$c->input->method='POST';fails(fn()=>$c->cartao('invoice_test_hash'),'403');
$c->input->post=['hosted_card_csrf'=>$c->rendered['csrf_token'],'installmentCount'=>'1'];
foreach (['holderName','cardNumber','expirationMonth','expirationYear','securityCode','card','creditCard','creditCardToken','creditCardHolderInfo'] as $key){$c->input->post[$key]='synthetic-forbidden';fails(fn()=>$c->cartao('invoice_test_hash'),'400');unset($c->input->post[$key]);}
check($c->asaas_gateway->calls===[],'raw input blocked before gateway');
$c->input->post['installmentCount']='2';$c->cartao('invoice_test_hash');check($c->asaas_gateway->calls===[],'multiple installments blocked before gateway');
$c->input->post['installmentCount']='1';$c->cartao('invoice_test_hash');check($GLOBALS['redirect']==='https://sandbox.asaas.com/i/test','redirect canonical origin environment');check(array_keys($c->asaas_gateway->calls[0])===['invoice','installmentCount'],'only invoice metadata reaches gateway');
$GLOBALS['redirect']=null;$c->asaas_gateway->response['invoiceUrl']='https://attacker.test/i/a';$c->cartao('invoice_test_hash');check($GLOBALS['redirect']===null,'unsafe redirect blocked');
$c->asaas_gateway->response['invoiceUrl']='https://sandbox.asaas.com/i/a';$c->asaas_gateway->adapter->store->origin['environment']='production';$c->cartao('invoice_test_hash');check($GLOBALS['redirect']===null,'origin environment used instead of current settings');
foreach ([2,5,6] as $status){$c=controller();$c->db->invoice->status=$status;fails(fn()=>$c->cartao('invoice_test_hash'),'409');}
foreach (['active','card_only'] as $flag){$c=controller();$c->asaas_gateway->settings[$flag]='0';fails(fn()=>$c->cartao('invoice_test_hash'),'409');}
$c=controller();$c->db->invoice->allowed_payment_modes=serialize(['stripe']);fails(fn()=>$c->cartao('invoice_test_hash'),'409');
$c=controller();$c->db->invoice=null;fails(fn()=>$c->cartao('unknown'),'404');fails(fn()=>$c->cartao('../bad'),'404');
function html_escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function format_invoice_number($id){return 'INV-'.$id;}function form_open($url,$attrs=[]){return '<form method="post" action="'.html_escape($url).'">';}function form_close(){return '</form>';}
$c=controller();$c->cartao('invoice_test_hash');extract($c->rendered);ob_start();require __DIR__.'/../modules/asaas/views/cartao.php';$html=ob_get_clean();
foreach(['cardNumber','securityCode','expiryMonth','holderName','creditCardToken'] as $field) check(!str_contains($html,'name="'.$field.'"'),'no raw card fields');
check(str_contains($html,'hosted_card_csrf')&&str_contains($html,'Pagar com cartão no Asaas'),'hosted POST form');
$controllerSource=file_get_contents(__DIR__.'/../modules/asaas/controllers/Checkout.php');$cardMethod=substr($controllerSource,strpos($controllerSource,'public function cartao'),strpos($controllerSource,'public function qrcode')-strpos($controllerSource,'public function cartao'));
check(!str_contains($cardMethod,'addPayment')&&!str_contains($cardMethod,'log_activity'),'checkout never settles or logs card input');
$gatewaySource=file_get_contents(__DIR__.'/../modules/asaas/libraries/Asaas_gateway.php');check(!str_contains($gatewaySource,'ac_a_ccta'),'no legacy raw card storage path');
echo "Asaas hosted card: {$checks} checks passed\n";
