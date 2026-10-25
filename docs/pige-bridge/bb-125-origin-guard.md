# Banco do Brasil 1.2.5 — bridge-owned origins

New additive migration125 creates `bb_api_origin_policy` (InnoDB, immutable
origin PK, unique SHA256 origin reference, issuer, `bridge_manual`, timestamp).
It never adopts existing titles, backfills ownership, removes policy, or enables
a financial integration. Native module tracking must advance after successful
DDL. Creation rejects an outer transaction; runtime validates schema without DDL.

## Trusted internal API (not HTTP input)

- `ApiService::issue(issuer,actor,profile,invoice,input,requestKey,guard=null,tables=[],reference=null)`
- `ApiService::query(issuer,actor,origin,key,guard=null,tables=[],reference=null)`
- `ApiCreditedReceipt::explicit(origin,queryOperation,reference,guard,tables=[])`
  supplied to `InvoicePaymentWriter::recordCompanyProviderReceipt(issuer,actor,source)`.

A reference requires a throwing veto callback. Tables are suffixes passed to the
native issuer transaction. The callback checks issuer/configuration revision,
consent and origin binding; it must not demand an unpaid invoice, since it also
runs after the canonical payment and on an idempotent replay. It receives no
ambient company session. Native ACL remains authoritative (BB issue/query/reconcile,
invoices.view, and canonical writer payments.create).

Issue records ownership atomically with number/origin reservation before OAuth or
registration. Guard runs before/after reservation, operation claim, sending,
OAuth/provider calls and proof persistence. Revocation after an external effect
cannot undo it: the existing native failure/unknown journal persists conservatively
without requiring the revoked guard. No automatic reissue is introduced.
Interest, discount, extended receipt and assignment profiles are rejected for
bridge origins. Default native resume/query/cancel/amend and receipt entrypoints
cannot operate bridge-owned titles without the trusted reference/guard. Other
origins keep native behavior. API/CNAB/component receipt paths using
receiptOriginInOwnedTransaction cannot bypass this ownership.

Query is explicit observation, not payment. Checkout projection remains the
existing DB-only ACL-bound method without policy mutation or provider calls.
Explicit reconciliation does no HTTP; it retains all canonical credit16,
account proof, exact nominal BRL, zero adjustments, adverse history, freshness
(first application 300s), ledger and invoice checks. Same proof returns the same
payment, not another payment. Latest proof and callback are rechecked around
receipt persistence; late veto rolls back payment and receipts together. Policy
remains intact after settlement. No PIGE or academic state is changed.

## Evidence / limits

`python tests/banco_brasil_bridge125_static.py`: five structural checks executed.
`tests/banco_brasil_bridge125_mysql.php`: new native migration/outbox/writer fixture
with synthetic transport, preflight veto, immutable policy, blocked unguarded
commands, no-HTTP issue replay, late payment rollback and explicit reconciliation
idempotency. **PHP/MySQL not executed in this environment.** Existing native
migration composition fixture now advances124→125 including tracking failure and
replay. Run both the new fixture and existing BB suites in isolated MySQL CI.
No production credentials, provider calls, activation, publication or deployment.

## Rollout / rollback

This module is composed with core473 after UI472; integrator owns publication.
Run native upgrade, inspect schema/tracking, execute fixtures, then review the
separate bridge configuration/gates. Reverting code must not erase ownership or
re-enable unguarded operations; disable integration and preserve the125 policy.

## Pull Request composition

Branch: `feature/pige-bridge-473` (local child checkpoint; no remote PR asserted).
Title: `feat(banco-brasil): guard bridge origins and explicit canonical receipts`.
Scope: native BB services, additive125, fixtures and this contract. No bridge/UI
controller edits, external provider request, merge, deploy or automatic academic
settlement. No dependencies added. Review MySQL fixture evidence before release.

## In-flight response quarantine

If the provider responded before consent was revoked, the bounded canonical
issue/query projection is retained in the original operation as
`{technical_only:true,projection:{...}}`, with response SHA256 and HTTP status.
Compensation verifies operation ID/claim/actor/type, issuer and immutable native
origin identity. It requires neither a new authorization nor the revoked guard.
It never stores raw response JSON, payer payload, headers or tokens.

The operation stays `unknown`, with `technical_result_quarantined`; issue origin
stays `api_unknown`; query also marks non-terminal origins unknown without erasing
previous settlement/cancellation/conflict observations. This is NOT a validated proof, instrument publication or
payment authority. It cannot authorize replayed POST, default reconciliation or
automatic settlement. Explicit later synchronization requires current consent
and new authoritative proof. On query, a veto prevents additional account HTTP.
The original native behavior for non-bridge origins remains unchanged.
