# Native ticket bulk actions — implementation checkpoint

The original `Tickets::bulk_action` endpoint now supports two owned operations: complete merge and logical batch deletion. Deletion calls the same individual tombstone/receipt logic inside a single issuer transaction. A failure on any selected source rolls back every tombstone, parent revision, audit and receipt. The batch receipt binds the entire exact ID/revision map and actor; retries cannot silently reuse a nonce with a different selection. Source rows, authorship, original messages and private bytes remain retained.

Evidence: PHP 8.4 with actual SQLite in `tests/native_company/ticket_bulk_delete.php`, including original controller, failure on the second audit, replay and changed-selection/CSRF negatives. No MySQL, SMTP or browser claim.

Remaining: batch status/department/priority/service/tags, native list/DataTable and browser selection-proof wiring. The original route is not ready for final admission until these consumers and the detail page are complete. Principal legacy behavior is unchanged.

The remaining mutation branch now implements the original status/department/priority/service/tags fields through their actual owned catalogs, metadata and tag writer. Every selection carries a current ticket revision (and a separate tag revision when tags change). A stale second source, a foreign department or failed per-source audit rolls back the complete batch. Original source marker/body provenance is unchanged; statuses remain explicit catalog references. Executed `ticket_bulk_update.php` with the original controller and real SQLite, covering all five fields and failure/replay cases. The native list/browser selected-row proof wiring and full detail still remain open.
