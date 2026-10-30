<?php
/** Original API bootstrap assertions plus the two finite recurrence endpoints. */
require __DIR__.'/operations_api_bootstrap.php';
$recurringBootstrapStart = $checks;
$pattern = 'company_operations_api/(task_schedules|standalone_schedules)(/[1-9][0-9]{0,9})?';
opsBootCheck(in_array($pattern, opsCsrfConfig()['csrf_exclude_uris'], true), 'Recurring bearer exemption is separate and finite');
$routes = opsRouteConfig();
foreach (['task_schedules','standalone_schedules'] as $domain) {
    foreach ([[], ['23']] as $suffix) {
        $segments = ['company_operations_api', $domain, ...$suffix];
        $autoload = opsBootAutoload(opsBootCi('company_operations_api', $domain, $segments), $segments);
        opsBootCheck($autoload === ['packages'=>[],'config'=>[],'helper'=>[],'language'=>[],'drivers'=>[],'libraries'=>['database'],'model'=>[]], 'Recurring bootstrap never loads an ambient session, module or provider');
        opsBootCheck(preg_match('#^'.$pattern.'$#D', implode('/', $segments)) === 1, 'Exact recurring bearer route is admitted');
    }
    opsBootCheck(($routes['company_operations_api/'.$domain] ?? null) === 'operations_api/'.$domain, 'Finite schedule list route');
    opsBootCheck(($routes['company_operations_api/'.$domain.'/([1-9][0-9]{0,9})'] ?? null) === 'operations_api/'.$domain.'/$1', 'Finite schedule detail route');
    foreach (['0','01','-1','1e2','2147483648','23.json','23/extra'] as $id) {
        $segments = ['company_operations_api', $domain, ...explode('/', $id)];
        $denied = false;
        try { opsBootAutoload(opsBootCi('company_operations_api', $domain, $segments), $segments); }
        catch (DomainException $error) { $denied = $error->getMessage() === 'native_machine_404'; }
        opsBootCheck($denied, 'Invalid schedule identifier rejected before hydration');
    }
    foreach (['admin/'.$domain, 'company_operations_api/'.$domain.'/0', 'company_operations_api/'.$domain.'/23/extra', 'company_operations_api_evil/'.$domain] as $uri) {
        opsBootCheck(preg_match('#^'.$pattern.'$#D', $uri) === 0, 'Invalid and GUI schedule paths retain CSRF protection');
    }
}
echo 'Task recurrence bootstrap: '.($checks-$recurringBootstrapStart).' additional / '.$checks.' cumulative checks.'.PHP_EOL;
