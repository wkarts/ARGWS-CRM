# Native invoices — finite closure inventory

Snapshot: local `e25d0ce3`, base main `abbf6c7f`, 2026-10-25. This is an internal
closure list, not a claim of complete functionality, HTTP validation or a release.
The core integrator controls exact route admission. No route is admitted merely
because its controller constructor has a native branch. Existing actions must be
implemented and tested, not removed to simulate parity.

## A. Invoices controller: exactly 30 public actions (constructor excluded)

Source: `application/controllers/admin/Invoices.php`. “Adapted” below means an
explicit `nativeFor` branch exists; it does not mean final browser/HTTP acceptance.

| Action | State | Concrete remaining contract / existing implementation |
|---|---|---|
| `index` | Adapted delegation | Same list/manage view. Real SQLite/controller/nested-view test passes; full action graph and HTTP/browser remain. |
| `list_invoices` | Adapted | Same `manage`, `quick_stats`, `filter_params`, `list_template`, `table_html`; real options/saved filters/custom fields. A/B and persisted-default view tests pass. Remaining full action graph/HTTP/browser. |
| `recurring` | Pending | Same recurring list and actions backed by `CompanyInvoiceRecurring`, modes/discovery/drafts; explicit schedule/principal namespace, not global invoices_model. |
| `table` | Adapted base table; recurring pending | Reader now executes 12 base plus authorized custom-field rules, counts/multiorder/pagination, principal NULL+named union and project/tag/custom sorting. Real DataTables parser and shared native row presenter exist. Base Invoices::table now uses this parser/reader/shared presenter and real authorized columns; ALL and native configured pagination are preserved. Real controller-double tests cover A/B, CSRF/context rejection and read-only state. Remaining: actual HTTP/browser acceptance, recurring-table submode and complete surrounding UI action graph. |
| `client_change_data` | Pending | `CompanyClients` plus authorized native relation resolver; billing/shipping defaults, customer/project/catalog data, no global customer hydration. |
| `update_number_settings` | Pending | Issued identity/number is immutable today. Existing action changes prefix and formatted number. Needs an explicit safe numbering contract; never write global counter or captured identity to obtain visual parity. |
| `validate_invoice_number` | Pending | Read-only issuer sequence/year checks and draft transport identity; cannot accept browser original_number as authority. |
| `add_note` | Adapted | POST + context/CSRF to `NativeCompanyAuxiliaryUi::saveNote`; real auxiliary integration/HTTP proof remains. |
| `get_notes` | Adapted | GET/HEAD + auxiliary facade to the same `sales_notes_template`; real auxiliary integration/HTTP proof remains. |
| `pause_overdue_reminders` | Pending | POST/revision + real owned reminder preference and scheduler consumption. No mere boolean UI flag disconnected from worker. |
| `resume_overdue_reminders` | Pending | Same preference/worker contract; existing GET mutator must become explicit POST without changing the visible action. |
| `mark_as_cancelled` | Adapted owned/private; NULL pending | POST/context/CSRF plus CAS invokes actual CompanyInvoices cancellation/provider/payment fences, or private draft cancellation with request key. Boundary test covers A/B, GET, paid-source veto and no draft emission. Historical NULL writer still requires its explicit adapter; no remote-provider cancellation is implied. |
| `unmark_as_cancelled` | Adapted owned; NULL/private pending | POST/context/CSRF + CAS. Reopens only a validated canonical local cancellation with issued-event and independent audit receipts. Provider/payment/credit/bank history vetoes the transition. Appends invoice_reopened, preserves cancellation and issue, restores ledger magnitude without reissuing, advances lifecycle CAS; repeated cycles/retry/late rollback covered in SQLite. No NULL/draft fallback. |
| `copy` | Pending | `CompanyInvoiceCopies` / `CompanyInvoiceDrafts::copyInvoice` preserve metadata and project relation; needs native review/form and unambiguous draft ID/redirect rather than global copy GET. |
| `get_merge_data` | Pending | Current merge UI accepts other invoices/tasks. Requires explicit canonical merge/source ownership/proof and transaction semantics; no owned merge service has been established in this slice. |
| `get_bill_expense_data` | Pending | `CompanyExpenseBilling`, `CompanyTaskBilling` and lifecycle/source locks; real selectable source DTO, not arbitrary line copies without provenance. |
| `invoice` | Pending | Same create/edit view and all fields/actions. Canonical native_fields/project metadata ready. Remaining native request translation, draft codec, numbering, sale agent, payment-mode policy, tags/custom fields, recurrence, source conversions and all JS dependencies. |
| `get_invoice_data_ajax` | Pending | Same preview partial: document/items/taxes/payments/credits, notes/reminders/activity, attachments, delivery and recurrence DTOs; no global model/helper hooks. |
| `apply_credits` | Pending | `CompanyCreditWriter::moveCompanyCredit` with explicit apply key, ownership/currency/source and revision; not legacy model movement. |
| `get_invoices_total` | Adapted | Same native totals partial; summary has currency/year/customer/project scopes, principal lineage distinction, POST context/CSRF. Real HTTP/browser acceptance remains. |
| `record_invoice_payment_ajax` | Pending | Same payment form with real allowed owned modes and typed receipt provenance. The current manual writer is not a license to accept arbitrary native paymentmode. |
| `record_payment` | Pending | `InvoicePaymentWriter::recordCompanyManual` or typed provider source; idempotency, live permission and correct mode semantics. Do not coerce provider receipts into manual. |
| `send_to_email` | Pending | `CompanyInvoiceDelivery::queue` with contact/revision/key/issuer transport and real templates. UI must distinguish queued from delivered. |
| `delete_payment` | Pending | Typed reversal via credit/manual/provider lifecycle, not physical deletion. Existing `moveCompanyCredit` and post-refund services require exact source contracts. |
| `delete` | Pending | No physical deletion equivalent for issued owned ledger. Needs explicitly preserved historical/journal semantics; private draft cancel is a distinct resource, not a hidden alias. |
| `delete_attachment` | Adapted native file identity; preview orchestration pending | Original function is shared, scoped POST/context/CSRF/CAS uses NativeCompanyInvoiceFiles::remove with actual files ID. Parent ownership comes from persisted projection, not POST. Same preview partial adds revision attributes only for verified DTOs. History retained, retries/A-B/GET/CSRF/stale covered; full preview HTTP/browser still pending. |
| `send_overdue_notice` | Pending | Owned contact/template/queue and reminder policy with final dispatch checks; no direct global SMTP send. |
| `pdf` | Adapted, incomplete principal dispatch | `NativeCompanyInvoicePdf` uses owned document/TCPDF/audit, GET/HEAD and safe I/D modes. Principal actual NULL invoice still needs explicit legacy branch after row ownership resolution; no miss fallback. |
| `mark_as_sent` | Pending | Journaled owned delivery/display state under POST/revision; must not falsely assert actual dispatch or receipt. |
| `get_due_date` | Adapted | Owned invoice_due_after/dateformat, explicit catalog defaults, zero preserved, same plain-text native response; POST context/CSRF. Real HTTP/browser acceptance remains. |

**Count:** 11 actions have native handling (including index delegation) (table still has an explicit pending recurring submode), 19 have no native branch yet. Constructor early return is not a
31st completed action. Backend services/pure shared presentation do not turn a
pending controller action into a delivered workflow.

## B. Exact adjacent actions already reached by native invoice UI

These are existing dependencies, not newly invented scope. Owners must coordinate
shared files and register the final routes only after implementations exist.

| Controller/action | Native path / dependency |
|---|---|
| Public `Invoice::index($id,$hash)` | `invoice/{id}/{hash}` from row “View”. One method contains HTML, `invoicepdf`, `make_payment`, `paymentpdf`, tracking and public gateway selection. `CompanyPortalInvoices` provides contact-session ACL but is **not equivalent to a bearer invoice hash**. Define explicit issuer-bound public authorization/CSRF, provider eligibility and receipt proof; adapt the same `invoicehtml` view. |
| `Payments::batch_payment_modal`, `add_batch_payment` | Batch action in `list_template`; unpaid/customer rows must be scoped, each receipt must have provenance/idempotency, and batch partial-failure/atomicity semantics must be explicit. |
| `Payments::payment`, `pdf`, `send_to_email`, `delete` | Payment table links/actions; own receipt PDF/details/queue/reversal, no global model or physical deletion shortcut. |
| `Payments::index`, `list_payments`, `table` | Existing redirect after successful batch/payment leads here; must remain the same native screen with owned data, not bounce to parallel company context. |
| `Utilities::bulk_pdf_exporter` | Native bulk-export link and POST. Existing `App_bulk_pdf_export` is global. Needs owned date/status/mode/tag selection, per-document audit/authorization, safe temporary files/archive and no cross-issuer PDFs. Keep the native form/action. |
| `Filters::create`, `update`, `delete`, `mark_as_default`, `unmark_as_default`, `validate_dynamic_date` | Central owner: issuer/actor/shared/default/revision and namespace37. Invoice table supplies contextIdentity/normalizeBuilder. Reader now executes rules. Full HTTP and Vue integration still needed. |
| `Credit_notes::credit_note_from_invoice`, `apply_credits_to_invoices`, `delete_invoice_applied_credit`, `delete_credit_note_applied_credit`, `list_credit_notes` | Native preview controls/dialog/detail links. Compose existing owned credit issue/apply/unapply/refund provenance and codec; never global credit lookup by raw ID. |
| `Misc::get_reminders`, `add_reminder`, `get_reminder`, `edit_reminder`, `delete_reminder`, `edit_note`, `delete_note` | Auxiliary owner, UI facades namespaces32/33, actual shared partials, revisions and POST mutations. Reminder worker must consume owned state. |
| `Misc::upload_sales_file`, `add_sales_external_attachment`, `toggle_file_visibility` | Shared attachment modal/JS. Owned file key/hash, byte/type limits, external URL policy, customer visibility and POST/revision. Current global `files` helper is not an adapter. |
| `Download::file`, `preview_image`, `preview_video` | Existing attachment rendering/download routes; actual parent/file ownership, issuer/contact authorization and no filesystem path fallback. |
| `Misc::get_currency`, `get_taxes_dropdown_template`; item/project/customer search | Auxiliary catalog UI and central relation search; tax/item/group IDs34–36, project codec1, current/historical validated tax snapshots. No request may return to global catalog/model SQL. |

## C. Exact shared view/JS closure points

* List: `admin/invoices/manage.php`, `quick_stats.php`, `filter_params.php`,
  `list_template.php`, `table_html.php`, `admin/tables/invoices.php`.
  Quick-stats now accepts a validated counts DTO and shares exact DOM; status
  helpers no longer hydrate Invoices_model. Remaining list/table wiring is pending.
* Editor: `admin/invoices/invoice.php`, `invoice_template.php`,
  `billing_and_shipping_template.php`, `merge_invoice.php`, `bill_expenses.php`,
  shared item/tax/custom-field templates and native sales JavaScript.
* Preview: `admin/invoices/invoice_preview_template.php`,
  `invoice_payments_table.php`, `record_payment_template.php`, existing credit,
  send-email, note/reminder and attachment fragments.
* Public: `themes/perfex/views/invoicehtml.php` through the existing clients layout;
  preserve issuer branding/settings without inheriting another company's secrets.
* Browser must capture the real request graph for list→create→edit→preview→actions;
  any unadapted global request is a failure. Same URLs/DOM with disabled or missing
  actions alone is not completion.

## D. Completion evidence, not inferred from unit counts

Principal + named-primary + A + B, an actor limited to A, and live revocation;
explicit source IDs for drafts/projects/files; valid/stale context and both CSRF
checks; same native navigation and complete forms; filters/counts/pagination,
attachments, PDFs/archives, public hash/portal differences, batch receipts, queue
versus delivery, retry and concurrent writes. Check actual affected rows/ledger,
provider calls (isolated fixture only), filesystem and request graph. Run native
PHP/MySQL matrix and HTTP/browser 1280/768/390. Local PHP8.4/SQLite/TCPDF and
controller doubles already provide component evidence but do not establish this
acceptance matrix or visual equivalence.

## Current concrete integration gaps (not new scope)

- Private `company_invoice_drafts` still needs the third canonical table lineage (namespace10), status6 and global filter/order/pagination with principal legacy+named. Issued draft receipts must never be double-counted. `list_invoices` already validates a requested private draft, but that alone does not render its preview.
- Offline `Paymentmodes` now adapts all five existing actions: index (HTML/POST DataTable), manage, delete, change_payment_mode_status and change_payment_mode_show_to_client_status. Shared row legacy byte parity and actual service/controller tests pass; scoped changes are POST/revision/CSRF, source namespace46. Native gateway catalogue/eligibility and payment choice→receipt metadata remain unimplemented. No provider is converted into company_manual.
- Payment_modes_model is not called by the scoped configuration controller; editor callers still need a complete explicit catalogue facade rather than the global gateway-loading constructor.
- Filters HTTP/Vue is owned and implemented centrally; this slice consumes the real factory/storage, not an empty filter placeholder.
- Existing manage markup retains its actual create/recurring/batch/export/modal links. This is why routes must stay closed until those dependencies are implemented; keeping their markup is not evidence their actions work.
