<?php
/** Controller request double; action and real queues are exercised by the required fixture. */
require __DIR__.'/native_payment_notice_delivery.php';
class CompanyRequestScope
{
 public static int $issuer;public static bool $csrf=true;
 public static function nativeFor($ci):bool{return true;}
 public static function assertNativeMutation($ci):void{if($ci->input->method(true)!=='POST'||!self::$csrf)throw new DomainException('native_company_csrf_invalid');}
 public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return 7;}};}
}
#[AllowDynamicProperties] class AdminController{public function __construct(){foreach(get_object_vars($GLOBALS['CI'])as$key=>$value)$this->$key=$value;}}
class InvoicePaymentHttpError extends RuntimeException{}
if(!function_exists('show_error')){function show_error($message,$status=500){throw new InvoicePaymentHttpError($message,$status);}}
$ci=$GLOBALS['CI']=new stdClass();$ci->db=$db;
$ci->input=new class{public string $verb='GET';public array $posts=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return $key===null?$this->posts:($this->posts[$key]??null);}};
$ci->output=new class{public array $headers=[];public function set_header($h){$this->headers[]=$h;return $this;}};
$ci->load=new class{public string $viewName='';public array $data=[];public function view($name,$data=[]){$this->viewName=$name;$this->data=$data;}public function model($name){throw new RuntimeException('unscoped model invoked');}public function library($name){throw new RuntimeException('library must not load before CSRF validation');}};
require_once APPPATH.'controllers/admin/Invoices.php';CompanyRequestScope::$issuer=$a;$controller=new Invoices();
$testInvoice=$s->create($a,7,$input,hash('sha256','controller-payment-form'));$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$testInvoice]);
$controller->record_invoice_payment_ajax($testInvoice);
essentialCheck($ci->load->viewName==='admin/invoices/record_payment_template'&&$ci->load->data['invoice']->id===$testInvoice&&array_column($ci->load->data['payment_modes'],'id')===[$mode['id']],'original payment template receives scoped issuer data');
if(!function_exists('_l')){function _l($text){return$text;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('admin_url')){function admin_url($path=''){return '/admin/'.$path;}}
if(!function_exists('form_open')){function form_open($url,$attrs=[]){return '<form action="'.e($url).'">';}}
if(!function_exists('form_close')){function form_close(){return '</form>';}}
if(!function_exists('form_hidden')){function form_hidden($name,$value){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_input')){function render_input($name,$label,$value='',$type='text',$attrs=[]){return '<input name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_date_input')){function render_date_input($name,$label,$value=''){return render_input($name,$label,$value);}}
if(!function_exists('_d')){function _d($value){return$value;}}
extract($ci->load->data);ob_start();require APPPATH.'views/admin/invoices/record_payment_template.php';$html=ob_get_clean();
essentialCheck(str_contains($html,'name="_native_payment_key"')&&str_contains($html,'data-native-mode-revision="'.$mode['revision'].'"')&&str_contains($html,'SMS indisponível'),'actual original payment template carries scoped mode revision, retry key and truthful SMS status');
// A second payment also renders the existing receipt table. The child partial
// must keep the scoped contract instead of invoking legacy action helpers.
if(!function_exists('app_format_money')){function app_format_money($amount,$currency){return $amount.' '.$currency;}}
$controller->record_invoice_payment_ajax($formInvoice);
extract($ci->load->data);ob_start();require APPPATH.'views/admin/invoices/record_payment_template.php';$secondHtml=ob_get_clean();
essentialCheck(count($invoice->payments)>0&&str_contains($secondHtml,'class="payment"')&&str_contains($secondHtml,'name="_native_payment_key"'),'second original payment form renders real previous receipts and retains the scoped command');
CompanyRequestScope::$issuer=$b;try{$controller->record_invoice_payment_ajax($testInvoice);throw new RuntimeException('cross issuer accepted');}catch(InvoicePaymentHttpError $e){essentialCheck($e->getCode()===403,'original GET denies another company');}
CompanyRequestScope::$issuer=$a;$ci->input->verb='POST';CompanyRequestScope::$csrf=false;try{$controller->record_payment();throw new RuntimeException('CSRF accepted');}catch(InvoicePaymentHttpError $e){essentialCheck($e->getCode()===403,'original POST denies invalid CSRF before payment and encryption');}
CompanyRequestScope::$csrf=true;$ci->input->verb='GET';try{$controller->record_payment();throw new RuntimeException('GET payment accepted');}catch(InvoicePaymentHttpError $e){essentialCheck($e->getCode()===403,'original action cannot mutate through GET');}
echo 'Original invoice payment controller: scoped GET and CSRF/method boundaries passed using request doubles; no HTTP/browser proof.'.PHP_EOL;
