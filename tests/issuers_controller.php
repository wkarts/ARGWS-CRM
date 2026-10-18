<?php

declare(strict_types=1);
define('BASEPATH', __DIR__);
class AdminController { public $input; public $session; }
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
echo "Issuer controller: {$checks} checks passed; POST, CSRF, input shape and trusted actor.\n";
