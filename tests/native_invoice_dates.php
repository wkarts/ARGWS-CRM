<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/application/services/NativeCompanyInvoiceDates.php';
$checks=0;
function dateCheck(bool $ok):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException('date contract failed');}
foreach(CompanySettingsFormats::dates()as$key=>$format){
    $input=(new DateTimeImmutable('2024-02-28'))->format($format);
    dateCheck(NativeCompanyInvoiceDates::calculate($input,'2',$key)===(new DateTimeImmutable('2024-03-01'))->format($format));
    dateCheck(NativeCompanyInvoiceDates::calculate('2024-02-28','2',$key)===(new DateTimeImmutable('2024-03-01'))->format($format));
    dateCheck(NativeCompanyInvoiceDates::calculate($input,'0',$key)==='');
}
foreach([['2024-02-30','1'],['2023-02-29','1'],['2024-01-01','-1'],['2024-01-01','01'],['2024-01-01',1],['2024-01-01','10000'],['9999-12-31','1'],[['2024-01-01'],'1'],['today','1']]as[$date,$days]){
    try{NativeCompanyInvoiceDates::calculate($date,$days,'d/m/Y|%d/%m/%Y');throw new RuntimeException('invalid date accepted');}catch(DomainException $expected){++$checks;}
}
echo "Native invoice date formats/leap/zero/invalid: {$checks} checks PASS.\n";
