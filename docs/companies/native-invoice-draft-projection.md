# Native invoice list: private-draft projection (additive 474)

The existing `company_invoice_drafts` payload remains authoritative. The new
`company_invoice_draft_projection` is derived using `CompanyInvoiceLines`, not
floating-point recalculation or an invoice/sequence reservation. Source state,
revision and canonical header hash accompany amounts, dates, customer snapshot,
currency and the existing explicit project association.

## Installation order

The central additive migration must run, after the original draft schema:

1. `NativeCompanyInvoiceDraftProjectionSchema::ensure($db, $prefix)`.
2. `(new NativeCompanyInvoiceDraftProjection($db, $prefix))->backfill()`.
3. Only then confirm the migration's normal success/tracking receipt.

DDL and data backfill are separate. The data stage is one owned transaction: each
source is checked against its latest immutable draft event and audit receipt,
payload/context/source hashes, canonical line contract, and (when issued) the
real invoice request/document receipt. A corrupt or ambiguous source aborts all
projection writes. Retrying is supported; source data is never guessed or repaired.
Backfill does not require impersonating an old, possibly revoked staff actor.

The source writer's existing event append synchronizes the projection inside the
same owned transaction once this additive table exists. A projection failure rolls
back creation/change/cancellation/issuance. Before this additive schema, historical
writer behavior is preserved. Native list readers do not silently omit drafts when
the draft table exists but the projection is absent or incomplete.

## Same table, three sources

The native SQL UNION now combines issued named invoices, proven-primary historical
NULL invoices, and private **open** drafts. Draft IDs use the existing namespace10
codec; they never alias physical `invoices.id`. Issued/cancelled private rows do not
appear as a second invoice. Sorting, counts, filtering and pagination happen once
over the UNION. Private drafts have no reserved number or bearer hash: their shared
native row displays `DRAFT` and links View to the authenticated native preview route.
It does not publish a public invoice URL.

Drafts use real source-specific tags/custom fields and project references. The
absence of payment/sent/scheduled-principal state is not provider evidence. No
fabricated financial `position` DTO is attached to a draft. Quick-stat counts and
year choices include open drafts, while receivable/paid/overdue totals exclude them.

The complete open-draft frame of the selected issuer is validated before and after
queries, including off-page rows. This is deliberately linear in private drafts,
not in all issued invoices; measure that cost before optimizing. There is no
invented row cap or truncation to obtain speed.

## Evidence and boundaries

Executed locally: PHP8.4/SQLite writer/backfill, corrupt source rollback, late
projection rollback, cancel/issue, A/B UNION/global pagination, same row rendering,
source-specific tags/custom search, count parity and off-page corruption rejection.
The integration fixture adapts SQL syntax for SQLite; MySQL with the PHP8.1/8.5 CI matrix, real HTTP and
browser validation remain required. This is not proof that editor/preview/public
invoice/payment/notification actions are complete. Their finite closure inventory
remains authoritative.
