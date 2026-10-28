<?php
require __DIR__.'/support.php';

function db_prefix(){return'a1_';}
function _l($key){return$key;}
function admin_url($path=''){return'/admin/'.$path;}
function set_alert($type,$message):void{$GLOBALS['a1_alert']=[$type,$message];}
function redirect($url,$unused='location',$status=302):void{$GLOBALS['a1_redirect']=[$url,$status];}

final class A1SettingsContext
{
    public function __construct(public int $issuer=10,public int $actor=2){}
    public function issuerId():int{return$this->issuer;}
    public function staffId():int{return$this->actor;}
}
final class CompanySettingsRuntime
{
    public static bool $active=true;public static int $issuer=10;public static int $actor=2;public static bool $edit=true;
    public static function active():bool{return self::$active;}
    public static function issuerId():int{return self::$issuer;}
    public static function staffId():int{return self::$actor;}
    public static function can(string $capability):bool{return$capability==='view'||($capability==='edit'&&self::$edit);}
}
final class CompanyRequestScope
{
    public static A1SettingsContext $context;
    public static function nativeFor($ci):bool{return true;}
    public static function context():A1SettingsContext{return self::$context;}
    public static function assertNativeMutation($ci):void
    {
        if($ci->input->verb!=='POST'||($ci->input->body['_native_company_csrf']??'')!=='native-csrf'||($ci->input->body['_company_context']??'')!=='context-10')throw new DomainException('native_company_csrf_invalid');
    }
    public static function invalidateCaches($ci):void{$GLOBALS['a1_invalidations']=($GLOBALS['a1_invalidations']??0)+1;}
    public static function nativeCsrfToken($ci):string{return'native-csrf';}
    public static function token():string{return'context-10';}
}
final class A1SettingsInput
{
    public string $verb='GET';public array $query=['group'=>'certificates'];public array $body=[];
    public function method($upper=true):string{return$this->verb;}
    public function get($name,$filter=false){return$this->query[$name]??null;}
    public function post($name=null,$filter=false){return$name===null?$this->body:($this->body[$name]??null);}
}
final class A1SettingsOutput
{
    public int $status=200;public array $headers=[];
    public function set_header($value){$this->headers[]=$value;return$this;}
    public function set_status_header($value){$this->status=(int)$value;return$this;}
    public function set_content_type($value){return$this;}
    public function set_output($value){return$this;}
}
final class A1SettingsSession
{
    public array $flash=[];
    public function set_flashdata($key,$value):void{$this->flash[$key]=$value;}
    public function flashdata($key){return$this->flash[$key]??null;}
}
final class A1SettingsLoad
{
    public array $views=[];public object $owner;
    public function library($name):void{$this->owner->encryption=a1encryption();}
    public function helper($names):void{}
    public function view($name,$data):void{$this->views[]=[$name,$data];}
}
final class A1SettingsCi
{
    public $db,$input,$output,$session,$load,$config,$security,$encryption;
    public function __construct($db)
    {
        $this->db=$db;$this->input=new A1SettingsInput();$this->output=new A1SettingsOutput();$this->session=new A1SettingsSession();
        $this->load=new A1SettingsLoad();$this->load->owner=$this;
        $this->config=new class{public function item($key){return config_item($key);}};
        $this->security=new class{public function get_csrf_token_name(){return'csrf_token';}public function get_csrf_hash(){return'framework-csrf';}};
    }
}

require_once APPPATH.'services/NativeCompanySettingsController.php';
$db=a1db();$db->query("INSERT INTO a1_issuers_resource_permissions VALUES(10,2,'settings','view'),(20,2,'settings','view')");
CompanyRequestScope::$context=new A1SettingsContext();

$ci=new A1SettingsCi($db);NativeCompanySettingsController::index($ci);$view=end($ci->load->views);
a1check($view[0]==='admin/settings/all'&&$view[1]['group']['id']==='certificates','official Settings shell owns the certificate group');
a1check($view[1]['native_settings_certificate']['status']==='unconfigured'&&$view[1]['native_settings_revision']==='0','GET exposes only the selected company safe descriptor and revision');
a1check(in_array('Cache-Control: no-store',$ci->output->headers,true),'official certificate GET is not cached');

$ci=new A1SettingsCi($db);$ci->input->verb='DELETE';NativeCompanySettingsController::index($ci);
a1check($ci->output->status===403,'certificate mutations accept only the official POST contract');

$ci=new A1SettingsCi($db);$ci->input->verb='POST';$ci->input->body=['action'=>'remove','certificate_password'=>'','native_settings_revision'=>'0','_company_context'=>'context-10'];NativeCompanySettingsController::index($ci);
a1check($ci->output->status===403&&a1row($db,10)===null,'missing native CSRF is denied before mutation');

$ci=new A1SettingsCi($db);$ci->input->verb='POST';$ci->input->body=['action'=>'replace','certificate_password'=>'DO-NOT-ECHO','native_settings_revision'=>'0','_native_company_csrf'=>'native-csrf','_company_context'=>'context-10'];NativeCompanySettingsController::index($ci);$view=end($ci->load->views);
a1check($ci->output->status===422&&!str_contains(json_encode($view,JSON_THROW_ON_ERROR),'DO-NOT-ECHO'),'missing multipart upload is rejected without reflecting its password');

$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$fixture=a1fixture();$manager->replace(10,2,0,$fixture['pfx'],$fixture['password']);
$ci=new A1SettingsCi($db);$ci->input->verb='POST';$ci->input->body=['action'=>'remove','certificate_password'=>'','native_settings_revision'=>'0','_native_company_csrf'=>'native-csrf','_company_context'=>'context-10'];NativeCompanySettingsController::index($ci);
a1check($ci->output->status===409&&(int)a1row($db,10)['revision']===1,'stale official Settings revision cannot remove current certificate');

$ci=new A1SettingsCi($db);$ci->input->verb='POST';$ci->input->body=['action'=>'remove','certificate_password'=>'','native_settings_revision'=>'1','_native_company_csrf'=>'native-csrf','_company_context'=>'context-10'];NativeCompanySettingsController::index($ci);
$row=a1row($db,10);a1check($GLOBALS['a1_redirect']===['/admin/settings?group=certificates',303]&&$row['status']==='removed'&&$row['pfx_cipher']===''&&$row['password_cipher']==='','official Settings removes only the selected company material and redirects with303');

CompanySettingsRuntime::$edit=false;$ci=new A1SettingsCi($db);
try{NativeCompanySettingsController::index($ci);throw new RuntimeException('View-only actor reached certificate form');}catch(Throwable$error){a1check($error->getMessage()==='http_403','certificate group stays hidden without current settings.edit');}

$scope=file_get_contents(APPPATH.'services/CompanyRequestScope.php');$settings=file_get_contents(APPPATH.'controllers/admin/Settings.php');
a1check(!str_contains($scope,"'company_certificates'")&&!str_contains($settings,'Company_certificates'),'parallel certificate controller is absent from active admission and Settings dispatch');
echo "Official Settings certificate controller tests complete; no socket opened.\n";
