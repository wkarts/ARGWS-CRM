<?php
ob_start();require __DIR__.'/invoice_issuer_scope.php';ob_end_clean();
require __DIR__.'/../application/models/Email_schedule_model.php';
class AdminController { public $db; public $load; public $input; public $invoices_model; public $email_schedule_model; }
require __DIR__.'/../application/controllers/admin/Email_schedule_invoice.php';
class IssuerEmailSchedule extends Email_schedule_model { public function __construct() {} }
class IssuerEmailController extends Email_schedule_invoice { public function __construct() {} }
class ScheduleHidden extends RuntimeException {}
function show_404() { throw new ScheduleHidden(); }
class ScheduleIssuerDb extends CoreIssuerDb {
 public function insert($table,$data) { $this->query('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));return true; }
}
$db=new ScheduleIssuerDb($pdo);
$idColumn=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
$db->query("CREATE TABLE {$prefix}scheduled_emails(id {$idColumn},rel_id INT,rel_type VARCHAR(20),scheduled_at VARCHAR(30),contacts VARCHAR(50),cc VARCHAR(50),attach_pdf INT,template VARCHAR(50))");
foreach([[1,1,'invoice'],[2,2,'invoice'],[3,3,'invoice'],[4,999,'invoice'],[5,2,'estimate']] as [$id,$rel,$type]) $db->query("INSERT INTO {$prefix}scheduled_emails VALUES(?,?,?,'2026-10-19','synthetic','',0,'invoice_send_to_customer')",[$id,$rel,$type]);
$model=new IssuerEmailSchedule();$model->db=$db;
$http=new IssuerEmailController();$http->db=$db;$http->email_schedule_model=$model;
$data=['scheduled_at'=>'2026-10-20','contacts'=>['synthetic'],'cc'=>'','attach_pdf'=>0,'template'=>'invoice_send_to_customer'];
$checks=0;
foreach([2,3,4] as $id) {
 same(null,$model->getById($id),'hidden/orphan scheduled email not hydrated');
 same(false,$model->update($id,$data+['rel_id'=>1]),'forged relation cannot update hidden schedule');
 try { $http->edit($id);throw new RuntimeException('Expected404'); } catch(ScheduleHidden $e) { same(true,true,'hidden edit returns404 before body'); }
}
foreach([2,3,999] as $invoice) {
 same(false,$model->create($invoice,'invoice',$data),'schedule cannot target X/Y/orphan');
 same(null,$model->get($invoice,'invoice'),'schedule lookup hidden');
 try { $http->create($invoice);throw new RuntimeException('Expected404'); } catch(ScheduleHidden $e) { same(true,true,'hidden create returns404 before effects'); }
}
same('estimate',$model->getById(5)->rel_type,'other domains preserved');
try { $http->edit(5);throw new RuntimeException('Expected404'); } catch(ScheduleHidden $e) { same(true,true,'invoice controller rejects other relation'); }
same(false,$model->update(1,$data+['rel_id'=>2]),'legacy schedule relation immutable');
same(true,$model->update(1,$data),'legacy schedule edit preserved');
$model->create(1,'invoice',$data);same(6,(int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}scheduled_emails")->row()->n,'legacy schedule creation preserved');
same('2026-10-19',$db->query("SELECT scheduled_at FROM {$prefix}scheduled_emails WHERE id=2")->row()->scheduled_at,'named schedule unchanged');
echo 'Invoice email schedule boundaries: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."; no transport).\n";
