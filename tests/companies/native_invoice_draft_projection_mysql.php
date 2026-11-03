<?php
/** Real historical schemas, nonempty proven sources and projection DDL; not a PDO SQLite fallback. */
declare(strict_types=1);
$dsn=(string)getenv('ISSUERS_TEST_DSN');
if(!preg_match('/^mysql:.*(?:^|;)dbname=(issuer_[a-zA-Z0-9_]*module_audit)(?:;|$)/D',$dsn))throw new RuntimeException('Dedicated disposable issuer_*module_audit MySQL required');
require __DIR__.'/native_invoice_draft_table.php';
if($db->driver()!=='mysql')throw new RuntimeException('Real MySQL required');
NativeCompanyInvoiceDraftProjectionSchema::ensure($db,$p);NativeCompanyInvoiceDraftProjectionSchema::assertValid($db,$p);
$db->query('ALTER TABLE '.$p.'company_invoice_draft_projection MODIFY total DECIMAL(14,2) NOT NULL');
try{essentialThrows(fn()=>NativeCompanyInvoiceDraftProjectionSchema::assertValid($db,$p),'native_invoice_draft_projection_schema_invalid','native decimal precision drift is not silently accepted');}
finally{$db->query('ALTER TABLE '.$p.'company_invoice_draft_projection MODIFY total DECIMAL(15,2) NOT NULL');}
NativeCompanyInvoiceDraftProjectionSchema::assertValid($db,$p);
echo "Private draft projection real MySQL DDL, backfill, source corruption/atomic rollback and native UNION passed.\n";
