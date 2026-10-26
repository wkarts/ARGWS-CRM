<?php
if(!defined('APPPATH'))define('APPPATH',dirname(__DIR__).'/application/');
ob_start();require __DIR__.'/invoice_issuer_scope.php';ob_end_clean();
require __DIR__.'/../application/models/Misc_model.php';
require __DIR__.'/../application/models/Subscriptions_model.php';
require __DIR__.'/../application/models/Utilities_model.php';
require __DIR__.'/../application/services/ValidatesContact.php';
class ClientsController { public $db; public $input; }
class AdminController { public $db; public $input; public $load; public $currencies_model; }
require __DIR__.'/../application/controllers/Clients.php';
require __DIR__.'/../application/controllers/admin/Reports.php';
class IssuerSearch extends Misc_model { public function __construct() {} }
class IssuerSubscription extends Subscriptions_model { public function __construct() {} }
class IssuerCalendar extends Utilities_model { public function __construct() {} }
class IssuerPortalChart extends Clients { public function __construct() {} }
class IssuerReportHttp extends Reports { public function __construct() {} }
function prefixed_table_fields_array($table,$asString=false,$excluded=[]) { $fields=array_map(fn($field)=>$table.'.'.$field,array_diff($GLOBALS['db']->list_fields($table),$excluded)); return $asString?implode(',',$fields):$fields; }
function startsWith($text,$prefix) { return str_starts_with($text,(string)$prefix); }
function strafter($text,$part) { return substr($text,strpos($text,$part)+strlen($part)); }
function hex2rgb($hex) { return [0,0,0]; }
function is_admin() { return true; }
function has_contact_permission(...$args) { return true; }
function admin_url($path) { return $path; }
function sales_number_format($number,$format,$prefix,$date) { return $prefix.$number; }
if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
 @$pdo->sqliteCreateFunction('YEAR',fn($date)=>(int)date('Y',strtotime($date)));
 @$pdo->sqliteCreateFunction('CONCAT',fn(...$values)=>implode('',$values));
 @$pdo->sqliteCreateFunction('VERSION',fn()=>'8.0');
}
$fields=['vat','phonenumber','city','state','zip','address','billing_street','billing_city','billing_state','billing_zip','shipping_street','shipping_city','shipping_state','shipping_zip'];
foreach($fields as $field) $db->query("ALTER TABLE {$prefix}clients ADD $field VARCHAR(50)");
foreach(array_merge(array_slice($fields,6),['formatted_number','clientnote','adminnote','duedate','prefix','number_format']) as $field) $db->query("ALTER TABLE {$prefix}invoices ADD $field VARCHAR(50)");
foreach(['subscription_id','subtotal','discount_total'] as $field) $db->query("ALTER TABLE {$prefix}invoices ADD $field INT DEFAULT 0");
$db->query("UPDATE {$prefix}invoices SET subscription_id=77,subtotal=500,duedate='2026-10-31',prefix='TEST-',number_format='1'");
$db->query("UPDATE {$prefix}invoices SET total=9000,subtotal=9000 WHERE id IN(2,3)");
$db->query("CREATE TABLE {$prefix}contacts(id INT,userid INT,is_primary INT,firstname VARCHAR(30),lastname VARCHAR(30))");
$db->query("CREATE TABLE {$prefix}tags(id INT,name VARCHAR(30))");$db->query("INSERT INTO {$prefix}tags VALUES (7,'shared')");
$db->query("CREATE TABLE {$prefix}taggables(rel_id INT,tag_id INT,rel_type VARCHAR(20))");
$db->query("INSERT INTO {$prefix}taggables VALUES (1,7,'invoice'),(2,7,'invoice'),(3,7,'invoice')");
$db->query("ALTER TABLE {$prefix}invoicepaymentrecords ADD date VARCHAR(10)");$db->query("ALTER TABLE {$prefix}invoicepaymentrecords ADD note VARCHAR(50)");
$db->query("UPDATE {$prefix}invoicepaymentrecords SET note='same token',date='2026-10-18'");
foreach(['description','long_description'] as $field) $db->query("ALTER TABLE {$prefix}itemable ADD $field VARCHAR(50)");
foreach(['qty','rate','is_selected'] as $field) $db->query("ALTER TABLE {$prefix}itemable ADD $field INT DEFAULT 1");
$db->query("UPDATE {$prefix}itemable SET description='same token',long_description='same token'");
$db->query("INSERT INTO {$prefix}itemable(id,rel_id,rel_type,description,long_description) VALUES(4,999,'invoice','same token','same token')");
$db->query("CREATE TABLE {$prefix}item_tax(id INT,rel_id INT,rel_type VARCHAR(20),taxname VARCHAR(30),taxrate INT)");
$db->query("INSERT INTO {$prefix}item_tax VALUES(1,1,'invoice','Legacy tax',1),(2,2,'invoice','Private X tax',9),(3,3,'invoice','Private Y tax',9)");
$db->query("CREATE TABLE {$prefix}customfieldsvalues(id INT,relid INT,fieldto VARCHAR(20),value VARCHAR(50))");
$db->query("INSERT INTO {$prefix}customfieldsvalues VALUES(1,1,'invoice','same token'),(2,2,'invoice','same token'),(3,3,'invoice','same token'),(4,999,'invoice','same token')");
$checks=0;$search=new IssuerSearch();$search->db=$db;
foreach(['Synthetic','#shared','1'] as $query) same([1],array_map('intval',array_column($search->_search_invoices($query)['result'],'invoiceid')),'invoice search and tag search exclude X/Y');
same([1],array_map('intval',array_column($search->_search_invoice_payments('same token')['result'],'paymentid')),'payment OR search excludes X/Y/orphan');
same([1],array_map('intval',array_column($search->_search_invoice_items('same token')['result'],'id')),'item OR search excludes X/Y/orphan');
same([1],array_map('intval',array_column($search->_search_custom_fields('same token')['result'],'id')),'custom field search excludes X/Y/orphan');
$subscription=new IssuerSubscription();$subscription->db=$db;$subscription->invoices_model=$model;
same([1],array_map(fn($row)=>(int)$row->id,$subscription->get_child_invoices(77)),'subscription does not disclose named children or null placeholders');
$input=new class { public function post($key) { return null; } public function is_ajax_request() { return true; } };
$chart=new IssuerPortalChart();$chart->db=$db;$chart->input=$input;
ob_start();$chart->client_home_chart();$rendered=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
same(500,(int)array_sum(array_merge(...array_column($rendered['datasets'],'data'))),'portal chart only includes NULL despite same customer');
$calendar=new IssuerCalendar();$calendar->db=$db;
$events=$calendar->get_calendar_data('2026-01-01','2026-12-31',100,7,['calendar_filters'=>true,'invoices'=>true]);
same([1],array_map('intval',array_column($events,'id')),'client calendar hides named invoices');
$report=new IssuerReportHttp();$report->db=$db;$report->input=$input;$report->load=$model->load;$report->currencies_model=new class { public function get_base_currency() { return (object)['name'=>'BRL']; } };
$taxes=(new ReflectionMethod(Reports::class,'distinct_taxes'))->invoke($report,'invoice');
same(['Legacy tax'],array_column($taxes,'taxname'),'report tax selector hides private tax metadata');
$GLOBALS['tableProjection']=true;
try { $report->customers_report();throw new RuntimeException('Report query not captured'); } catch(CapturedIssuerTable $expected) {}
$values=array_values($GLOBALS['tableRows'][0]);
same([1,500,500],array_map('intval',array_slice($values,1)),'customer report aggregates legacy only');
$GLOBALS['tableProjection']=false;
try { $report->invoices_report();throw new RuntimeException('Report query not captured'); } catch(CapturedIssuerTable $expected) {}
same([1],array_map('intval',array_column($GLOBALS['tableRows'],'id')),'invoice report legacy only');
$db->query("UPDATE {$prefix}invoices SET status=2");
try { $report->items();throw new RuntimeException('Report query not captured'); } catch(CapturedIssuerTable $expected) {}
same([1],array_map('intval',array_column($GLOBALS['tableRows'],'id')),'sold items report legacy only');
echo 'Invoice discovery and report HTTP: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).").\n";
