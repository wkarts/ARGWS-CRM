<?php
/** Real services/database/native view; only framework request boundary and rendering helpers are explicit doubles. */
require __DIR__.'/native_invoice_project.php';
class CompanyRequestScope
{
    public static int $issuer;public static bool $csrf=true,$contextValid=true;
    public static function nativeFor($ci):bool{return true;}
    public static function assertNativeMutation($ci):void{if($ci->input->method(true)!=='POST'||!self::$csrf||!self::$contextValid)throw new DomainException('native_company_csrf_invalid');}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return 7;}};}
}
#[AllowDynamicProperties]class AdminController{public function __construct(){foreach(get_object_vars($GLOBALS['CI'])as$k=>$v)$this->$k=$v;}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->body=$message;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function _l($key){return $key;}
function app_format_money($amount,$currency){if(!is_object($currency)||!isset($currency->name))throw new RuntimeException('native currency DTO missing');return $currency->name.' '.$amount;}
$ci=$GLOBALS['CI'];
$ci->input=new class{public string $verb='POST';public array $posts=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return $key===null?$this->posts:($this->posts[$key]??null);}};
$ci->security=new class{public function get_csrf_token_name(){return 'synthetic_framework_csrf';}};
$ci->output=new class{public int $status=200;public string $body='';public array $headers=[];public function set_header($header){$this->headers[]=$header;return $this;}public function set_content_type($type){return $this;}public function set_output($body){$this->body=$body;return $this;}};
$ci->load=new class{public array $views=[];public $input;public function model($name){throw new RuntimeException('legacy model hydrated');}public function view($path,$data){$this->views[]=$path;$this->input=$GLOBALS['CI']->input;extract($data,EXTR_SKIP);ob_start();include APPPATH.'views/'.$path.'.php';$GLOBALS['CI']->output->body=ob_get_clean();}};
require_once APPPATH.'controllers/admin/Invoices.php';
CompanyRequestScope::$issuer=$a;$controller=new Invoices();
$ci->input->posts=['currency'=>'1','years'=>['2026'],'project_id'=>$transport,'init_total'=>'true','display_type'=>'badge','synthetic_framework_csrf'=>'synthetic'];
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$controller->get_invoices_total();
essentialCheck($ci->output->status===200&&end($ci->load->views)==='admin/invoices/invoices_total_template','same native totals action renders the actual existing native partial');
essentialCheck(str_contains($ci->output->body,'BRL 30.00')&&str_contains($ci->output->body,'outstanding_invoices'),'native badge receives exact scoped total and retains native text/markup');
essentialCheck(in_array('Cache-Control: no-store',$ci->output->headers,true),'native totals response is never cacheable');
$ci->input->posts['issuer_id']=$b;$controller->get_invoices_total();essentialCheck($ci->output->status===422,'issuer override is rejected rather than dropped');unset($ci->input->posts['issuer_id']);
CompanyRequestScope::$issuer=$b;$controller->get_invoices_total();essentialCheck($ci->output->status===403,'same native URL cannot hydrate A project under B');
CompanyRequestScope::$issuer=$a;
foreach(['csrf','contextValid']as$flag){CompanyRequestScope::${$flag}=false;$controller->get_invoices_total();essentialCheck($ci->output->status===403,'missing native request authority rejects totals before render');CompanyRequestScope::${$flag}=true;}
$ci->input->verb='GET';$controller->get_invoices_total();essentialCheck($ci->output->status===403,'GET cannot fall through to legacy totals loader');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'all totals request cases leave financial rows untouched');
echo 'Native invoice totals controller double/real view: '.$GLOBALS['essentialChecks']." cumulative checks PASS. Not full HTTP/browser proof.\n";
