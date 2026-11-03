<?php
/** Database differential coverage of the restored original financial/PDF actions. */
$dsn = getenv('ISSUERS_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:') || !str_contains($dsn, ';dbname=issuer_native_module_audit;')) {
    throw new RuntimeException('Dedicated synthetic MySQL required');
}
require_once dirname(__DIR__) . '/support/RunPhpContractChecks.php';
runPhpContractChecks([
    'tests/companies/contracts_value_branding.php',
    'tests/companies/native_invoice_issued_form_update.php',
]);
echo "Restored contracts, invoices and PDF checks passed on dedicated MySQL.\n";
