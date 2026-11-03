# Isolated registry CI shards

The successful main 4.7.1 run [39996938632](https://github.com/wkarts/ARGWS-CRM/actions/runs/39996938632)
ran PHP 8.1 for 88m58s and PHP 8.5 concurrently for 80m14s. Its serial MySQL step
accounted for 87m14s and 78m36s respectively, not setup or one long silent timeout.
The invoice/PDF baseline reported completion 224 times per PHP version because
scenario scripts require other executable scenario suites. Those includes and
all lock/contention assertions remain unchanged.

## Coverage contract

The manifest is the exact argv inventory extracted from the long MySQL step at
`2da2dff1`, including additions in this PR: **472 commands (470 PHP + 2 Python)**.
The current manifest contains 470 PHP and 2 Python commands; 29 additional PHP
preflight/audit commands remain outside it. Contract tests freeze all 499 PHP argv
entries and their external shard1 conditions per version. The scheduling change
preserves the complete 472-command manifest and its digest. The previously retired
parallel-calendar controller stays recorded in historical evidence; its coverage
uses the original Settings/Utilities flows.

Five frozen cost-balanced shards receive 94–95 commands each, preserving relative
order within each shard. The same paired PHP 8.1/8.5 observations are replayed for
five capacities; the historical four-shard replay remains reproducible without
rewriting samples or ZIP receipts. See [timing baseline and limitations](ci-registry-balance.md).
Every command runs in a fresh process with its existing environment. Each matrix
job owns a container and MySQL service; no workers share a test database.
Intentional concurrency inside a scenario remains unchanged.

The core matrix allows five workers in total. The two focused PHP jobs execute
one at a time, preserving both checks and leaving the long phase at 20 workers:
core 5 + banking 2 + focused 1 + HTTP 11 + monitor 1.

Tests verify the original manifest digest, reproducible frozen assignments, exact union, no duplicate argv,
arguments (including `--tracking`), fail-fast behavior, and timing output.
When intentionally adding coverage, add its argv and update the inventory test;
never remove a command merely to reduce execution time. Do not cache test results
or mutable test databases. Jobs run afresh on both pull requests and main/develop
pushes, preserving the original branch/path filters and adding CI-tool paths.

## Required checks and evidence

The aggregate matrix retains the previous `registry (8.1)` / `registry (8.5)`
check names. Each requires success of the manifest preflight and **all ten**
PHP × shard jobs. A failure, cancellation, or skipped dependency cannot produce
a passing aggregate. Matrix fail-fast is disabled so independent diagnostics finish; any failure still rejects the aggregate.

Each executed command emits start/end events and a JSONL timing record containing
only committed argv, index, shard, duration and exit code. Per-shard artifacts are
uploaded even on failure (where available). No environment/credentials are logged.
The first new full run must establish actual shard balance and wall-clock savings;
94–95 commands each do not imply equal runtime or a guaranteed speedup.

### First completed registry measurement

[Run 40057616635](https://github.com/wkarts/ARGWS-CRM/actions/runs/40057616635)
at `c7bc0bd3eab1dada9f2adb6e39f32786095c41b1` completed successfully on
2026-10-24, from 03:16:18 to 03:43:07 UTC: **26m49s**. The requested main 4.7.1
baseline [39996938632](https://github.com/wkarts/ARGWS-CRM/actions/runs/39996938632)
ran from 17:40:01 to 19:09:06 UTC on 2026-10-23: **89m05s**. This observed
workflow wall-clock reduction is approximately **70%**, not a guaranteed future
duration or a controlled benchmark of the image alone.

All eight timing ZIP hashes matched GitHub artifact receipts. Their JSONL records
contain exactly 118 successful commands per shard, 472 per PHP version, with the
complete manifest argv/index union and no omissions or duplicates (944 executions).
The ten external PHP commands per version also passed. Command-duration sums:

| PHP | Shard 1 | Shard 2 | Shard 3 | Shard 4 |
| --- | ---: | ---: | ---: | ---: |
| 8.1 | 1159.838s | 1313.273s | 738.688s | 1112.623s |
| 8.5 | 973.455s | 1001.017s | 1432.896s | 1245.002s |

These sums exclude setup and queue time and must not be added as wall-clock time.
Both required aggregates passed. The separate Stacks HTTP/browser workflow later
failed an outdated Bootstrap stylesheet assertion; this registry measurement is
not evidence that the entire PR CI passed. Its artifact and failure remain preserved.

## Validation images (not application images)

The registry shards now consume immutable images built successfully in
[run 40033441190](https://github.com/wkarts/ARGWS-CRM/actions/runs/40033441190)
from source `b6c023be` and build-input fingerprint
`aba4c4a89d17021677d25778b11417413370cbd84318c63d79392221751b9885`:

- PHP 8.1: `ghcr.io/wkarts/argws-crm-ci-php@sha256:c6246e2740c225e7db4f0db2b8ba8657fb72cb9638efdedcfe1a8853fc081a14`
- PHP 8.5: `ghcr.io/wkarts/argws-crm-ci-php@sha256:d050af83a621363bdc79e5868f85d7203e8b5fb03be6cd37fbb7c41500894677`

Each of the ten jobs pulls the public package without registry credentials and
runs `argws-ci-smoke` with its expected PHP version before any suite. The smoke
checks extensions, GD JPEG, Python, Poppler, Git, Node and the paired browser.
No apt install or extension compilation remains in the consuming job. An image
pull failure fails the job; there is no authenticated or mutable-tag fallback.

Publishing the images is established by the build receipt above. Public visibility, anonymous pulls and consumer smoke for both versions are now
verified; exact jobs/timestamps are recorded in [validation-base.md](../ci/validation-base.md).
The subsequent full suite was cancelled after a separate UI overflow assertion;
its partial timings are not a completed validation duration.
Updating `docker/ci/**` triggers the original PR and push validations; new image
digests must be explicitly reviewed and updated after successful builds. No
production image, assertion, lock behavior, or database isolation is changed.
