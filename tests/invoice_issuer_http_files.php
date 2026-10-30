<?php
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__).'/application/'); }
ob_start();require __DIR__.'/invoice_issuer_scope.php';ob_end_clean();
require_once APPPATH.'services/CompanyRequestScope.php';
class App_Controller { public $db; public $load; public $input; public $invoices_model; public $payments_model; public $misc_model; }
class AdminController extends App_Controller {}
class ClientsController extends App_Controller {}
require __DIR__.'/../application/controllers/Download.php';
require __DIR__.'/../application/controllers/admin/Invoices.php';
require __DIR__.'/../application/controllers/admin/Payments.php';
require __DIR__.'/../application/controllers/admin/Misc.php';
require __DIR__.'/../application/controllers/Invoice.php';
require __DIR__.'/../application/models/Misc_model.php';
require __DIR__.'/../application/helpers/upload_helper.php';
class IssuerDownload extends Download { public function __construct() {} }
class IssuerInvoiceHttp extends Invoices { public function __construct() {} }
class IssuerPaymentHttp extends Payments { public function __construct() {} }
class IssuerMiscHttp extends Misc { public function __construct() {} }
class IssuerPublicInvoice extends Invoice { public function __construct() {} }
class IssuerMiscModel extends Misc_model { public function __construct() {} }
class HiddenIssuerResource extends RuntimeException {}
function show_404(...$args) { throw new HiddenIssuerResource(); }
function is_admin() { return true; } function is_client_logged_in() { return false; }
function force_download($path,$data) { $GLOBALS['downloaded']=$path; }
function forbidden(callable $operation,$label) { try{$operation();}catch(HiddenIssuerResource $expected){same(true,true,$label);return;}throw new RuntimeException('Expected 404: '.$label); }
$root=sys_get_temp_dir().'/issuer_files_'.bin2hex(random_bytes(4));mkdir($root);
foreach(['CONTRACTS_UPLOADS_FOLDER','PROJECT_ATTACHMENTS_FOLDER','EXPENSE_ATTACHMENTS_FOLDER','TICKET_ATTACHMENTS_FOLDER','TASKS_ATTACHMENTS_FOLDER']as$constant)define($constant,$root.'/unused-'.strtolower($constant).'/');
define('FCPATH',$root.'/');define('INVOICE_ATTACHMENTS_FOLDER',$root.'/uploads/invoices/');define('CLIENT_ATTACHMENTS_FOLDER',$root.'/uploads/clients/');
foreach([1,2,3] as $id) { mkdir(INVOICE_ATTACHMENTS_FOLDER.$id,0777,true);file_put_contents(INVOICE_ATTACHMENTS_FOLDER.$id.'/proof.jpg','synthetic'); }
$db->query("ALTER TABLE {$prefix}files ADD attachment_key VARCHAR(20)");$db->query("ALTER TABLE {$prefix}files ADD file_name VARCHAR(20)");
foreach([1,2,3] as $id) $db->query("UPDATE {$prefix}files SET attachment_key=?,file_name='proof.jpg' WHERE id=?",['key-'.$id,$id]);
$db->query("CREATE TABLE {$prefix}sales_activity(id INT,rel_id INT,rel_type VARCHAR(20))");$db->query("INSERT INTO {$prefix}sales_activity VALUES (2,2,'invoice'),(3,3,'invoice')");
$input=new class { public array $values=[]; public function post($key=null){return null;} public function get($key){return $this->values[$key]??null;} };
$download=new IssuerDownload();$download->db=$db;$download->load=$model->load;$download->input=$input;
$invoiceHttp=new IssuerInvoiceHttp();$invoiceHttp->db=$db;
$paymentHttp=new IssuerPaymentHttp();$paymentHttp->db=$db;$paymentHttp->payments_model=$payments;$paymentHttp->input=$input;
$miscHttp=new IssuerMiscHttp();$miscHttp->db=$db;
$miscModel=new IssuerMiscModel();$miscModel->db=$db;
$publicInvoice=new IssuerPublicInvoice();$publicInvoice->invoices_model=$model;
$checks=0;
try {
 $download->file('sales_attachment','key-1');same(INVOICE_ATTACHMENTS_FOLDER.'1/proof.jpg',$GLOBALS['downloaded'],'legacy protected download preserved');
 foreach([2,3] as $id) {
  unset($GLOBALS['downloaded']);forbidden(fn()=>$download->file('sales_attachment','key-'.$id),'named attachment key blocked');same(false,isset($GLOBALS['downloaded']),'blocked download not emitted');
  $input->values=['path'=>'uploads/invoices/'.$id.'/proof.jpg','type'=>'image/jpeg'];
  forbidden(fn()=>$download->preview_image(),'image preview named issuer blocked');forbidden(fn()=>$download->preview_video(),'video preview named issuer blocked');
  foreach(['update_number_settings','pause_overdue_reminders','resume_overdue_reminders','apply_credits','record_invoice_payment_ajax','get_merge_data'] as $method) forbidden(fn()=>$invoiceHttp->$method($id),'controller '.$method.' rejects named issuer before side effects');
  forbidden(fn()=>$invoiceHttp->client_change_data(100,$id),'customer change rejects named invoice');
  foreach(['pdf','send_to_email','payment'] as $method) forbidden(fn()=>$paymentHttp->$method($id),'payment controller '.$method.' returns404 instead of hydrating hidden payment');
  forbidden(fn()=>$miscHttp->toggle_file_visibility($id),'visibility toggle hidden invoice blocked');
  forbidden(fn()=>$miscHttp->delete_sale_activity($id),'activity delete hidden invoice blocked');
  forbidden(fn()=>handle_sales_attachments($id,'invoice'),'upload rejected before extension hook/storage');
  same(false,$miscModel->add_attachment_to_database($id,'invoice',[],true),'external attachment rejected before write');
  forbidden(fn()=>$publicInvoice->index($id,'synthetic'),'public invoice hash does not bypass scope');
 }
 $guard=new ReflectionMethod(Download::class,'assert_legacy_preview_path');
 $guard->invoke($download,INVOICE_ATTACHMENTS_FOLDER.'1/proof.jpg');same(true,true,'legacy image preview path remains valid');
 $input->values=['path'=>'../'.basename($root).'/../issuer_outside_'.basename($root).'.jpg','type'=>'image/jpeg'];
 $outside=dirname($root).'/issuer_outside_'.basename($root).'.jpg';file_put_contents($outside,'synthetic outside');
 forbidden(fn()=>$download->preview_image(),'preview cannot traverse outside app root');unlink($outside);
 same(3,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}files")->fetchColumn(),'denied attachment operations did not write');
} finally {
 foreach([1,2,3] as $id) { unlink(INVOICE_ATTACHMENTS_FOLDER.$id.'/proof.jpg');rmdir(INVOICE_ATTACHMENTS_FOLDER.$id); }
 rmdir(INVOICE_ATTACHMENTS_FOLDER);rmdir($root.'/uploads');rmdir($root);
}
echo 'Invoice HTTP and file boundaries: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).").\n";
