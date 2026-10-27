# Native work routes — finite implementation inventory

Base: `abbf6c7`. This is an internal integration inventory, not a claim of completed native parity. No broad route admission is authorized by the existence of a service or DTO.

## Completed adapters / actual execution evidence

- Dashboard: four native actions have issuer-bound branches; primary invoices/ticket report/counters/payment lineage tested. Full composed dashboard remains pending auxiliary/calendar/catalog and widget AJAX completion.
- Staff: exactly three dashboard-preference actions adapted, with actor+issuer storage and native POST/CSRF.
- Tasks::get_staff_started_timers: real reader/policy/formatter, same native partial; controller + SQLite test passes. Header composition and mutator admission remain gated.
- Owned assignment schema/backfill and replace service: tested; not yet wired to every native controller action.
- Blank timer start/discard services: tested, journal/audit nonce/replay; assigning blank time to a task and native stop/edit/delete timesheet contracts remain pending.

## Explicit native controller actions still requiring an issuer adapter

### Projects (52 native methods; no added route family)

- `Projects::index` — connected to original manage view and explicit issuer/primary-lineage table factory; real controller/SQLite tests pass, full browser admission pending.
- `Projects::table` — connected to native DataTables rows/filter rules, exact tenant predicates and original row markup; controller/SQLite tests pass.
- `Projects::staff_projects` — original four-column DataTable endpoint connected to exact project memberships, explicit company formatter and source-aware links. Own dashboard and administrative staff ACLs are distinct; actual controller + SQLite composition passes. Primary retains its existing legacy route; authenticated HTTP/browser admission remains pending.
- `Projects::expenses` — pending native issuer adapter and route-level proof.
- `Projects::add_expense` — pending native issuer adapter and route-level proof.
- `Projects::project` — original GET form and basic create/update POST connected atomically (source/configuration/members/tags/custom fields/flags). Actual controller, original field helpers and SQLite pass. Legacy edits, estimate conversion, email dispatch, bulk task completion and recurring-task cancellation still require composition; route must not be broadly admitted.
- `Projects::gantt` — pending native issuer adapter and route-level proof.
- `Projects::view` — pending native issuer adapter and route-level proof.
- `Projects::mark_as` — pending native issuer adapter and route-level proof.
- `Projects::file` — pending native issuer adapter and route-level proof.
- `Projects::update_file_data` — pending native issuer adapter and route-level proof.
- `Projects::add_external_file` — pending native issuer adapter and route-level proof.
- `Projects::download_all_files` — pending native issuer adapter and route-level proof.
- `Projects::export_project_data` — pending native issuer adapter and route-level proof.
- `Projects::update_task_milestone` — pending native issuer adapter and route-level proof.
- `Projects::update_milestones_order` — pending native issuer adapter and route-level proof.
- `Projects::pin_action` — pending native issuer adapter and route-level proof.
- `Projects::add_edit_members` — pending native issuer adapter and route-level proof.
- `Projects::discussions` — pending native issuer adapter and route-level proof.
- `Projects::discussion` — pending native issuer adapter and route-level proof.
- `Projects::get_discussion_comments` — pending native issuer adapter and route-level proof.
- `Projects::add_discussion_comment` — pending native issuer adapter and route-level proof.
- `Projects::update_discussion_comment` — pending native issuer adapter and route-level proof.
- `Projects::delete_discussion_comment` — pending native issuer adapter and route-level proof.
- `Projects::delete_discussion` — pending native issuer adapter and route-level proof.
- `Projects::change_milestone_color` — pending native issuer adapter and route-level proof.
- `Projects::upload_file` — pending native issuer adapter and route-level proof.
- `Projects::change_file_visibility` — pending native issuer adapter and route-level proof.
- `Projects::change_activity_visibility` — pending native issuer adapter and route-level proof.
- `Projects::remove_file` — pending native issuer adapter and route-level proof.
- `Projects::milestones_kanban` — pending native issuer adapter and route-level proof.
- `Projects::milestones_kanban_load_more` — pending native issuer adapter and route-level proof.
- `Projects::milestones` — pending native issuer adapter and route-level proof.
- `Projects::milestone` — pending native issuer adapter and route-level proof.
- `Projects::delete_milestone` — pending native issuer adapter and route-level proof.
- `Projects::bulk_action_files` — pending native issuer adapter and route-level proof.
- `Projects::timesheets` — pending native issuer adapter and route-level proof.
- `Projects::timesheet` — pending native issuer adapter and route-level proof.
- `Projects::timesheet_task_assignees` — pending native issuer adapter and route-level proof.
- `Projects::remove_team_member` — pending native issuer adapter and route-level proof.
- `Projects::save_note` — pending native issuer adapter and route-level proof.
- `Projects::edit_note` — pending native issuer adapter and route-level proof.
- `Projects::delete_note` — pending native issuer adapter and route-level proof.
- `Projects::delete` — pending native issuer adapter and route-level proof.
- `Projects::copy` — pending native issuer adapter and route-level proof.
- `Projects::mass_stop_timers` — pending native issuer adapter and route-level proof.
- `Projects::get_pre_invoice_project_info` — pending native issuer adapter and route-level proof.
- `Projects::get_invoice_project_data` — pending native issuer adapter and route-level proof.
- `Projects::get_rel_project_data` — original scoped task-form selector admitted for GET only; tests exercise source and issuer ownership and POST rejection at a controller seam. Authenticated HTTP/browser evidence remains pending; the project and task forms are not admitted by this change.
- `Projects::invoice_project` — pending native issuer adapter and route-level proof.
- `Projects::view_project_as_client` — pending native issuer adapter and route-level proof.
- `Projects::get_staff_names_for_mentions` — pending native issuer adapter and route-level proof.

### Tasks (58)

- `Tasks::index` — pending native issuer adapter and route-level proof.
- `Tasks::list_tasks` — pending native issuer adapter and route-level proof.
- `Tasks::table` — pending native issuer adapter and route-level proof.
- `Tasks::kanban` — pending native issuer adapter and route-level proof.
- `Tasks::ajax_search_assign_task_to_timer` — the existing scoped adapter is admitted for POST only. It requires the native company context and CSRF, resolves current actor assignments and source lineage, and excludes ineligible or cross-company candidates. The existing controller/SQLite fixture exercises these boundaries with a context double; authenticated HTTP, browser interaction and final-SHA evidence remain pending. This admission does not open the remaining task actions.
- `Tasks::tasks_kanban_load_more` — pending native issuer adapter and route-level proof.
- `Tasks::update_order` — pending native issuer adapter and route-level proof.
- `Tasks::switch_kanban` — pending native issuer adapter and route-level proof.
- `Tasks::get_billable_tasks_by_project` — the original read-only GET endpoint is admitted to the existing issuer-bound selection adapter. The controller requires invoice view plus create or edit; the reader also checks task/customer/invoice views, source ownership and live snapshots. Controller/SQLite tests cover empty and denied selections, and the reader suite covers populated results; authenticated HTTP/browser and final-SHA proof remain pending. Selection does not authorize invoicing.
- `Tasks::get_billable_tasks_by_customer_id` — same finite GET admission and selection-only guard; foreign customers and stale or unsupported lineage are denied. Authenticated HTTP/browser and final-SHA proof remain pending.
- `Tasks::update_task_description` — pending native issuer adapter and route-level proof.
- `Tasks::detailed_overview` — pending native issuer adapter and route-level proof.
- `Tasks::init_relation_tasks` — pending native issuer adapter and route-level proof.
- `Tasks::task` — pending native issuer adapter and route-level proof.
- `Tasks::copy` — pending native issuer adapter and route-level proof.
- `Tasks::get_billable_task_data` — pending native issuer adapter and route-level proof.
- `Tasks::get_task_data` — pending native issuer adapter and route-level proof.
- `Tasks::add_reminder` — pending native issuer adapter and route-level proof.
- `Tasks::edit_reminder` — pending native issuer adapter and route-level proof.
- `Tasks::delete_reminder` — pending native issuer adapter and route-level proof.
- `Tasks::save_checklist_item_template` — pending native issuer adapter and route-level proof.
- `Tasks::remove_checklist_item_template` — pending native issuer adapter and route-level proof.
- `Tasks::init_checklist_items` — pending native issuer adapter and route-level proof.
- `Tasks::task_tracking_stats` — original scoped task statistics admitted for GET only; tests exercise issuer ownership, historical lineage and POST rejection at a controller seam. Authenticated HTTP/browser evidence remains pending; this does not admit task navigation or editing.
- `Tasks::checkbox_action` — pending native issuer adapter and route-level proof.
- `Tasks::add_checklist_item` — pending native issuer adapter and route-level proof.
- `Tasks::update_checklist_order` — pending native issuer adapter and route-level proof.
- `Tasks::delete_checklist_item` — pending native issuer adapter and route-level proof.
- `Tasks::update_checklist_item` — pending native issuer adapter and route-level proof.
- `Tasks::make_public` — pending native issuer adapter and route-level proof.
- `Tasks::add_external_attachment` — pending native issuer adapter and route-level proof.
- `Tasks::add_task_comment` — pending native issuer adapter and route-level proof.
- `Tasks::download_files` — pending native issuer adapter and route-level proof.
- `Tasks::add_task_followers` — pending native issuer adapter and route-level proof.
- `Tasks::add_task_assignees` — pending native issuer adapter and route-level proof.
- `Tasks::edit_comment` — pending native issuer adapter and route-level proof.
- `Tasks::remove_comment` — pending native issuer adapter and route-level proof.
- `Tasks::remove_assignee` — pending native issuer adapter and route-level proof.
- `Tasks::remove_follower` — pending native issuer adapter and route-level proof.
- `Tasks::unmark_complete` — pending native issuer adapter and route-level proof.
- `Tasks::mark_as` — pending native issuer adapter and route-level proof.
- `Tasks::change_priority` — pending native issuer adapter and route-level proof.
- `Tasks::change_milestone` — pending native issuer adapter and route-level proof.
- `Tasks::task_single_inline_update` — pending native issuer adapter and route-level proof.
- `Tasks::delete_task` — pending native issuer adapter and route-level proof.
- `Tasks::remove_task_attachment` — pending native issuer adapter and route-level proof.
- `Tasks::upload_file` — pending native issuer adapter and route-level proof.
- `Tasks::timer_tracking` — pending native issuer adapter and route-level proof.
- `Tasks::delete_user_unfinished_timesheet` — pending native issuer adapter and route-level proof.
- `Tasks::delete_timesheet` — pending native issuer adapter and route-level proof.
- `Tasks::update_timesheet` — pending native issuer adapter and route-level proof.
- `Tasks::log_time` — pending native issuer adapter and route-level proof.
- `Tasks::update_tags` — pending native issuer adapter and route-level proof.
- `Tasks::bulk_action` — pending native issuer adapter and route-level proof.
- `Tasks::gantt_date_update` — pending native issuer adapter and route-level proof.
- `Tasks::get_task_by_id` — pending native issuer adapter and route-level proof.
- `Tasks::get_staff_names_for_mentions` — pending native issuer adapter and route-level proof.
- `Tasks::save_checklist_assigned_staff` — pending native issuer adapter and route-level proof.

### Tickets (27)

- `Tickets::index` — pending native issuer adapter and route-level proof.
- `Tickets::add` — pending native issuer adapter and route-level proof.
- `Tickets::delete` — pending native issuer adapter and route-level proof.
- `Tickets::delete_attachment` — pending native issuer adapter and route-level proof.
- `Tickets::update_staff_replying` — pending native issuer adapter and route-level proof.
- `Tickets::check_staff_replying` — pending native issuer adapter and route-level proof.
- `Tickets::ticket` — pending native issuer adapter and route-level proof.
- `Tickets::edit_message` — pending native issuer adapter and route-level proof.
- `Tickets::delete_ticket_reply` — pending native issuer adapter and route-level proof.
- `Tickets::change_status_ajax` — pending native issuer adapter and route-level proof.
- `Tickets::update_single_ticket_settings` — pending native issuer adapter and route-level proof.
- `Tickets::priorities` — pending native issuer adapter and route-level proof.
- `Tickets::priority` — pending native issuer adapter and route-level proof.
- `Tickets::delete_priority` — pending native issuer adapter and route-level proof.
- `Tickets::predefined_replies` — pending native issuer adapter and route-level proof.
- `Tickets::get_predefined_reply_ajax` — pending native issuer adapter and route-level proof.
- `Tickets::ticket_change_data` — pending native issuer adapter and route-level proof.
- `Tickets::predefined_reply` — pending native issuer adapter and route-level proof.
- `Tickets::delete_predefined_reply` — pending native issuer adapter and route-level proof.
- `Tickets::statuses` — pending native issuer adapter and route-level proof.
- `Tickets::status` — pending native issuer adapter and route-level proof.
- `Tickets::delete_ticket_status` — pending native issuer adapter and route-level proof.
- `Tickets::services` — pending native issuer adapter and route-level proof.
- `Tickets::service` — pending native issuer adapter and route-level proof.
- `Tickets::delete_service` — pending native issuer adapter and route-level proof.
- `Tickets::block_sender` — pending native issuer adapter and route-level proof.
- `Tickets::bulk_action` — pending native issuer adapter and route-level proof.

## Dependency contracts, without alternate UI

- Listing/table/kanban/view: reuse existing partials and data-table contracts; canonical transport IDs separate historical and owned storage. Resolve ACL and customer boundary before hydration. Principal legacy + owned union must retain lineage.
- Creation/edit/status/copy: canonical writers plus native status/catalog/membership parity. Current project/task/ticket state enums are narrower than the native UI; do not relabel resolved as answered or accept unsupported states.
- Files/download/export/editor: same native controls, issuer-bound filesystem/connector and ownership verification. Central owns Media/elFinder; shared editor opens `misc/tinymce_file_browser`. No global file-picker admission.
- Timer actions: native auto-stop OFF permits multiple assigned timers, but historical CompanyTaskAutoStop/Transitions currently enforce one per actor. A separate explicit native operation must implement the requested semantics with all billing fences and cross-company exclusions, without silently changing historical APIs.
- Notes/reminders: coordinate the auxiliary shared component and native resource resolver; do not hydrate global notes/reminders.
- Project invoicing/billable tasks: use CompanyInvoices/NativeCompanyInvoiceRead owned relation and scope contracts; do not write raw owned IDs into native `tblinvoices.project_id`.
- Portal/publication actions: use existing company portal rules, contact permission and immutable task authorship; no fallback global portal/session.
- Catalog actions: statuses/departments/priorities/services/tags require owned storage and shared native helpers. Work-tag schema/readers are currently WIP, not yet integrated. No schema request implies controller completion.

## Contact deletion/revocation

Projects/tickets reference clients; native legacy tickets also reference contact IDs. Existing portal task origin/comment/file journals and project-contact-file receipts preserve contact IDs. Contact revocation must deny future portal access without rewriting authorship/history. Hard deletion must reject referenced contacts or use an explicitly supported archival contract; do not cascade-delete project/ticket/financial evidence or infer reassignment.

## Still not proven

No MySQL/InnoDB concurrency, PHP 8.1/8.5 matrix, authenticated HTTP, full native modal/browser or responsive screenshot validation for this new composition. PHP 8.4/SQLite and pure Node results do not substitute for those gates.

## Current 2026-10-25 checkpoint boundaries

- `ff401040` + `4e538163`: original Projects create/edit form composition; 212 cumulative real PHP 8.4/SQLite checks. The HTTP request/context/sanitizer/custom-field-renderer boundaries are test doubles; original controller, form partial, field helpers and storage services execute. Rendered JavaScript passes Node syntax, not a browser proof.
- `fb38b314`: project timers are projected from their actual lineage and persisted durations; original HoursOverviewChart is reused. 217 cumulative checks, plus eight existing header-timer regression checks. Billable/billed classification is required explicitly and is never inferred from status.
- `3f6ba175`: verified billing status may be read with explicit view grants in all four financial domains. Existing mutating APIs retain create/edit gates; the exact same proof closure supplies both paths. No DML or operation capabilities are returned by read-only projection.
- `6ef9c7f0`: already-declared task quote candidates are offered only after current source/context/rate/fence/receipt checks. This does not create quotes and is not evidence of complete native task price configuration.

### Required before the original project detail route can be admitted

1. Compose original `view.php`, overview and each existing tab with real owned/legacy-lineage readers; preserve all native action URLs.
2. Aggregate financial time and expenses with confirmed allocation/quote evidence and one currency at a time. CompanyExpenses already has an explicit owned project relationship; no new relation may be inferred by ID coincidence.
3. Complete native task `hourly_rate`/`billable` configuration where not represented by an existing immutable quote; old task status is not a price or eligibility declaration.
4. Connect member edits, milestones, discussions, attachments, notes, copy/delete/export, timers and billing selectors to their existing authoritative services under native POST/CSRF/revision contracts.
5. Finish actual primary legacy edit path and visibility rules without promoting or copying legacy records.
6. Run composed MySQL concurrency, authenticated HTTP and real browser desktop/tablet/mobile proofs. SQLite execution does not establish InnoDB or browser behavior.

Tickets/departments are now owned by the presentation worker. This branch must not overwrite its controller/service changes. Staff ownership here remains exactly the three dashboard-preference methods, not all Staff administration.
