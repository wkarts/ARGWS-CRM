<?php
/** Newly reconstructed tests after source recovery. Not an assertion of prior test restoration. */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/application/services/CompanyInvoiceRecurringDates.php';
$checks=0;
function recurrenceDateCheck(bool $ok,string $label):void { global $checks; ++$checks; if(!$ok)throw new RuntimeException($label); }
function recurrenceDateDenied(callable $f,string $label):void { try{$f();}catch(DomainException $e){recurrenceDateCheck($e->getMessage()==='company_invoice_recurring_invalid',$label);return;}throw new RuntimeException('Expected rejection: '.$label); }
$conf=static fn(string $unit,int $interval=1,int $cycles=0)=>CompanyInvoiceRecurringDates::configuration(['unit'=>$unit,'interval'=>$interval,'custom'=>true,'cycles'=>$cycles]);
foreach([
 ['2024-01-31','month',1,0,'2024-01-31'], ['2024-01-31','month',1,1,'2024-02-29'], ['2024-01-31','month',1,2,'2024-03-31'],
 ['2023-01-31','month',1,1,'2023-02-28'], ['2024-02-29','year',1,1,'2025-02-28'], ['2024-02-29','year',1,4,'2028-02-29'],
 ['2024-12-31','day',1,1,'2025-01-01'], ['2024-12-31','week',2,1,'2025-01-14'], ['2024-01-31','month',3,1,'2024-04-30'],
 ['9999-12-31','day',1,1,null], ['9999-12-31','week',1,1,null], ['9999-12-31','month',1,1,null], ['9999-12-31','year',1,1,null],
] as [$date,$unit,$interval,$step,$expected])recurrenceDateCheck(CompanyInvoiceRecurringDates::occurrence($date,$conf($unit,$interval),$step)===$expected,'calendar '.$date.'/'.$unit.'/'.$step);
foreach(['2024-02-30','2023-02-29','0000-01-01','0999-12-31','10000-01-01','2024-1-01','2024-01-01 00:00:00','2024-01-01Z',null,20240101] as $bad)recurrenceDateDenied(fn()=>CompanyInvoiceRecurringDates::date($bad),'strict civil date');
foreach(['day','week','month','year'] as $unit){$c=$conf($unit);recurrenceDateCheck($c['cycles']===0&&$c['send']===false&&$c['contact_id']===0,'unlimited and no implicit transport '.$unit);}
$default=CompanyInvoiceRecurringDates::configuration(['unit'=>'month','interval'=>'12','custom'=>'0','cycles'=>'10000','send'=>'0']);
recurrenceDateCheck($default['interval']===12&&$default['cycles']===10000&&!$default['custom'],'native monthly range canonical types');
foreach([
 ['unit'=>'day','interval'=>1], ['unit'=>'month','interval'=>13], ['unit'=>'month','interval'=>0], ['unit'=>'month','interval'=>'01'],
 ['unit'=>'month','interval'=>1,'cycles'=>-1], ['unit'=>'month','interval'=>1,'cycles'=>10001], ['unit'=>'month','interval'=>1,'custom'=>'yes'],
 ['unit'=>'month','interval'=>1,'unknown'=>true], ['unit'=>'month','interval'=>1,'contact_id'=>5], ['unit'=>'month','interval'=>1,'base_url'=>'https://example.test'],
 ['unit'=>'month','interval'=>1,'send'=>true,'contact_id'=>1,'base_url'=>'http://example.test'],
 ['unit'=>'month','interval'=>1,'send'=>true,'contact_id'=>1,'base_url'=>'https://u:p@example.test'],
 ['unit'=>'month','interval'=>1,'send'=>true,'contact_id'=>1,'base_url'=>'https://example.test/?token=1'],
 ['unit'=>'month','interval'=>1,'send'=>true,'contact_id'=>1,'base_url'=>'https://example.test/#x'],
] as $bad)recurrenceDateDenied(fn()=>CompanyInvoiceRecurringDates::configuration($bad),'invalid config');
foreach(['https://example.test/crm','http://127.0.0.1:48181','http://localhost:48181','http://[::1]:48181']as$url)recurrenceDateCheck(CompanyInvoiceRecurringDates::configuration(['unit'=>'month','interval'=>1,'send'=>1,'contact_id'=>1,'base_url'=>$url])['base_url']===$url,'explicit transport URL');
foreach([-1,4000001]as$step)recurrenceDateDenied(fn()=>CompanyInvoiceRecurringDates::occurrence('2024-01-01',$conf('day'),$step),'bounded calendar step');
// Enumerate complete Gregorian leap-year cycle to prove anchors do not drift after February.
for($year=2000;$year<2400;$year++){
 $anchor=sprintf('%04d-01-31',$year);$feb=CompanyInvoiceRecurringDates::occurrence($anchor,$conf('month'),1);$expected=(new DateTimeImmutable(sprintf('%04d-02-01',$year)))->format('t');
 recurrenceDateCheck($feb===sprintf('%04d-02-%02d',$year,$expected),'Gregorian February '.$year);
 recurrenceDateCheck(CompanyInvoiceRecurringDates::occurrence($anchor,$conf('month'),2)===sprintf('%04d-03-31',$year),'anchor restored '.$year);
}
echo "Reconstructed439 recurring calendar/configuration: {$checks} checks passed. No DB, transport or historical test claim.\n";
