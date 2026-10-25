# Native customers migration — engineering checkpoint, not parity acceptance

The customer domain must use `/admin/clients`, its existing controllers, native
profile/contact/group modals, table and forms. Company selection is authorization
context, not a second application. Shared customer identity never implies access
to private contacts, billing/shipping data, documents or financial history.

## Implemented locally (runtime validation pending)

- Native directory/profile data adapter with scoped count/search/pagination.
- Owner transaction writes existing native website, language, currency,
  billing/shipping and identity fields; request idempotency, revision, audit,
  readback and rollback remain in the same `CompanyClients` writer.
- Currency cannot change after financial documents exist. Explicit sharing
  continues to expose only basic identity.
- Existing profile fields and identity lookup are reused, with native context
  mutation verification and native routes, not a redirect to `company_clients`.
- Group definitions retain native IDs/tables and gain explicit ownership through
  `company_customer_group_owners`. There is no adoption/backfill of old global
  groups. This schema helper is for the centrally managed new migration only.

## Required before enabling the full controller

No wildcard admission is safe. Native methods must be admitted only after their
adapters and tests are complete. The interim profile-only guard and suppressed
legacy actions in this worktree are **not an acceptable final user experience**.

| Area | Existing native contract | Remaining work |
|---|---|---|
| Directory | DataTable, contacts/groups columns, filters, bulk actions | Owner contact/group projections; scoped filters/bulk writes |
| Identity | Profile, Brazilian details, billing/shipping, currency/language | Runtime proof, field-reference rules, catalog/customfield integration |
| Contacts | Native modal, permissions, notifications, passwords, profile image | Full owner-bound writer, native portal auth/email transport, file boundary |
| Catalogs | Group modal/table/inline creation, customfields | Group controller/membership wiring; central customfield adapter |
| Staff assignment | Customer admins | Active issuer membership + explicit delegated permission checks |
| Files/vault | Attachments, external files, private vault | Scoped metadata/storage/download and encryption/authorization |
| Finance | Existing client financial tabs, exports/statements/ZIP | Domain-owned scoped writers/readers; no global model fallback |
| Import/export | Native import UI and export contracts | Adapt bounded native input/owner receipts and scoped projection |
| Lifecycle | Status, contact removal, customer removal | Atomic ownership/dependency checks, no cross-issuer deletion |
| Privacy | Consent/registration/login-as-client | Explicit issuer/authentication/consent contract; no global hooks |

Contact permission rows already derive ownership from `contacts.userid` and the
customer owner. A second ownership table is not required; every mutation still
must validate that chain and reject a mismatched contact. Group administration
uses explicit `customers/manage_groups` plus customer edit authorization; global
admin status alone does not grant management of another company's catalogs.

## Verification

New PHP fixtures are intended to exercise real storage/transactions and A/B
isolation. PHP 8.4.26 became available in the isolated local executor. All changed PHP
files passed lint; native_clients, native_customer_groups, native_contacts and
native_portal_identity passed using real SQLite. Existing legacy model/controller/
table/mutation suites passed 10/25/3/53 checks respectively. The actual identity
partial rendered successfully through PHP. Full Chromium execution was blocked
before rendering because the Playwright-paired headless shell was absent. MySQL,
PHP 8.1/8.5 CI and complete native browser acceptance remain pending. Native UI must ultimately be
rendered at desktop/tablet/mobile sizes with two distinct issuers, revoked ACL,
shared identity, stale revisions/context, CSRF failures, and legacy regression.

Historical migrations and installation seed are unchanged. The integrator owns
new migration 474, official inventories, complete CI and the single draft PR.
No merge, deployment or production mutation is part of this work.

## Native customer attachments

The customer attachment tab now uses the original Clients controller, view and
modals. Named-issuer uploads allocate a real `files.id` and an ownership/content
sidecar in the same issuer transaction. Existing principal-company files remain
on their historical route; no path or ownership is inferred for them.

Uploads, external references, sharing and removal use POST, native CSRF, an exact
client owner/grant and revision checks. Sharing additionally validates every
contact against the client. Removal is a tombstone, preserving provenance.
Download uses the original `download/file/client/{attachment_key}` route, an
explicit staff issuer lease and no-store/nosniff headers. Only normalized PNG can
be displayed inline. The public reader separately requires a real portal binding
and explicit contact sharing; public bootstrap wiring is not implied by that API.

The additive `NativeCompanyCustomerFileSchema` must be included in migration 474.
It does not adopt existing files. `NativeCompanyFileRows` is shared with financial
attachments and validates affected rows and a random receipt to prevent stale
insert IDs. Private bytes are not included in list DTOs. The bounded store permits
100 retained files / 100 MiB per client. Container documents are attachments, not
executed or extracted; signature checks are not a malware scan or an Office
validity assertion. The shared configuration-aware normalizer reads the explicit company extension
policy and the smaller PHP upload/post limit, bounded above by 20 MiB. It validates
ZIP/Office structure without extraction and re-encodes bounded PNG/JPEG images.
Configuration is checked again inside the owned transaction before persistence.

The native send-file modal carries source, real file ID, revision and a request
key, never an authoritative filesystem path. Its Misc dispatcher and a dedicated
attachment-mail receipt are owned by the attachment integration. The chooser
still requires scoped Google/Dropbox configuration; accepting a provider URL
reference does not prove that chooser integration or perform an outbound fetch.

Executed locally with PHP 8.4 / SQLite: 27 file-service checks, 9 actual controller
checks and 4 original-view checks, including A/B, revoked grants, portal sharing,
CAS, audit rollback and ignored INSERT. Existing contact tests pass, including
read-only contact listing for an inactive client. The MySQL schema fixture is
written and linted but not executed. Browser geometry, MySQL concurrency and the
PHP 8.1/8.5 matrix remain required; no production delivery has been tested.

## All contacts

`Clients::all_contacts` now uses the original all-contacts view and DataTable,
with the issuer owner relation and authorized customer assignments included in
queries, counts, search and sorting. Contact custom fields and the consent filter
use the same scoped definitions/history; the most recent opt-out overrides an
older opt-in. Status and delete actions retain the original routes but use POST,
CSRF and current contact revisions. Revocation is rechecked before returning.
The actual PHP controller/view and SQLite contracts pass 14 new checks. This does
not claim browser validation or MySQL execution. The historical GDPR export route
is still a distinct pending adaptation, and is not exposed as a working scoped
action by this table.

## Send-file queue, not a delivery claim

`NativeCompanyFileMessage` accepts the native modal's explicit recipient, subject
and message, but derives attachment bytes only from a real customer/invoice file
ID, revision and current source authorization. It uses an independent receipt
(`NativeCompanyFileMessageSchema`, migration 474), not a password-reset token or
registration notification. The issuer/request key is unique and authenticates
recipient, payload, file content and configuration. Recipient length is bounded
by the actual native queue column. MySQL packet capacity is checked before INSERT,
and persisted queue fields are read back exactly, so truncation cannot authorize
sending. The message is sanitized with the bundled purifier without hooks.

The final SMTP attempt requires the generic file-kind dispatch fence to commit
independently. Unknown/sent attempts cannot be reissued by resetting the native
queue. Source/configuration is revalidated during OAuth refresh and immediately
before SMTP. A revocation after an accepted send preserves only its technical
receipt; it does not authorize another send. Both engines receive in-memory
attachments, not request-supplied paths. The original modal displays a queue
receipt and does not claim that the email was delivered.

Executed: 17 real SQLite/service checks (including actual Google token-provider
code with a synthetic refresh that revokes the file), 3 invoice-adapter checks,
14 actual App_Email worker checks with only final SMTP captured, and 4 rendered
JavaScript handler cases. Existing queue/contact regressions also pass. The
App_Email/Misc hooks are supplied by the attachment owner and must be composed
with this service. The MySQL schema fixture is written/linted, not executed;
external SMTP, Microsoft refresh and browser pixels remain unproven here.

## Historical invoice preview fence

The original invoice image/video preview route cannot bypass the file sidecar by
passing an old filesystem path. Once projection storage exists, an exact native
file record, active owner projection and explicit staff issuer lease are required.
The response uses bytes returned by the canonical file reader, rather than
reopening a request-derived path after authorization. A revoked projection
therefore blocks both opaque download and old raw-path preview while preserving
the historical file. Public access still needs its separate contact binding and
is not authorized by this staff route. The central bootstrap must admit only the
exact implemented Download methods. Six actual-controller/SQLite/filesystem
checks passed against the financial owner's canonical file fixtures.


## Public authentication: completed contract versus pending route integration

The existing `CompanyPortal` identity service now requires a fresh, nonce-bound
contact audit receipt before returning login success or committing recovery and
password changes. Persistent attempt counters also require exact INSERT/UPDATE
readback; ignored writes cannot authorize attempts. Twelve new real SQLite
trigger-suppression checks pass, along with the 37-check portal baseline and the
native identity adapter tests. No schema change is required for this correction.

This is not a claim that the original public routes are already adapted. The
following finite native surface remains to be composed and validated:

- `Authentication::{index,login,register,forgot_password,reset_password,logout,
  contact_email_exists,change_language,recaptcha}`: explicit issuer or an already
  authenticated contact binding, original forms/theme, owned settings, CSRF,
  persistent remember-me revocation, registration policy and recovery transport.
  The existing admin redirect is not an issuer-authentication implementation.
- `Clients::{index,announcements,announcement,dismiss_announcement,calendar,
  get_calendar_data,projects,project,download_all_project_files,files,upload_files,
  delete_file,remove_task_comment,edit_comment,tickets,change_ticket_status,
  open_ticket,ticket,proposals,contracts,invoices,statement,statement_pdf,
  estimates,company,profile,remove_profile_image,subscriptions,cancel_subscription,
  resume_subscription,gdpr,change_language,export,client_home_chart,
  contact_email_profile_unique}`: original routes and view DTOs must consume
  contact-authorized domain readers/writers, never an impersonated staff actor.
- `Clients::{update_credit_card,success_update_card,credit_card,delete_credit_card}`:
  the original provider/account contract also needs issuer-bound authorization;
  no global provider fallback or silent automatic financial mutation is valid.
- Admin `Clients::{export,delete,login_as_client,import,bulk_action}` still need
  complete scoped native semantics. Financial ZIPs, statements and linked
  proposal-email updates depend on their respective domain owners. Do not admit
  these methods by wildcard or use a partial export as a complete privacy ZIP.

`App_Controller`/autoload are centrally owned. Public candidate detection before
hydration must be separated from contact binding resolution after minimal session
loading. Selecting an issuer must not load global hooks, settings or providers,
use the staff selector as contact authority, or expose another issuer's identity.
The existing `NativePortalIdentity` and private attachment/announcement/domain
readers are reusable contracts, not evidence that all controllers or layouts
above are connected. MySQL, browser and full final-matrix validation remain open.

## Current endpoint reconciliation — 25 October 2026

The earlier public-route inventory above records the original gap, not current
completion. At checkpoint `94b212d4`:

- Authentication: all nine contact actions have explicit native branches and
  boundary tests; the separate `admin` login is not contact authentication.
- Verification: all three original actions have explicit native branches.
- Public Clients: 7 of 39 methods are connected and tested at explicit controller
  boundaries: `profile`, `remove_profile_image`, `company`, `announcements`,
  `announcement`, `dismiss_announcement`, `gdpr`. **None of the Clients controller
  is admitted as complete in the production portal gate.** The other 32 methods
  remain the finite list above minus these seven.
- Public Contacts is an additional existing consumer, not a new feature:
  `index`, `contact`, `delete`, `delete_profile_image`,
  `contact_email_profile_unique`. All five still require contact-bound adapters.
  `allow_primary_contact_to_manage_other_contacts` cannot be described as fully
  operational until those same original routes are connected and tested.
- The Clients settings compositor is checkpointed but depends on the real owned
  EstimateRequest catalog and controller wiring. Its two file flags also depend
  on the three original public Clients file actions; a stored option alone is not
  evidence of implemented behavior.

No method count includes helpers, schemas or a duplicate view. This reconciliation
is not an authorization to widen the portal gate or relax any resource ACL.

### Native file endpoint update

The original public `Clients::{files,upload_files,delete_file}` now have native
branches and real storage/controller/view tests; the finite count is **10 of 39**
connected boundary-tested methods, not production admission. The project and task
variants of `delete_file` use their existing contact writers, with the original
Clients deletion setting revalidated inside the transaction. The same native
Download key route is connected separately. See
[native portal files evidence](native-portal-customer-files.md) for exact tests,
mail/notification dependencies and integration limits. Public Contacts remains
five pending original actions. Do not interpret this checkpoint as the complete
public Clients experience or enable it by wildcard.
