<?php
declare(strict_types=1);
require $argv[1]??dirname(__DIR__,2).'/application/services/CompanyHrAccessScope.php';
$checks=0;
function hrAccessScopeCheck(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException('FAIL: '.$message);++$checks;}
function hrAccessScopeCi(array $segments,string $class='company_hr_access',string $method='index',string $directory='',string $module=''):object
{
 return (object)['input'=>new class{public function is_cli_request(){return false;}},'router'=>new class($class,$method,$directory,$module){public function __construct(private string $class,private string $method,private string $directory,private string $module){}public function fetch_class(){return $this->class;}public function fetch_method(){return $this->method;}public function fetch_directory(){return $this->directory;}public function fetch_module(){return $this->module;}},'uri'=>new class($segments){public function __construct(private array $segments){}public function segment($n){return $this->segments[$n-1]??null;}}];
}
$original=$_SERVER;$secret='hrco_'.str_repeat('a',64);$segments=['company_hr_access','index',$secret];$tail='/'.implode('/',$segments);
try{
 foreach([['/index.php',$tail],['/index.php','/index.php'.$tail],['/crm/index.php','/crm'.$tail.'?file=23'],['/crm/index.php','/crm/index.php'.$tail],['/company-suite/argws/index.php','/company-suite/argws'.$tail]] as [$script,$uri]){
  $_SERVER=['SCRIPT_NAME'=>$script,'REQUEST_URI'=>$uri];hrAccessScopeCheck(CompanyHrAccessScope::earlyCandidate($_SERVER)&&CompanyHrAccessScope::prepare(hrAccessScopeCi($segments))&&CompanyHrAccessScope::active(),'Canonical raw recipient request supports the installation base and native front controller');
 }
 foreach(['/recipient-alias','/COMPANY_HR_ACCESS/index/'.$secret,'/company_%68r_access/index/'.$secret,'/company_hr_access/%69ndex/'.$secret,'/company_hr_access//index/'.$secret,$tail.'/','/index.php'.$tail.'/extra','/wrong-base'.$tail,'//company_hr_access/index/'.$secret,'http://untrusted.invalid'.$tail,$tail.'#fragment',$tail.'?file=23#fragment','/company_hr_access/index/hrco_%61'.str_repeat('a',63),'/./company_hr_access/index/'.$secret,'/company_hr_access/../company_hr_access/index/'.$secret] as $uri){
  $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri];hrAccessScopeCheck(!CompanyHrAccessScope::prepare(hrAccessScopeCi($segments))&&!CompanyHrAccessScope::active()&&CompanyHrAccessScope::requested(),'Decoded or aliased router state cannot authorize a noncanonical raw recipient request: '.$uri);
 }
 foreach([[],['SCRIPT_NAME'=>'/other.php','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$tail."\n"],['SCRIPT_NAME'=>'index.php','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/crm//index.php','REQUEST_URI'=>'/crm'.$tail],['SCRIPT_NAME'=>'/crm/../index.php','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/crm/%2e/index.php','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/crm/index.php?x=1','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/crm/index.php','REQUEST_URI'=>$tail],['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/crm'.$tail]] as $server){
  $_SERVER=$server;hrAccessScopeCheck(!CompanyHrAccessScope::prepare(hrAccessScopeCi($segments))&&!CompanyHrAccessScope::active(),'Missing, malformed or mismatched original front-controller evidence is denied');
 }
 $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$tail];
 foreach([hrAccessScopeCi($segments,'dashboard','index','admin'),hrAccessScopeCi($segments,'company_hr_access','index','admin'),hrAccessScopeCi($segments,'company_hr_access','index','','hr_profile'),hrAccessScopeCi($segments,'company_hr_access','download'),hrAccessScopeCi([...$segments,'0']),hrAccessScopeCi([...$segments,'extra']),hrAccessScopeCi(['company_hr_access','index','hrco_'.str_repeat('A',64)])] as $ci)hrAccessScopeCheck(!CompanyHrAccessScope::prepare($ci)&&!CompanyHrAccessScope::active(),'Router/module/method/tail or token aliases cannot become recipient authority');
 foreach(['/company%5fhr_access/invalid','/COMPANY_HR_ACCESS/invalid','/company_hr_access//index/'.$secret] as $uri)hrAccessScopeCheck(CompanyHrAccessScope::earlyCandidate(['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri]),'Early hint remains a broad hydration veto, not authority');
 foreach(['/company_hr_access_evil/index/'.$secret,'/admin/company_hr','/hr_profile/hr_profile_login'] as $uri)hrAccessScopeCheck(!CompanyHrAccessScope::earlyCandidate(['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri]),'Early hint does not broaden another controller family');
}finally{$_SERVER=$original;}
echo 'HR recipient raw scope: '.$checks.' checks passed; source '.hash_file('sha256',$argv[1]??dirname(__DIR__,2).'/application/services/CompanyHrAccessScope.php').PHP_EOL;
