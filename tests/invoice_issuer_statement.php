<?php
require __DIR__.'/invoice_issuer_scope.php';
require __DIR__.'/../application/models/Statement_model.php';
require __DIR__.'/../application/models/Credit_notes_model.php';
class IssuerStatement extends Statement_model { public function __construct() {} }
class IssuerCredits extends Credit_notes_model { public function __construct() {} }
function invoices_statuses_available_for_credits() { return [1,3,4]; }
if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
 @$pdo->sqliteCreateFunction('CONCAT',fn(...$parts)=>implode('',$parts));
 @$pdo->sqliteCreateFunction('LOCATE',fn($needle,$haystack)=>strpos($haystack,$needle)+1);
 @$pdo->sqliteCreateFunction('RIGHT_FN',fn($text,$count)=>substr($text,-$count));
}
foreach(['duedate','datecreated'] as $field) $db->query("ALTER TABLE {$prefix}invoices ADD $field VARCHAR(20)");
$db->query("UPDATE {$prefix}invoices SET duedate='2026-10-20',datecreated='2026-10-18 09:00:00'");
foreach(['date','daterecorded'] as $field) $db->query("ALTER TABLE {$prefix}invoicepaymentrecords ADD $field VARCHAR(20)");
$db->query("UPDATE {$prefix}invoicepaymentrecords SET date='2026-10-18',daterecorded='2026-10-18 09:00:00'");
$db->query("CREATE TABLE {$prefix}creditnotes(id INT,clientid INT,total INT,status INT,date VARCHAR(10),datecreated VARCHAR(20))");
$db->query("INSERT INTO {$prefix}creditnotes VALUES (9,100,0,1,'2026-10-18','2026-10-18 09:00:00')");
$db->query("CREATE TABLE {$prefix}credits(id INT,invoice_id INT,credit_id INT,date VARCHAR(10),date_applied VARCHAR(20),amount INT)");
foreach([1,2,3] as $id) $db->query("INSERT INTO {$prefix}credits VALUES (?,?,9,'2026-10-18','2026-10-18 09:00:00',2)",[$id,$id]);
$db->query("CREATE TABLE {$prefix}creditnote_refunds(id INT,credit_note_id INT,amount INT,refunded_on VARCHAR(20),created_at VARCHAR(20))");
$statement=new IssuerStatement();$statement->db=$db;$statement->load=$model->load;
$statement->clients_model=new class { public function get($id) { return (object)['company'=>'Synthetic']; } public function get_customer_default_currency($id) { return 1; } };
$statement->currencies_model=new class { public function get($id) { return (object)['id'=>1,'name'=>'BRL']; } };
$checks=0;
$result=$statement->get_statement(100,'2026-10-01','2026-10-31');
same([1],array_map('intval',array_column($result['result'],'invoice_id')),'statement invoice lines exclude X/Y');
same([1],array_map('intval',array_column($result['result'],'payment_invoice_id')),'statement payments exclude X/Y/orphan');
same([1],array_map('intval',array_column($result['result'],'credit_invoice_id')),'statement applied credit invoice references exclude X/Y');
same(500,(int)$result['invoiced_amount'],'statement invoice total only legacy');same(10,(int)$result['amount_paid'],'statement paid total only legacy');
$db->query("UPDATE {$prefix}invoices SET date='2026-09-18'");$db->query("UPDATE {$prefix}invoicepaymentrecords SET date='2026-09-18'");
$result=$statement->get_statement(100,'2026-10-01','2026-10-31');same(490,(int)$result['beginning_balance'],'beginning invoice/payment balance scopes both subqueries');
$credits=new IssuerCredits();$credits->db=$db;
foreach([2,3,999] as $id) {
 same(false,$credits->apply_credits(9,['invoice_id'=>$id,'amount'=>1]),'cannot apply credit to hidden invoice');
 same(false,$credits->credit_note_from_invoice($id),'cannot derive credit note from hidden invoice');
 same([],$credits->get_applied_invoice_credits($id),'cannot list hidden invoice credits');
}
same(false,$credits->delete_applied_credit(2,9,2),'cannot delete hidden invoice credit');
same(false,$credits->delete_applied_credit(2,9,1),'cannot forge legacy invoice to delete hidden credit');
same(false,$credits->delete_applied_credit(1,10,1),'credit note ID must match stored allocation');
same([1],array_map('intval',array_column($credits->get_applied_credits(9),'id')),'credit note allocation detail excludes hidden invoice IDs');
same([1],array_map('intval',array_column($credits->get_available_creditable_invoices(9),'id')),'creditable invoice selector excludes X/Y');
same(3,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}credits")->fetchColumn(),'blocked credit mutations changed no rows');
$db->query("CREATE TABLE {$prefix}issuers_clients(client_id INT PRIMARY KEY,owner_issuer_id INT)");
$db->query("INSERT INTO {$prefix}issuers_clients VALUES(100,11)");
$before=count($db->queries);
denied(fn()=>$statement->get_statement(100,'2026-01-01','2026-12-31'),'client_scope_denied');
$after=array_slice($db->queries,$before);
same(1,count($after),'private statement stops after client ownership lookup');
same(true,str_starts_with($after[0][0],'SELECT userid FROM '),'private statement never selects financial rows');
echo 'Invoice statement and applied-credit boundaries: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).").\n";
