# Full native invoice form command receipts

474 adds `NativeCompanyInvoiceCommandSchema::ensure(db,prefix)` after issuer/staff/audit dependencies. There is no historical backfill and no replacement of invoice/draft/journal writers. `company_invoice_native_commands` binds issuer, actor, explicit operation, caller request key, normalized full submission hash, result and independent audit receipt.

`NativeCompanyInvoiceCommands::createDraft` currently composes financial payload, invoice custom fields, each persisted draft line's custom fields, and native tags inside the existing draft transaction. Required full metadata keys are `invoice_fields`, `line_fields` (one explicit array per canonical input line), and `tags`. Missing/unsupported metadata is rejected. Same key/body replays the original result without overwriting later values; another body/actor conflicts. The original private creation proof permits create-only actors without granting edit.

A pending request row exists only inside the in-flight owned writer transaction. Completion is mandatory, read-back confirmed and audited. Late tag failure or ignored completion UPDATE rolls back request/draft/lines/metadata together. No financial invoice/number is issued by this operation.

This is a backend for the original native editor, not a new UI or endpoint. Issue form orchestration and native controller/view wiring remain work in progress. PHP8.4 SQLite tests exercise the actual composed services and failures; MySQL concurrency and PHP8.1/8.5 remain CI obligations.

`updateDraft` composes the original financial draft change with all metadata in the same owned transaction. It requires explicit `line_sources`, `removed_line_ids` and the actual tag revision. Retained plus removed IDs must equal the previous revision exactly, without duplicates or foreign sources. Each retained source preserves verified hidden metadata internally before authorized visible overrides; new lines inherit nothing. The source history remains unchanged. Late acknowledgement failure rolls back the whole update; same-key/body replay returns the original result.
