<?php
require __DIR__.'/settings_email.php';
// Real native controllers and OAuth storage; only framework input/output/encryption boundaries are synthetic.
function admin_url($path=''){return 'https://crm.example.test/sub/admin/'.$path;}
function redirect($url,$method='location',$code=302){$GLOBALS['nativeRedirect']=[$url,$code];}
function show_error($message,$status=500){$GLOBALS['nativeError']=$status;}
class CompanyRequestScope{
 public static bool $valid=true;
 public static function nativeFor($ci){return self::$valid;}
 public static function context(){return new class{function issuerId(){return 41;}function staffId(){return 7;}};}
 public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->data['_native_company_csrf']??'')!=='synthetic-csrf')throw new DomainException('csrf');}
 public static function invalidateCaches($ci){}
}
class AdminController{public $input,$output,$db,$load,$encryption,$security;}
require APPPATH.'controllers/admin/Smtp_oauth_google.php';require APPPATH.'controllers/admin/Smtp_oauth_microsoft.php';
$input=new class{public $verb='POST';public $data=[];public $query=[];function method($upper=true){return$this->verb;}function post($key=null,$clean=false){return$key===null?$this->data:($this->data[$key]??null);}function get($key=null,$clean=false){return$key===null?$this->query:($this->query[$key]??null);}};
$output=new class{function set_header($value){return$this;}};$load=new class{function library($name){if($name!=='encryption')throw new LogicException('unexpected library');}};
$encryption=new class{function encrypt($v){return'test-sealed:'.base64_encode($v);}function decrypt($v){return base64_decode(substr($v,12),true);}};
$security=new class{function get_csrf_token_name(){return'csrf';}};
foreach(['google'=>Smtp_oauth_google::class,'microsoft'=>Smtp_oauth_microsoft::class]as$provider=>$class){
 $controller=new $class;foreach(compact('input','output','db','load','encryption','security')as$key=>$value)$controller->$key=$value;
 $input->data=['action'=>'begin','revision'=>'1','_native_company_csrf'=>'synthetic-csrf'];$GLOBALS['nativeRedirect']=null;$GLOBALS['nativeError']=null;
 $controller->token();essentialCheck($GLOBALS['nativeError']===null&&$GLOBALS['nativeRedirect'][1]===303,'native '.$provider.' token controller begins explicit authorization');
 parse_str(parse_url($GLOBALS['nativeRedirect'][0],PHP_URL_QUERY),$params);essentialCheck($params['redirect_uri']===admin_url('smtp_oauth_'.$provider.'/token'),'OAuth uses exact original native route with subpath');
 $record=$db->query('SELECT * FROM '.$p.'company_'.$provider.'_mail_states WHERE state_hash=?',[hash('sha256',$params['state'])])->row_array();essentialCheck((int)$record['issuer_id']===41&&(int)$record['actor_id']===7,'persisted state binds selected issuer and authorized actor');
 $count=count($db->query('SELECT state_hash FROM '.$p.'company_'.$provider.'_mail_states')->result_array());
 $input->data['_native_company_csrf']='wrong';$controller->token();essentialCheck($GLOBALS['nativeError']===403&&count($db->query('SELECT state_hash FROM '.$p.'company_'.$provider.'_mail_states')->result_array())===$count,'invalid CSRF creates no authorization state');
 $input->verb='GET';$input->query=[];$GLOBALS['nativeRedirect']=null;$controller->token();essentialCheck($GLOBALS['nativeError']===403&&$GLOBALS['nativeRedirect']===null,'plain GET never initiates remote authorization');
 $input->query=['state'=>'wrong','code'=>'synthetic-code'];$controller->token();essentialCheck($GLOBALS['nativeError']===403,'invalid callback fails closed before transport');
 $input->verb='POST';$input->query=[];$input->data=['action'=>'revoke','revision'=>'1','_native_company_csrf'=>'synthetic-csrf'];$GLOBALS['nativeError']=null;$controller->token();essentialCheck($GLOBALS['nativeError']===null&&$GLOBALS['nativeRedirect']===[admin_url('settings?group=email'),303],'revoke returns to existing Settings, not a company sub-application');
}
foreach([CompanyGoogleMailOAuth::class=>'google',CompanyMicrosoftMailOAuth::class=>'microsoft']as$class=>$provider)foreach(['http://crm.example.test/admin/smtp_oauth_'.$provider.'/token','https://crm.example.test/admin/smtp_oauth_'.$provider.'/token?return=evil','https://crm.example.test/admin/smtp_oauth_'.$provider.'/token/other','https://user:secret@crm.example.test/admin/smtp_oauth_'.$provider.'/token']as$bad)settingThrows(fn()=>$class::redirectUri($bad),'company_'.$provider.'_mail_https_required','untrusted callback shape rejected');
echo "Native OAuth endpoints: real state storage, exact subpath callback, issuer/actor binding and POST/CSRF/GET/revoke negatives passed; no provider network.\n";
