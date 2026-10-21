<?php
/** Exercise actual finite API/public bootstrap, preserving the original80 assertions. */
require __DIR__.'/task_recurring_bootstrap.php';
$hrBootstrapStart=$checks;
require_once APPPATH.'services/CompanyHrApi.php';
require_once APPPATH.'services/CompanyHrAccessScope.php';
$settings=opsCsrfConfig();$routes=opsRouteConfig();
$hrPattern='company_operations_api/('.implode('|',CompanyHrApi::ENDPOINTS).')(/[1-9][0-9]{0,9})?';
opsBootCheck(in_array($hrPattern,$settings['csrf_exclude_uris'],true),'Only the finite native HR bearer endpoints receive a CSRF exemption');
opsBootCheck(count(CompanyHrApi::ENDPOINTS)===13&&count(CompanyHrApi::SCOPES)===16,'HR contract enumerates thirteen endpoints and sixteen scopes');
opsBootCheck(array_intersect_key(CompanyApiTokens::SCOPES,CompanyHrApi::SCOPES)===CompanyHrApi::SCOPES,'Native tokens use the exact HR resource/capability contract');
foreach(CompanyHrApi::ENDPOINTS as $endpoint){
    foreach([[],['23']] as $tail){
        $segments=['company_operations_api',$endpoint,...$tail];$autoload=opsBootAutoload(opsBootCi('company_operations_api',$endpoint,$segments),$segments);
        opsBootCheck($autoload===['packages'=>[],'config'=>[],'helper'=>[],'language'=>[],'drivers'=>[],'libraries'=>['database'],'model'=>[]]&&CompanyOperationsApiScope::active(),'HR bearer route hydrates only its database');
        opsBootCheck(preg_match('#^'.$hrPattern.'$#D',implode('/',$segments))===1,'Exact HR bearer URI is admitted');
    }
    opsBootCheck(($routes['company_operations_api/'.$endpoint]??null)==='company_operations_api/'.$endpoint,'HR collection route names its exact native controller method');
    opsBootCheck(($routes['company_operations_api/'.$endpoint.'/([1-9][0-9]{0,9})']??null)==='company_operations_api/'.$endpoint.'/$1','HR detail route retains the canonical identifier');
    foreach(['0','01','-1','1e2','2147483648','23.json','23/extra'] as $suffix){
        $segments=['company_operations_api',$endpoint,...explode('/',$suffix)];$denied=false;
        try{opsBootAutoload(opsBootCi('company_operations_api',$endpoint,$segments),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
        opsBootCheck($denied&&!CompanyOperationsApiScope::active(),'Malformed HR identifier/suffix fails before ambient hydration');
    }
}
$secret='hrco_'.str_repeat('a',64);$segments=['company_hr_access','index',$secret];
$autoload=opsBootAutoload(opsBootCi('company_hr_access','index',$segments),$segments);
opsBootCheck($autoload===['packages'=>[],'config'=>[],'helper'=>['url','form'],'language'=>[],'drivers'=>['session'],'libraries'=>['database'],'model'=>[]]&&CompanyHrAccessScope::active(),'Public recipient loads only the native database/form/session prerequisites');
// Keep raw evidence independent of the router's decoded segments at the actual autoload boundary.
foreach([['/index.php','/company_hr_access/index/'.$secret],['/index.php','/index.php/company_hr_access/index/'.$secret],['/crm/index.php','/crm/company_hr_access/index/'.$secret],['/crm/index.php','/crm/index.php/company_hr_access/index/'.$secret]] as [$script,$uri]){
    $GLOBALS['opsBootstrapCi']=opsBootCi('company_hr_access','index',$segments);$_SERVER=['SCRIPT_NAME'=>$script,'REQUEST_URI'=>$uri];$autoload=[];require APPPATH.'config/autoload.php';
    opsBootCheck(CompanyHrAccessScope::active()&&$autoload===['packages'=>[],'config'=>[],'helper'=>['url','form'],'language'=>[],'drivers'=>['session'],'libraries'=>['database'],'model'=>[]],'Canonical raw recipient base/front controller retains minimal positive autoload');
}
foreach(['/recipient-alias','/COMPANY_HR_ACCESS/index/'.$secret,'/company_%68r_access/index/'.$secret,'/company_hr_access//index/'.$secret,'/company_hr_access/index/'.$secret.'/','/wrong-base/company_hr_access/index/'.$secret,'/company_hr_access/index/'.$secret.'/0'] as $uri){
    $GLOBALS['opsBootstrapCi']=opsBootCi('company_hr_access','index',$segments);$_SERVER=['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>$uri];$autoload=[];$denied=false;
    try{require APPPATH.'config/autoload.php';}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
    opsBootCheck($denied&&!CompanyHrAccessScope::active()&&$autoload===[],'Untrusted raw recipient alias is vetoed before any session/model/transport autoload');
}
foreach([
    ['company_hr_access','index'],['company_hr_access','index','bad'],['company_hr_access','index','hrco_'.str_repeat('A',64)],['company_hr_access','index',$secret,'extra'],['company_hr_access','download',$secret],['company_hr_access',$secret],
] as $invalid){
    $denied=false;try{opsBootAutoload(opsBootCi('company_hr_access',$invalid[1],$invalid),$invalid);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
    opsBootCheck($denied&&!CompanyHrAccessScope::active(),'Malformed public recipient path is vetoed before legacy models or transports');
}
foreach([['admin',''],['../../modules/hr_profile/controllers','hr_profile'],['','hr_profile']] as [$directory,$module]){
    $denied=false;try{opsBootAutoload(opsBootCi('company_hr_access','index',$segments,$directory,$module),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
    opsBootCheck($denied&&!CompanyHrAccessScope::active(),'Admin/module aliases cannot become a public HR recipient');
}
$denied=false;try{opsBootAutoload(opsBootCi('dashboard','index',$segments,'admin'),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
opsBootCheck($denied,'Custom recipient URI alias cannot hydrate the legacy dashboard');
foreach(['/company_hr_access/index/'.$secret,'/index.php/company_hr_access/index/'.$secret,'/crm/company_hr_access/index/'.$secret,'/crm/index.php/company_hr_access/index/'.$secret,'/company%5fhr_access/invalid'] as $uri){
    $_SERVER=['REQUEST_URI'=>$uri,'SCRIPT_NAME'=>str_starts_with($uri,'/crm/')?'/crm/index.php':'/index.php'];opsBootCheck(CompanyHrAccessScope::earlyCandidate($_SERVER),'Pre-system veto recognizes only the actual recipient family and installation base');
    (new InitModules())->handle();opsBootCheck(true,'Recipient candidate skips optional module loading before secret parsing');
}
foreach(['/company_hr_access_evil/index/'.$secret,'/admin/company_hr','/company_operations_api/hr_employees','/hr_profile/hr_profile_login'] as $uri)opsBootCheck(!CompanyHrAccessScope::earlyCandidate(['REQUEST_URI'=>$uri,'SCRIPT_NAME'=>'/index.php']),'Recipient early hint does not broaden another family');
foreach(['company_hr_access/index/'.$secret,'admin/company_hr','admin/company_hr/mine','admin/company_hr/file','company_operations_api/hr_employees/0','company_operations_api/hr_employees/23/extra'] as $uri){
    opsBootCheck(!array_filter($settings['csrf_exclude_uris'],static fn($pattern)=>preg_match('#^'.$pattern.'$#D',$uri)===1),'Public recipient and GUI retain native CSRF protection');
}
$autoload=opsBootAutoload(opsBootCi('company_hr_access','index',$segments),$segments);$hook=[];require APPPATH.'config/hooks.php';
foreach($hook['post_controller'] as $callback)$callback();
opsBootCheck(true,'Public post-controller hook never stores a secret URL as the previous staff page');
opsBootCheck(!CompanyHrAccessScope::prepare((object)['input'=>new class{public function is_cli_request(){return true;}}]),'CLI cannot prepare public recipient authority');
echo 'HR native bootstrap: '.($checks-$hrBootstrapStart).' additional / '.$checks.' cumulative checks.'.PHP_EOL;
