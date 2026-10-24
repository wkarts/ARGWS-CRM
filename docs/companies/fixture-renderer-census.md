# Shared-shell renderer census

Diagnosis: synthetic Settings renderer omitted db/router, so PHP warnings were
printed before HTML and created the observed 433px document at a 390px viewport.
No application CSS or overflow assertion was changed.

The three standalone full-shell renderers now declare dependencies explicitly;
Settings and both Asaas renderers use a strict buffered boundary that converts
warnings to exceptions, restores handlers/buffers, and propagates fatal Errors.
The common essentialFixture supplies a neutral presentation router for proxy
renderers; route-specific fixtures can still override it. NativeHeaderFixture
already declares db/router. CompanyShellRouteFixture uses the actual loader.

Literal include/require census across tests/**/*.php:

- `tests/companies/asaas_boundaries.php` — explicit db/router; strict render boundary.
- `tests/companies/asaas_checkout_views.php` — explicit db/router; strict render boundary.
- `tests/companies/asaas_isolated_translation.php` — fragment without shared shell.
- `tests/companies/asaas_preferences_controller.php` — fragment without shared shell.
- `tests/companies/asaas_refund_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/bank_reversal_report_views.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/billing_invoice_lifecycle.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/client_identity_fixture.php` — fragment without shared shell.
- `tests/companies/client_imports_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/contracts_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/expense_billing_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/expense_copies_resume_442_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/expense_recurring_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/invoice_recurring_modes_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/ledger_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/post_refund_manual_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/post_refund_manual_cycle_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/project_copies_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/project_fixed_billing_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/projects_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/sales_mutations_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/task_billing_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/task_copies_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/companies/task_recurring_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/company_identity_fixture.php` — fragment without shared shell.
- `tests/settings/company_ai_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/settings/resume_439_pusher_recovery_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).
- `tests/settings/settings_model_controller.php` — explicit db/router; strict render boundary.
- `tests/settings/ticket_connect_controller.php` — framework proxy to CI (db + neutral router in essentialFixture).

This is a source census, not a claim of executing every renderer. PHP/browser
checks and regenerated Settings screenshots must pass in CI. Fragment-only
renderers deliberately do not instantiate shell dependencies.
