<?php
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__,2).'/system/'); define('APPPATH',dirname(__DIR__,2).'/application/');
$file=$argv[1]??APPPATH.'config/routes.php'; $route=[]; require $file;
$expected=['company_purchase_portal'=>'company_purchase_portal/index','company_purchase_portal/index'=>'company_purchase_portal/index'];
foreach (['suppliers','requests','quotes','orders','receipts','documents','payables','events','files','file_download','settings','reports','invites','order_pdf','export'] as $resource) {
    $expected['company_purchases_api/index/'.$resource]='company_purchases_api/index/'.$resource;
    $expected['company_purchases_api/index/'.$resource.'/([1-9][0-9]{0,9})']='company_purchases_api/index/'.$resource.'/$1';
}
$actual=array_filter($route,static fn($value,$key)=>str_starts_with($key,'company_purchase'),ARRAY_FILTER_USE_BOTH); ksort($expected); ksort($actual);
if ($actual!==$expected) { throw new RuntimeException('Finite executable Purchase route catalog differs: expected32, actual'.count($actual)); }
foreach (['company_purchases_api/index/unknown','company_purchases_api/index/orders/0','company_purchases_api/index/orders/01','company_purchases_api/index/orders/23/extra','company_purchase_portal/extra','company_purchase_portal_evil'] as $invalid) {
    foreach ($actual as $pattern=>$target) { if (preg_match('#^'.$pattern.'$#D',$invalid)) { throw new RuntimeException('Unreviewed route matched finite catalog'); } }
}
echo 'Purchases executable route catalog: 32 exact mappings and six invalid paths passed; source '.hash_file('sha256',$file).PHP_EOL;
