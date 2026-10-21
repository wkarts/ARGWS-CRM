<?php
declare(strict_types=1);
require $argv[1]??dirname(__DIR__,2).'/application/services/CompanyPurchasesScope.php';
$checks=0; function purchaseScopeCheck(bool $condition,string $message): void { global $checks; if (!$condition) { throw new RuntimeException('FAIL: '.$message); } $checks++; }
function purchaseScopeCi(array $segments,string $class='company_purchases_api',string $method='index',string $directory='',string $module=''): object {
    return (object)['input'=>new class { public function is_cli_request() { return false; } },'router'=>new class($class,$method,$directory,$module) { public function __construct(private string $class,private string $method,private string $directory,private string $module) {} public function fetch_class() { return $this->class; } public function fetch_method() { return $this->method; } public function fetch_directory() { return $this->directory; } public function fetch_module() { return $this->module; } },'uri'=>new class($segments) { public function __construct(private array $segments) {} public function segment($n) { return $this->segments[$n-1]??null; } }];
}
$original=$_SERVER; $api=['company_purchases_api','index','orders','23']; $portal=['company_purchase_portal'];
try {
    foreach ([['/index.php','/company_purchases_api/index/orders/23'],['/index.php','/index.php/company_purchases_api/index/orders/23'],['/crm/index.php','/crm/company_purchases_api/index/orders/23?after=0'],['/crm/index.php','/crm/index.php/company_purchases_api/index/orders/23']] as [$script,$uri]) {
        $_SERVER=['SCRIPT_NAME'=>$script,'REQUEST_URI'=>$uri]; purchaseScopeCheck(CompanyPurchasesScope::earlyCandidate($_SERVER)&&CompanyPurchasesScope::prepare(purchaseScopeCi($api))&&CompanyPurchasesScope::activeApi(),'canonical API raw path works with native front-controller/base path');
    }
    foreach ([['/index.php','/company_purchase_portal',$portal],['/index.php','/company_purchase_portal/index',[...$portal,'index']],['/crm/index.php','/crm/index.php/company_purchase_portal',$portal]] as [$script,$uri,$segments]) {
        $_SERVER=['SCRIPT_NAME'=>$script,'REQUEST_URI'=>$uri]; purchaseScopeCheck(CompanyPurchasesScope::prepare(purchaseScopeCi($segments,'company_purchase_portal'))&&CompanyPurchasesScope::activePortal(),'canonical portal has a distinct authenticated session boundary');
    }
    foreach (['/alias','/COMPANY_PURCHASES_API/index/orders/23','/company_%70urchases_api/index/orders/23','/company_purchases_api//index/orders/23','/company_purchases_api/index/orders/23/','/index.php/company_purchases_api/index/orders/23/extra','/wrong-base/company_purchases_api/index/orders/23','/company_purchases_api/index/orders/%32%33','//company_purchases_api/index/orders/23','http://untrusted.invalid/company_purchases_api/index/orders/23','/company_purchases_api/index/orders/23#fragment'] as $uri) {
        $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri]; purchaseScopeCheck(!CompanyPurchasesScope::prepare(purchaseScopeCi($api))&&!CompanyPurchasesScope::activeApi()&&CompanyPurchasesScope::requested(),'noncanonical or aliased raw API path remains vetoed before hydration: '.$uri);
    }
    foreach (['/portal-alias','/COMPANY_PURCHASE_PORTAL','/company_purchase_portal//','/company_purchase_%70ortal','/company_purchase_portal?x=1#fragment'] as $uri) {
        $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri]; purchaseScopeCheck(!CompanyPurchasesScope::prepare(purchaseScopeCi($portal,'company_purchase_portal'))&&!CompanyPurchasesScope::activePortal(),'noncanonical portal cannot adopt another router alias');
    }
    foreach (['0','01','-1','1e2','2147483648','23.json'] as $id) {
        $segments=['company_purchases_api','index','orders',$id]; $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/'.implode('/',$segments)]; purchaseScopeCheck(!CompanyPurchasesScope::prepare(purchaseScopeCi($segments)),'noncanonical or overflowing resource ID rejected');
    }
    $_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/'.implode('/',$api)];
    foreach ([purchaseScopeCi($api,'dashboard','index','admin'),purchaseScopeCi($api,'company_purchases_api','index','admin'),purchaseScopeCi($api,'company_purchases_api','index','','purchase'),purchaseScopeCi($api,'company_purchases_api','orders')] as $ci) { purchaseScopeCheck(!CompanyPurchasesScope::prepare($ci),'custom router/directory/module alias cannot authorize Purchase'); }
    foreach ([[],['SCRIPT_NAME'=>'/other.php','REQUEST_URI'=>'/'.implode('/',$api)],['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>"/company_purchases_api/index/orders/23\n"]] as $server) { $_SERVER=$server; purchaseScopeCheck(!CompanyPurchasesScope::prepare(purchaseScopeCi($api)),'missing or malformed native request evidence is denied'); }
} finally { $_SERVER=$original; }
echo 'Purchases raw scope: '.$checks.' checks passed; source '.hash_file('sha256',$argv[1]??dirname(__DIR__,2).'/application/services/CompanyPurchasesScope.php').PHP_EOL;
