# Native invoice calculation contract

New native invoice payloads explicitly declare `calculation_contract=native_sales_v1`.
Omission retains the existing algorithm and canonical bytes. A version is never
inferred from the amount, current settings, company, or date. Unknown versions fail.

The native version follows the original sales form: rounded line amounts, summed
unrounded taxes, proportional before-tax discount, and after-tax percentage on
subtotal plus taxes. Exact rational decimal-string arithmetic avoids floating-point
loss, including amounts beyond safe JS integer cents. Displayed component rounding
can have the same one-cent reconciliation difference as the original form; the
final total is rounded independently, not manufactured from rounded components.

The marker is captured in the immutable `invoice_issued` event in the same issuer
transaction. Its reader verifies invoice ownership, ACL, event hashes, captured
basis, matching audit provenance, and rereads the proof. Draft payloads, issuance,
copies and recurring occurrences retain this explicit contract. Manual edits may
not silently add/remove/change it. Historical records are not rewritten.

Free lines are retained only for the explicit new contract, including draft,
preview and recurring issue. The original update_invoice_status helper marks an
all-zero invoice paid without a payment. The new writer mirrors that status only
for this explicit contract; the balance reader requires its immutable zero event,
status 2, and absence of receipts, credits, provider origins and noncash settlement.
Provider-table discovery is fresh, not framework cache authority. No fake 0.01
amount, deleted line, receipt, bank request or provider charge is created.

Validation includes the actual PHP native calculator as differential oracle and the
original shared `calculate_total` JS function with real accounting.js (DOM seams),
plus SQLite issuance/provenance/copy/draft/recurrence/replay negatives. These are not
browser/HTTP screenshots or MySQL/PHP-matrix evidence. The HTTP adapter must always
select this contract for new native submissions and reject client/server total
mismatches; that adapter is still being completed.
