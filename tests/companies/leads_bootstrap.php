<?php
/** Actual autoload, early module veto, CSRF and routes; preserve original80 assertions. */
require __DIR__.'/task_recurring_bootstrap.php';
$leadsBootstrapStart=$checks;
$settings=opsCsrfConfig();$routes=opsRouteConfig();
$leadPatterns=[
    'company_operations_api/(leads|lead_stages|lead_sources|lead_inbox)(/[1-9][0-9]{0,9})?',
    'company_operations_api/(lead_assignees|lead_exports)',
    'company_operations_api/lead_imports(/[a-f0-9]{32})?',
    'company_operations_api/lead_files/[1-9][0-9]{0,9}(/[a-f0-9]{64})?',
];
foreach($leadPatterns as $pattern)opsBootCheck(in_array($pattern,$settings['csrf_exclude_uris'],true),'Explicit finite lead bearer exemption');
$valid=[];
foreach(['leads','lead_stages','lead_sources','lead_inbox'] as $endpoint){$valid[]=[$endpoint];$valid[]=[$endpoint,'2147483647'];}
$valid[]=['lead_files','23'];$valid[]=['lead_files','23',str_repeat('a',64)];
$valid[]=['lead_imports'];$valid[]=['lead_imports',str_repeat('b',32)];
$valid[]=['lead_assignees'];$valid[]=['lead_exports'];
foreach($valid as $tail){
    $segments=['company_operations_api',...$tail];$uri=implode('/',$segments);
    $autoload=opsBootAutoload(opsBootCi('company_operations_api',$tail[0],$segments),$segments);
    opsBootCheck($autoload===['packages'=>[],'config'=>[],'helper'=>[],'language'=>[],'drivers'=>[],'libraries'=>['database'],'model'=>[]],'Lead bearer route loads only database, never a session/provider/module');
    opsBootCheck(CompanyOperationsApiScope::active(),'Exact lead scope prepared');
    $csrf=array_filter($leadPatterns,static fn($pattern)=>preg_match('#^'.$pattern.'$#D',$uri)===1);
    opsBootCheck(count($csrf)===1,'Exactly one lead CSRF pattern admits the endpoint');
    $matches=[];foreach($routes as $pattern=>$target)if(str_starts_with($pattern,'company_operations_api/')&&preg_match('#^'.$pattern.'$#D',$uri)===1)$matches[]=$target;
    opsBootCheck(count($matches)===1&&str_starts_with($matches[0],'operations_api/'.$tail[0]),'Exact finite native route only');
}
$invalid=[];
foreach(['leads','lead_stages','lead_sources','lead_inbox'] as $endpoint)foreach(['0','01','-1','1e2','2147483648','23.json','23/extra'] as $id)$invalid[]=[$endpoint,...explode('/',$id)];
foreach(['lead_assignees','lead_exports'] as $endpoint){$invalid[]=[$endpoint,'23'];$invalid[]=[$endpoint,'index'];}
foreach([[],['0'],['23','bad'],['23',str_repeat('A',64)],['23',str_repeat('a',64),'extra'],['2147483648',str_repeat('a',64)]] as $tail)$invalid[]=['lead_files',...$tail];
foreach(['23',str_repeat('A',32),str_repeat('a',31),str_repeat('a',33),str_repeat('a',32).'/extra'] as $batch)$invalid[]=['lead_imports',...explode('/',$batch)];
$invalid[]=['lead_delete_all'];$invalid[]=['lead_files','23',str_repeat('a',64),'extra','suffix','tail'];
foreach($invalid as $tail){
    $segments=['company_operations_api',...$tail];$denied=false;
    try{opsBootAutoload(opsBootCi('company_operations_api',$tail[0],$segments),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
    opsBootCheck($denied&&!CompanyOperationsApiScope::active(),'Malformed lead path rejected before legacy hydration');
}
foreach(['admin/leads','admin/leads/file','admin/leads/export','company_operations_api/lead_files','company_operations_api/lead_files/23/bad','company_operations_api/lead_exports/23','company_operations_api/lead_imports/'.str_repeat('a',32).'/extra','company_operations_api_evil/leads'] as $uri){
    opsBootCheck(!array_filter($leadPatterns,static fn($pattern)=>preg_match('#^'.$pattern.'$#D',$uri)===1),'GUI and malformed paths retain their CSRF boundary');
}
$segments=['company_operations_api','leads'];$denied=false;
try{opsBootAutoload(opsBootCi('dashboard','index',$segments,'admin'),$segments);}catch(DomainException $e){$denied=$e->getMessage()==='native_machine_404';}
opsBootCheck($denied,'Custom alias cannot hydrate the dashboard for a lead bearer URI');
require_once APPPATH.'services/CompanyLeadsApi.php';
opsBootCheck(array_intersect_key(CompanyApiTokens::SCOPES,CompanyLeadsApi::SCOPES)===CompanyLeadsApi::SCOPES,'All nine token scopes have the exact intended resource/capability mappings');
echo 'Leads native bootstrap: '.($checks-$leadsBootstrapStart).' additional / '.$checks.' cumulative checks.'.PHP_EOL;
