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
The older main run had **479 PHP test invocations** across all steps. This PR
adds `module_version_column_mysql.php`, yielding **480 PHP invocations**: the
manifest's 470 PHP commands plus **6 preflight + 4 legacy-audit** commands outside
it. The 472-command manifest also includes 2 Python checks, hence the different
counts. Tests freeze the ten external argv entries and their shard-1 conditions,
proving each runs once per PHP version. No old main PHP invocation was removed.
Preflight and the legacy audit's exclusive database remain in the workflow.

Four round-robin shards receive 118 commands each, preserving relative order
within each shard. Every command still runs in a fresh process with the existing
environment and repository working directory. Each matrix job has its own
container and MySQL service; no concurrent workers share a test database between
shards. A scenario's own intentional concurrency is unchanged.

Tests verify the original manifest digest, exact union, no duplicate argv,
arguments (including `--tracking`), fail-fast behavior, and timing output.
When intentionally adding coverage, add its argv and update the inventory test;
never remove a command merely to reduce execution time. Do not cache test results
or mutable test databases. Jobs run afresh on both pull requests and main/develop
pushes, preserving the original branch/path filters and adding CI-tool paths.

## Required checks and evidence

The aggregate matrix retains the previous `registry (8.1)` / `registry (8.5)`
check names. Each requires success of the manifest preflight and **all eight**
PHP × shard jobs. A failure, cancellation, or skipped dependency cannot produce
a passing aggregate. Matrix fail-fast remains enabled.

Each executed command emits start/end events and a JSONL timing record containing
only committed argv, index, shard, duration and exit code. Per-shard artifacts are
uploaded even on failure (where available). No environment/credentials are logged.
The first new full run must establish actual shard balance and wall-clock savings;
118 commands each does not imply equal runtime or a guaranteed fourfold speedup.

Future pinned CI images can replace setup after their build and digest are
verified. This change keeps the existing PHP images and extension setup and does
not publish an image, weaken assertions, or alter production behavior.
