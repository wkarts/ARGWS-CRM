<?php

declare(strict_types=1);
define('BASEPATH', __DIR__);
class AdminController { public $input; public $session; }
class CompanyRequestScope {
    public static function sealedFor($ci){return false;}
    public static function context(){return null;}
    public static function invalidateCaches($ci){}
    public static function adminUrl($ci,$path=''){return '/admin/'.$path;}
}
class HttpStop extends RuntimeException {}
class RedirectStop extends RuntimeException {}
function _l($message) { return $message; }
function get_staff_user_id() { return 7; }
function admin_url($path) { return '/admin/' . $path; }
function show_error($message,$status) { throw new HttpStop($message,$status); }
function set_alert($type,$message) { $GLOBALS['alert'] = [$type,$message]; }
function redirect($path) { throw new RedirectStop($path); }
function log_message($level,$message) { $GLOBALS['log'] = $message; }
require_once __DIR__ . '/../application/services/issuers/IssuerPolicy.php';
require_once __DIR__ . '/../application/controllers/admin/Issuers.php';

class ControllerInput
{
    public array $data = [];
    public string $verb = 'POST';
    public function method($uppercase) { return $this->verb; }
    public function post($key) { return $this->data[$key] ?? null; }
}
class ControllerSession
{
    public ?string $token;
    public function userdata($key) { return $this->token; }
    public function set_flashdata($key,$value) {$GLOBALS['flash'][$key]=$value;}
}
class ControllerRegistry
{
    public array $calls = [];
    public ?string $failure = null;
    public function __call($name,$args) {
        if ($this->failure) { throw new DomainException($this->failure); }
        $this->calls[] = [$name,$args];
        return 11;
    }
}
$checks = 0;
function check($expected,$actual,$label) {
    global $checks; $checks++;
    if ($expected !== $actual) { throw new RuntimeException($label . ': ' . var_export($actual,true)); }
}
function controller(array $input, string $verb='POST', ?string $sessionToken=null): array {
    $reflection = new ReflectionClass(Issuers::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new ControllerInput(); $controller->input->data = $input; $controller->input->verb = $verb;
    $controller->session = new ControllerSession(); $controller->session->token = $sessionToken;
    $registry = new ControllerRegistry();
    $reflection->getProperty('registry')->setValue($controller,$registry);
    return [$controller,$registry];
}
$token = str_repeat('a',64);
foreach ([['GET',[],$token,405],['POST',[],null,403],['POST',['issuers_csrf'=>'bad'],$token,403],['POST',['issuers_csrf'=>[]],$token,403]] as [$method,$input,$stored,$code]) {
    [$controller,$registry] = controller($input,$method,$stored);
    try { $controller->save(); throw new LogicException('Request was not blocked'); } catch (HttpStop $error) { check($code,$error->getCode(),'HTTP guard'); }
    check([], $registry->calls, 'no mutation before method/token validation');
}
$base = ['issuers_csrf'=>$token,'issuer_id'=>'11'];
[$controller,$registry] = controller(array_merge($base,['action'=>'issuer','actor_id'=>'999','legal_name'=>'Synthetic','tax_id'=>'12345678000195','active'=>'1']),'POST',$token);
try {$controller->save();} catch (RedirectStop $stop) {check('/admin/issuers/index/11',$stop->getMessage(),'redirect saved company');}
check([['saveIssuer',[7,['legal_name'=>'Synthetic','tax_id'=>'12345678000195','active'=>'1','profile'=>[]],'11']]],$registry->calls,'company actor always from authenticated session');
foreach ([['action'=>'register_client','client_id'=>'101'],['action'=>'share_client','client_id'=>'101','targets'=>['12']],['action'=>'member'],['action'=>'remove_member'],['action'=>'default'],['action'=>'unknown'],['action'=>'share_client','targets'=>'12'],['action'=>'member','permissions'=>'admin'],['action'=>'default','issuer_id'=>'0']] as $bad) {
    [$controller,$registry] = controller(array_merge($base,$bad),'POST',$token);
    try { $controller->save(); } catch (RedirectStop $stop) {}
    check([],$registry->calls,'malformed action never dispatches');
    check('danger',$GLOBALS['alert'][0],'malformed action reported');
}
[$controller,$registry] = controller(array_merge($base,['action'=>'issuer','actor_id'=>'1']),'POST',$token);
$registry->failure = 'issuer_access_denied';
try { $controller->save(); } catch (RedirectStop $stop) {}
check(['danger','issuer_access_denied'],$GLOBALS['alert'],'service authorization honored');
foreach ([['GET',$token,405],['POST','invalid',403]] as [$method,$submitted,$code]) {
    [$controller,$registry]=controller(['action'=>'issuer_status','issuer_id'=>'11','active'=>'0','issuers_csrf'=>$submitted],$method,$token);
    try {$controller->save();throw new LogicException('status guard bypassed');}catch(HttpStop $error){check($code,$error->getCode(),'status HTTP/CSRF guard');}
    check([],$registry->calls,'status blocked before mutation');
}
[$controller,$registry]=controller(array_merge($base,['action'=>'issuer_status','actor_id'=>'999','active'=>'0']),'POST',$token);
try {$controller->save();}catch(RedirectStop $stop){check('/admin/issuers',$stop->getMessage(),'status returns to grid');}
check([['setActive',[7,11,'0']]],$registry->calls,'status uses trusted actor and no identity/profile payload');
// Existing-company editing never reuses the wizard/create action.
foreach ([['GET',$token,405],['POST','invalid',403]] as [$method,$submitted,$code]) {
    [$controller,$registry]=controller(['action'=>'edit_identity','issuer_id'=>'11','identity_revision'=>str_repeat('b',64),'issuers_csrf'=>$submitted],$method,$token);
    try {$controller->save();throw new LogicException('edit guard bypassed');}catch(HttpStop $error){check($code,$error->getCode(),'edit HTTP/CSRF guard');}
    check([],$registry->calls,'edit blocked before mutation');
}
[$controller,$registry]=controller(array_merge($base,['action'=>'edit_identity','identity_revision'=>str_repeat('b',64),'actor_id'=>'999','active'=>'0','legal_name'=>'Existing','tax_id'=>'12345678000195']),'POST',$token);
try {$controller->save();}catch(RedirectStop $stop){check('/admin/issuers/index/11?edit_modal=1',$stop->getMessage(),'edit returns to existing modal');}
check([['updateExistingIdentity',[7,['legal_name'=>'Existing','tax_id'=>'12345678000195','profile'=>[]],11,str_repeat('b',64)]]],$registry->calls,'edit binds authenticated actor/id/revision and ignores status');
foreach(['issuer_access_denied','company_identity_revision_stale'] as $failure){
    [$controller,$registry]=controller(array_merge($base,['action'=>'edit_identity','identity_revision'=>str_repeat('b',64)]),'POST',$token);$registry->failure=$failure;
    try{$controller->save();throw new LogicException('edit service rejection ignored');}catch(HttpStop $error){check(409,$error->getCode(),'edit ACL/stale rejection is not success');}
}
[$controller,$registry]=controller(array_merge($base,['action'=>'edit_identity','issuer_id'=>'','identity_revision'=>str_repeat('b',64)]),'POST',$token);
try{$controller->save();}catch(HttpStop $error){check(409,$error->getCode(),'empty issuer never becomes create');}
check([],$registry->calls,'missing existing ID cannot reach writer');
echo "Issuer controller: {$checks} checks passed; POST, CSRF, input shape and trusted actor.\n";
