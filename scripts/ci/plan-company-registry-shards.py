#!/usr/bin/env python3
"""Build a frozen, count-capped LPT plan from paired successful PHP timings."""
import argparse
import hashlib
import json
import math
import zipfile
from decimal import Decimal
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASELINE = ROOT / 'tests/ci/company-registry-timing-baseline.json'
PLAN = ROOT / 'tests/ci/company-registry-shard-plan.json'
UNMEASURED = ROOT / 'tests/ci/company-registry-unmeasured.json'


def balanced_assignment(weights, shards=4):
    if not weights or len(weights) % shards or any(type(w) is not int or w < 0 for w in weights):
        raise ValueError('Nonnegative integer weights and equal shard capacity required')
    capacity = len(weights) // shards
    totals, counts, assignment = [0] * shards, [0] * shards, [0] * len(weights)
    for index in sorted(range(len(weights)), key=lambda i: (-weights[i], i)):
        target = min((s for s in range(shards) if counts[s] < capacity), key=lambda s: (totals[s], counts[s], s))
        assignment[index] = target + 1
        totals[target] += weights[index]
        counts[target] += 1
    return assignment


def measured_commands(commands, unmeasured):
    additions = [entry['argv'] for entry in unmeasured]
    if len({tuple(argv) for argv in additions}) != len(additions) or any(commands.count(argv) != 1 for argv in additions):
        raise ValueError('Each explicit unmeasured command must occur exactly once')
    return [argv for argv in commands if argv not in additions]


def build_plan(commands, baseline, unmeasured=None):
    if unmeasured is None:
        unmeasured = json.loads(UNMEASURED.read_text())
    measured = measured_commands(commands, unmeasured)
    # Preserve the exact historical argv/order and paired observations. Unknown
    # durations are not zero-duration samples and must never enter the LPT model.
    base = build_measured_plan(measured, baseline)
    assignment_by_argv = {tuple(argv): shard for argv, shard in zip(measured, base['assignments'])}
    counts = [base['assignments'].count(shard) for shard in range(1, 5)]
    for entry in unmeasured:
        target = min(range(4), key=lambda shard: (counts[shard], shard))
        assignment_by_argv[tuple(entry['argv'])] = target + 1
        counts[target] += 1
    return {**base, 'model': 'frozen-paired-lpt-plus-unmeasured-count-v1',
            'manifest_sha256': hashlib.sha256(json.dumps(commands, separators=(',', ':')).encode()).hexdigest(),
            'measured_manifest_sha256': base['manifest_sha256'],
            'unmeasured': unmeasured,
            'assignments': [assignment_by_argv[tuple(argv)] for argv in commands]}


def build_measured_plan(commands, baseline):
    manifest_hash = hashlib.sha256(json.dumps(commands, separators=(',', ':')).encode()).hexdigest()
    if baseline['manifest_sha256'] != manifest_hash or set(baseline['milliseconds']) != {'8.1', '8.5'}:
        raise ValueError('Timing evidence must match exact command manifest and both PHP versions')
    samples = baseline['milliseconds']
    for times in samples.values():
        if len(times) != len(commands) or any(type(t) is not int or t < 0 for t in times):
            raise ValueError('Incomplete/invalid paired timings')
    # Integer geometric mean avoids platform floating-point tie changes. A 1ms
    # floor prevents a rounded zero in one sample from erasing its paired cost.
    weights = [math.isqrt(max(1, a) * max(1, b)) for a, b in zip(samples['8.1'], samples['8.5'])]
    return {'model': 'paired-geometric-ms-capacity-lpt-v1', 'manifest_sha256': manifest_hash,
            'baseline_sha256': hashlib.sha256(json.dumps(baseline, sort_keys=True, separators=(',', ':')).encode()).hexdigest(),
            'assignments': balanced_assignment(weights)}


def verify_artifacts(directory, commands, baseline):
    for artifact in baseline['artifacts']:
        matches = list(Path(directory).glob('*/' + artifact['zip']))
        if len(matches) != 1:
            raise ValueError('Require exactly one original ZIP for each PHP/shard')
        payload = matches[0].read_bytes()
        if hashlib.sha256(payload).hexdigest() != artifact['zip_sha256']:
            raise ValueError('Artifact ZIP digest mismatch')
        with zipfile.ZipFile(matches[0]) as archive:
            if archive.namelist() != ['registry-timings.jsonl'] or archive.getinfo('registry-timings.jsonl').file_size > 2 * 1024 * 1024:
                raise ValueError('Unexpected timing artifact contents')
            lines = archive.read('registry-timings.jsonl')
        if hashlib.sha256(lines).hexdigest() != artifact['jsonl_sha256']:
            raise ValueError('Artifact JSONL digest mismatch')
        rows = [json.loads(line) for line in lines.splitlines()]
        expected_indices = [i for i in range(len(commands)) if i % 4 == artifact['shard'] - 1]
        if [row['index'] for row in rows] != expected_indices:
            raise ValueError('Missing, duplicated or reordered timing evidence')
        for row in rows:
            index = row['index']
            if row['argv'] != commands[index] or row['exit_code'] != 0 or row['shard'] != artifact['shard']:
                raise ValueError('Timing argv/status/shard mismatch')
            if int(Decimal(str(row['seconds'])) * 1000) != baseline['milliseconds'][artifact['php']][index]:
                raise ValueError('Timing duration mismatch')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true')
    parser.add_argument('--verify-artifacts', type=Path, help='Optional original attachments directory; verify hashes and all 944 records')
    args = parser.parse_args()
    commands = json.loads((ROOT / 'tests/ci/company-registry-commands.json').read_text())
    baseline = json.loads(BASELINE.read_text())
    if args.verify_artifacts:
        verify_artifacts(args.verify_artifacts, measured_commands(commands, json.loads(UNMEASURED.read_text())), baseline)
    plan = build_plan(commands, baseline)
    if args.check:
        if json.loads(PLAN.read_text()) != plan:
            raise SystemExit('Frozen shard plan does not match the committed evidence')
    else:
        PLAN.write_text(json.dumps(plan, indent=2) + '\n')
    measured = measured_commands(commands, plan['unmeasured'])
    measured_assignments = [plan['assignments'][commands.index(argv)] for argv in measured]
    for php, times in baseline['milliseconds'].items():
        old = [sum(t for i, t in enumerate(times) if i % 4 == shard) / 1000 for shard in range(4)]
        new = [sum(t for i, t in enumerate(times) if measured_assignments[i] == shard) / 1000 for shard in range(1, 5)]
        print(json.dumps({'php': php, 'observed_round_robin_command_seconds': old,
                          'estimated_replay_balanced_command_seconds': new,
                          'unmeasured_commands_excluded': plan['unmeasured'],
                          'partial_historical_replay_not_current_total': True, 'not_a_new_run': True}))


if __name__ == '__main__':
    main()
