<?php
/** Run each real controller fixture in a separate PHP process against the dedicated synthetic MySQL. */
$dsn=getenv('ISSUERS_TEST_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||!str_contains($dsn,';dbname=issuer_native_module_audit;'))throw new RuntimeException('Dedicated synthetic MySQL required');
require_once dirname(__DIR__).'/support/RunPhpContractChecks.php';
runPhpContractChecks(['tests/companies/native_sales_history_controller.php','tests/companies/native_contract_actions.php','tests/companies/native_contract_mail_worker.php','tests/companies/native_project_copy.php','tests/companies/native_task_detail_portal_authorship.php','tests/companies/native_client_update_controller.php','tests/settings/sales_pdf.php','tests/settings/document_pdf.php','tests/settings/sales_documents.php','tests/settings/native_presentation.php']);
echo "Consolidated PR52 original-action and settings fixtures passed on dedicated MySQL.\n";
