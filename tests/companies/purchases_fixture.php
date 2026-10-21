<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyPurchases.php';

require_once __DIR__.'/purchases_database.php';
$f=essentialFixture('1.0.2',1); $p=$f->prefix; $db=new PurchaseTestDb($f->db);
if ($f->db->driver()!=='mysql') { throw new RuntimeException('Purchases require real MySQL/MariaDB'); }
register_shutdown_function(static function () use ($db,$p): void {
    $db->observer=null; $db->ignore=null; $db->fail=null;
    if ($db->pdo->inTransaction()) { $db->pdo->rollBack(); }
    foreach (array_reverse(array_keys(CompanyPurchasesSchema::definitions($p))) as $table) { $db->pdo->exec('DROP TABLE IF EXISTS `'.$p.$table.'`'); }
    foreach (['company_financial_events','company_expense_files','company_expense_requests','company_expenses','company_expense_categories','company_projects','pur_orders','pur_vendor','expenses'] as $table) { $db->pdo->exec('DROP TABLE IF EXISTS `'.$p.$table.'`'); }
    essentialCleanup();
});
IssuerCore::reconcile($f->db,$p); IssuerCore::bindInstallationMaster($f->db,$p,7);
CompanyExpenseSchema::ensure($db,$p); CompanyFinancialJournalSchema::ensure($db,$p);
$db->query('CREATE TABLE '.$p.'company_projects(id int unsigned PRIMARY KEY,issuer_id int unsigned,name varchar(191),status varchar(20),revision int unsigned,client_id int) ENGINE=InnoDB');
CompanyPurchasesSchema::ensure($db,$p); CompanyPurchasesSchema::ensure($db,$p);
$registry=new IssuerRegistry($db,$p); $a=$registry->saveIssuer(7,['legal_name'=>'Purchase A','tax_id'=>'12345678000195']); $b=$registry->saveIssuer(7,['legal_name'=>'Purchase B','tax_id'=>'52998224725']); $primary=(new IssuerResourceAccess($db,$p))->primaryId();
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(12,1,0),(13,1,1),(14,1,0)");
foreach ([$a,$b] as $i) { $db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)",[$i]); foreach (['view','create','edit','approve','receive','pay','portal','report','settings'] as $cap) { $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'purchases',?)",[$i,$cap]); } foreach (['view','create','edit','delete'] as $cap) { $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'expenses',?)",[$i,$cap]); } }
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,14,1)",[$a]); $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,14,'purchases','view')",[$a]);
foreach (['pur_orders','pur_vendor','expenses'] as $table) { $db->query('CREATE TABLE '.$p.$table.'(id int PRIMARY KEY,issuer_id int NULL,description varchar(191)) ENGINE=InnoDB'); $db->query('INSERT INTO '.$p.$table." VALUES(900,NULL,'Original NULL record'),(901,?,'Original principal record')",[$primary]); }
$svc=new CompanyPurchases($db,$p); $expenseService=new CompanyExpenses($db,$p); $categories=[];
$key=static fn(string $value): string=>hash('sha256','purchase455-'.$value); $today=gmdate('Y-m-d');
foreach ([$a,$b,$primary] as $i) { $svc->configure($i,7,null,['prefix'=>'C'.$i,'base_currency'=>'BRL','rates'=>['BRL'=>'1.000000','USD'=>'5.250000','EUR'=>'6.100000']],$key('config-'.$i)); $categories[$i]=$expenseService->saveCategory($i,7,'Purchases',$key('category-'.$i)); }
function purchaseCheck(bool $v,string $label): void { essentialCheck($v,$label); }
function purchaseThrows(callable $f,string $code,string $label): void { essentialThrows($f,$code,$label); }
function purchaseSnapshot(): array { global $db,$p; $r=[]; foreach (array_merge(array_keys(CompanyPurchasesSchema::definitions($p)),['company_expenses','company_expense_requests','company_financial_events','issuers_audit','invoices','expenses','pur_orders','pur_vendor']) as $t) { $rows=$db->query('SELECT * FROM '.$p.$t)->result_array(); foreach ($rows as &$row) { ksort($row); } unset($row); usort($rows,static fn($x,$y)=>json_encode($x)<=>json_encode($y)); $r[$t]=$rows; } return $r; }
function purchaseOrder(int $issuer,int $actor,string $tag,string $quantity='3.000',string $price='10.01',string $tax='10.00'): array {
    global $svc,$key,$today;
    $supplier=$svc->saveSupplier($issuer,$actor,null,null,['name'=>'Vendor '.$tag,'tax_id'=>'12345678000195','email'=>'vendor@example.invalid','phone'=>'','address'=>'Synthetic','active'=>1],$key($tag.'-vendor'));
    $request=$svc->createRequest($issuer,$actor,['title'=>'Procurement '.$tag,'needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'Item '.$tag,'unit'=>'UN','quantity'=>$quantity]]],$key($tag.'-request'));
    $svc->approveRequest($issuer,$actor,$request,1,$key($tag.'-approve'));
    $quote=$svc->quote($issuer,$actor,['request_id'=>$request,'supplier_id'=>$supplier,'valid_until'=>$today,'unit_prices'=>[$price],'tax_rate'=>$tax,'note'=>'Synthetic quote'],$key($tag.'-quote'));
    $order=$svc->createOrder($issuer,$actor,['quote_id'=>$quote,'request_revision'=>2,'order_date'=>$today,'expected_date'=>$today],$key($tag.'-order'));
    return compact('supplier','request','quote','order');
}
function purchaseDocument(int $issuer,int $actor,int $order,array $receipts,string $tag): int {
    global $svc,$today,$categories,$key;
    return $svc->postDocument($issuer,$actor,['order_id'=>$order,'receipt_ids'=>$receipts,'vendor_number'=>'INV-'.$tag,'document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$issuer]],$key($tag.'-document'));
}
$f->db->queries=[];
