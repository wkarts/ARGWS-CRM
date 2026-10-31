<?php
/** Real controller, projection, original views and SQLite; shell/form transport/CSRF are explicit boundary spies; general and fields helpers are production. */
declare(strict_types=1);
define('BASEPATH', true); define('APPPATH', __DIR__.'/../../application/');
require APPPATH.'vendor/neitanod/forceutf8/src/ForceUTF8/Encoding.php';
require APPPATH.'helpers/general_helper.php';require APPPATH.'helpers/fields_helper.php';require APPPATH.'helpers/template_helper.php';
require __DIR__.'/ticket_create.php';
function &get_instance(){return $GLOBALS['ci'];}
function html_escape($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_html($v){return strip_tags($v)!==$v;}
function set_value($name,$value){return html_escape($value);}
function db_prefix(){return 'tbl';} function e($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
function site_url($v=''){return 'https://crm.invalid/'.$v;} function admin_url($v=''){return '/admin/'.$v;}
function get_option(...$args){throw new RuntimeException('global options forbidden');} function hooks(){throw new RuntimeException('global hooks forbidden');}
function is_admin(){throw new RuntimeException('global privilege helper forbidden');}
function init_head(){} function init_tail(){} function form_close(){return '</form>';}
function form_open_multipart($url,$attrs=[]){return form_open($url,['enctype'=>'multipart/form-data']+$attrs);}
function form_open($url,$attrs=[]){$html='<form action="'.e($url).'"';foreach($attrs as $key=>$value)$html.=' '.e($key).'="'.e($value).'"';return $html.'>';}
function form_error($name){return '';}
function show_error($message,$status=500){throw new DomainException('http_'.$status);}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function file_upload_max_size(){return 1024;} function get_mime_by_extension($ext){return 'application/octet-stream';}
require APPPATH.'helpers/tickets_helper.php';
function redirect($url,$method,$status){$GLOBALS['ticketRedirect']=[$url,$status];}
class CompanyRequestScope {
    static bool $valid=true; static int $issuer=2,$actor=17;
    static function nativeFor($ci){return true;} static function sealedFor($ci){return self::$valid;}
    static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
    static function token(){return 'synthetic-context';} static function nativeCsrfToken($ci){return 'synthetic-csrf';}
    static function assertNativeMutation($ci){if(!self::$valid || $ci->input->verb!=='POST' || ($ci->input->data['_native_company_csrf']??'')!=='synthetic-csrf')throw new DomainException('csrf_denied');}
    static function adminUrl($ci,$path){return '/admin/'.$path.'?_company_context=synthetic-context';}
}
class NativeCompanyAdminPresentation {static function label($key){return $key;}static function prepare($ci){kc(CompanyRequestScope::sealedFor($ci),'shell attestation boundary');}}
class TicketAddInput {public array $data=[],$query=[];public string $verb='GET';function method(){return $this->verb;}function post($key=null,$x=false){return $key===null?$this->data:($this->data[$key]??null);}function get($key){return $this->query[$key]??null;}}
class TicketAddOutput {public string $body='';function set_content_type($v){return $this;}function set_output($v){$this->body=$v;return $this;}}
class TicketAddLoader {public string $html=''; public array $shared=[]; public $load,$uri;function __construct(){$this->load=$this;$this->uri=new class{function uri_string(){return 'admin/tickets/add';}};}function model($v){throw new RuntimeException('global models forbidden');}function view($path,$data=[]){$this->shared=array_merge($this->shared,$data);extract($this->shared);if($path==='admin/tickets/services/service'){require APPPATH.'views/'.$path.'.php';return;}kc($path==='admin/tickets/add','same real add template');ob_start();require APPPATH.'views/'.$path.'.php';$this->html=ob_get_clean();}}
$db->query("ALTER TABLE tblstaff ADD COLUMN firstname TEXT NOT NULL DEFAULT 'Test'");$db->query("ALTER TABLE tblstaff ADD COLUMN lastname TEXT NOT NULL DEFAULT 'Staff'");
$db->query('CREATE TABLE tblcompany_projects(id INTEGER PRIMARY KEY,issuer_id INTEGER,client_id INTEGER,name TEXT)');$db->query('CREATE TABLE tblprojects(id INTEGER PRIMARY KEY,clientid INTEGER,name TEXT)');
$db->query("INSERT INTO tblcompany_projects VALUES(1,2,2,'A project'),(2,3,3,'B project')");
$db->query('UPDATE tblcompany_kb_articles SET active=1,staff_article=0 WHERE id=1');
$ci=(object)['lang'=>new class{function line($v,$log=true){return $v;}},'db'=>$wrapped,'input'=>new TicketAddInput(),'output'=>new TicketAddOutput(),'load'=>new TicketAddLoader()];
require APPPATH.'helpers/custom_fields_helper.php';
$customDefinition=NativeCompanyCustomFields::definition(['fieldto'=>'tickets','name'=>'Actual ticket field','type'=>'input']);
$db->query('INSERT INTO tblcompany_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[2,'tickets','native_ticket_field',json_encode($customDefinition,JSON_THROW_ON_ERROR)]);
$customId=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
require APPPATH.'services/NativeCompanyTicketPageController.php';
$ci->router=new class{function fetch_method(){return 'add';}};
#[AllowDynamicProperties] class AdminController{function __construct(){foreach(get_object_vars($GLOBALS['ci'])as$k=>$v)$this->$k=$v;}}
require APPPATH.'controllers/admin/Tickets.php';
$page=new Tickets();$page->add();$html=$ci->load->html;
kc(str_contains($html,'id="new_ticket_form"')&&str_contains($html,'name="_native_ticket_request"')&&str_contains($html,'id="ticket_contact_w"'),'real native add DOM and durable nonce preserved');
kc(!str_contains($html,'Legacy primary')&&!str_contains($html,'B support')&&!str_contains($html,'B subject'),'real add projection excludes other issuer/global data');
kc(str_contains($html,'?issuer_id=2')&&str_contains($html,'data-public-url='),'public KB URL explicit issuer');
kc(str_contains($html,'name="custom_fields[tickets]['.$customId.']"'),'native custom field boundary retained');
$ci->input->verb='POST';$ci->input->data=['contact_id'=>'2','_native_company_csrf'=>'synthetic-csrf'];$page->ticket_change_data();$response=json_decode($ci->output->body,true);kc($response['contact_data']['ticket_emails']===1&&$response['contact_data']['email']==='a@example.invalid'&&$response['customer_has_projects']===true,'real contact DTO email flag and project visibility');
$ci->input->data['contact_id']='3';$deny(fn()=>$page->ticket_change_data());$ci->input->data['contact_id']='2';$ci->input->data['_native_company_csrf']='bad';$deny(fn()=>$page->ticket_change_data());
$ci->input->data=$input+['_native_company_csrf'=>'synthetic-csrf','_native_ticket_request'=>str_repeat('7',64)];$_FILES=[];$page->add();$redirect=$GLOBALS['ticketRedirect'];kc($redirect[1]===303&&str_contains($redirect[0],'tickets/ticket/'),'real create command redirect to native ticket');$page->add();kc($GLOBALS['ticketRedirect']===$redirect,'repeated post receipt same target');
$_FILES=['attachments'=>['error'=>[UPLOAD_ERR_OK]]];$deny(fn()=>$page->add());$_FILES=[];
CompanyRequestScope::$valid=false;$deny(fn()=>new Tickets());CompanyRequestScope::$valid=true;
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$page->add());
echo "Ticket add/contact page: actual SQLite/controller/projection/original add template; A/B, CSRF boundary, privilege revocation, replay, upload refusal PASS. Actual general/fields helpers execute with throwing global hooks/options; shell/form transport remain boundary spies; custom-field renderer/service/schema are real, not browser evidence.\n";
