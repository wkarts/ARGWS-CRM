<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/application/services/NativeCompanyInvoiceTotals.php';
$checks=0;
$check=static function(bool$ok)use(&$checks){++$checks;if(!$ok)throw new RuntimeException('native totals parser mismatch');};
$check(NativeCompanyInvoiceTotals::request([])===['currency'=>null,'years'=>[],'scope'=>[]]);
$project=NativeCompanyResourceId::encode('company_project',7);
$parsed=NativeCompanyInvoiceTotals::request(['currency'=>'1','years'=>['2026'],'init_total'=>'true','display_type'=>'badge','project_id'=>$project]);
$check($parsed===['currency'=>1,'years'=>['2026'],'scope'=>['project_id'=>$project]]);
$check(NativeCompanyInvoiceTotals::request(['currency'=>'','customer_id'=>'','years'=>[]])===['currency'=>null,'years'=>[],'scope'=>[]]);
foreach([['issuer_id'=>2],['currency'=>[]],['years'=>'2026'],['years'=>['2026 OR 1=1']],['display_type'=>[]],['project_id'=>0],['project_id'=>$project,'customer_id'=>1],['init_total'=>'false']]as$input){
 try{NativeCompanyInvoiceTotals::request($input);throw new RuntimeException('unsafe totals payload accepted');}catch(DomainException$expected){++$checks;}
}
echo "Native invoice totals HTTP parser: {$checks} checks PASS.\n";
