<?php
/** Pure transport boundary; database authorization is still performed by CompanyLeads::get. */
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
class AdminController {}
require APPPATH.'controllers/admin/Leads.php';
require_once APPPATH.'services/NativeCompanyResourceId.php';
$method=new ReflectionMethod(Leads::class,'nativeLeadPathId');$checks=0;
$check=static function($expected,$actual)use(&$checks){if($expected!==$actual)throw new RuntimeException('Lead route identity mismatch');++$checks;};
$check(null,$method->invoke(null,''));$check(null,$method->invoke(null,null));
foreach([1,17,2147483647]as$id)$check($id,$method->invoke(null,NativeCompanyResourceId::encode('company_lead',$id)));
foreach(['17','0','01','1e2',[],NativeCompanyResourceId::encode('company_contract',17),NativeCompanyResourceId::encode('company_project',17)]as$id){$denied=false;try{$method->invoke(null,$id);}catch(DomainException$expected){$denied=true;}$check(true,$denied);}
echo 'Lead native path identities: '.$checks.' checks passed. No GUI or database-isolation claim.'.PHP_EOL;
