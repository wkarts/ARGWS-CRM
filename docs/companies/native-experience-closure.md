# Native company experience — closure contract (WIP)

## Acceptance, not a second application

The selected company must change data and authorization, not the existing CRM's routes, controllers, views, menu or components. The parallel `company_context` landing page and related presentation must be removed, not hidden behind an alias. Company selection remains a technical POST operation. No company records or historical financial data are deleted by this work.

This branch and PR #52 are **not ready for release or merge**. The obsolete `Company_context` controller and its native admission were removed; the selector now uses the shared CRM dashboard and the technical `Company_selection::select` POST. The other parallel company controllers and views remain open work. Existing guards remain closed until their replacement has a tested native data contract. A service or rendered fragment passing tests does not establish that its HTTP action is admitted or that all neighboring actions work.

The source census still lists **36 parallel company controllers**. Reconciliation including the new BB131/132 migrations and shared native event mount lists **15,085 unresolved source files** and keeps `operational_ready=false`; the official CI generators must confirm these counts. They include dependency boundaries and are not a completion percentage. Company_context, Company_master, their retired context/registry views and master_resolution.php have been removed. These removals and the native selector are checkpoints, not full application convergence.

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

### Private contact image checkpoint

The original `clients/contact_profile_image/{customer}/{contact}` action is admitted only for GET and HEAD in a selected secondary company. The server-attested company scope determines the issuer; the reader verifies membership, customer ownership and contact identity before serving PNG bytes, and verifies the stored image hash and marker. HEAD returns no image body, and the response is private/no-store with content-type and sandbox protections. Direct references from another company and actors without access are denied by the scoped reader. This single admission does not open customer or contact forms, file uploads, public portals or parallel routes.

The company principal is not attested by `CompanyRequestScope::nativeFor` for this endpoint. The controller therefore returns 404 for that new private-image URL; the principal's existing legacy image helper and URLs remain the path for its historical contacts. Principal historical-image parity and an authenticated browser test of selection, direct URLs, revoked grants and response headers still require verification. The existing controller fixture uses a scope/output double; it is not evidence of the actual HTTP middleware or browser behavior.

### Required distinctions

- A legitimate refusal to delete a referenced contact, erase ledger history, mutate an issued invoice number or dispatch an unauthorized financial operation remains a business/security rule **inside the native route**, not a generic redirect into another application.
- Public customer login cannot be enabled over the existing global model bootstrap until every reachable page/action has contact-bound readers and writers.
- Native hooks with side effects require an explicit tenant event contract. In particular, the existing calendar update hook is consumed by the webhooks module; silently dropping that integration or calling its global configuration would both be incorrect.
- Historical and owned resource IDs remain distinct. Browser transport IDs do not confer authorization; each action resolves the underlying lineage before private hydration.
- Shared filters, public events and announcements are shared within their authorized company, not across all companies.
- No denied SQL seed or Asaas workflow publication is moved to another path. Migration 474 uses the existing migration mechanism; clean-install, upgrade, interrupted replay and failure tracking still require real database proof.

## Evidence available locally

An earlier executor reported PHP8.4/SQLite service, controller-seam, native PHP-view and PDF checks. That is historical evidence, not the current local runtime. This executor has no local PHP, Docker or database. Focused pull_request40422558652 passed PHP8.1/8.5 on1539097; its native calendar feed ran23 added SQLite assertions. Negative tests cover cross-company access, stale forms, revocation, rollback and ignored audit inserts. These are not substitutes for complete MySQL/MariaDB locking/DDL or authenticated browser interaction.

Local runtime and Chromium restrictions have not been bypassed. CI2650e95 proved the original principal widget creates exactly one company-owned event, but its subsequent shared feed failed409 and remains unaccepted. Its MySQL/PHP8.1 task-copy composition passed1179 cumulative assertions before the larger matrix was cancelled without this executor requesting cancellation. Final CI must establish complete MySQL/MariaDB results, native HTTP routes, desktop/tablet/mobile rendering, keyboard/scroll behavior and no cross-company leakage. The old11 unpublished files and the previous physical worktree were unavailable; neither has been claimed recovered or discarded.

## Exit gates

1. Every existing in-scope action has a reviewed classification, native route/view binding, owner and positive/negative test; no silent dropped button or payload.
2. Two companies with distinct records plus principal historical data exercise create/read/edit/selection/back/deep-link flows. Revoked grants and stale tabs fail before side effects.
3. Technical selection returns to the native application. `/admin/company_context`, parallel menus/views and route dependencies are actually removed; no alternate app or alias remains.
4. Migration 474 and each changed module pass official version rules, clean install/upgrade/replay/failure tests; inventories are generated with their authentic tools.
5. Native HTTP/browser tests and screenshots pass on the final SHA, with complete permanent coverage retained. Healthy runs are not invalidated; failed validation runs are cancelled only for the same SHA.
6. Remote HEAD is reconciled before publication. The PR remains draft until the single complete delivery meets acceptance. No merge, deploy, force-push, production mutation or financial activation is authorized here.

## Checkpoint local posterior

As duplicidades de recorrência de faturas, rascunhos/cópias de faturas e equipe foram fechadas localmente. Os controllers/views `Company_invoice_recurring`, `Company_invoice_drafts`, `Company_invoice_copies`, `Company_staff` e suas páginas paralelas deixaram de conter código executável. As rotas oficiais `Invoices::recurring`, `Invoices::drafts`, `Invoices::copy` e `Staff` são as únicas superfícies admitidas para esses fluxos; testes e links internos usam os caminhos oficiais. Os tombstones não são rotas alternativas e não aparecem no inventário ativo.

## Atualização local r8 — 2026-10-28

A composição local posterior retirou os controllers `Company_*` administrativos executáveis e as views ativas em `admin/companies`. As 38 ações especializadas agora são métodos dos controllers oficiais, com rotas finitas no `NativeCompanyRoutes` e escopo nativo no `CompanyRequestScope`. Consulte `native-experience-closure-local-2026-10-28-r8.md` para contagens e gates. A validação PHP/MySQL/browser e a homologação CNAB 400 continuam dependências externas deste executor.
