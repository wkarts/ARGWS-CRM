<?php
declare(strict_types=1);
require __DIR__.'/native_invoice_status_presentation.php';
require_once APPPATH.'libraries/App_table_filter.php';
require_once APPPATH.'services/utilities/StrClickable.php';require_once APPPATH.'services/utilities/Str.php';require_once APPPATH.'services/utilities/Js.php';
require_once APPPATH.'services/NativeCompanyInvoiceStats.php';
function staff_can($cap,$feature){return true;}
function endsWith($value,$suffix){return str_ends_with($value,$suffix);}
function db_prefix(){return 'synthetic_';}
$GLOBALS['counts']=[1=>2,2=>1,3=>1,4=>1,5=>1,6=>0];$GLOBALS['nativeRendering']=false;$GLOBALS['queries']=0;
function total_rows($table,$where){if($GLOBALS['nativeRendering'])throw new RuntimeException('owned quick stats attempted unscoped query');++$GLOBALS['queries'];return is_array($where)?$GLOBALS['counts'][$where['status']]:array_sum($GLOBALS['counts']);}
$render=static function(bool $owned):string{
 $invoices_statuses=range(1,6);$invoices_table=new class{public function findRule($id){return App_table_filter::new($id,'MultiSelectRule');}};
 if($owned)$native_invoice_statistics=NativeCompanyInvoiceStats::fromCounts($GLOBALS['counts']);
 $GLOBALS['nativeRendering']=$owned;ob_start();include APPPATH.'views/admin/invoices/quick_stats.php';return ob_get_clean();
};
$legacy=$render(false);$owned=$render(true);
if($legacy!==$owned)throw new RuntimeException('same native quick-stats DOM diverged');
if($GLOBALS['queries']!==6||substr_count($owned,'<button type="button"')!==5||!str_contains($owned,'33.33')||!preg_match('/2\s*\/\s*6/',$owned))throw new RuntimeException('native denominator/button contract changed');
foreach([[1=>-1,2=>0,3=>0,4=>0,5=>0,6=>0],[1=>PHP_INT_MAX,2=>1,3=>0,4=>0,5=>0,6=>0],[]]as$bad){try{NativeCompanyInvoiceStats::fromCounts($bad);throw new RuntimeException('invalid count accepted');}catch(DomainException$expected){}}
echo "Native quick stats real shared view: exact legacy/owned DOM, five controls, no owned SQL, cancelled denominator and invalid counts PASS.\n";
