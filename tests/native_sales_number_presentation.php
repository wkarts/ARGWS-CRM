<?php
require_once __DIR__.'/../application/services/SalesNumberPresentation.php';
$checks=0;
foreach([1=>'INV-000042',2=>'INV-2026/000042',3=>'INV-000042-26',4=>'INV-000042/10/2026',9=>42]as$format=>$expected){if(SalesNumberPresentation::format(42,$format,'INV-','2026-10-25',6)!==$expected)throw new RuntimeException('native number mismatch');++$checks;}
if(SalesNumberPresentation::format(1234567,1,'','2026-10-25',6)!=='1234567')throw new RuntimeException('number truncated');++$checks;
if(SalesNumberPresentation::format(7,1,'Literal <prefix>','2026-10-25',0)!=='Literal <prefix>7')throw new RuntimeException('presentation changed raw prefix');++$checks;
$source=file_get_contents(__DIR__.'/../application/helpers/sales_helper.php');
if(!str_contains($source,'SalesNumberPresentation::format($number, $format, $applied_prefix, $date, $prefixPadding)')||!str_contains($source,"apply_filters('sales_number_format'"))throw new RuntimeException('legacy formatter/hook no longer shares presentation');++$checks;
echo "Native sales number formats: {$checks} checks PASS.\n";
