
## Direct issued creation

`NativeCompanyInvoiceCommands::createIssued` composes the same complete form metadata with the existing `CompanyInvoices::create` allocator and writer, in one transaction. It does not save an intermediate draft. Actual inserted invoice/item IDs are attested only during the exact writer instance's post-create callback. The command receipt binds actor, issuer, operation, complete normalized financial payload, invoice/line fields and tags. An exact replay returns the committed result without rewriting metadata; divergent metadata is a conflict. A late acknowledgement failure rolls back the invoice, allocator, journal/audit and metadata together.

`tests/companies/native_invoice_issue_commands.php` exercises the real SQLite services, create-only permissions, metadata replay/conflicts and late failure/retry. This is not HTTP, browser, MySQL or native editor wiring evidence. The existing principal controller/writer remains the legacy route; this compositor is for the named adapter.

## Draft issuance metadata

The existing `CompanyInvoiceDrafts::issue` post-create callback now transfers invoice-header custom fields (including already persisted hidden/inactive fields, without exposing them to the actor) and ordered draft tags into the newly attested invoice. The original draft remains unchanged. A receipt in the immutable issue result references an independently checked audit record; replay verifies the receipt instead of copying again. No new schema is required. Table detection is fresh and rejects partial owned schemas; historical installations with only legacy tag tables are not treated as having the new owned tag schema. Historical issue results without the new receipt are not rewritten or backfilled.

`native_invoice_draft_header_transfer.php` covers real services, create-only issuance, hidden field visibility, order, replay, tampered audit, late receipt failure/rollback/retry and partial-schema rejection. It is SQLite evidence, not MySQL/browser evidence.

## Optional native due date

The manual invoice/draft form may leave `duedate` empty (including `invoice_due_after=0`). The canonical manual payload normalizes only empty string/NULL to NULL, and the existing invoice column/document DTO preserve that absence. Malformed non-empty values still fail. The new, not-yet-published migration474 draft projection declares `duedate DATE NULL`; no historical migration or seed changes. Draft backfill, issue and replay preserve NULL. Automatic recurrence continues to require its separate explicit date contract rather than guessing a due date.

### Explicit creation preferences

`native_preferences.cancel_overdue_reminders` is an optional canonical creation
field, normalized only from integer/string 0 or 1. Absence preserves historical
hashes and the existing default. It is separate from editable native fields.
The draft payload retains it; issue applies it through the same writer's private
creation proof before financial journal/command acknowledgement, and verifies the
persisted value. A failed readback rolls back the complete issue. Replay never
reapplies the preference after a later authorized communication command.
Manual invoice edit rejects this creation-only object rather than bypassing the
communication revision/idempotency contract. This does not prove that named
reminder cron or SMTP delivery is enabled or working.
