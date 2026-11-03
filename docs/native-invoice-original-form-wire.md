# Original invoice form: creation wire checkpoint

`Invoices::invoice` now has an explicit named-company branch for the original **new** form GET and POST. It renders `admin/invoices/invoice` and calls the durable `NativeCompanyInvoiceCommands` writers; it never loads the global invoices model. Primary remains the existing native branch.

The original row order, tax selections, custom fields, tags, client note, presentation fields, payment permissions, reminder preference, issuer date format and allocator revision are validated. Browser subtotal/discount/total are compared with server recomputation under `native_sales_v1`. Draft creation does not reserve a number. A repeated complete POST reuses its existing receipt. Record-payment only preserves the native redirect/session flag, not a receipt or a provider action.

The blank row controls (`description`, `quantity`, `rate`, etc.) are the original item composer, not document rows: only `newitems` is the document payload. Fields selecting another source (`task_id`, expense/merge) are not silently converted to manual lines. Recurrence and mail actions currently fail before issuing until their real command composition is connected. Existing document editing also remains closed; it never falls through to a global writer.

**This checkpoint does not authorize route admission or claim the full form graph is complete.** AJAX dependencies, existing draft/editor/preview, merge/expense/task composition, recurrence and mail actions still need their concrete adapters. No buttons are removed to pretend parity.

Evidence: `native_invoice_form_controller.php` runs the actual controller and real SQLite financial writers with an explicit request/auth boundary double and a view-loader spy. `native_invoice_form_view.php` renders the original nested production views with shell/formatting seams. Neither substitutes for actual HTTP, browser, PHP 8.1/8.5 or MySQL validation.

## Original task AJAX dependencies

The two original billable-task selectors and `Tasks::get_billable_task_data` now use the issuer-authorized candidate reader. The detail preview consumes persisted task timers, issuer task rounding settings, original quantity/time formatting, and explicit project metadata for project hourly billing. Missing project pricing is not inferred; a declared zero rate is not replaced by the task rate. This remains selection/preview only, not a reservation or authorization to convert task billing. The form POST continues rejecting task-source lines until their dedicated conversion composition is attached.

## Explicit expense billability and original expense modal

`NativeCompanyExpenseBillabilitySchema::ensure` is a separate additive helper for core 474 (the migration orchestrator is not changed here). It creates `company_expense_native_billability`, linked to the real expense/issuer and an auditable explicit choice. **It does not backfill historical choices.** No column is added to `company_expenses`, preserving historical billing `source_json`/`source_hash` bytes.

`CompanyExpenses::create/change` accepts optional `billable` 0/1. Omission preserves old canonical request hashes and leaves unknown metadata unchanged. An explicit value is committed with the original writer/CAS/request receipt using a short-lived private proof; metadata failure rolls back the expense. Old receipt replay does not revert a later choice. The native Expenses HTTP editor must pass its real checkbox through this contract; that separate controller wiring is not claimed here.

`NativeCompanyInvoiceExpenseCandidates::forCustomer` queries real company costs, verifies metadata, consumes `CompanyExpenseBilling::status`, and revalidates. Unknown historical classification is reported separately, explicit false is not selectable, and converting/converted sources stay blocked. It never prepares a billing origin. The existing `admin/invoices/bill_expenses` partial consumes the same native DTO/markup without the global currencies model. Tax-presence booleans are rendering indicators, not fabricated catalogue IDs. Final submission still requires the canonical expense conversion composition; a modal candidate is not billing authority.

PHP 8.4/SQLite tests cover historical-byte preservation, hash-compatible replay, explicit create/update/CAS, proof forgery, cross-issuer denial, late-failure rollback, audit corruption, real candidate selection, the original partial, and actual canonical conversion exclusion. MySQL DDL/upgrade and real browser evidence remain pending.

### Original customer-change callback

`Invoices::client_change_data` now invokes real scoped readers and renders the existing `merge_invoice` and `bill_expenses` partials. The response retains billing/shipping, currency, project-presence, task selection and the two original HTML fields. Customer native fields are owner-only; a shared link does not expose the other issuer's private profile. The callback is GET-only, no-store and checks the current invoice's canonical source before exposing selections. It neither prepares a billing origin nor issues/cancels/copies an invoice.

Merge selection preserves the native status rules and includes persisted private drafts without ID collisions. It is explicitly **not merge authority**: final merge composition remains a separate guarded command, and this endpoint alone does not authorize admission of the complete form. Actual PHP/SQLite controller tests render both original partials, including a real classified expense; request/auth and translation boundaries are explicit test doubles. No browser execution is claimed.
