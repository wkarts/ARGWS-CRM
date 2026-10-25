<?php
require __DIR__.'/staff_invite_creation.php';
#[AllowDynamicProperties] class App_Model {}
#[AllowDynamicProperties] class App_Controller {}
require APPPATH.'models/Authentication_model.php';require APPPATH.'controllers/admin/Authentication.php';
$auth=(new ReflectionClass(Authentication_model::class))->newInstanceWithoutConstructor();$auth->db=$db;$auth->config=new class($secret){function __construct(private string $key){}function item($name){return$name==='encryption_key'?$this->key:null;}};
[$staff,$mail,$headers,$token]=$createInvite('authentication');
essentialCheck($auth->can_set_password(true,$staff,$token),'actual native authentication model validates creation token');
essentialCheck($auth->set_password(true,$staff,$token,'synthetic-auth-model-password')===true,'actual model completes same native password action');
essentialCheck($auth->set_password(true,$staff,$token,'synthetic-auth-retry-password')===['expired'=>true],'actual model refuses consumed invitation');
function _l($value){return$value;}function admin_url($path=''){return'https://example.test/admin/'.$path;}function set_alert($type,$message){$GLOBALS['staffInviteAlerts'][]=[$type,$message];}function redirect($url){throw new RuntimeException('synthetic_redirect');}function show_404(){throw new RuntimeException('synthetic_404');}
$controller=(new ReflectionClass(Authentication::class))->newInstanceWithoutConstructor();$controller->Authentication_model=$auth;
$controller->output=new class{public array$headers=[];function set_header($v){$this->headers[]=$v;return$this;}};
$controller->load=new class{public array$views=[];function view($path,$data){$this->views[]=[$path,$data];}};
$controller->input=new class{public string$verb='GET';public array$data=[];function method($upper=false){return$this->verb;}function post($key,$clean=false){return$this->data[$key]??null;}};
$controller->set_password('1',(string)$staff,'native');essentialCheck($controller->load->views[0][0]==='authentication/set_password'&&$controller->load->views[0][1]['native_staff_invitation']===true,'actual admin controller keeps original form without exposing identity in GET');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true)&&in_array('Referrer-Policy: no-referrer',$controller->output->headers,true),'native invitation form response is private and non-referring');
[$staff,$mail,$headers,$token]=$createInvite('controller');$controller->input->verb='POST';$controller->input->data=['native_staff_invitation_token'=>$token,'password'=>'synthetic-controller-password','passwordr'=>'synthetic-controller-password'];
essentialThrows(fn()=>$controller->set_password('1',(string)$staff,'native'),'synthetic_redirect','actual original controller redirects only after real completion');essentialCheck(end($GLOBALS['staffInviteAlerts'])===['success','password_reset_message']&&!$invite->canComplete($staff,$token),'controller records genuine one-time completion, not generic success');
echo "Staff original authentication controller/model: seven SQLite checks; framework I/O doubles, not HTTP/browser.\n";
