<?php
require __DIR__.'/native_clients.php';
CompanyCustomerAdminSchema::ensure($db,$p);foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(191)');
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(82,12,'customers','view')");
if(!defined('BASEPATH'))define('BASEPATH',__DIR__);
#[AllowDynamicProperties] class AdminController {}
class NativeClientHttpError extends RuntimeException {}
function show_error($message,$status=500){throw new NativeClientHttpError('http_error',(int)$status);}
function show_404(){throw new NativeClientHttpError('not_found',404);}
function _l($key,$args=null){return $key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($path=''){return 'https://fixture.test/admin/'.$path;}
class CompanyRequestScope {
    public static int $issuer=41;
    public static function nativeFor($ci){return $ci instanceof Clients;}
    public static function sealedFor($ci){return self::nativeFor($ci);}
    public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 12;}function isPrimary(){return false;}};}
    public static function nativeCsrfToken($ci){return 'synthetic-native-csrf';}
    public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='synthetic-native-csrf')throw new DomainException('native_csrf_denied');}
    public static function invalidateCaches($ci){}
}
class NativeClientInput {
    public array $post=[],$get=[];public string $method='GET';
    function method($upper=false){return $upper?$this->method:strtolower($this->method);}
    function post($key=null,$filter=null){return $key===null?$this->post:($this->post[$key]??null);}
    function get($key=null){return $key===null?$this->get:($this->get[$key]??null);}
    function is_ajax_request(){return false;}
}
require APPPATH.'controllers/admin/Clients.php';
$controller=new Clients();$controller->db=$db;$controller->input=new NativeClientInput();
$controller->security=new class{function get_csrf_token_name(){return 'csrf_token';}};
$controller->load=new class{public array $views=[];function view($view,$data){$this->views[]=[$view,$data];}function model($name){throw new RuntimeException('global_model_loaded');}};
$controller->clients_model=new class{function __call($method,$args){throw new RuntimeException('global_client_model_used');}};
$controller->output=new class{public string $body='';function set_header($v){return $this;}function set_content_type($v){return $this;}function set_output($v){$this->body=$v;return $this;}};
$controller->index();essentialCheck($controller->load->views[0][0]==='admin/clients/manage','native controller renders original directory view');
$controller->client((string)$nativeA);$profile=end($controller->load->views);
essentialCheck($profile[0]==='admin/clients/client'&&$profile[1]['native_customer_owned']===true&&$profile[1]['client']->website==='https://example.test/a','native controller uses original profile with owned private values');
$controller->input->get=['group'=>'contacts'];$controller->client((string)$nativeA);$contactsView=end($controller->load->views);
essentialCheck($contactsView[1]['tab']['view']==='admin/clients/groups/contacts'&&isset($contactsView[1]['native_contact_metadata']),'native group dispatch chooses original contacts view rather than profile fallback');
$controller->input->get=[];
CompanyRequestScope::$issuer=82;$controller->client((string)$nativeA);$sharedView=end($controller->load->views);
essentialCheck($sharedView[1]['native_customer_owned']===false&&$sharedView[1]['client']->website==='','shared native view never receives owner website');
$controller->get_customer_billing_and_shipping_details($nativeB);$billing=json_decode($controller->output->body,true);
essentialCheck($billing[0]['billing_street']==='A billing','native billing controller keeps original response shape');
try{$controller->get_customer_billing_and_shipping_details($nativeA);throw new RuntimeException('billing leaked');}catch(NativeClientHttpError $error){essentialCheck($error->getCode()===403,'shared billing denied');}
CompanyRequestScope::$issuer=41;$before=$adapterA->details($nativeA);$controller->input->method='POST';$controller->input->post=['company'=>'Forged','revision'=>$before['revision']];
try{$controller->client((string)$nativeA);throw new RuntimeException('CSRF accepted');}catch(NativeClientHttpError $error){essentialCheck($error->getCode()>=400,'native mutation guard invoked before writer');}
essentialCheck($adapterA->details($nativeA)===$before,'CSRF rejection preserves exact row and revision');
echo "Native clients actual controller projections and mutation guards passed.\n";
