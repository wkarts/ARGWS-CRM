# Timing-based registry allocation — proposed isolated follow-up

This patch is prepared separately from the active PR-validation SHA. Its replay
estimates are **not measurements of a new run**, and it must not replace a running
validation merely to claim an improvement.

## Evidence

Source revision: `c7bc0bd3`. Eight original `company-registry-timings-php-*-shard-*.zip`
artifacts contain 944 successful command records: all 472 argv entries in both
PHP 8.1 and PHP 8.5. `company-registry-timing-baseline.json` records every elapsed
millisecond, original ZIP/JSONL SHA-256, and original shard total. Import validation
checked index, argv, exit code, original assignment and totals against
`/tmp/crm-final-timings.json`. No credentials, test output or account data is copied.

The command manifest is unchanged: 470 PHP + 2 Python commands. The six PHP
preflight and four PHP legacy-audit commands still execute once per PHP version
outside this manifest, on shard 1. All eight independent DB/container jobs and
fail-closed compatibility aggregate gates remain unchanged.

## Deterministic allocation

For each command use the integer geometric mean of its two elapsed milliseconds:
`isqrt(max(1, php81_ms) * max(1, php85_ms))`. The floor is only for weights, not a
test delay or execution timeout. It keeps one rounded zero from erasing its pair.

Assign commands in descending weight order (ties by original manifest index) to
the least estimated-load shard with capacity remaining. Ties use command count,
then shard number. Capacity is 118 commands per shard. The resulting assignment
is committed, identical for both PHP versions, and coupled to the exact manifest
hash. The runner executes each shard in **original manifest order**, not weight
order; no argv, fixture, assertion, include, lock wait or test subprocess changes.
A missing/stale/malformed plan fails rather than falling back or skipping tests.

The geometric mean limits the influence of one multiplicatively slow observation
relative to an arithmetic maximum, while retaining both versions. It is not
statistically robust outlier rejection with only two samples. We deliberately do
not infer per-runner correction factors from unrelated commands or promise that
PHP itself caused the difference. Queue, VM load, filesystem and DB cache variation
remain confounders. There is no per-PHP schedule tuned to one favorable runner.

## Comparison: command sums only

| PHP | Observed round-robin shard seconds | Estimated replay of frozen balanced plan | Observed / estimated maximum |
|---|---|---|---|
| 8.1 | 1159.838 / 1313.273 / 738.688 / 1112.623 | 1076.416 / 1090.051 / 1077.735 / 1080.220 | 1313.273 / 1090.051 (-17.0%) |
| 8.5 | 973.455 / 1001.017 / 1432.896 / 1245.002 | 1170.506 / 1144.818 / 1165.540 / 1171.506 | 1432.896 / 1171.506 (-18.2%) |

Across both versions the largest command sum would fall from 23m52.896s to
19m31.506s under unchanged per-command runtimes: **4m21.390s estimated**, not
measured. Setup, queue, pulls, smoke, preflight, external audit and upload are not
included. Shard 1's extra checks can affect the true critical path. Total work and
summed command duration are unchanged. Fewer GitHub concurrency slots can also
reduce or erase wall-clock benefit.

A sensitivity replay using only 8.1 weights gives an 8.5 maximum of 1236.258s;
using only 8.5 weights gives an 8.1 maximum of 1089.115s. These same-run cross-version
checks indicate how schedule estimates depend on the chosen samples; they are
**not independent validation**. The next authorized full run must measure all eight
jobs before claiming improvement, and multiple runs should precede further tuning.

## Reproduce / verify

```sh
python3 scripts/ci/plan-company-registry-shards.py --check
python3 scripts/ci/plan-company-registry-shards.py --check --verify-artifacts /path/to/attachments
python3 -m unittest discover -s tests/ci -p test_company_registry_shards.py
```

The optional evidence directory has the original `*/company-registry-timings*.zip`
layout. No network or provider action is used. To intentionally update the plan,
review new complete successful paired evidence and run the planner without
`--check`; never silently accept timings from a failed/partial run.

## Repeated setup: analysis only in this patch

The older successful 89-minute run showed executable invoice/PDF baselines repeated
224 times per PHP. Examples include `asaas_remote_concurrency.php` requiring
`asaas_remote_operations.php` → `asaas_positive.php` → `invoices.php`, and chained
native migration scenarios. Allocation does not remove that work.

Reducing it safely is a separate refactor: extract pure state-building helpers
from executable scenarios, run the baseline assertions as explicit retained tests,
and prove identical initial schema/data/revisions plus failure/rollback and cleanup
behavior for each consumer. Do not remove includes blindly, reuse a mutable DB
snapshot across jobs, skip baseline assertions, lower password costs or shorten
intentional lock waits. No such scenario/fixture change is included here.
# Frozen plan holdout replay

Run: 40061938353; revision: 846af95c235e5f8a32493f64eadf2dfaedb60c42
Frozen plan: 777a1f6a; SHA-256: 1a4f54a480af305d6828192a52378796487d387c4fc0890ee9935c56c4411b92

All 8 shards and 2 aggregate checks completed successfully. All 944 timing records match manifest argv and have exit code 0. All 8 ZIP hashes match GitHub artifact digests. No assignments or baseline were changed.

| PHP | Observed max command sum | Frozen-plan replay max | Estimated difference |
|---|---:|---:|---:|
| 8.1 | 1660.370s | 1404.235s | -15.43% |
| 8.5 | 1410.109s | 1342.425s | -4.80% |

Independent sample from training, but still a counterfactual replay rather than execution of the balanced plan. Does not measure cache/order interactions or account for setup/queue/preflight/audit/uploads. Real workflow duration was reported as 30m32s; no balanced workflow duration is claimed. PHP8.5 improvement is smaller than in training; no material regression observed in this replay.

Artifact IDs and verified SHA-256 values:
- PHP 8.5 shard 4: 12548725357 / 8fd50a4e4ae7bb8ef809a91a78dab4101314ce9c81b9dc3322754fa2b266d103
- PHP 8.1 shard 1: 12548695511 / a74544fec66befd0e5e9ea6fe124e4ba127adf693e21b00a27fd0721dd7a3364
- PHP 8.5 shard 2: 12548487257 / d102271d9319e864b8a044ad7984b3b2e10c4aa1aba0322bd046eaadc5f47316
- PHP 8.1 shard 2: 12548331879 / ec558c899691f117cbd1c748ae4b5554f6a5eb2fee9cd333ba661877a216f497
- PHP 8.5 shard 3: 12548298304 / 979af8430094c1e53c23a6f71296e28ee37bc49fdf936aca1dc953a080b63048
- PHP 8.1 shard 4: 12548194165 / b9605ea81984f8f49aee6446041eabe54f37fc0d9afa6de5da8efa843f807253
- PHP 8.1 shard 3: 12548009276 / c571d591894728e32d8414252de8510f7ea19286bdea6c3254b7d4f0f8662caf
- PHP 8.5 shard 1: 12547494341 / b55581920c711bf15ede3767b753c619b6bb0ea59f4b22b1a85e06634b82e5dc
