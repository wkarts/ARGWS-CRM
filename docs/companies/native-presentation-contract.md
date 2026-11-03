# Native presentation foundation

This change is a foundation, not complete route or module parity. It does not enable any controller or remove a company boundary.

## Bootstrap ownership

The central authenticated bootstrap must implement `CompanyRequestScope::nativeFor($ci)` and `nativeRouteAllowed($ci, $relativeGetRoute)`, then call `NativeCompanyAdminPresentation::prepare($ci)` only after the normal company identity, membership, ACL, context-token and CSRF checks. Both nativeFor and sealedFor are required. The presentation object belongs to the exact controller, issuer and token; a changed token cannot reuse it.

A null issuer is accepted only for the authenticated native dashboard/index selection-recovery page. It produces no resource menu and makes no resource ACL query. The shared selector remains available; no legacy dashboard query is permitted.

Helpers required by the view boundary are the existing pure URL, form, admin and branding helpers, including escaping and `_attributes_to_string`. Global `_l()` is not used by the contextual chrome because it runs hooks. The provider reads the shipped Portuguese-Brazilian language file and only projects the authenticated active staff's name/email. No models, module hooks, credentials, notification/timer queries, or provider transport are loaded.

The service publishes `locale`, `sidebar_menu` and `setup_menu` to the normal loader. The same five native head/header/aside/setup-menu/scripts files render both modes. `init_head` does not query `misc_model` for contextual pages. PWA links remain the existing static shared include. Native styles are loaded from the shipped assets. NativeAdminNavigation is extracted from the actual main.js menu, metisMenu, active-state, header toggle, resize, geometry and setup routines. Both primary main.js and the contextual initializer call this single implementation; idempotence prevents duplicate binding. Only the primary injects its existing setup-session transport. Device detection is a pure shared NativeDevice primitive: webpack combines it before app.js in common.js for admin AND client portals, while the contextual asset list loads it directly. No global application bootstrap is introduced. Shared accessibility additions protect covered forms and synchronize aria/Escape behavior in both modes. The compiled common.js and main.min.js must be regenerated centrally before publication.

## Single-source menu, not URL aliases

63 static core descriptors have been extracted from menu_helper into NativeAdminMenuCatalog. The legacy function consumes those exact descriptors under its unchanged authorization conditions. The contextual builder consumes the same labels/icons/positions/hierarchy, then requires both scoped resource ACL and an explicitly implemented canonical route. There is no `company_*` rewrite.

Canonical destinations inventoried: dashboard, clients, proposals, estimates, invoices, payments, credit_notes, invoice_items, subscriptions, expenses, contracts, projects, tasks, leads, estimate_request, knowledge_base, utilities/calendar, reports, issuers, staff, settings and emails. Ticket/status/count providers, setup subresources, module contributions and quick-create actions still need explicit scoped adapters. Static report/setup child declarations now share the same catalog; this does not itself implement their controllers. Missing destinations remain unavailable during composition; this must not be reported as final menu parity.

Search is visibly disabled until a scoped search adapter exists. Timers, notifications, newsfeed, pinned projects and todo counts are not fabricated as zero. Their providers are separate work. Header capabilities and template integration must be completed before accepting full functional parity. Existing backend company controllers remain untouched by this slice.

## Verification

- `node --test tests/native_company/presentation.test.cjs`: static single-source, attestation and no automatic transport contracts.
- `php tests/native_company/presentation.php`: real native includes, hook/model/option dependencies throw, explicit A/B/null/stale context negatives.
- `node tests/native_company/presentation.browser.cjs`: actual PHP templates and native reference at 1280/768/390, native geometry, menu interaction, form preservation and screenshots. Synthetic identities/providers are not a live login or database isolation test.

The integration still needs authenticated A/B switching, revocation, cross-tab409, CRUD/export/attachments and zero unexpected provider/global requests across every admitted route. Local absence of PHP/browser runtime is not a passing result. Do not remove or relax isolation to make a template render.

## Header storage contract (implementation in progress)

`NativeCompanyHeaderSchema` defines two new core-owned stores; the central new migration
must call `ensure`, and installation parity must be composed centrally. This helper
never alters existing columns or adopts global notifications/staff history. Existing
schema is checked before any missing table is created. MySQL/MariaDB metadata must
match column types, nullability, defaults, collation, indexes and restrictive foreign
keys. `tests/native_company/header_schema.php` is a pure metadata contract, not a
substitute for real engine installation/replay tests.

`NativeCompanyNotifications::recordInOwnedTransaction` accepts only source kind/id,
issuer, actor and recipient. It resolves the owned reminder/announcement and derives
the event revision, text and native destination. The writer must enlist the notification
store AND every source/resource table in its original transaction. Repeated identical
events return the existing row; mismatched payloads fail. Current membership/resource
access is revalidated when rendering. Old global notifications remain separate.

`NativeCompanySearchHistory` stores only issuer/staff-owned terms. Removal uses stable
row ID/revision, not a mutable visual array index. Controller mutations still require
central context/CSRF validation. These services are not yet wired into the header or
Misc endpoints; incomplete UI must not be described as full native parity.

### Search/notification view adapters and shared search interaction

The search and notification adapters render the existing `admin/search.php` and
`admin/includes/notifications.php`, not substitute templates. Owned search labels
and notification text are escaped, routes come from an authorized resolver, and
legacy formatters/hooks are not executed for an owned DTO. Missing notification
provider data is an explicit error rather than a fabricated zero count.

`native-admin-search.js` extracts the existing search events, 700ms debounce,
history/dropdown behavior and result highlighting from `main.js`. Both contexts
must invoke this one implementation with their appropriate transport. History text
is now inserted using DOM text, and an obsolete response cannot replace a newer
query. Global setup still uses the original HTTP endpoints; contextual transport
must use central CSRF/context validation. `main.min.js` needs central regeneration.

Current search candidate implementation covers owned clients, invoices, projects,
tasks (both sources), tickets, contracts and leads. It is not wired as final search:
legacy-primary lineage and contacts/staff/expenses/estimates/proposals/credits,
received payments, line items, custom fields and knowledge base remain explicit
provider gaps. A first-page filter is not a substitute for scoped search SQL.

Notification semantics match the original model: `read_at` controls the badge/desktop
state; `inline_read_at` controls each inline unread row. Opening the dropdown marks
only the badge state; desktop dismissal and marking all inline rows affect both.
`NativeCompanyHeaderEndpoints` enforces attestation plus central POST/context/CSRF
for every mutation. Polling performs only reads. `NativeAdminNotifications` extracts
the actual original handlers and desktop behavior with transport injection, preserving
one interaction implementation. Contextual desktop/user options and remaining header
providers still require composition and browser proof; this checkpoint is not a
claim that the complete header is wired or that all native features are operational.

`NativeAdminQuickActionsCatalog` now contains the exact 15 original descriptors,
including task/lead modal handlers, ticket staff policy and formatted calendar date.
The central `AdminController` owner composes the small consumer change; no alternative
create routes are introduced. `quick_actions.php` compares every descriptor against
the frozen native method for both ticket-policy modes.

Local PHP 8.4.26 became available during implementation: presentation rendering,
metadata validator negatives, quick-action comparison and SQLite service contracts
were actually executed successfully. SQLite proves service authorization/replay/
rollback behavior only; it does not prove MySQL/MariaDB metadata, locks or concurrency.
CI PHP 8.1/8.5 and real browser validation remain required.

Historical header state is now handled by `NativeCompanyLegacyHeader`, only after
explicit primary-company equality and current actor membership. Global history stays
in `user_meta`; removal is protected by a hash of the exact current JSON, not just a
visual index. Global notification IDs remain in the native namespace; owned events
use namespace 40, and owned search rows namespace 41. A raw historical ID on a named
company is denied, never retried against another store. Serialized historical argument
objects are rejected. Known legacy resource links are resolved with ownership/ACL;
unsupported historical link families remain non-clickable until an explicit adapter
exists (not full interaction parity). No arbitrary stored link becomes trusted.

`header_legacy.php` executes primary-only/history-stale/recipient isolation and
serialized-object negatives on SQLite. Mark-all across owned and legacy lineages
currently uses separate idempotent transactions, not an atomic cross-lineage commit;
retry is safe but that distinction must remain visible in review.

Provider mounting now consults the central exact endpoint catalog in addition to
attestation. Once the complete Misc endpoint set is composed, search/history and
notifications mount into the existing header with real data. Contextual glue supplies
same-origin fixed endpoints and captured company/native-CSRF/framework-CSRF values
to the shared interactions. It performs no automatic mutation or provider call on
load. Missing route sets remain an acceptance blocker, not a finished reduced UI.

Search also includes explicitly primary-only legacy candidates with the same
CustomerRelationBoundary/TaskClientBoundary before hydration, followed by the
resolver again. SQLite tests prove mixed owned/historical projects cannot surface
a historical project tied to another issuer's client. Remaining custom-field,
knowledge-base and line-item search families are still open acceptance items.
