<?php
ob_start();require __DIR__.'/invoice_issuer_discovery.php';ob_end_clean();
class_alias(CoreIssuerDb::class,'CI_DB_mysqli_driver');
class CI_Controller { public $db; }
class ExportIssuerDb extends CoreIssuerDb { public function select($columns='*') { $columns=trim($columns,', ');return $columns===''?$this:parent::select($columns); } }
$db=new ExportIssuerDb($pdo);$ci=new CI_Controller();$ci->db=$db;
require __DIR__.'/../modules/exports/services/CSVExport.php';
require __DIR__.'/../modules/exports/services/PaymentCSVExport.php';
require __DIR__.'/../modules/exports/services/ExpenseCSVExport.php';
require __DIR__.'/../modules/exports/services/CustomerCSVExport.php';
require __DIR__.'/../modules/exports/services/ContactCSVExport.php';
require __DIR__.'/../application/libraries/App_bulk_pdf_export.php';
require __DIR__.'/../modules/einvoice/src/BulkExporter.php';
require __DIR__.'/../modules/einvoice/src/BulkExporterConfig.php';
class CapturedExportQuery extends RuntimeException {}
class IssuerPdfExport extends App_bulk_pdf_export {
 public function __construct($ci) { $this->ci=$ci;$this->can_view=true;$this->status='all'; }
 public function read($type) { $this->type=$type;$this->$type(); }
 protected function finalize() { $GLOBALS['exportRows']=$this->ci->db->get()->result_array();throw new CapturedExportQuery(); }
}
function csvExportFixture($class,$ci) {
 $object=(new ReflectionClass($class))->newInstanceWithoutConstructor();
 foreach(['ci'=>$ci,'startDate'=>null,'stopDate'=>null] as $name=>$value) (new ReflectionProperty(app\modules\exports\services\CSVExport::class,$name))->setValue($object,$value);
 return $object;
}
$db->query("ALTER TABLE {$prefix}invoicepaymentrecords ADD daterecorded VARCHAR(20)");
$db->query("CREATE TABLE {$prefix}expenses(id INT,invoiceid INT,paymentmode VARCHAR(20),tax INT,tax2 INT,category INT,currency INT,clientid INT,project_id INT,addedfrom INT,dateadded VARCHAR(20))");
foreach([[1,1],[2,2],[3,3],[4,0],[5,null],[6,999]] as [$id,$invoice]) $db->query("INSERT INTO {$prefix}expenses VALUES(?,?,'manual',0,0,1,1,100,0,0,'2026-10-18')",[$id,$invoice]);
$db->query("CREATE TABLE {$prefix}expenses_categories(id INT,name VARCHAR(20))");$db->query("INSERT INTO {$prefix}expenses_categories VALUES(1,'Synthetic')");
$db->query("CREATE TABLE {$prefix}taxes(id INT,name VARCHAR(20),taxrate INT)");
$db->query("CREATE TABLE {$prefix}staff(staffid INT,firstname VARCHAR(20),lastname VARCHAR(20))");
$checks=0;
$csv=csvExportFixture(app\modules\exports\services\PaymentCSVExport::class,$ci);
same([1],array_map('intval',array_column($csv->queryData()->get()->result_array(),'id')),'actual payment CSV query excludes X/Y/orphan');
$csv=csvExportFixture(app\modules\exports\services\ExpenseCSVExport::class,$ci);
same([1,4,5],array_map('intval',array_column($csv->queryData()->get()->result_array(),'id')),'expense CSV excludes named invoice links while preserving unbilled expenses');
$pdf=new IssuerPdfExport($ci);
foreach(['invoices'=>'id','payments'=>'paymentid'] as $kind=>$key) {
 try { $pdf->read($kind);throw new RuntimeException('Export query not captured'); } catch(CapturedExportQuery $expected) {}
 same([1],array_map('intval',array_column($GLOBALS['exportRows'],$key)),'actual '.$kind.' bulk PDF query excludes X/Y/orphan before rendering');
}
define('TEMP_FOLDER',sys_get_temp_dir().'/');
$electronic=(new ReflectionClass(Argws\CRM\EInvoice\BulkExporter::class))->newInstanceWithoutConstructor();
foreach(['ci'=>$ci,'config'=>new Argws\CRM\EInvoice\BulkExporterConfig('invoice',true,null,null,'all')] as $name=>$value) (new ReflectionProperty($electronic,$name))->setValue($electronic,$value);
(new ReflectionMethod($electronic,'buildExportQuery'))->invoke($electronic);
same([1],array_map('intval',array_column($db->get()->result_array(),'id')),'electronic invoice export query excludes X/Y before renderer');
// Customer exports enumerate the directory directly, so they need their own scope.
foreach(['leadid INT','addedfrom INT','default_currency INT','datecreated VARCHAR(20)'] as $column)$db->query("ALTER TABLE {$prefix}clients ADD $column");
$db->query("ALTER TABLE {$prefix}contacts ADD email VARCHAR(80)");$db->query("ALTER TABLE {$prefix}contacts ADD datecreated VARCHAR(20)");
$db->query("CREATE TABLE {$prefix}leads(id INT,name VARCHAR(80))");
$db->query("CREATE TABLE {$prefix}customer_groups(customer_id INT,groupid INT)");
$db->query("CREATE TABLE {$prefix}customers_groups(id INT,name VARCHAR(80))");
$db->query("CREATE TABLE {$prefix}issuers_clients(client_id INT,owner_issuer_id INT)");
$db->query("INSERT INTO {$prefix}contacts(id,userid,is_primary,firstname,lastname,email) VALUES(1100,100,1,'Legacy','Contact','synthetic@example.invalid')");
foreach([200,300] as $client){
 $db->query("INSERT INTO {$prefix}clients(userid,company) VALUES(?,?)",[$client,'Private '.$client]);
 $db->query("INSERT INTO {$prefix}issuers_clients VALUES(?,?)",[$client,$client]);
 $db->query("INSERT INTO {$prefix}contacts(id,userid,is_primary,firstname,lastname,email) VALUES(?,?,1,'Private','Contact','synthetic@example.invalid')",[$client,$client]);
}
$csv=csvExportFixture(app\modules\exports\services\CustomerCSVExport::class,$ci);
same([100],array_map('intval',array_column($csv->queryData()->get()->result_array(),'userid')),'customer CSV excludes private X/Y before selecting profile fields');
$csv=csvExportFixture(app\modules\exports\services\ContactCSVExport::class,$ci);
$rows=$csv->queryData()->get()->result_array();same([],array_values(array_intersect([200,300],array_map('intval',array_column($rows,'id')))),'contact CSV excludes private X/Y contacts before projection');
same([1100],array_map('intval',array_column($rows,'id')),'legacy contacts remain exportable');
$db->query("ALTER TABLE {$prefix}invoices DROP COLUMN issuer_id");
$csv=csvExportFixture(app\modules\exports\services\ExpenseCSVExport::class,$ci);
same([1,2,3,4,5,6],array_map('intval',array_column($csv->queryData()->get()->result_array(),'id')),'pre-migration expense selection remains unchanged');
echo 'Invoice export query boundaries: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."; SQL selection only, no generated PDF/CSV or ZIP).\n";
