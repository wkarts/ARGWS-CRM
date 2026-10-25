<?php
/** Pure projection contract. No database, provider, clock dependency or HTTP. */
define('BASEPATH',dirname(__DIR__).'/system/');
define('APPPATH',dirname(__DIR__).'/application/');
require_once __DIR__.'/../application/services/CompanyDashboardPayments.php';
function dashboardChartCheck($ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$rows=[['date'=>'2026-10-19','effective_amount'=>'0.10'],['date'=>'2026-10-19','effective_amount'=>'0.20'],['date'=>'2026-10-12','effective_amount'=>'4.15'],['date'=>'2026-10-25','effective_amount'=>'0.00']];
$chart=CompanyDashboardPayments::project($rows,'weekly','2026-10-25');
dashboardChartCheck($chart['datasets'][0]['data'][0]==='0.30','decimal aggregation, no floating sum');
dashboardChartCheck($chart['datasets'][1]['data'][0]==='4.15','previous week separate');
dashboardChartCheck(count($chart['labels'])===7&&count($chart['datasets'])===2,'native weekly contract');
dashboardChartCheck($chart['datasets'][0]['data'][6]==='0.00','reversed receipt remains zero effective, not raw');
$monthly=CompanyDashboardPayments::project([['date'=>'2026-01-01','effective_amount'=>'12.34'],['date'=>'2026-12-31','effective_amount'=>'99.01']],'monthly','2026-10-25');
dashboardChartCheck(count($monthly['labels'])===12&&$monthly['datasets'][0]['data'][0]==='12.34'&&$monthly['datasets'][0]['data'][11]==='99.01','calendar-year bounds');
foreach([
    [[['date'=>'2026-02-30','effective_amount'=>'1.00']],'monthly','2026-10-25'],
    [[['date'=>'2026-10-11','effective_amount'=>'1.00']],'weekly','2026-10-25'],
    [[['date'=>'2026-10-19','effective_amount'=>'-1.00']],'weekly','2026-10-25'],
    [[['date'=>'2026-10-19','effective_amount'=>'1e2']],'weekly','2026-10-25'],
    [[], 'arbitrary','2026-10-25'],[[],'weekly','2026-02-30'],
] as $i=>$args){try{CompanyDashboardPayments::project(...$args);throw new RuntimeException('accepted invalid projection '.$i);}catch(DomainException $e){echo "PASS invalid projection $i\n";}}
echo "Native dashboard payment projection passed; no bank cash-flow claim.\n";
