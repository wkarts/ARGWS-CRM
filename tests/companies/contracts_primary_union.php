<?php

/** Primary legacy/owned union and route disambiguation without replacing either ID space. */
require __DIR__.'/contracts_operations.php';
require_once APPPATH.'services/CompanyRequestScope.php';

$session=new class($primary){
    public array$data;
    public function __construct(int$primary){$this->data=[CompanyContext::SELECTION=>['staff_id'=>7,'issuer_id'=>$primary]];}
    public function userdata($key){return$this->data[$key]??null;}
    public function set_userdata($key,$value=null):void{if(is_array($key))$this->data=array_replace($this->data,$key);else$this->data[$key]=$value;}
    public function unset_userdata($key):void{foreach((array)$key as$name)unset($this->data[$name]);}
};
$context=new CompanyContext($db,$p,$session,7);
essentialCheck($context->resolve()===$primary&&$context->isPrimary(),'fixture resolves the installation principal context');
$contextProperty=new ReflectionProperty(CompanyRequestScope::class,'context');
$contextProperty->setValue(null,$context);

#[AllowDynamicProperties] class AdminController{public function __construct(){}}
require_once APPPATH.'controllers/admin/Contracts.php';
if(!function_exists('_l')){function _l($key){return$key;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('admin_url')){function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}}

$controller=(new ReflectionClass(Contracts::class))->newInstanceWithoutConstructor();
$controller->db=$db;
$controller->input=new class{public array$get=[];public function get($key,$filter=false){return$this->get[$key]??null;}};
$reader=new ReflectionMethod(Contracts::class,'primaryOwnedContracts');
$owned=$reader->invoke($controller);
$primaryId=$contractIds[$primary];
essentialCheck(is_array($owned)&&count($owned['directory']['items'])===1&&(int)$owned['directory']['items'][0]['id']===$primaryId,'principal union reads only its own company-owned contract');
essentialCheck(!in_array($contractIds[$a],array_column($owned['directory']['items'],'id'),false)&&!in_array($contractIds[$b],array_column($owned['directory']['items'],'id'),false),'principal union excludes A and B contracts');

$canonical=NativeCompanyResourceId::encode('company_contract',(string)$primaryId);
$db->query("INSERT INTO {$p}contracts(id,issuer_id,subject) VALUES(?,?,?)",[$primaryId,null,'Same raw ID historical contract']);
$legacyBefore=$db->query("SELECT * FROM {$p}contracts WHERE id=?",[$primaryId])->row_array();
$harness=new class{public function render(array$data):string{extract($data);ob_start();include APPPATH.'views/admin/contracts/company_owned_directory.php';return ob_get_clean();}};
$html=$harness->render(['company_owned_contracts'=>$owned]);
essentialCheck(str_contains($html,'/admin/contracts/contract/'.$canonical)&&str_contains($html,'contracts/contract?source=company'),'principal union emits typed detail and explicit owned-create routes');
essentialCheck(!str_contains($html,'/admin/company_contracts'),'principal union has no parallel controller link');
essentialCheck($db->query("SELECT * FROM {$p}contracts WHERE id=?",[$primaryId])->row_array()===$legacyBefore,'rendering the typed union never mutates the colliding historical row');

$route=new ReflectionMethod(CompanyRequestScope::class,'ownedContractRoute');
$probe=new class{
    public object$input;public object$uri;
    public function __construct(){$this->input=new class{public array$get=[];function get($key,$filter=false){return$this->get[$key]??null;}};$this->uri=new class{public string$value='';function rsegment($position){return$position===3?$this->value:'';}};}
};
$probe->uri->value=(string)$primaryId;
essentialCheck(!$route->invoke(null,$probe,'contracts','contract'),'raw colliding ID remains on the historical Contracts action');
$probe->uri->value=$canonical;
essentialCheck($route->invoke(null,$probe,'contracts','contract'),'typed colliding ID selects the company-owned adapter');
$probe->uri->value=NativeCompanyResourceId::encode('company_project','1');
essentialCheck($route->invoke(null,$probe,'contracts','contract'),'wrong high typed namespace is retained for fail-closed adapter validation');
$probe->uri->value='';$probe->input->get=['source'=>'company'];
essentialCheck($route->invoke(null,$probe,'contracts','contract'),'explicit owned create selects the company adapter');
$probe->input->get=[];
essentialCheck(!$route->invoke(null,$probe,'contracts','index')&&$route->invoke(null,$probe,'contracts','file'),'principal index remains the legacy union while owned files use the scoped reader');

echo 'Primary contracts legacy/owned union and typed collision routing: '.($GLOBALS['essentialChecks']).' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
