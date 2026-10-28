# Migração física da aplicação paralela — PR 52

Baseline `c2fe8ab2b698af5f257729b4f60e433b8eab788e`: **141** basenames com prefixo `company_` (comparação sem distinção de maiúsculas/minúsculas).

Todos os caminhos abaixo foram removidos ou renomeados no índice final; o inventário não depende de tombstones e não inclui a árvore `application/views/admin/companies/`.

| Caminho no baseline | Resultado no pacote |
|---|---|
| `application/controllers/Company_api.php` | `application/controllers/Api.php` (renomeado fisicamente) |
| `application/controllers/Company_asaas_callback.php` | `application/controllers/Asaas_callback.php` (renomeado fisicamente) |
| `application/controllers/Company_hr_access.php` | `application/controllers/Hr_access.php` (renomeado fisicamente) |
| `application/controllers/Company_operations_api.php` | `application/controllers/Operations_api.php` (renomeado fisicamente) |
| `application/controllers/Company_portal.php` | `application/controllers/Portal.php` (renomeado fisicamente) |
| `application/controllers/Company_purchase_portal.php` | `application/controllers/Purchase_portal.php` (renomeado fisicamente) |
| `application/controllers/Company_purchases_api.php` | `application/controllers/Purchases_api.php` (renomeado fisicamente) |
| `application/controllers/Company_recurring_worker.php` | `application/controllers/Recurring_worker.php` (renomeado fisicamente) |
| `application/controllers/admin/Company_ai.php` | removido fisicamente |
| `application/controllers/admin/Company_api_tokens.php` | removido fisicamente |
| `application/controllers/admin/Company_asaas.php` | removido fisicamente |
| `application/controllers/admin/Company_assets.php` | removido fisicamente |
| `application/controllers/admin/Company_calendar.php` | removido fisicamente |
| `application/controllers/admin/Company_certificates.php` | removido fisicamente |
| `application/controllers/admin/Company_client_imports.php` | removido fisicamente |
| `application/controllers/admin/Company_connect_api.php` | removido fisicamente |
| `application/controllers/admin/Company_contracts.php` | removido fisicamente |
| `application/controllers/admin/Company_credits.php` | removido fisicamente |
| `application/controllers/admin/Company_efi.php` | removido fisicamente |
| `application/controllers/admin/Company_expense_copies.php` | removido fisicamente |
| `application/controllers/admin/Company_expenses.php` | removido fisicamente |
| `application/controllers/admin/Company_google_mail_oauth.php` | removido fisicamente |
| `application/controllers/admin/Company_hr.php` | removido fisicamente |
| `application/controllers/admin/Company_imports.php` | removido fisicamente |
| `application/controllers/admin/Company_invoice_copies.php` | removido fisicamente |
| `application/controllers/admin/Company_invoice_drafts.php` | removido fisicamente |
| `application/controllers/admin/Company_invoice_recurring.php` | removido fisicamente |
| `application/controllers/admin/Company_invoices.php` | removido fisicamente |
| `application/controllers/admin/Company_microsoft_mail_oauth.php` | removido fisicamente |
| `application/controllers/admin/Company_onboarding.php` | removido fisicamente |
| `application/controllers/admin/Company_post_refund.php` | removido fisicamente |
| `application/controllers/admin/Company_post_refund_manual.php` | removido fisicamente |
| `application/controllers/admin/Company_project_billing_rates.php` | removido fisicamente |
| `application/controllers/admin/Company_project_copies.php` | removido fisicamente |
| `application/controllers/admin/Company_project_extras.php` | removido fisicamente |
| `application/controllers/admin/Company_project_fixed_billing.php` | removido fisicamente |
| `application/controllers/admin/Company_project_portal_rules.php` | removido fisicamente |
| `application/controllers/admin/Company_projects.php` | removido fisicamente |
| `application/controllers/admin/Company_purchases.php` | removido fisicamente |
| `application/controllers/admin/Company_pusher.php` | removido fisicamente |
| `application/controllers/admin/Company_recurring.php` | removido fisicamente |
| `application/controllers/admin/Company_reports.php` | removido fisicamente |
| `application/controllers/admin/Company_sales.php` | removido fisicamente |
| `application/controllers/admin/Company_selection.php` | removido fisicamente |
| `application/controllers/admin/Company_staff.php` | removido fisicamente |
| `application/controllers/admin/Company_standalone_billing.php` | removido fisicamente |
| `application/controllers/admin/Company_standalone_tasks.php` | removido fisicamente |
| `application/controllers/admin/Company_task_billing.php` | removido fisicamente |
| `application/controllers/admin/Company_task_copies.php` | removido fisicamente |
| `application/controllers/admin/Company_task_recurring.php` | removido fisicamente |
| `application/controllers/admin/Company_tasks.php` | removido fisicamente |
| `application/controllers/admin/Company_ticket_connect.php` | removido fisicamente |
| `application/controllers/admin/Company_tickets.php` | removido fisicamente |
| `application/controllers/admin/Company_time_batches.php` | removido fisicamente |
| `application/controllers/admin/Company_time_billing.php` | removido fisicamente |
| `application/services/email_templates_ptbr/company_defaults.json` | `application/services/email_templates_ptbr/defaults.json` (renomeado fisicamente) |
| `application/views/admin/contracts/company_native.php` | `application/views/admin/contracts/native.php` (renomeado fisicamente) |
| `application/views/admin/contracts/company_owned_directory.php` | `application/views/admin/contracts/owned_directory.php` (renomeado fisicamente) |
| `application/views/admin/credit_notes/company_movement_fields.php` | `application/views/admin/credit_notes/movement_fields.php` (renomeado fisicamente) |
| `application/views/admin/credit_notes/company_native.php` | `application/views/admin/credit_notes/native.php` (renomeado fisicamente) |
| `application/views/admin/emails/company_scoped.php` | `application/views/admin/emails/scoped.php` (renomeado fisicamente) |
| `application/views/admin/expenses/company_billing.php` | `application/views/admin/expenses/billing.php` (renomeado fisicamente) |
| `application/views/admin/expenses/company_copy.php` | `application/views/admin/expenses/copy.php` (renomeado fisicamente) |
| `application/views/admin/expenses/company_fields.php` | `application/views/admin/expenses/fields.php` (renomeado fisicamente) |
| `application/views/admin/expenses/company_native.php` | `application/views/admin/expenses/native.php` (renomeado fisicamente) |
| `application/views/admin/expenses/company_recurring.php` | `application/views/admin/expenses/recurring.php` (renomeado fisicamente) |
| `application/views/admin/includes/company_context_script.php` | `application/views/admin/includes/context_script.php` (renomeado fisicamente) |
| `application/views/admin/includes/company_selector.php` | `application/views/admin/includes/selector.php` (renomeado fisicamente) |
| `application/views/admin/invoices/company_billing_current.php` | `application/views/admin/invoices/billing_current.php` (renomeado fisicamente) |
| `application/views/admin/payments/company_post_refund.php` | `application/views/admin/payments/post_refund.php` (renomeado fisicamente) |
| `application/views/admin/payments/company_post_refund_manual.php` | `application/views/admin/payments/post_refund_manual.php` (renomeado fisicamente) |
| `application/views/admin/reports/company_financial.php` | `application/views/admin/reports/financial.php` (renomeado fisicamente) |
| `application/views/admin/reports/company_ledger.php` | `application/views/admin/reports/ledger.php` (renomeado fisicamente) |
| `application/views/admin/settings/company_scoped.php` | removido fisicamente |
| `application/views/admin/settings/includes/company_identity_fields.php` | `application/views/admin/settings/includes/identity_fields.php` (renomeado fisicamente) |
| `application/views/admin/settings/includes/company_policy.php` | `application/views/admin/settings/includes/policy.php` (renomeado fisicamente) |
| `application/views/themes/perfex/views/company_profile.php` | `application/views/themes/perfex/views/native_profile.php` (renomeado fisicamente) |
| `modules/asaas/docs/COMPANY_PREFERENCES.md` | `modules/asaas/docs/NATIVE_PREFERENCES.md` (renomeado fisicamente) |
| `modules/hr_payroll/assets/js/settings/company_contributions_list_js.php` | `modules/hr_payroll/assets/js/settings/contributions_list_js.php` (renomeado fisicamente) |
| `modules/hr_payroll/views/includes/company_contributions_list.php` | `modules/hr_payroll/views/includes/contributions_list.php` (renomeado fisicamente) |
| `modules/multiple_companies/includes/company_modal.php` | `modules/multiple_companies/includes/modal.php` (renomeado fisicamente) |
| `modules/purchase/views/vendor_portal/vendors/company_profile.php` | `modules/purchase/views/vendor_portal/vendors/profile.php` (renomeado fisicamente) |
| `tests/companies/company_ai_tls_probe.php` | `tests/companies/native_ai_tls_probe.php` (renomeado fisicamente) |
| `tests/companies/company_identity_documents.php` | `tests/companies/native_identity_documents.php` (renomeado fisicamente) |
| `tests/company_client_imports_browser.cjs` | `tests/native_client_imports_browser.cjs` (renomeado fisicamente) |
| `tests/company_context.php` | `tests/native_context.php` (renomeado fisicamente) |
| `tests/company_context_tabs_browser.cjs` | `tests/native_context_tabs_browser.cjs` (renomeado fisicamente) |
| `tests/company_dashboard_payments.php` | `tests/native_dashboard_payments.php` (renomeado fisicamente) |
| `tests/company_dashboard_presenter.php` | `tests/native_dashboard_presenter.php` (renomeado fisicamente) |
| `tests/company_email_catalog.test.cjs` | `tests/native_email_catalog.test.cjs` (renomeado fisicamente) |
| `tests/company_identity_browser.cjs` | `tests/native_identity_browser.cjs` (renomeado fisicamente) |
| `tests/company_identity_fixture.php` | `tests/native_identity_fixture.php` (renomeado fisicamente) |
| `tests/company_navigation_tabs.test.cjs` | `tests/native_navigation_tabs.test.cjs` (renomeado fisicamente) |
| `tests/company_portal_files.test.cjs` | `tests/native_portal_files.test.cjs` (renomeado fisicamente) |
| `tests/company_registry_lookup.php` | `tests/native_registry_lookup.php` (renomeado fisicamente) |
| `tests/company_registry_lookup_controller.php` | `tests/native_registry_lookup_controller.php` (renomeado fisicamente) |
| `tests/company_registry_modal_browser.cjs` | `tests/native_registry_modal_browser.cjs` (renomeado fisicamente) |
| `tests/company_selector_driver.test.cjs` | `tests/native_selector_driver.test.cjs` (renomeado fisicamente) |
| `tests/company_settings_certificate_link.php` | `tests/native_settings_certificate_link.php` (renomeado fisicamente) |
| `tests/company_settings_layout.browser.cjs` | `tests/native_settings_layout.browser.cjs` (renomeado fisicamente) |
| `tests/company_settings_layout.php` | `tests/native_settings_layout.php` (renomeado fisicamente) |
| `tests/company_settings_native.php` | `tests/native_settings_native.php` (renomeado fisicamente) |
| `tests/company_settings_native.test.cjs` | `tests/native_settings_native.test.cjs` (renomeado fisicamente) |
| `tests/company_settings_native_browser.cjs` | `tests/native_settings_native_browser.cjs` (renomeado fisicamente) |
| `tests/company_shell_browser.cjs` | `tests/native_shell_browser.cjs` (renomeado fisicamente) |
| `tests/company_shell_controls.test.cjs` | `tests/native_shell_controls.test.cjs` (renomeado fisicamente) |
| `tests/company_shell_fidelity.browser.cjs` | `tests/native_shell_fidelity.browser.cjs` (renomeado fisicamente) |
| `tests/company_shell_presentation.php` | `tests/native_shell_presentation.php` (renomeado fisicamente) |
| `tests/company_ui_surface_inventory.py` | `tests/native_ui_surface_inventory.py` (renomeado fisicamente) |
| `tests/company_workspace_browser.cjs` | `tests/native_workspace_browser.cjs` (renomeado fisicamente) |
| `tests/company_workspace_driver.test.cjs` | `tests/native_workspace_driver.test.cjs` (renomeado fisicamente) |
| `tests/efi/company_accounts_contract.php` | `tests/efi/native_accounts_contract.php` (renomeado fisicamente) |
| `tests/efi/company_controller_contract.php` | `tests/efi/native_controller_contract.php` (renomeado fisicamente) |
| `tests/efi/company_proof_contract.php` | `tests/efi/native_proof_contract.php` (renomeado fisicamente) |
| `tests/efi/company_ui.browser.cjs` | `tests/efi/native_ui.browser.cjs` (renomeado fisicamente) |
| `tests/fixtures/company_ui_surfaces.json` | `tests/fixtures/native_ui_surfaces.json` (renomeado fisicamente) |
| `tests/helpers/company_selector_driver.cjs` | `tests/helpers/native_selector_driver.cjs` (renomeado fisicamente) |
| `tests/helpers/company_workspace_driver.cjs` | `tests/helpers/native_workspace_driver.cjs` (renomeado fisicamente) |
| `tests/settings/company_ai.php` | `tests/settings/native_ai.php` (renomeado fisicamente) |
| `tests/settings/company_ai_boundaries.php` | `tests/settings/native_ai_boundaries.php` (renomeado fisicamente) |
| `tests/settings/company_ai_client.php` | `tests/settings/native_ai_client.php` (renomeado fisicamente) |
| `tests/settings/company_ai_concurrency.php` | `tests/settings/native_ai_concurrency.php` (renomeado fisicamente) |
| `tests/settings/company_ai_controller.php` | `tests/settings/native_ai_controller.php` (renomeado fisicamente) |
| `tests/settings/company_ai_final_boundaries.php` | `tests/settings/native_ai_final_boundaries.php` (renomeado fisicamente) |
| `tests/settings/company_ai_http_fixture.php` | `tests/settings/native_ai_http_fixture.php` (renomeado fisicamente) |
| `tests/settings/company_ai_schema.php` | `tests/settings/native_ai_schema.php` (renomeado fisicamente) |
| `tests/settings/company_ai_setup.php` | `tests/settings/native_ai_setup.php` (renomeado fisicamente) |
| `tests/settings/company_ai_transport.php` | `tests/settings/native_ai_transport.php` (renomeado fisicamente) |
| `tests/settings/company_assets.php` | `tests/settings/native_assets.php` (renomeado fisicamente) |
| `tests/settings/company_email_templates.php` | `tests/settings/native_email_templates.php` (renomeado fisicamente) |
| `tests/settings/company_formatting.php` | `tests/settings/native_formatting.php` (renomeado fisicamente) |
| `tests/settings/company_identity_transaction.php` | `tests/settings/native_identity_transaction.php` (renomeado fisicamente) |
| `tests/settings/company_mail_config.php` | `tests/settings/native_mail_config.php` (renomeado fisicamente) |
| `tests/settings/company_mail_queue.php` | `tests/settings/native_mail_queue.php` (renomeado fisicamente) |
| `tests/settings/company_mail_schema.php` | `tests/settings/native_mail_schema.php` (renomeado fisicamente) |
| `tests/settings/company_options_helpers.php` | `tests/settings/native_options_helpers.php` (renomeado fisicamente) |
| `tests/settings/company_presentation.php` | `tests/settings/native_presentation.php` (renomeado fisicamente) |
| `tests/settings/company_presentation_delivery.php` | `tests/settings/native_presentation_delivery.php` (renomeado fisicamente) |
| `tests/settings/company_registry_identity.php` | `tests/settings/native_registry_identity.php` (renomeado fisicamente) |
| `tests/settings/company_registry_profile.php` | `tests/settings/native_registry_profile.php` (renomeado fisicamente) |
| `tests/settings/company_settings.php` | `tests/settings/native_settings.php` (renomeado fisicamente) |
