# Native contact consent

The original `clients/consents`, `gdpr/contact_consent_opt_action` and
`consent/contact/{key}` components are retained. Public contact decisions use a
purpose-specific HMAC capability, not a portal login or staff impersonation.
The runtime encryption key is required; it is never embedded in source.

New links expire after 24 hours and are bound to issuer, contact, customer and
contact identity. Email/password changes, contact/customer revocation, issuer
revocation or disabling consent policy invalidate them. Rendering and link
creation do not write database state. POST requires the issuer-bound public
session CSRF token, a complete-state revision and an idempotency key. Responses
are private/no-store, no-referrer and noindex. A link grants only contact consent
operations; it cannot be reused as a portal identity.

The existing canonical consent table gains an explicit `purpose_source` in its
unpublished 474 adjunct. The persisted installation principal can read original
historical purposes/history alongside owned records without confusing colliding
IDs. New decisions record their actual source and actor; historical rows are not
rewritten. Earlier owned receipts can be upgraded only when their issuer and
immutable purpose-name/revision receipt prove the origin. Ambiguity fails before
backfill. An intermediate MySQL DDL default is recognized only after validating
its exact shape and every affected receipt; the final schema has no source
default.

Old unbounded `user_meta` consent keys are not silently converted into expiring
capabilities. Issue a fresh link from the existing contact modal. Historical
consent records remain readable. Revoked contacts retain administrative history,
but cannot receive a new live public capability.

Executed locally: PHP 8.4 with real SQLite storage, actual native controllers and
original view rendering; A/B, principal/historical collisions, revocation,
CSRF, stale/replay conflict, audit rollback and predecessor-schema migration.
MySQL/MariaDB metadata/partial-DDL execution, full bootstrap and Chromium remain
required in the integrated suite. This does not admit the still-incomplete
public `Clients` controller or claim the entire portal is finished.
