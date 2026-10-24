# Temporary portal-chain pilot (not a semantic-equivalence certificate)

## Wiring and scope

`serial` preserves the complete original pipeline and all100 top-level Node invocations.
`portal-pilot` is independent and parallel: its own product build, MySQL service,
network, application containers and filesystem execute the identical genuine
install/upgrade and prefix through contract discussions, then the same six portal
scripts in the same order. No SQL snapshot or shared mutable cache is used.
The existing required job name `validate` aggregates both jobs and comparison:
failure, cancellation, skip, missing artifact or comparison failure blocks it.
The image is built locally for disposable tests, not published.

The six invocations moved verbatim into `tests/ci/portal_chain.sh`. Environment,
assertions, includes, browser waits and ACL/CSRF checks remain unchanged. The
standalone default is `serial`; only the explicit `portal-pilot` mode stops after
the six-script chain. Unknown modes fail closed. This duplication is temporary,
not a permanent replacement for optimization.

## Evidence and limits

Before/after snapshots use read-only PDO transactions against the strictly
allowlisted disposable database. All table rows are fingerprinted, including BLOBs;
no row values are exported. Column/index/key/reference/CHECK/view/trigger metadata
is fingerprinted without cardinality or the mutable AUTO_INCREMENT counter.
Schema metadata must remain unchanged within each job. Source revision, prefix,
chain/helpers, PHP/MySQL versions and schema fingerprints must agree across jobs.

Four known-populated sentinels (installation identity, invoices, creditnotes,
native payment records) must be byte-preserved. The prefix proves these are real:
`companies_invoices_browser.cjs:89,103` checks invoice value and one native payment;
`companies_credits_browser.cjs:14,22,24` checks issued/voided credit rows and retained
payment rows through zero-remaining reversal. Immediately before the portal block,
`companies_ledger_browser.cjs:19,21` checks a positive payment ID and13.00 remaining;
its fixture additionally creates101 canonical invoices/events. The subsequent
operations/support/discussion cases do not delete these financial rows. All installed company credit,
payment, financial, expense, invoice and bank-receipt tables must also be preserved,
even if empty. Files under uploads and generated config are recorded with opaque
path hashes, byte hashes, size, mode, owner and group; preexisting files must stay
unchanged. Project file bytes also live in database BLOBs (`company_project_files`).

Coverage requires six ordered successful completion records, with per-scenario
and total elapsed seconds. Cross-job comparison checks source/runtime/schema,
populated sentinel counts, table count/delta/changed footprints and filesystem
counts. `tblsessions`, `tblactivity_log` and `tbluser_auto_login` counts are explicitly
reported as observations, not equality: independent authentication runs may create
or expire different sessions/logging rows. No existing audit assertion is removed.

**FOOTPRINT_MATCH is not semantic equivalence.** Different owner/amount values in a
changed table can produce the same footprint; different new-file bytes can produce
the same file count. Negative-contract examples explicitly demonstrate this
limitation. Result always states `semantic_equivalence_proven: false` and
`serial_removal_authorized: false`. Unchanged fingerprints are checked within each
job; timestamps, random IDs, tokens and password salts are not normalized globally.
Transient temp/log/cache files, module upload roots, host artifacts and proxy event
streams are not exhaustively fingerprinted by this pilot. They are not restored or
shared; existing serial checks retain their coverage. Thus this census cannot be
used as a general snapshot/restore mechanism.

## Exit criterion and removal of duplicate cost

After the first paired run, review its actual write set. Before promoting a split:

1. establish an explicit expected-write allowlist and byte preservation for every
   other table/file, with populated non-owned/financial sentinels;
2. prove expected changed rows semantically: owners, relationships, revisions,
   permissions, flags and exact file content—not counts alone;
3. execute downstream cases with and without the chain and retain identical
   positive, negative, preservation and lock assertions;
4. keep all six scripts in their own isolated job, retain the aggregate required
   gate, and only then remove the duplicated serial block/pilot scaffolding;
5. compare measured critical-path time, including bootstrap and artifact overhead.

Until then the complete serial run remains mandatory. No speedup is claimed from
this temporary validation configuration. The historical block measured about10min;
that is a target, not a measured saving.

## Validation and rollback

Local Python tests exercise omission/drift rejection, nonempty preservation,
shared-chain placement, default serial mode and aggregate fail-closed wiring.
The environment contract still executes each command's real environment block with
an intercepted Node executable. PHP/Docker/browser execution is unavailable locally;
paired CI is required before any correctness claim.

Rollback is reverting this test/workflow commit; no production schema, module,
application source or deployment changes exist. Core version/inventories remain the
integration coordinator's responsibility. No publication is performed by this patch.
