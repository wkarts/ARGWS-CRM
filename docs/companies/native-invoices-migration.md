# Native invoices: route and data contract

Base: `abbf6c7f`. This is an incremental migration, **not complete invoice UX parity**.
No ownership adoption, ledger rewriting, provider dispatch or core migration is introduced.

## Bootstrap contract

The integrator owns `CompanyRequestScope::nativeFor($controller)`, the sealed controller
attestation and the exact route/verb allowlist. The native branch returns from the
Invoices constructor before either legacy financial model is loaded. Do not admit
another method merely because its controller now has this constructor branch.
The principal legacy branch remains unchanged. Positive named-company authorization
must precede rendering; neither a browser issuer parameter nor a view flag grants it.

## Implemented first transport

`invoices/pdf/{id}` GET/HEAD uses the existing `CompanyInvoices::document`,
`Issuer_invoice_pdf`, and confirmed download audit. Only output modes I/D and print
are accepted; no filesystem TCPDF output mode is passed through. HEAD authorizes the
same document but returns no bytes and does not record a download. Failure after
rendering but before audit returns no document bytes. No hooks or legacy model run.

The route is a candidate for the central allowlist **after PHP/HTTP execution**;
this patch does not activate routes. UI list/editor/preview are still pending.

## Historical first-increment checklist

The authoritative current finite inventory is [native-invoices-action-inventory.md](native-invoices-action-inventory.md). The table below records the original breakdown, not current completion status.

### Original methods

| Native method(s) | Existing owned foundation | Missing presentation/transport contract |
|---|---|---|
| index, list_invoices, table | CompanyInvoices page/document | Exact filtered DataTables counts/order/page; customer/project/tags/extra columns; native manage fragments |
| invoice | CompanyInvoices create/change/editorItems/adjustments/revision | Strict native-field translation; no client totals or numbering override; create request key and edit revision |
| get_invoice_data_ajax | document/payments/position; delivery and attachment services | Native preview DTO; reminders/notes/activity/credits/recurrence tabs and all action dependencies |
| get_invoices_total | CompanyInvoiceBalance/document | Per-currency totals, same filters, no sum across currencies |
| record_invoice_payment_ajax, record_payment | InvoicePaymentWriter::recordCompanyManual | Same native payment form with explicit receipt request key; no legacy payment processor |
| mark_as_cancelled | CompanyInvoices::change(null) | Explicit POST, CSRF, revision; no provider cancellation; native link currently GET |
| send_to_email, send_overdue_notice | CompanyInvoiceDelivery::queue | Contact/revision/idempotency; queue semantics must not masquerade as immediate delivery |
| delete_attachment | CompanyInvoiceAttachments::hide | File-key/hash ownership and POST semantics; not legacy numeric-file deletion |
| recurring | Owned recurrence services | Native recurring list/actions and scheduler policy |
| copy | Owned copy/draft services require separate review | Snapshot, numbering and downstream origin invariants |
| client_change_data, get_merge_data, get_bill_expense_data | CompanyClients and owned billing services | Customer links, project/task/expense provenance and canonical conversion |
| apply_credits, delete_payment | CompanyCreditWriter/InvoicePaymentWriter | Explicit typed credit/reversal, not physical removal; provider fences preserved |
| update_number_settings, validate_invoice_number | Issuer sequence/snapshot writer | Never override issued snapshot/global counter from native POST |
| add_note, get_notes, pause_overdue_reminders, resume_overdue_reminders | To be mapped | Owned storage, ACL, revision and background-mail behavior |
| unmark_as_cancelled, delete, mark_as_sent, get_due_date | To be mapped | No legacy fallback; transitions need exact owned service semantics |

## Native-view dependencies that must not silently call global endpoints

`manage.php` loads quick_stats (now has an explicit owned-count DTO branch; legacy still uses total_rows), filter_params,
list_template/App_table, table_html (custom fields/hooks), and `init_invoice`.
The editor loads items/search, project search, customer-change billing/merge data,
tax dropdowns, currency helpers, numbering validation and shared sales JS.
Preview calls misc/get_reminders, note edit/delete, file visibility and external
attachment actions, credit_notes actions, and payments/batch_payment_modal.
Every reachable URL needs an explicit owned implementation or a visible unavailable
capability with no active request. Hiding a button alone is not authorization.

## Acceptance

Run existing owned writer A/B/NULL/provider/retry/rollback suites unchanged.
Add real HTTP principal+A+B tests through native URLs, native views and native JS;
capture the request graph and fail on an unadapted endpoint. Check scoped rows and
counts, exact document ownership, same functional controls, both CSRF defenses,
stale tabs, revocation between GET and POST, export bytes, and untouched other-owner
rows. Browser comparisons must cover 1280/768/390 and identify synthetic fixtures.

Current commands:

* `node --test tests/native_invoice_transport.test.cjs` — static contract only.
* `php tests/companies/native_invoice_pdf.php` — real disposable fixture/services/TCPDF;
  executed locally later; see the dated checkpoint below.

No browser or full native invoice UI proof is claimed by the PDF transport increment.

## Internal read contract (not yet a route activation)

`NativeCompanyInvoiceRead::page(issuer, actor, query)` returns draw, recordsTotal,
recordsFiltered and authorized document DTOs. Canonical query keys are draw/start/
length(1..100)/search/order/direction/status/year/client_id. Search uses issued
recipient names and formatted numbers; sorting is whitelisted with ID tie-breaks.
The native DataTables/rules parser still needs to translate all native controls
explicitly; it must not discard unsupported filters.

`summary(issuer, actor, currencyId=null, years=[])` supplies native total_result
(due/paid/overdue/currency-object/currencyid), invoices_years, currency selector
metadata, counts1..6, not_sent and percent_by_status. Empty years means current
year, matching the existing native contract. Currency defaults to the unique base
catalog currency; money from different currencies is never aggregated. Effective
received and receivable come from the existing verified balance. Native overdue
uses the full total of status4 invoices, not a newly invented due-date calculation.
Cancelled/draft rows are excluded from monetary totals; percentage denominator
excludes cancelled and not_sent excludes paid/cancelled, as in the native helper.

`php tests/companies/native_invoice_read.php` adds real disposable A/B/snapshot/
NULL/filter/currency/year/ACL coverage. It was subsequently executed with PHP8.4/SQLite; see the dated checkpoint below.

Principal summary now explicitly unions only its named records with native NULL
records, after verifying the persisted installation primary ID and live invoice ACL.
Lineages remain separately reported: `canonical_verified_position` for named
documents and `native_legacy_recorded` for historical native sums. Same-currency
totals are combined for the native UI, without asserting provider proof for NULL
records or adopting them. Secondary summaries never read this lineage. Both source
sets and primary identity are checked again before returning the result.
Table/PDF transport still requires the corresponding per-record principal dispatch;
do not infer that summary support activates other native routes.

## Canonical writer extension: existing native document fields

Optional `native_fields` now carries adminnote, terms, billing/shipping address
columns, include/show-shipping flags, and show_quantity_as. It is a strict allowlist:
no owner, amount, number, status, provider or payment mode can enter this object.
Writes occur only in a private CompanyInvoices method inside the existing issue/edit
transaction, after the existing ACL/provenance/financial fences. Countries are
validated, updates read back exactly, and metadata participates in request hashes
and edit revisions. Absent metadata does not add an empty key to historical request
hashes. Old copy receipt JSON remains decodable; new copies and drafts retain the
fields rather than silently dropping them.

PDF terms now come from the authorized document's terms column; captured issuer
identity remains unchanged. Staff adminnote is never placed in the public document
DTO/PDF. Terms remain escaped literal text; quantity labels follow the stored
native quantity mode. No schema change or financial writer/proof relaxation occurs.

Additional commands:

* `php tests/native_invoice_fields_contract.php`
* `php tests/companies/native_invoice_fields.php`

Project/sale-agent/tag/custom-field associations, payment modes, recurrence and
other native actions still need their own checked contracts and native view wiring;
this extension is not a claim that those fields work yet.


## Local execution checkpoint (2026-10-25)

PHP 8.4.26 from the isolated official Debian runtime, with PDO SQLite, curl and GD,
executed the production services and TCPDF fixtures locally. This is not the CI
PHP 8.1/8.5 matrix, HTTP/browser proof, or a MySQL result.

| Command | Result |
| --- | --- |
| `php tests/native_invoice_fields_contract.php` | 18 checks PASS |
| `php tests/companies/native_invoice_read.php` | 115 cumulative checks PASS |
| `php tests/companies/native_invoice_pdf.php` | 99 cumulative checks PASS |
| `php tests/companies/native_invoice_fields.php` | 158 cumulative checks PASS |
| `php tests/companies/invoice_amounts.php` | 27 checks PASS |
| `php tests/companies/invoice_drafts_atomic.php` | 180 cumulative checks PASS |
| `php tests/companies/invoice_recurring_native.php` | 86 cumulative checks PASS |
| `php tests/companies/invoice_credit_pdf_preferences.php` | 253 cumulative checks PASS |
| `php tests/companies/invoice_credit_pdf_typography.php` | 240 cumulative checks PASS |

Cumulative totals include shared prerequisite fixtures and must not be added as
unique coverage. `invoice_drafts_native.php` reached 136 prerequisite checks, then
correctly stopped because native schema proof requires MySQL/MariaDB: NOT PASS.
The MySQL late-trigger branch in native_invoice_fields remains unexecuted.

Real execution found a synthetic summary fixture that incorrectly forced a
partially paid invoice into status4. The canonical balance rejects that state;
the fixture now explicitly asserts this rejection and uses separate status3
partial-payment and status4 wholly unpaid documents. No balance guard was changed.
The native JS serializer also required its pure Str/StrClickable dependencies in
the minimal bootstrap; those dependencies are explicit without loading models or
hooks. Native routes still require their complete controller/view adapters before
activation.

## Project association contract (pending native UI wiring)

`CompanyInvoiceProjectSchema::ensure` is reserved for the coordinated additive core
migration (474); this branch does not edit or choose a migration number. Its
`company_invoice_native_relations` table stores issuer/invoice/client and explicit
source kind/id/revision. There is no inferred backfill. Selecting a project does
not create a billing origin, charge, payment or proof. Raw company project IDs are
never stored in the legacy `invoices.project_id` column.

The canonical invoice writer now accepts optional `native_project` with
`source_kind=company_project`, real `source_id`, and reviewed `source_revision`.
An absent key preserves historical payload hashing; explicit null detaches. New
relations require the owned project reader under the same transaction, active
project, matching active authorized customer and current revision. Final readback
runs after audit/journal/origin guard. This is not a public independent mutator.
Copy and private-draft payloads preserve this relation rather than silently dropping
it, and new draft save checks its ownership before any issue. Invoice revisions and
copy source hashes include the relation. Existing payment/provider fences remain.

`CompanyProjects::getInOwnedTransaction` is a read-only composition point, requiring
`CompanyProjects::ownedReferenceTables()` and actual `IssuerTransaction` ownership;
a raw PDO transaction does not qualify. It never opens a nested transaction.

Local PHP 8.4/SQLite results: project schema replay/negative tests 68 cumulative;
owned project reference 42 cumulative; canonical project association/copy/draft/
late rollback 156 cumulative; pure canonical fields/project payload 24 checks.
MySQL schema, lock contention, HTTP/native selector and browser remain pending.
Legacy project association for a named principal invoice is intentionally not
accepted by this input yet; it needs the native lineage resolver and client
boundary, not an alias or matching integer. No invoice route is activated by this
internal checkpoint.
