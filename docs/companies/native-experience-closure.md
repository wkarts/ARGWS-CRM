# Native company experience — closure contract (WIP)

## Acceptance, not a second application

The selected company must change data and authorization, not the existing CRM's routes, controllers, views, menu or components. The parallel `company_context` landing page and related presentation must be removed, not hidden behind an alias. Company selection remains a technical POST operation. No company records or historical financial data are deleted by this work.

This branch is **not ready for release or merge**. Internal commits and a future draft PR are validation mechanisms, not staged user deliveries. Existing guards remain closed until their replacement has a tested native data contract. A service or rendered fragment passing tests does not establish that its HTTP action is admitted or that all neighboring actions work.

## Finite source boundary

`scripts/native-experience-inventory.php` reads PHP tokens without bootstrapping the application and emits `native-experience-action-census.json`. This census enumerates public methods of the admin controllers present in the repository. It is a review boundary, **not a claim of supported endpoints**. Installation maintenance, public callbacks, background jobs and customer portal entrypoints must be classified separately; a company selection never grants installation ownership or provider authority.

Run with PHP's tokenizer extension enabled:

```sh
php scripts/native-experience-inventory.php > docs/companies/native-experience-action-census.json
```

The census is regenerated after composition. No source method may disappear from acceptance merely because its route is currently blocked or absent from a menu.

## Ownership and remaining closure work

| Area | Owner | Concrete remaining boundary |
|---|---|---|
| Native bootstrap, route admission, Settings, Utilities/calendar, Custom_fields, Filters, media/file picker, migration 474, removal of parallel controllers/views | Central integrator | Complete native dispatch and group settings, real media ownership, native calendar sources and event webhook contract, configuration forms, saved-filter HTTP transport; then remove old parallel presentation and redirects. |
| Original head/header/sidebar/search/notifications | Presentation worker | Map remaining search resources and historical destinations; newsfeed and realtime channels require explicit tenant data, not global hooks. Native timer/header wiring awaits final task operations. |
| Clients and contacts | Customers worker | Finish registration/status/delete/import/admin assignment, attachments/vault, consent/GDPR/export and existing public identity/profile flows. Finance/project/support portal sections depend on their owners. |
| Invoices and PDFs | Invoice worker | Finish native form/table/action dispatch, principal historical union, public invoice/hash contract, bulk/export/payment/task/expense integrations. Advanced filter execution is a backend dependency, not proof of table HTTP parity. |
| Projects, Tasks, Tickets, native dashboard | Work worker | Exact method list in `native-work-action-inventory.md`; finish timers/assignments/catalogs, existing modal/CRUD/files/billing/portal actions and their shared native dependencies. |
| Todo, announcements, auxiliary Misc, taxes/items | Auxiliary worker | Finish principal catalog references and currency-column safety; remaining sales/files helpers need domain adapters; SMS must never use another company's global transport. |
| Contracts, estimates/proposals, expenses, leads, credits/payments, reports, subscriptions, staff/roles, integrations and remaining menu targets | Central composition queue | Map each existing method to its canonical scoped service and original view; preserve primary legacy lineage, APIs/jobs and provider boundaries. These are not implicitly complete because similar backend services already exist. |

### Required distinctions

- A legitimate refusal to delete a referenced contact, erase ledger history, mutate an issued invoice number or dispatch an unauthorized financial operation remains a business/security rule **inside the native route**, not a generic redirect into another application.
- Public customer login cannot be enabled over the existing global model bootstrap until every reachable page/action has contact-bound readers and writers.
- Native hooks with side effects require an explicit tenant event contract. In particular, the existing calendar update hook is consumed by the webhooks module; silently dropping that integration or calling its global configuration would both be incorrect.
- Historical and owned resource IDs remain distinct. Browser transport IDs do not confer authorization; each action resolves the underlying lineage before private hydration.
- Shared filters, public events and announcements are shared within their authorized company, not across all companies.
- No denied SQL seed or Asaas workflow publication is moved to another path. Migration 474 uses the existing migration mechanism; clean-install, upgrade, interrupted replay and failure tracking still require real database proof.

## Evidence available locally

PHP 8.4 and SQLite execute actual service, controller-seam, native PHP-view and PDF tests. Negative tests cover cross-company access, stale forms, revocation, rollback and ignored audit inserts. These are not substitutes for the supported PHP matrix, MySQL locking/DDL or browser interaction.

The local MySQL/MariaDB server could not open its Unix socket under the execution policy. Chromium likewise could not create its required socket. Those operations are not retried through another route. Final CI must establish MySQL/locking, native HTTP routes, desktop/tablet/mobile rendering, keyboard/scroll behavior and no cross-company leakage.

## Exit gates

1. Every existing in-scope action has a reviewed classification, native route/view binding, owner and positive/negative test; no silent dropped button or payload.
2. Two companies with distinct records plus principal historical data exercise create/read/edit/selection/back/deep-link flows. Revoked grants and stale tabs fail before side effects.
3. Technical selection returns to the native application. `/admin/company_context`, parallel menus/views and route dependencies are actually removed; no alternate app or alias remains.
4. Migration 474 and each changed module pass official version rules, clean install/upgrade/replay/failure tests; inventories are generated with their authentic tools.
5. Native HTTP/browser tests and screenshots pass on the final SHA, with complete permanent coverage retained. Healthy runs are not invalidated; failed validation runs are cancelled only for the same SHA.
6. Remote HEAD is reconciled before publication. The PR remains draft until the single complete delivery meets acceptance. No merge, deploy, force-push, production mutation or financial activation is authorized here.
