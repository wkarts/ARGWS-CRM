<?php
require __DIR__.'/invoice_issuer_scope.php';
require __DIR__.'/../application/models/Reports_model.php';
require __DIR__.'/../application/models/Dashboard_model.php';
require_once __DIR__.'/../application/services/ClientIssuerScope.php';
require __DIR__.'/../application/models/Clients_model.php';
require __DIR__.'/../application/services/utilities/Arr.php';
class IssuerReports extends Reports_model { public function __construct() {} }
class IssuerDashboard extends Dashboard_model { public function __construct() {} }
class IssuerCustomers extends Clients_model { public function __construct() {} }
function get_weekdays_original() { return ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']; }
function get_weekdays() { return get_weekdays_original(); }
function get_system_favourite_colors() { return []; }
function adjust_color_brightness($color,$amount) { return $color; }
function handle_custom_fields_post(...$args) { return false; }
function do_action_deprecated(...$args) {}
function log_activity(...$args) {}
if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
 TestPdo::registerSqliteFunction($pdo,'YEAR',fn($date)=>(int)date('Y',strtotime($date)));
 TestPdo::registerSqliteFunction($pdo,'MONTH',fn($date)=>(int)date('n',strtotime($date)));
 TestPdo::registerSqliteFunction($pdo,'YEARWEEK',fn($date)=>(int)date('oW',strtotime($date)));
}
$db->query("ALTER TABLE {$prefix}invoicepaymentrecords ADD date VARCHAR(10)");
$db->query("UPDATE {$prefix}invoicepaymentrecords SET date=?",[date('Y-m-d')]);
$db->query("UPDATE {$prefix}invoicepaymentrecords SET amount=1000,paymentmode='corporate' WHERE id IN (2,3,4)");
$db->query("CREATE TABLE {$prefix}creditnote_refunds(amount INT,refunded_on VARCHAR(10),credit_note_id INT)");
$db->query("CREATE TABLE {$prefix}expenses(id INT,date VARCHAR(10))");
$db->query("CREATE TABLE {$prefix}customers_groups(id INT,name VARCHAR(20))");$db->query("INSERT INTO {$prefix}customers_groups VALUES (1,'Same group')");
$db->query("CREATE TABLE {$prefix}customer_groups(groupid INT,customer_id INT)");$db->query("INSERT INTO {$prefix}customer_groups VALUES (1,100)");
$input=new class { public function post($key) { return $key==='year'?(int)date('Y'):null; } };
$reports=new IssuerReports();$reports->db=$db;$reports->load=$model->load;$reports->input=$input;
$reports->payment_modes_model=new class { public function get(...$args) { return [['id'=>'manual','name'=>'Manual'],['id'=>'corporate','name'=>'Other issuer only']]; } };
$reports->clients_model=new class { public function get_groups() { return [['name'=>'Same group']]; } };
$dashboard=new IssuerDashboard();$dashboard->db=$db;
$checks=0;
$chart=$dashboard->get_weekly_payments_statistics('undefined');same(10,(int)array_sum($chart['datasets'][0]['data']),'weekly dashboard excludes X/Y/orphan');
$chart=$dashboard->get_monthly_payments_statistics('undefined');same(10,(int)array_sum($chart['datasets'][0]['data']),'monthly dashboard excludes X/Y/orphan');
$chart=$reports->total_income_report();same(10,(int)array_sum($chart['datasets'][0]['data']),'total income invoice portion legacy only');
$chart=$reports->get_expenses_vs_income_report((int)date('Y'));same(10,(int)array_sum($chart['datasets'][0]['data']),'expenses vs income invoice portion legacy only');
$chart=$reports->report_by_customer_groups();same(10,(int)array_sum($chart['datasets'][0]['data']),'group income legacy only despite shared customer');
$chart=$reports->report_by_payment_modes();same(1,count($chart['datasets']),'named issuer-only payment mode not disclosed');same(10,(int)array_sum($chart['datasets'][0]['data']),'mode income legacy only');
$db->query("UPDATE {$prefix}invoicepaymentrecords SET date='2050-01-01' WHERE id IN (2,3,4)");
same([(int)date('Y')],array_map('intval',array_column($reports->get_distinct_payments_years(),'year')),'payment filter years exclude named/orphan');
$db->query("UPDATE {$prefix}invoices SET date='2050-01-01' WHERE id IN (2,3)");
same([2026],array_map('intval',array_column($reports->get_distinct_customer_invoices_years(),'year')),'portal year options exclude named issuers');
$customer=new IssuerCustomers();$customer->db=$db;$customer->client_groups_model=new class { public function sync_customer_groups(...$args) { return false; } };
same(['referenced'=>true],$customer->delete(100),'customer deletion/GDPR cannot detach another issuer invoice');
$fields=['billing_street','billing_city','billing_state','billing_zip','billing_country','shipping_street','shipping_city','shipping_state','shipping_zip','shipping_country'];
foreach($fields as $field) {
 $db->query("ALTER TABLE {$prefix}clients ADD $field VARCHAR(50)");
 if(!in_array($field,['billing_country','shipping_country'])) $db->query("ALTER TABLE {$prefix}invoices ADD $field VARCHAR(50)");
}
foreach(['show_primary_contact','default_currency','country'] as $field) $db->query("ALTER TABLE {$prefix}clients ADD $field INT");
$db->query("CREATE TABLE {$prefix}estimates (clientid INT,".implode(',',array_map(fn($field)=>$field.' VARCHAR(50)',$fields)).')');
$data=array_fill_keys($fields,'New synthetic address');$data['billing_country']=1;$data['shipping_country']=1;$data['update_all_other_transactions']=1;
same(true,$customer->update($data,100),'legacy customer update preserved');
same('New synthetic address',$db->query("SELECT billing_street FROM {$prefix}invoices WHERE id=1")->row()->billing_street,'legacy invoice address updated');
same(null,$db->query("SELECT billing_street FROM {$prefix}invoices WHERE id=2")->row()->billing_street,'issuer X document untouched');
same(null,$db->query("SELECT billing_street FROM {$prefix}invoices WHERE id=3")->row()->billing_street,'issuer Y document untouched');
echo 'Issuer reports and customer maintenance: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).").\n";
