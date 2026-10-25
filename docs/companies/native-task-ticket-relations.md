# Native task / ticket relation composition

The existing `company_standalone_tasks.native_fields_json` stores the explicit relation source and ID. This is not a third task domain and does not adopt historical `tasks.rel_id` by numeric coincidence.

The original task form now supports ticket-related creation and editing. The existing canonical writer owns the transaction and request receipt. Ticket reader tables are enlisted, current ticket/customer/department authority is checked before related task data, and the parent proof is checked again after audit. The same boundary covers resource references, header timers, canonical standalone get/page, mutation frames, replay, and native timer eligibility. Project tasks keep their existing project-parent contract.

Real local PHP/SQLite checks cover the original GET/modal, creation and edit payloads, source identity, durable replay, cross-company refusal, pre-label reads, post-audit revocation rollback and historical standalone regression. Framework request/context/render dependencies are explicit test doubles; this is not authenticated HTTP, browser or production MySQL evidence. The ticket fixture uses production column definitions but does not prove production DDL constraints.

Not yet completed: related-tasks table/filter wiring, native single-task detail/modal graph, and `ticket_to_task` attachment copying. The conversion flag must not be silently treated as a plain relation: the original native operation also copies ticket attachments. No broad route admission is justified by this checkpoint alone.

## Original related DataTable composition

`Tasks::init_relation_tasks` now composes the same nine-column row renderer and
`init_relation_tasks_table`/`admin/tasks/filters`, with the exact `related_tasks`
identifier and `tasks_relations` view. The original `init_rel_tasks_table` JS is
shared byte-for-byte, not copied into a second UI. Requests retain the canonical
parent URL, pagination, search, scoped saved rules and task order preference.
Candidate enumeration is bounded at 10,000 and fails explicitly above this limit;
it never reports a truncated count as the complete set. Parent, task, field,
assignment, recurrence and rendering snapshots are revalidated.

The positive path in this checkpoint is a named-company ticket with canonical
standalone tasks. Raw principal ticket relations still require their historical
projection and remain explicitly unadmitted. `ticket_to_task` attachment copying,
`single_task` detail and all adjacent original mutation actions are **not** proven
complete by this table change. Partial or in-flight billing is not mapped to a
false unbilled state and cannot enable a new timer. Route admission remains with
the central integrator after the full action graph is tested.

Executed PHP 8.4/SQLite: `native_task_related_controller.php` 139 cumulative checks,
`native_task_related_helper.php` 139 cumulative checks (real preference store),
and `native_task_related_filters.php` 136 cumulative checks. These counts overlap
shared fixtures; they are not additive independent cases. Actual authenticated
HTTP, MySQL and browser acceptance remain separate and were not run here.
