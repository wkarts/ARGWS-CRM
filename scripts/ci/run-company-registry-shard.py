#!/usr/bin/env python3
"""Run unchanged registry commands in independent CI jobs, never parallel in one DB."""
import argparse
import json
import subprocess
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / 'tests/ci/company-registry-commands.json'
SHARDS = 4


def load_commands(path=MANIFEST):
    commands = json.loads(Path(path).read_text())
    if not isinstance(commands, list) or not commands:
        raise ValueError('Empty or invalid command manifest')
    seen = set()
    for command in commands:
        if not isinstance(command, list) or len(command) < 2 or not all(isinstance(arg, str) and arg for arg in command):
            raise ValueError('Invalid command argv')
        if command[0] not in ('php', 'python3') or command[1].startswith('/') or '..' in Path(command[1]).parts:
            raise ValueError('Invalid command executable/path')
        if not command[1].startswith(('tests/', 'scripts/')) or not (ROOT / command[1]).is_file():
            raise ValueError('Command file missing/outside test scope')
        key = tuple(command)
        if key in seen:
            raise ValueError('Duplicate exact command')
        seen.add(key)
    return commands


def select_commands(commands, shard):
    if shard not in range(1, SHARDS + 1):
        raise ValueError('Shard must be 1..4')
    return [(index, command) for index, command in enumerate(commands) if index % SHARDS == shard - 1]


def run(commands, shard, timings, execute=subprocess.run):
    selected = select_commands(commands, shard)
    Path(timings).parent.mkdir(parents=True, exist_ok=True)
    with Path(timings).open('w') as evidence:
        for index, command in selected:
            # Only committed argv; no environment, DB credentials, or provider data.
            record = {'index': index, 'shard': shard, 'argv': command}
            print('REGISTRY_START ' + json.dumps(record), flush=True)
            started = time.monotonic()
            try:
                result = execute(command, cwd=ROOT, check=False)
                code = result.returncode
            except OSError as error:
                code = 127
                print('REGISTRY_EXEC_ERROR ' + type(error).__name__, flush=True)
            record.update(seconds=round(time.monotonic() - started, 3), exit_code=code)
            evidence.write(json.dumps(record) + '\n')
            evidence.flush()
            print('REGISTRY_END ' + json.dumps(record), flush=True)
            if code != 0:
                return code if code > 0 else 1
    return 0


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--shard', type=int, choices=range(1, SHARDS + 1), required=True)
    parser.add_argument('--timings', required=True)
    args = parser.parse_args()
    return run(load_commands(), args.shard, args.timings)


if __name__ == '__main__':
    raise SystemExit(main())
