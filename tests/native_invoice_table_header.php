<?php
/** Same production native header, both contexts; framework lookups are explicit doubles. */
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
class CompanyRequestScope{public static bool $native=false;public static function nativeFor($ci){return self::$native;}}
$ci=(object)['agent'=>new class{public function browser(){return 'Synthetic';}}];
function &get_instance(){return$GLOBALS['ci'];}function _l($v){return$v;}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$GLOBALS['fieldReads']=0;$GLOBALS['hookReads']=0;
function get_custom_fields($type,$filters){++$GLOBALS['fieldReads'];return$GLOBALS['definitions'];}
function hooks(){return new class{public function apply_filters($name,$value){++$GLOBALS['hookReads'];return$value;}};}
require APPPATH.'helpers/datatables_helper.php';
$definitions=[['id'=>17,'name'=>'Synthetic <field>','type'=>'input']];$native_invoice_custom_fields=$definitions;
$path=APPPATH.'views/admin/invoices/table_html.php';
ob_start();include$path;$legacy=ob_get_clean();CompanyRequestScope::$native=true;
ob_start();include$path;$owned=ob_get_clean();
if($legacy!==$owned||$GLOBALS['fieldReads']!==1||$GLOBALS['hookReads']!==1)throw new RuntimeException('native header or scoped lookup isolation mismatch');
if(!str_contains($owned,'Synthetic &lt;field&gt;')||!str_contains($owned,'class="dt-table-loading table table-invoices"'))throw new RuntimeException('native classes/escaping mismatch');
unset($native_invoice_custom_fields);ob_start();try{include$path;throw new RuntimeException('missing native fields fell back global');}catch(DomainException$e){if($e->getMessage()!=='native_company_invoice_columns_missing')throw$e;}finally{ob_end_clean();}
if($GLOBALS['fieldReads']!==1||$GLOBALS['hookReads']!==1)throw new RuntimeException('fallback called global');
echo "Shared invoice header native/owned byte parity, escaped fields and no global fallback: PASS.\n";
