<?php
/** Real MySQL projection DDL and cross-source native sales SQL, never a SQLite fallback. */
declare(strict_types=1);
$dsn=(string)getenv('ISSUERS_TEST_DSN');
if(!preg_match('/^mysql:.*(?:^|;)dbname=(issuer_[a-zA-Z0-9_]*module_audit)(?:;|$)/D',$dsn))throw new RuntimeException('Dedicated disposable issuer_*module_audit MySQL required');
require __DIR__.'/native_sales_table.php';
if($db->driver()!=='mysql')throw new RuntimeException('Real MySQL required');
require __DIR__.'/native_r8_migration_dependencies.php';
require_once APPPATH.'migrations/489_version_489.php';
$snapshot=static function()use($db,$p):array{
    $data=[];foreach(['company_sales_documents','company_sales_projection','issuers_audit','invoices'] as $table)
        $data[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY id')->result_array();
    return $data;
};
$beforeMigration=$snapshot();$parentsBeforeMigration=$r8MigrationParents();$migration=new Migration_Version_489();$migration->db=$db;$migration->up();$migration->up();
essentialCheck($snapshot()===$beforeMigration,'actual native migration489 up twice preserves source/audit/projection/issued invoices exactly');
essentialCheck($r8MigrationParents()===$parentsBeforeMigration,'actual migration489 preserves existing lead, contract and category values/revisions exactly');
CompanyLeadMetadataSchema::assertValid($db,$p);
CompanyContractMetadataSchema::assertValid($db,$p);
NativeCompanyExpenseCategorySchema::assertValid($db,$p);
essentialCheck($db->query('SELECT * FROM '.$p.'company_lead_metadata')->result_array()===[]&&$db->query('SELECT * FROM '.$p.'company_contract_metadata')->result_array()===[],'additive private fields never invent metadata for existing leads/contracts');
essentialCheck($db->query('SELECT description FROM '.$p.'company_expense_categories WHERE id=?',[$r8MigrationCategory])->row_array()['description']===null,'new optional expense category description remains NULL on existing rows');
$dueDate=$db->query('SHOW FULL COLUMNS FROM '.$p.'company_contracts LIKE ?', ['due_date'])->row_array();
essentialCheck(strtolower((string)$dueDate['Type'])==='date'&&$dueDate['Null']==='YES','actual MySQL migration admits omitted contract end dates without changing stored dates');
NativeCompanySalesProjectionSchema::assertValid($db,$p);
$db->query('ALTER TABLE '.$p.'company_sales_projection MODIFY subtotal DECIMAL(14,2) NOT NULL');
try{essentialThrows(fn()=>NativeCompanySalesProjectionSchema::assertValid($db,$p),'native_sales_projection_schema_invalid','native monetary precision drift is rejected');}
finally{$db->query('ALTER TABLE '.$p.'company_sales_projection MODIFY subtotal DECIMAL(15,2) NOT NULL');}
NativeCompanySalesProjectionSchema::assertValid($db,$p);
echo 'Native sales real MySQL DDL/precision/replay/atomic rollback/owned-legacy UNION passed.'.PHP_EOL;
