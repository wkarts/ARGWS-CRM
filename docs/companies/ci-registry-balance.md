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

The historical evidence remains immutable. The current command manifest contains
470 PHP + 2 Python commands after one parallel-calendar check was retired. Its
original timing row stays available only to verify the signed evidence and is not
executed. All eight independent DB/container jobs and fail-closed compatibility
aggregate gates remain unchanged.

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
| 8.1 | 1159.838 / 1313.273 / 738.688 / 1112.623 | 1076.416 / 1082.531 / 1077.735 / 1080.220 | 1313.273 / 1082.531 (-17.6%) |
| 8.5 | 973.455 / 1001.017 / 1432.896 / 1245.002 | 1170.506 / 1131.005 / 1165.540 / 1171.506 | 1432.896 / 1171.506 (-18.2%) |

Across both versions the largest command sum would fall from 23m52.896s to
19m31.506s under unchanged per-command runtimes: **4m21.390s estimated**, not
measured. Setup, queue, pulls, smoke, preflight, external audit and upload are not
included. Shard 1's extra checks can affect the true critical path. The only
removed work is the retired parallel-calendar controller check; all retained
commands preserve their historical shard. Fewer GitHub concurrency slots can
also reduce or erase wall-clock benefit.

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

## Executed balanced registry: 56a99618

[Run40065855623](https://github.com/wkarts/ARGWS-CRM/actions/runs/40065855623): SUCCESS. Official `/timing`: **24m43s** (1483000ms).
Published plan byte-for-byte SHA256: `1a4f54a480af305d6828192a52378796487d387c4fc0890ee9935c56c4411b92`.
All 8 ZIP digests match GitHub; all 944 records have exact argv/assignment and exit0; 118 commands per PHP/shard. Both aggregate gates SUCCESS.

| PHP/shard | Actual job seconds | Actual MySQL step seconds | Actual command sum seconds |
|---|---:|---:|---:|
| 8.1/1 | 1340 | 1259 | 1258.749 |
| 8.1/2 | 771 | 709 | 708.896 |
| 8.1/3 | 1390 | 1323 | 1322.746 |
| 8.1/4 | 1358 | 1292 | 1292.466 |
| 8.5/1 | 1199 | 1127 | 1127.601 |
| 8.5/2 | 746 | 678 | 677.361 |
| 8.5/3 | 786 | 721 | 720.901 |
| 8.5/4 | 1126 | 1054 | 1053.662 |

### Real workflow comparisons
- 846af95: official30m32s →24m43s =5m49s less (-19.05%).
- c7bc0bd3: integrator-reported26m49s →24m43s =2m06s less (-7.83%); earlier run ID not independently re-fetched here.
- Originalmain39996938632: official89m05s →24m43s =64m22s less (-72.26%).
These are distinct executions, not a controlled causal scheduler comparison.

### Same-run counterfactual (not executed)

| PHP | Actual balanced max command sum | Hypothetical RR max on same individual times | Actual above hypothetical |
|---|---:|---:|---:|
| 8.1 | 1322.746s | 1251.754s | 5.67% |
| 8.5 | 1127.601s | 989.859s | 13.92% |

Under invariant per-command runtime, RR would have a global command maximum70.992s lower. This is NOT an executed RR result and does NOT prove reverting would help: these timings came from VMs and DB states assigned by the balanced plan. Reassignment changes conditions. Large shard differences are consistent with runner/environment variability but do not isolate its cause.
Do not attribute the full5m49s observed reduction to balancing, retrain from this one run, or guarantee savings. Keep this negative counterfactual alongside the previous positive holdout. The frozen allocation was not retrained after this result.

### Artifact IDs / verified ZIP digests
- 8.1/3: `12550360137` — `27e22b6cba2f90ef89447e3a646cc107792dd8e767dc1e4f02ea6815ac3104e4`
- 8.1/4: `12550150499` — `a90276492922074c0447c18ec76564cc24a0e0fbfa91ed22476dbce65b276e11`
- 8.5/1: `12550050510` — `c19bc41de6d4a31d38db6dd7edaf8742911bff66f773eaa8c54af92c7cc0277e`
- 8.5/2: `12549453407` — `0f10f05031501e315b39738ba14732966a85308a8aebde5f95c711b4dc69da3b`
- 8.5/4: `12549392822` — `dec53591f04907c46565e0b7eb694a62ab96066dd5654d042ca72b3cc4c70476`
- 8.5/3: `12549119908` — `1c86b816a20e7689f85a526062d49918fcd97725fc20197edb04dbdb20c20c6f`
- 8.1/1: `12549079161` — `9e884eed1dbf344eb292e65e56a7a59b6f620ef92b5173f7331df2b0a65d3eb2`
- 8.1/2: `12548973524` — `5979012121be9a93b2eb8d33ca2a5a958c7bcabd312ed5096eb1268c1d0040f0`

## Explicit extension and retired parallel calendar

Commit `aa01d59e` prepends `php tests/issuers_original_administrator.php` to the
manifest. It has **no paired timing observation** in the frozen evidence. The
explicit `company-registry-unmeasured.json` ledger keeps this fact visible; the
planner assigns unmeasured additions by lowest command count, then shard number,
without a fabricated zero duration or retraining the LPT model.

The parallel `tests/settings/calendar_controller.php` check is now retired because
the product route moved to the official Settings and Utilities flows. The baseline
records that argv at historical measured index 406, reconstructs the original
472-command evidence for hash/ZIP verification, and removes it before producing
the current assignments. Its former shard-2 capacity receives the unmeasured
original-administrator command. Every retained historical command therefore keeps
its original shard and the current 472 argv split exactly 118/118/118/118.

The current manifest contains 470 PHP + 2 Python commands. Together with 29 PHP
preflight/audit commands outside the manifest, it executes 499 unique PHP commands
per version. The paired timing arrays, original argv hash and eight ZIP/JSONL
digests remain unchanged; the baseline only gained an explicit retirement ledger.

New canonical manifest hash:
`bb4d99bc6a7363393204bf3099f1079fc95b78af17cef87dc2ed81b95c0d1fbc`.
Artifact verification still compares all 944 historical records against the exact
historical manifest. Replay output is explicitly partial and excludes both the
unmeasured and retired commands: it is **not** a predicted new total. All previous
measured-run receipts above remain historical evidence, not claims that the
current suite has already run.

`--check` reconstructs the extension and fails closed on an undeclared command,
missing addition, changed argv/order, altered baseline, or stale assignment.
Runner capacity validation requires all four current shards to remain exactly
count-balanced. The eight isolated databases,
PHP versions, execution order within each shard, and required aggregate gates are
unchanged. Local validation includes the registry planner unit suite and planner
`--check`; actual
execution of the expanded PHP suite remains a CI responsibility.
