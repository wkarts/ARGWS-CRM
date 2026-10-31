# Native invoice number presentation

New canonical company issues capture `invoice_number_format` (1–4), the invoice
numbering date, and `number_padding_prefixes` in the immutable document identity.
They use the existing `SalesNumberPresentation` implementation. `tblinvoices`
receives the same number format and formatted label. Reading, changing the business
date, or retrying an issue never reformats an already captured number.

Missing options use the public installation defaults (format 1, padding 6, prefix
`INV-`); explicit prefix `''` and padding `0` remain explicit values. Native
presentation padding is bounded to 0–99, matching the existing native reader's
validated wire and fitting the 191-character document field with the allowed
prefix/date formats. Historical snapshots without the optional format/date retain
the exact previous prefix + series + padded-number decoder (padding 1–20).

`CompanyInvoicePresentation::fiscalIdentity` validates the full canonical identity
before excluding number presentation and the existing validated PDF preferences
from fiscal-origin comparisons. Prefix, legal name, taxpayer identity, recipient,
and stored source hashes are not removed or rewritten. Existing issued receipts
can therefore be finalized with their original snapshots. An unissued source
whose captured empty prefix came from absence may become stale when the public
`INV-` default is introduced; it must be explicitly refreshed through its existing
revisioned workflow, never silently converted or rewritten by migration.

No sequence allocator, principal legacy counter, provider, or migration is changed
here. Principal native routes remain on their existing legacy writer. Secondary
native editor admission and complete UI/PHP8.1/PHP8.5/MySQL/browser proof remain
separate integration gates.

Executed locally: PHP8.4/SQLite `native_invoice_number_snapshot.php` (93 cumulative
checks), invoices, drafts, copies, recurring modes including late acknowledgement,
project relations, options, and reopening. These are not browser or MySQL evidence.
