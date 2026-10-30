<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php';
$before=purchaseSnapshot(); CompanyPurchasesSchema::ensure($db,$p); purchaseCheck(purchaseSnapshot()===$before,'schema retry preserves all preexisting company data');
$cases=[
    ['ALTER TABLE '.$p.'company_purchase_suppliers ALTER active SET DEFAULT 0','ALTER TABLE '.$p.'company_purchase_suppliers ALTER active DROP DEFAULT','unrequested column default'],
    ['ALTER TABLE '.$p.'company_purchase_commands MODIFY id int unsigned NOT NULL','ALTER TABLE '.$p.'company_purchase_commands MODIFY id int unsigned NOT NULL AUTO_INCREMENT','missing AUTO_INCREMENT'],
    ['ALTER TABLE '.$p.'company_purchase_files MODIFY filename varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL','ALTER TABLE '.$p.'company_purchase_files MODIFY filename varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','wrong column collation'],
    ['ALTER TABLE '.$p.'company_purchase_commands DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci','ALTER TABLE '.$p.'company_purchase_commands DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','wrong table collation'],
    ['ALTER TABLE '.$p.'company_purchase_suppliers DROP INDEX purchase_supplier_search, ADD INDEX purchase_supplier_search(issuer_id,active)','ALTER TABLE '.$p.'company_purchase_suppliers DROP INDEX purchase_supplier_search, ADD INDEX purchase_supplier_search(issuer_id,active,id)','incomplete operational index'],
];
foreach ($cases as [$change,$restore,$label]) { $db->query($change); purchaseThrows(fn()=>CompanyPurchasesSchema::assertValid($db,$p),'issuer_purchase_schema_invalid',$label.' rejected'); $db->query($restore); CompanyPurchasesSchema::assertValid($db,$p); }
$fk=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_purchase_commands'])->row_array()['CONSTRAINT_NAME'];
$db->query('ALTER TABLE '.$p.'company_purchase_commands DROP FOREIGN KEY `'.$fk.'`'); $db->query('ALTER TABLE '.$p.'company_purchase_commands ADD CONSTRAINT `'.$fk.'` FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id) ON DELETE CASCADE');
purchaseThrows(fn()=>CompanyPurchasesSchema::assertValid($db,$p),'issuer_purchase_schema_invalid','destructive cascading FK rejected');
$db->query('ALTER TABLE '.$p.'company_purchase_commands DROP FOREIGN KEY `'.$fk.'`'); $db->query('ALTER TABLE '.$p.'company_purchase_commands ADD CONSTRAINT `'.$fk.'` FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id)');
$db->trans_begin(); purchaseThrows(fn()=>CompanyPurchasesSchema::ensure($db,$p),'issuer_outer_transaction_not_allowed','DDL never implicitly commits an outer caller transaction'); purchaseCheck($db->pdo->inTransaction(),'outer transaction remains open after refusal'); $db->trans_rollback();
$db->query('DROP TABLE '.$p.'company_purchase_commands'); $db->ignore='CREATE TABLE IF NOT EXISTS `'.$p.'company_purchase_commands`';
purchaseThrows(fn()=>CompanyPurchasesSchema::ensure($db,$p),'issuer_purchase_schema_invalid','ignored final DDL cannot claim complete purchasing schema'); $db->ignore=null;
CompanyPurchasesSchema::ensure($db,$p); CompanyPurchasesSchema::ensure($db,$p); purchaseCheck($db->table_exists($p.'company_purchase_commands'),'repair retry creates the missing final table and remains idempotent');
$after=purchaseSnapshot(); unset($before['company_purchase_commands'],$after['company_purchase_commands']); purchaseCheck($after===$before,'partial-DDL retry never recreates or mutates earlier tables');
foreach (array_reverse(array_keys(CompanyPurchasesSchema::definitions($p))) as $table) { $db->query('DROP TABLE '.$p.$table); } unset($db->data_cache['table_names']);
$legacyCompatible=$expenseService->create($a,12,['category_id'=>$categories[$a],'currency'=>'BRL','date'=>$today,'description'=>'Ordinary expense without Purchase schema','amount'=>'1.00','tax_rate'=>'0.00'],$key('without-purchase-schema'));
$row=$expenseService->get($a,12,$legacyCompatible); $expenseService->change($a,12,$legacyCompatible,$row['revision_token'],$key('cancel-without-schema'));
purchaseCheck($expenseService->get($a,12,$legacyCompatible)['state']==='cancelled','optional Purchase fence preserves ordinary expenses before schema installation');
echo 'Purchases schema/retry: '.$GLOBALS['essentialChecks'].' checks passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
