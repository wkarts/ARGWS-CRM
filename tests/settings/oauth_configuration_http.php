<?php
require __DIR__.'/oauth_unconfigured_sender.php';
function show_error($message,$status=500){$GLOBALS['oauthHttpErrors'][]=$status;}
function redirect($url,$method='location',$status=302){$GLOBALS['oauthHttpRedirect']=[$url,$status];}
class CompanyRequestScope{
    public static int $issuer;public static int $actor=7;
    static function nativeFor($ci){return true;}static function sealedFor($ci){return true;}
    static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
    static function adminUrl($ci,$path){return 'https://fixture.invalid/admin/'.$path;}
    static function invalidateCaches($ci){}static function scrubDeniedMailCallback($ci){}
}
#[AllowDynamicProperties] class AdminController{function __construct(){}}
require APPPATH.'controllers/admin/Settings.php';
$controller=new Settings();$controller->db=$db;
$controller->encryption=new class{function encrypt($v){return 'sealed:'.base64_encode($v);}function decrypt($v){return base64_decode(substr($v,7),true);}};
$controller->input=new class{public string $verb='GET';public array $body=[];function method($upper=true){return $this->verb;}function post($key=null,$filter=false){return $key===null?$this->body:($this->body[$key]??null);}function get($key,$filter=false){return null;}};
$controller->session=new class{function userdata($key){return str_repeat('a',64);}function flashdata($key){return null;}};
$controller->output=new class{public int $status=200;function set_header($value){return $this;}function set_status_header($value){$this->status=$value;return $this;}};
$controller->load=new class{public array $views=[];function library($name){}function view($name,$data){$this->views[]=[$name,$data];}};
foreach(['google','microsoft'] as $provider){
    $action=$provider.'_mail_oauth';$csrf='company_'.$provider.'_mail_csrf';
    foreach([$primary,41] as $owner){
        CompanyRequestScope::$issuer=$owner;CompanyRequestScope::$actor=7;
        $call=fn()=>CompanySettingsRuntime::run($db,$p,$owner,7,fn()=>$controller->$action());
        $store->mutate($owner,7,'update','smtp_email','',null);
        $controller->input->verb='GET';$controller->input->body=[];$GLOBALS['oauthHttpErrors']=[];
        $call();$page=end($controller->load->views);
        essentialCheck($GLOBALS['oauthHttpErrors']===[]&&$page[0]==='admin/settings/native_'.$provider.'_mail_oauth'&&$page[1]['profile']['sender_email']==='','actual '.$action.' GET renders incomplete primary/secondary configuration');
        $controller->input->verb='POST';$controller->input->body=[$csrf=>'bad','action'=>'save'];$call();
        essentialCheck(end($GLOBALS['oauthHttpErrors'])===403,'actual '.$action.' rejects CSRF');
        $controller->input->body=[$csrf=>str_repeat('a',64),'action'=>'save','revision'=>(string)$page[1]['profile']['revision'],'client_id'=>$provider==='google'?'syntheticclient.apps.googleusercontent.com':'22222222-2222-4222-8222-222222222222','tenant_id'=>'11111111-1111-4111-8111-111111111111','client_secret'=>'new-synthetic-secret'];
        $call();essentialCheck($controller->output->status===422&&end($controller->load->views)[1]['error']!==null,'missing sender remains recoverable and cannot save');
        $store->mutate($owner,7,'update','smtp_email','sender-'.$owner.'@example.invalid',null);
        $GLOBALS['oauthHttpRedirect']=null;$call();essentialCheck($GLOBALS['oauthHttpRedirect']===[CompanyRequestScope::adminUrl($controller,'settings/'.$action),303],'valid actual OAuth settings save redirects to same endpoint');
        $call();essentialCheck($controller->output->status===409,'obsolete OAuth HTTP revision rejected');
    }
    CompanyRequestScope::$issuer=82;CompanyRequestScope::$actor=14;$controller->input->verb='GET';$GLOBALS['oauthHttpErrors']=[];$before=count($controller->load->views);
    $controller->$action();essentialCheck($GLOBALS['oauthHttpErrors']===[403]&&count($controller->load->views)===$before,'unauthorized OAuth GET never renders another company');
}
echo "Actual Google/Microsoft configuration controllers: primary/secondary GET, valid POST, CSRF, stale, recoverable sender and denied ACL passed.\n";
