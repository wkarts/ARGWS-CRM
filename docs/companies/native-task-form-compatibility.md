# Original task form: compatibility boundary (core 474)

This is an internal implementation checkpoint, not route admission or full native task parity.

The existing `CompanyTasks` and `CompanyStandaloneTasks` own `createNative` and
`updateNative`. They reuse their canonical insert/update, issuer lock, ACL,
receipt, priority, completion and financial-fence code. Their historical APIs
remain strict. Updating a newly declared native row through the old API is
rejected rather than leaving native attributes stale.

`NativeCompanyTaskFormSchema` evolves those same two tables: real nullable
assignee/deadline plus nullable `native_fields_json` and `native_fields_revision`.
It does not copy tasks, infer assignments, backfill financial intent, or create a
third task domain. Historical DDL definitions are unchanged. Core 474 must call
the helper; this checkpoint does not edit migration 474 or seed.

`testing` and `awaiting_feedback` are real distinct states. Existing catalog
transport IDs remain unchanged; appended source IDs 4 and 5 represent those
states. Native workflow numbering is not the transport source numbering.

## Consumer census

- CompanyTasks / CompanyStandaloneTasks: shared canonical writer and strict old
  API; actual NULL allowed only by explicit native input.
- CompanyTaskPriority / PriorityOperations / TimerTransitions: preserve actual
  NULL and exact frame comparisons; new metadata participates after declaration.
- CompanyTaskAutoStop: absence of assignee is not an invented user; current actor,
  company, project and financial fences remain mandatory. Optional deadline is
  compared only when it exists.
- CompanyTaskBillingStorage: explicit projection preserves NULL assignee.
- CompanyProjectFixedBillingStorage, CompanyTaskTimeBillingStorage,
  CompanyStandaloneBillingStorage, CompanyTaskTimeBatchStorage: additive NULL
  fields are omitted from historical snapshots. Positive historical IDs and
  snapshot bytes remain unchanged. Declared native fields remain in proofs.
- Whole standalone billing still requires a valid assigned actor as before;
  this change does not silently grant billing eligibility to an unassigned task.
- CompanyTaskCopies and CompanyTaskRecurring still use their reviewed historical
  input contract, which requires explicit assignee/deadline. Native modal copy
  and recurrence adapters remain unfinished, not silently delegated.
- CompanyTaskPusherNotice still requires an actual permitted recipient. No notice
  is generated for an invented assignee.

## Proof and remaining work

Real PHP 8.4 / PDO SQLite tests cover native project and standalone writers,
NULL fields, two distinct statuses, idempotency, changed-key payload, A/B,
revision conflict, metadata failure rollback, priority sidecars and timer
start/stop; historical snapshot/birth-hash preservation has a separate test.
The schema test checks final shape and replay. SQLite does not prove MySQL ALTER,
InnoDB concurrency, PHP 8.1/8.5 or authenticated browser flows.

The original `Tasks::task` modal/controller wiring is still in progress. Other
relations, recurrence/copy, attachments, tags/custom fields, milestones and
multi-assignee composition must use their actual owned writers; they cannot be
ignored or represented by fabricated empty data. Routes remain closed until the
full controller/template contract and HTTP/browser checks are established.

### Checklist template actions

The original `Tasks::save_checklist_item_template` and
`Tasks::remove_checklist_item_template` routes use the same controller, selectors
and shared JavaScript functions. Contextual deletion is POST-only with the native
request proof, reviewed row fingerprint and durable retry identity. Creation and
deletion check the existing `checklist_templates.create/delete` permissions in
addition to task visibility. Template descriptions are inserted into browser DOM
as text, not HTML.

`NativeCompanyTaskChecklistSchema` is an additive core-474 prerequisite containing
only the owned template catalog and action receipts. The proven primary company
continues to read/write its historical `tasks_checklist_templates`; secondary
companies use exact issuer-owned rows with namespace 50. No historical catalog is
copied, guessed or adopted. Unexpected owned primary rows fail visibly rather
than being discarded. A transaction verifies the write, audit nonce and receipt;
an ignored INSERT cannot reuse a previous insert ID.

Executed evidence: real PHP 8.4/PDO SQLite service and original-controller tests,
A/B/primary, create-only denial, stale deletion, idempotent replay, ignored INSERT
and receipt-failure rollback; shared-function Node tests cover transport, retries,
no automatic requests and hostile description text. MySQL, authenticated HTTP and
browser interaction remain separate gates. This does not complete Task modal GET,
selected-template-to-task composition, followers, recurrence or remaining task
actions; route admission must remain contingent on the complete composed graph.

### Original `Tasks::task` integration checkpoint

The original new-task GET now renders `admin/tasks/task` using real company
members, template catalog, explicit creation preferences, upload policy and date
format. The original POST uses `NativeCompanyTaskForm` and returns the existing
`success/id/message` shape. An empty browser upload (`UPLOAD_ERR_NO_FILE`) is not
mistaken for a real attachment. Authenticated context/CSRF remain mandatory.

This is an internal integration checkpoint, not completed route admission:
existing-task GET still refuses until its full historical projection is composed;
real uploads, nonempty followers, recurrence, milestones and selected templates
are not silently ignored and remain explicit pending errors. No existing task is
shown with invented boolean, dates, recurrence or attachment counts. The zero
counts in the new-task DTO describe a not-yet-created resource only. Principal
historical task writes are still pending. Keep `Tasks::task` closed in the route
manifest until these remaining original actions and browser acceptance pass.

`NativeCompanyTaskFormPreferences::read` is the single typed source for
`task_biillable_checked_on_creation`: exact company setting, public native default
`1` only when absent, explicit `0` preserved, present empty/invalid rejected.

### Existing owned task editor and explicit historical declaration

`Tasks::task/{canonical-id}` now composes the original edit modal for existing
owned tasks without changing identity. Declared attributes come from the exact
stored native field document. Older owned rows with a NULL document show the
original controls with an explicit historical notice: start date/rate stay blank,
boolean controls start indeterminate, and the user must resolve each and confirm
the declaration. The POST requires the explicit boolean declaration, existing
canonical revision and nullable field revision; it preserves known checklist,
assignee, deadline and lifecycle. No created-at/date or false/zero backfill occurs.
An edit-only role does not require create permission to read the editor.

This checkpoint still does not admit the entire Tasks graph. Existing recurrence
sources are detected and refused rather than displayed as nonrecurring; mapped
milestone mutation, terminal-date declaration, global historical `tasks` lineage,
attachments and remaining task actions need their full native composition. The
project-specific editor additionally depends on existing milestone/portal-rule
and native project metadata schemas. SQLite evidence here covers the actual
standalone canonical writer and original modal/controller, not MySQL or a live
browser. Financially reserved task edits remain refused by current fences.

Completion-date follow-up: an owned task whose actual lifecycle is already `done`
can explicitly declare or edit the original `datefinished` field. Unknown historic
completion timestamps remain blank/required rather than using creation time or
now. Strict company-local date/time parsing preserves 24-hour seconds and the
original 12-hour display. It does not mark an open task done or bypass checklist,
financial or revision checks. Real SQLite regression creates then completes the
historic source through the original canonical writer before exercising this
form; 123 cumulative checks pass. Recurrence and other open actions above remain
pending.

Follower field follow-up: original creation `followers[]` now participates in the
same existing canonical task transaction. Project tasks keep their existing
`company_task_followers` source. `NativeCompanyStandaloneFollowersSchema` adds the
missing equivalent source for existing standalone task IDs (core474 prerequisite,
no copied followers or inferred assignees). Each selected member must have current
company/task visibility, plus project visibility for a project task. Selection is
revalidated after later writes; a trigger revoking a follower aborts and rolls back
the full creation. Explicitly unchecked native selection overrides only that
native form's auto-follow suggestion; historical APIs retain their original
self-follow policy, as proven by the 224/156/331-check regression fixtures.
