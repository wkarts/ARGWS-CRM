# Evidência do checkpoint local r5

- Revisão: 8
- Mudança principal: recorrência de faturas usa somente `admin/invoices/recurring` com `NativeCompanyInvoiceRecurringHttp`.
- Tombstones inertes: `application/controllers/admin/Company_invoice_recurring.php` e `application/views/admin/companies/invoice_recurring.php`.
- Inventário: 87 views, 46 controllers/endpoints.
- Node: 219 pass, 3 skip de PHP ausente, 0 fail.
- Python focused CI: 10 pass.
- Validador local: 4 pass, 0 fail, 3 blocked (PHP, Mix/node_modules, Ruby).
- Nenhuma publicação no GitHub.

Os testes PHP/HTTP/MySQL/Playwright/Mix/Ruby não foram falsamente marcados como aprovados porque os runtimes não estão instalados nesta execução. CNAB 400 permanece sujeito à documentação e homologação bancária.
