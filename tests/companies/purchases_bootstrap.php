<?php
/** Execute actual autoload, route table and CSRF patterns; preserve prior 195 checks. */
require __DIR__.'/leads_bootstrap.php';
$purchasesBootstrapStart=$checks;
$settings=opsCsrfConfig();$routes=opsRouteConfig();
$purchaseResources=['suppliers','requests','quotes','orders','receipts','documents','payables','events','files','file_download','settings','reports','invites','order_pdf','export'];
$purchasePattern='company_purchases_api/index/(suppliers|requests|quotes|orders|receipts|documents|payables|events|files|file_download|settings|reports|invites|order_pdf|export)(/[1-9][0-9]{0,9})?';
opsBootCheck(in_array($purchasePattern,$settings['csrf_exclude_uris'],true),'One finite purchase bearer CSRF exemption exists');
foreach($purchaseResources as $resource){
    foreach([[],['2147483647']] as $id){
        $segments=['company_purchases_api','index',$resource,...$id];$uri=implode('/',$segments);
        $autoload=opsBootAutoload(opsBootCi('company_purchases_api','index',$segments),$segments);
        opsBootCheck($autoload===['packages'=>[],'config'=>[],'helper'=>[],'language'=>[],'drivers'=>[],'libraries'=>['database'],'model'=>[]],'Purchase bearer loads only database, without session, models or provider credentials');
        opsBootCheck(CompanyPurchasesScope::activeApi()&&!CompanyPurchasesScope::activePortal(),'API and portal scopes stay distinct');
        $matches=[];foreach($routes as $pattern=>$target)if(str_starts_with($pattern,'company_purchases_api/')&&preg_match('#^'.$pattern.'$#D',$uri)===1)$matches[]=$target;
        opsBootCheck(count($matches)===1&&str_starts_with($matches[0],'purchases_api/index/'.$resource),'Executed native route table has exactly one finite purchase match');
        opsBootCheck(preg_match('#^'.$purchasePattern.'$#D',$uri)===1,'Exact bearer URI matches CSRF exclusion');
    }
}
foreach([['company_purchase_portal'],['company_purchase_portal','index']] as $segments){
    $autoload=opsBootAutoload(opsBootCi('company_purchase_portal','index',$segments),$segments);
    opsBootCheck($autoload===['packages'=>[],'config'=>[],'helper'=>['url','form'],'language'=>[],'drivers'=>['session'],'libraries'=>['database'],'model'=>[]],'Supplier portal uses its finite session/form bootstrap, without legacy hydration');
    opsBootCheck(CompanyPurchasesScope::activePortal()&&!CompanyPurchasesScope::activeApi(),'Portal scope cannot become bearer scope');
    opsBootCheck(($routes[implode('/',$segments)]??null)==='purchase_portal/index','Supplier portal is an executable explicit native route');
}
// Raw request evidence remains independent of decoded/router-derived segments.
foreach([
    ['company_purchases_api',['company_purchases_api','index','orders','23'],false],
    ['company_purchase_portal',['company_purchase_portal'],true],
] as [$class,$segments,$portal]){
    $tail='/'.implode('/',$segments);
    $expected=['packages'=>[],'config'=>[],'helper'=>$portal?['url','form']:[],'language'=>[],'drivers'=>$portal?['session']:[],'libraries'=>['database'],'model'=>[]];
    foreach([['/index.php',$tail],['/index.php','/index.php'.$tail],['/crm/index.php','/crm'.$tail],['/crm/index.php','/crm/index.php'.$tail]] as [$script,$uri]){
        $GLOBALS['opsBootstrapCi']=opsBootCi($class,'index',$segments);$_SERVER=['SCRIPT_NAME'=>$script,'REQUEST_URI'=>$uri];$autoload=[];require APPPATH.'config/autoload.php';
        opsBootCheck($autoload===$expected&&($portal?CompanyPurchasesScope::activePortal():CompanyPurchasesScope::activeApi()),'Canonical raw base/front controller preserves the exact positive bootstrap');
    }
    foreach(['/purchase-alias',strtoupper($tail),str_replace('company_','company%5f',$tail),'//'.ltrim($tail,'/'),$tail.'/','/wrong-base'.$tail,$tail.'/0'] as $uri){
        $GLOBALS['opsBootstrapCi']=opsBootCi($class,'index',$segments);$_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri];$autoload=[];$denied=false;
        try{require APPPATH.'config/autoload.php';}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
        opsBootCheck($denied&&$autoload===[]&&!CompanyPurchasesScope::activeApi()&&!CompanyPurchasesScope::activePortal(),'Raw aliases and suffixes are vetoed before session/model/provider hydration');
    }
}
$invalid=[['company_purchases_api'],['company_purchases_api','index'],['company_purchases_api','suppliers'],['company_purchases_api','index','unknown'],['company_purchase_portal','index','1'],['company_purchase_portal','unknown']];
foreach($purchaseResources as $resource)foreach(['0','01','-1','1e2','2147483648','23.json','23/extra'] as $id)$invalid[]=['company_purchases_api','index',$resource,...explode('/',$id)];
foreach($invalid as $segments){
    $denied=false;try{opsBootAutoload(opsBootCi($segments[0],'index',$segments),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
    opsBootCheck($denied&&!CompanyPurchasesScope::activeApi()&&!CompanyPurchasesScope::activePortal(),'Malformed purchase route fails before any legacy hydration');
}
foreach(['admin/company_purchases','company_purchase_portal','company_purchase_portal/index','company_purchases_api/index/unknown','company_purchases_api/index/suppliers/0','company_purchases_api/index/orders/1/extra','company_purchases_api_evil/index/orders'] as $uri){
    opsBootCheck(!array_filter($settings['csrf_exclude_uris'],static fn($pattern)=>preg_match('#^'.$pattern.'$#D',$uri)===1),'GUI, supplier portal and malformed paths retain CSRF protection');
}
foreach(['/company_purchases_api/index/suppliers','/index.php/company_purchases_api/index/orders/23','/crm/company_purchase_portal','/crm/index.php/company_purchase_portal/index','/company_purchases_api/unknown/extra','/company%5fpurchases_api/index/suppliers'] as $uri){
    $_SERVER=['REQUEST_URI'=>$uri,'SCRIPT_NAME'=>str_starts_with($uri,'/crm/')?'/crm/index.php':'/index.php'];
    opsBootCheck(CompanyPurchasesScope::earlyCandidate($_SERVER),'Early veto recognizes purchase candidate before optional modules');
    (new InitModules())->handle();opsBootCheck(true,'Purchase candidate never initializes optional module metadata');
}
foreach(['/company_purchases_api_evil/index/suppliers','/admin/company_purchases','/company_operations_api/leads','/company_api/invoices'] as $uri)opsBootCheck(!CompanyPurchasesScope::earlyCandidate(['REQUEST_URI'=>$uri,'SCRIPT_NAME'=>'/index.php']),'Purchase veto does not consume other namespaces');
$segments=['company_purchases_api','index','suppliers'];$denied=false;
try{opsBootAutoload(opsBootCi('dashboard','index',$segments,'admin'),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
opsBootCheck($denied,'Custom route alias cannot hydrate the dashboard from a purchase URI');
require_once APPPATH.'services/CompanyPurchasesApi.php';
opsBootCheck(array_intersect_key(CompanyApiTokens::SCOPES,CompanyPurchasesApi::SCOPES)===CompanyPurchasesApi::SCOPES,'All purchase scopes map exactly to their required capabilities');
echo 'Purchases native bootstrap: '.($checks-$purchasesBootstrapStart).' additional / '.$checks.' cumulative checks.'.PHP_EOL;
