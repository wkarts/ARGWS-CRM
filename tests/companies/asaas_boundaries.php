<?php
/** Real isolated HTML view and exact public bootstrap selection; synthetic I/O only. */
require __DIR__.'/asaas_positive.php';
require_once APPPATH.'services/CompanyAsaasCallbackScope.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
$viewStart=$GLOBALS['essentialChecks'];
$probe=new class {
    public object $input;public object $router;public object $uri;
    public function __construct(){
        $this->uri=new class {public array $segments=['company_asaas_callback','integration','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];public function segment($i){return $this->segments[$i-1]??null;}};
        $this->input=new class {public bool $cli=false;public function is_cli_request(){return $this->cli;}};
        $this->router=new class {public string $directory='';public string $module='';public string $class='company_asaas_callback';public string $method='integration';public function fetch_directory(){return $this->directory;}public function fetch_module(){return $this->module;}public function fetch_class(){return $this->class;}public function fetch_method(){return $this->method;}};
    }
};
foreach([['class','callback'],['method','index'],['directory','admin'],['module','asaas']] as [$field,$wrong]){$original=$probe->router->$field;$probe->router->$field=$wrong;essentialCheck(!CompanyAsaasCallbackScope::prepare($probe),'public minimal bootstrap rejects a different '.$field);$probe->router->$field=$original;}
$probe->input->cli=true;essentialCheck(!CompanyAsaasCallbackScope::prepare($probe),'public minimal bootstrap rejects CLI');$probe->input->cli=false;essentialCheck(CompanyAsaasCallbackScope::prepare($probe),'exact server router proves the public minimal bootstrap');
foreach([['admin','company_asaas_callback','integration',str_repeat('a',32)],['company_asaas_callback','integration',str_repeat('a',32),'extra'],['company_asaas_callback','integration','bad'],['company_asaas_callback','index',str_repeat('a',32)]] as $segments){$probe->uri->segments=$segments;essentialCheck(!CompanyAsaasCallbackScope::prepare($probe)&&!CompanyAsaasCallbackScope::active(),'URI mismatch resets public attestation and denies aliases/admin/extra segments');}
$probe->uri->segments=['company_asaas_callback','integration',str_repeat('a',32)];essentialCheck(CompanyAsaasCallbackScope::prepare($probe),'valid exact public URI is independently reattested');
if(!class_exists('CompanyRequestScope',false)){
    class CompanyRequestScope {public static function context(){return null;}public static function adminUrl($owner,string $path=''):string{return 'https://synthetic.invalid/admin/'.$path;}public static function token():string{return 'synthetic-token';}}
}
function base_url($path){return 'https://synthetic.invalid/'.$path;}
$harness=new class {
    public object $config;public object $security;
    public function __construct(){$this->config=new class {public function item($name){return $name==='csrf_protection';}};$this->security=new class {public function get_csrf_token_name(){return 'synthetic_csrf';}public function get_csrf_hash(){return 'synthetic-csrf-hash';}};}
    public function render(array $data):string{extract($data);ob_start();include APPPATH.'views/admin/companies/asaas.php';return ob_get_clean();}
};
$data=$asaas->overview($b,7)+['error'=>null,'saved'=>null,'one_time'=>null,'invoice'=>null,'can_configure'=>true,'can_settle'=>true,'can_fee'=>true,'can_issue'=>true,'context_token'=>'synthetic-context-token','action_url'=>'https://synthetic.invalid/admin/company_asaas','callback_base'=>null];
$data['accounts'][0]['account_id']='wallet <script>injected</script>';$html=$harness->render($data);
essentialCheck(str_contains($html,'name="_company_context" value="synthetic-context-token"')&&str_contains($html,'name="synthetic_csrf" value="synthetic-csrf-hash"'),'all actual forms retain company scope and native CSRF');
essentialCheck(!str_contains($html,'<script>injected</script>')&&str_contains($html,'&lt;script&gt;injected'),'account identifiers are escaped in actual native renderer');
essentialCheck(!str_contains($html,'synthetic:')&&!str_contains($html,'key-B')&&!str_contains($html,'token_hash'),'real view does not contain persisted API secrets or token hashes');
essentialCheck(str_contains($html,'Gerar outro token local')&&str_contains($html,'URL HTTPS confiável'),'local token preparation and missing trusted callback URL are visibly distinct');
$once=$data;$once['one_time']=['one_time_token'=>str_repeat('a',64),'public_id'=>str_repeat('b',32)];essentialCheck(str_contains($harness->render($once),'id="asaas-one-time-token"'),'token only appears when explicitly supplied by the successful rotation response');
essentialCheck(!str_contains($html,'id="asaas-one-time-token"'),'normal refresh does not hydrate a one-time token');
echo 'Company Asaas bootstrap/view boundaries: '.($GLOBALS['essentialChecks']-$viewStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed.'.PHP_EOL;
