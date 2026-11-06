<?php
/** Disposable MySQL/MariaDB only; actual controller/services, synthetic encryption. */
require __DIR__.'/../companies/efi_company_checkout.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
function show_error($message,$status=500){$GLOBALS['efiHttpErrors'][]=[$status,$message];}
class CompanyRequestScope{
    static function sealedFor($ci){return true;}
    static function nativeFor($ci){return true;}
}
#[AllowDynamicProperties] class AdminController{function __construct(){}}
require APPPATH.'controllers/admin/Settings.php';
$controller=new Settings();$controller->db=$db;$controller->encryption=$encryption;
$controller->input=new class{public string $verb='GET';public array $body=[];function method($upper=true){return $this->verb;}function post($key=null,$xss=false){return $key===null?$this->body:($this->body[$key]??null);}};
$controller->session=new class{function userdata($key){return str_repeat('a',64);}};
$controller->output=new class{public int $status=200;function set_header($value){return $this;}function set_status_header($value){$this->status=$value;return $this;}};
$controller->load=new class{public array $views=[];function library($name){}function view($name,$data){$this->views[]=[$name,$data];}};
$principal=(new IssuerResourceAccess($db,$p))->primaryId();$call=fn()=>CompanySettingsRuntime::run($db,$p,$principal,7,fn()=>$controller->efi());
$other=$accounts->overview($b,7);$factoryBefore=$factoryCalls;$callsBefore=count($calls);
$call();$page=end($controller->load->views);
essentialCheck($page[0]==='admin/settings/native_efi'&&$page[1]['profiles']===[],'principal Efí GET renders empty own configuration');
$controller->input->verb='POST';$controller->input->body=['company_efi_csrf'=>str_repeat('a',64),'confirm'=>'1','action'=>'save','environment'=>'sandbox','revision'=>'new','client_id'=>'synthetic-primary','client_secret'=>'synthetic-secret','pix_key'=>'synthetic-pix'];
$call();$page=end($controller->load->views);$profile=$page[1]['profiles'][0];
essentialCheck($page[1]['saved']!==null&&$profile['enabled']==1,'principal Efí valid save remains local and confirmed');
essentialCheck($accounts->overview($b,7)===$other,'principal credential save preserves secondary account');
$call();essentialCheck($controller->output->status===422&&end($controller->load->views)[1]['saved']===null,'obsolete Efí form rejected without success');
$controller->input->body['company_efi_csrf']='wrong';
$GLOBALS['efiHttpErrors']=[];$call();essentialCheck(end($GLOBALS['efiHttpErrors'])[0]===403,'Efí CSRF denied');
$controller->input->verb='GET';$controller->input->body=[];
foreach([['active',0],['installed_version','5.1.4']] as [$column,$value]){
    $db->query('UPDATE '.$p.'modules SET '.$column.'=? WHERE module_name=?',[$value,'gerencianet_gateway']);
    try{$GLOBALS['efiHttpErrors']=[];$call();$error=end($GLOBALS['efiHttpErrors']);essentialCheck($error[0]===503&&str_contains($error[1],'módulo Efí'),'module setup failure distinguished from ACL');}
    finally{$db->query("UPDATE {$p}modules SET active=1,installed_version='5.1.5' WHERE module_name='gerencianet_gateway'");}
}
essentialCheck($factoryCalls===$factoryBefore&&count($calls)===$callsBefore,'configuration HTTP tests never construct or call a provider');
echo "Principal Efí actual GET/save, stale, CSRF, module readiness and A/B isolation passed; no provider calls.\n";
