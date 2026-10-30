<?php
require __DIR__.'/native_portal_identity.php';
require APPPATH.'services/PortalRequestScope.php';
#[AllowDynamicProperties] class CI_Controller {
 public function __construct(){foreach($GLOBALS['portal_constructor_parts']as$key=>$value)$this->$key=$value;PortalRequestScope::prepare($this);}
}
function show_error($message,$status=500){throw new RuntimeException('http_'.$status);}
// The real parent constructor must return before the legacy hooks/options/model branch.
require APPPATH.'core/App_Controller.php';
$router=new class{function fetch_directory(){return'';}function fetch_module(){return null;}function fetch_class(){return'authentication';}function fetch_method(){return'login';}};
$input=new class{public array$get=[];function is_cli_request(){return false;}function get($k,$filter=false){return$this->get[$k]??null;}function post($k,$filter=false){return null;}};
$config=new class{function item($key){return$key==='encryption_key'?str_repeat('synthetic-',4):null;}};
$GLOBALS['portal_constructor_parts']=['db'=>$db,'router'=>$router,'input'=>$input,'config'=>$config,'session'=>new NativePortalTestSession()];
$input->get=['issuer_id'=>(string)$a];$controller=new App_Controller();
essentialCheck(PortalRequestScope::sealedFor($controller)&&PortalRequestScope::issuerId()===$a,'real App_Controller resolves A before any legacy hydration');
$input->get=['issuer_id'=>(string)$b];$controllerB=new App_Controller();
essentialCheck(PortalRequestScope::sealedFor($controllerB)&&PortalRequestScope::issuerId()===$b&&!PortalRequestScope::sealedFor($controller),'request seal cannot leak between controllers/companies');
$input->get=['issuer_id'=>['invalid']];essentialThrows(fn()=>new App_Controller(),'http_403','malformed issuer fails closed before legacy branch');
$input->get=[];$principal=new App_Controller();essentialCheck(PortalRequestScope::sealedFor($principal)&&PortalRequestScope::issuerId()===(int)$db->query('SELECT primary_issuer_id FROM '.$p.'issuers_installation_identity WHERE id=1')->row()->primary_issuer_id,'same real constructor resolves persisted principal without global login lookup');
echo "Native public App_Controller bootstrap: 4 PHP/SQLite checks PASS; CI parent is an explicit minimal-autoload seam, not HTTP proof.\n";
