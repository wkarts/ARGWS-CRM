<?php
/** Actual native controller, service, view and input helper; only framework transport/labels are fixtures. */
require __DIR__.'/native_contact_form.php';
#[AllowDynamicProperties] class AdminController {public function __construct(){}}
class NativeContactHttpError extends RuntimeException{}
function show_error($message,$status=500){throw new NativeContactHttpError('http_error',$status);}
function show_404(){throw new NativeContactHttpError('not_found',404);}
function _l($key,$args=null,$log=true){return $key;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($path=''){return 'https://fixture.test/admin/'.$path;}
function base_url($path=''){return 'https://fixture.test/'.$path;}
function site_url($path=''){return 'https://fixture.test/'.$path;}
function form_open($url,$attributes=[]){$html='<form action="'.e($url).'" method="post"';foreach($attributes as$key=>$value)$html.=' '.e($key).'="'.e($value).'"';return$html.'>';}
function form_close(){return '</form>'; }
function form_hidden($name,$value){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function set_value($name,$value=''){return e($value);}
function _dt($value){return e($value);}
function time_ago($value){return e($value);}
function get_option($name){throw new LogicException('global option forbidden');}
function total_rows(...$args){throw new LogicException('global count forbidden');}
function get_company_name(...$args){throw new LogicException('global customer projection forbidden');}
function has_contact_permission(...$args){throw new LogicException('global contact permission forbidden');}
function is_email_template_active(...$args){throw new LogicException('global template lookup forbidden');}
class CompanyRequestScope{
 public static int $issuer;public static int $actor=7;
 public static function nativeFor($ci){return $ci instanceof Clients || $ci instanceof Gdpr;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}function isPrimary(){return false;}};}
 public static function nativeCsrfToken($ci){return 'synthetic-native-csrf';}
 public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='synthetic-native-csrf')throw new DomainException('native_csrf_denied');}
 public static function invalidateCaches($ci){}
}
class NativeContactHttpInput{public array $body=[];public string $verb='GET';function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}function post($key=null,$filter=null){return$key===null?$this->body:($this->body[$key]??null);}function ip_address(){return '127.0.0.1';}}
require APPPATH.'helpers/fields_helper.php';require APPPATH.'helpers/custom_fields_helper.php';require APPPATH.'controllers/admin/Clients.php';
$controller=new Clients();$controller->db=$db;$controller->input=new NativeContactHttpInput();$GLOBALS['CI']=$controller;CompanyRequestScope::$issuer=$a;
$controller->security=new class{function get_csrf_token_name(){return 'ci_csrf';}};
$controller->config=new class($portalSecret){function __construct(private $secret){}function item($key){return$key==='encryption_key'?$this->secret:null;}};
$controller->encryption=new class{function decrypt($v){return 'native:'.$v;}};
$controller->clients_model=new class{function __call($m,$a){throw new LogicException('legacy client model forbidden');}};
$controller->output=new class{public string $body='';public int $status=200;public array $headers=[];function set_status_header($v){$this->status=$v;return$this;}function set_header($v){$this->headers[]=$v;return$this;}function set_content_type($v){return$this;}function set_output($v){$this->body=$v;return$this;}};
$controller->load=new class{public string $html='';public array $data=[];function library($name){if($name!=='encryption')throw new LogicException('unexpected library');}function view($view,$data){$this->data=$data;extract($data);ob_start();try{include APPPATH.'views/'.$view.'.php';$this->html=ob_get_contents();}finally{ob_end_clean();}}};
$start=$GLOBALS['essentialChecks'];$controller->form_contact($client,(string)$id);
essentialCheck($controller->output->status===200&&str_contains($controller->load->html,'clients/form_contact/'.$client.'/'.$id)&&str_contains($controller->load->html,'native_contact_fields_revision'),'same native modal rendered from actual controller without global helpers');
essentialCheck(str_contains($controller->load->html,'name="firstname"')&&str_contains($controller->load->html,'name="send_set_password_email"')&&str_contains($controller->load->html,'name="permissions[]"')&&str_contains($controller->load->html,'name="profile_image"'),'native identity, permissions, notification and upload controls retained');
$controller->input->verb='POST';$controller->input->body=['client_id'=>(string)$client,'userid'=>(string)$id,'email'=>'unused@example.invalid'];$controller->contact_email_exists();essentialCheck($controller->output->status===422,'contact email probe requires current native form token');
$controller->input->body['_native_company_csrf']='synthetic-native-csrf';$controller->contact_email_exists();essentialCheck(json_decode($controller->output->body,true)===true,'same native contact email validation accepts unused owner address');
CompanyRequestScope::$issuer=$b;$controller->input->verb='GET';$controller->form_contact($client,(string)$id);essentialCheck($controller->output->status===403&&!json_decode($controller->output->body,true)['success'],'foreign modal denied before native data hydration');
CompanyRequestScope::$issuer=$a;$fresh=$form->data($client,$id);$controller->input->verb='POST';$controller->input->body=$input;
unset($controller->input->body['userid']);$controller->input->body['native_contact_revision']=$fresh['native_contact_revision'];$controller->input->body['native_contact_fields_revision']=$fresh['native_contact_fields_revision'];$controller->input->body['_native_company_csrf']='synthetic-native-csrf';$controller->input->body['title']='Saved by actual controller';$_FILES=[];
$controller->form_contact($client,(string)$id);$result=json_decode($controller->output->body,true);essentialCheck(($result['success']??false)&&$form->data($client,$id)['contact']->title==='Saved by actual controller','actual native POST saves through owner transaction and native response shape');
$controller->form_contact($client,(string)$id);essentialCheck($controller->output->status===409,'same native POST stale revision rejected');
echo 'Native contact controller/modal: '.($GLOBALS['essentialChecks']-$start).' checks passed; actual PHP view, no browser claim.'.PHP_EOL;
$start=$GLOBALS['essentialChecks'];$controller->input->body=['draw'=>'3','length'=>'25','order'=>[['column'=>'0','dir'=>'asc']],'search'=>['value'=>'Form']];$controller->contacts($client);$table=json_decode($controller->output->body,true);
essentialCheck($table['draw']===3&&count($table['data'])===1&&str_contains($table['data'][0][0],'onclick="contact('),'native contacts endpoint returns original table/modal action and filters owned rows');
essentialCheck(str_contains($table['data'][0][4],'native-contact-status')&&!str_contains($table['data'][0][4],'data-switch-url'),'status uses same native toggle without global GET mutation handler');
$service=new CompanyNativeContacts($db,$p);$state=$service->get($a,7,$client,$id);$controller->input->body=['client_id'=>(string)$client,'revision'=>$state['revision']];
try{$controller->change_contact_status($id,'0');throw new RuntimeException('tokenless mutation accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'status requires native mutation token');}
$controller->input->body['_native_company_csrf']='synthetic-native-csrf';$controller->change_contact_status($id,'0');essentialCheck((int)$service->get($a,7,$client,$id)['contact']['active']===0,'native status action commits owned contact revocation');
try{$controller->change_contact_status($id,'1');throw new RuntimeException('stale status accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===409,'status requires current contact revision');}
CompanyRequestScope::$issuer=$b;try{$controller->contacts($client);throw new RuntimeException('foreign table accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===403,'foreign tenant cannot hydrate native contacts table');}CompanyRequestScope::$issuer=$a;
echo 'Native contact table/status controller: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
