# Native customer/contact work — paused continuity checkpoint

This is a local WIP checkpoint, not a completed native CRM migration or a new
published PR. The user requested a handoff; no further implementation should be
inferred from the presence of this checkpoint.

## Already handed to the integrator

`d36faa97`: original public Clients files/upload/delete, private native file
storage, original templates, contact notifications and durable upload-mail
outbox. Dependencies supplied separately: `2dd4d645`, `9e008ceb`, `487e5150`,
`9a7ec121`. The integrator confirmed composition and 474 schema wiring.

## New unintegrated WIP in this checkpoint

- `Contacts::{index,contact,delete,delete_profile_image,contact_email_profile_unique}`
  now dispatch to `NativePortalContactsHttp` and the **same original** contact/list
  views. No route alias or new parallel customer page.
- `NativePortalContacts` requires a real active primary-contact binding, the
  selected issuer's original management option, and the exact same client.
  No staff impersonation. Create uses configured default permissions; edit
  preserves permissions and inactive state. Private mutation attestation protects
  custom fields and avatars; clone/serialization are rejected.
- Contact creation/edit/delete, custom fields and audit are transactional. Deletion
  retains referenced financial/project/file/contact history. Principal NULL-owner
  clients are resolved only through persisted installation identity; no adoption.
- Managed welcome/setup mail extends the existing native contact queue with a
  closed `managed` source. The initiating primary contact, option, target, receipt,
  token and transport are revalidated. No password in email; unknown SMTP results
  cannot retry. `CompanyPortalMailDispatchSource` has an explicit managed purpose.
- Separate App_Email dependency **`9db03f31`**, owned by the auxiliary worker,
  changes only `isMessage || isManaged`. Do not omit it or duplicate prior hooks.
- Avatar storage records the actual primary contact who changed another contact's
  image, not the target or a fake staff actor. The 474 helper upgrades the exact
  earlier self-avatar constraint, preserves bytes/revisions/proven authors, and
  adds a bounded named actor FK. Existing staff-only predecessor handling remains.
  No seed or orchestration migration file changed here.

## Evidence and explicit limits

Executed PHP 8.4.26 with real SQLite/GD and captured final SMTP, not external mail:

- `native_portal_contacts.php`: 10 authority/read + 10 mutation/rollback +
  4 deletion/retention + 3 persisted-principal compatibility checks.
- `native_portal_contacts_avatar.php`: 8 actual image/author/revocation/physical FK
  and exact intermediate-schema upgrade/replay checks.
- `native_portal_contacts_controller.php`: 10 actual controller/original-template
  checks plus 3 creation checks. The finite public boundary is explicitly set by
  the fixture; this is not a full production bootstrap proof.
- `native_portal_contacts_mail.php`: 7 source/queue/fence checks, in addition to
  17 existing contact-mail checks and the inherited 135-check mail suite.
- `native_portal_contacts_mail_worker.php`: 14 actual App_Email checks with both
  engines, header stripping, manager revocation, unknown/no-retry and frozen
  technical receipt after revocation. Auxiliary App_Email and its existing
  dependencies were temporary test overlays and restored; not duplicated here.
- Existing native profile/controller/avatar and staff contact-image regressions
  were rerun. Changed PHP lint and whitespace checks are recorded in the handoff.
- The actual rendered contact JavaScript was parsed by Node. This is syntax proof,
  **not** browser behavior, screenshots, accessibility or responsive validation.

`native_portal_contact_image_mysql.php` is written/linted but **not executed**.
It covers exact prior-474 upgrade/replay, retained bytes, distinct actor FK and
missing-FK rejection. MySQL/MariaDB, PHP 8.1/8.5, complete CI, actual multipart HTTP,
concurrency, OAuth refresh after managed-authority revocation, and full browser
navigation remain necessary. The managed-contact mail lifecycle callback is a
labelled seam in the pure CRUD fixture; the separate queue/worker fixtures use the
actual source and dispatch path.

The integrator owns full bootstrap/route admission and `ClientsController` layout.
The local layout list did not yet include `files`, `contacts`, `contact`; this was
reported before handoff. Do not assume controller/view boundary tests prove that
list or the real constructor. No partial public controller admission is approved.

## Exact public endpoint inventory (excluding constructors/helpers)

### Authentication — 9 contact actions connected

`index`, `login`, `register`, `forgot_password`, `reset_password`, `logout`,
`contact_email_exists`, `recaptcha`, `change_language`.
The separate `admin` compatibility redirect is not contact authentication.
Verification: `index`, `verify`, `resend` are connected (3/3).

### Clients — 10 of 39 connected at tested boundaries

`profile`, `remove_profile_image`, `company`, `announcements`, `announcement`,
`dismiss_announcement`, `gdpr`, `files`, `upload_files`, `delete_file`.

Still not connected (29): `index`, `calendar`, `get_calendar_data`, `projects`,
`project`, `download_all_project_files`, `remove_task_comment`, `edit_comment`,
`tickets`, `change_ticket_status`, `proposals`, `open_ticket`, `ticket`, `contracts`,
`invoices`, `statement`, `statement_pdf`, `estimates`, `update_credit_card`,
`success_update_card`, `credit_card`, `delete_credit_card`, `subscriptions`,
`cancel_subscription`, `resume_subscription`, `change_language`, `export`,
`client_home_chart`, `contact_email_profile_unique`.

Contacts is separate: the five original methods are connected in this WIP, with
the test/production-bootstrap limitations above. None of these counts includes
helper classes, schemas or duplicated UI, and none authorizes wildcard admission.

## Admin Clients: source inventory, not an assertion of full parity

46 public methods excluding constructor were inspected. 33 have explicit native
branches; this count alone does not prove every tab, linked domain or final CI.
The 13 methods without the native dispatch in this worktree are:
`export`, `update_all_proposal_emails_linked_to_customer`, `delete`,
`login_as_client`, `zip_credit_notes`, `zip_invoices`, `zip_estimates`,
`zip_payments`, `import`, `bulk_action`, `statement_pdf`, `send_statement`,
`statement`.

A complete GDPR ZIP must use actual authorized exporters for invoices/projects/
contracts/tickets and cannot silently omit these domains. Customer assignment
never grants financial write authority. Public domain readers must use contact
binding/publication, not staff DTOs or email-based identity inference.

## Integration rules

Preserve the integrator's current HEAD and concurrent custom-field/helper changes.
Apply only this delta, not whole service copies from an older worktree. The
integrator owns version/474 orchestration and the final single draft PR. Reconcile
actual remote state before any authorized publication. No force push, merge,
deploy, seed edit, provider activation, credential generation or production data
mutation is authorized by this handoff. Do not bypass any publication restriction.

The real global goal remains removal of the parallel application after all native
routes and domains are safe and tested. A partial list, hidden route, disabled
menu, copied lookalike view, or weakened issuer guard does not satisfy that goal.
