#!/usr/bin/env python3
"""Run unchanged SQL entrypoints on four independent service databases."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shlex
import subprocess
import sys
import time

PLAN = Path(__file__).with_name('bb-sql-plan.json')
VERIFY = ['python3', 'tests/ci/verify_bb_checkout_chain.py']
CHECKOUT = ['php', 'tests/banco_brasil_checkout_mysql.php']
DSN = 'mysql:host=127.0.0.1;dbname=bb_test;charset=utf8mb4'


class PlanError(ValueError):
    pass


def digest(commands):
    return hashlib.sha256(json.dumps(commands, separators=(',', ':')).encode()).hexdigest()


def parse_commands(source):
    commands = []
    for line in source.splitlines():
        line = line.strip()
        if not line or line.startswith('#') or line.split('#', 1)[0].strip() == 'set -x':
            continue
        try:
            argv = shlex.split(line, comments=True)
        except ValueError as error:
            raise PlanError('Invalid SQL command quoting') from error
        if len(argv) < 2 or argv[0] not in {'php', 'python3'} or not argv[1].startswith('tests/'):
            raise PlanError('Unexpected SQL shell command')
        commands.append(argv)
    return commands


def owners_for(commands):
    """Round-robin independent units; the source audit stays before checkout."""
    owners = []
    index = unit = 0
    if commands.count(VERIFY) != 1 or commands.count(CHECKOUT) != 1:
        raise PlanError('Missing or repeated checkout-chain guard')
    while index < len(commands):
        width = 1
        if commands[index] == VERIFY:
            if commands[index + 1:index + 2] != [CHECKOUT]:
                raise PlanError('Checkout must immediately follow its source guard')
            width = 2
        elif commands[index] == CHECKOUT:
            raise PlanError('Checkout has no local predecessor')
        owners.extend([unit % 4] * width)
        index += width
        unit += 1
    return owners


def load_plan(path=PLAN):
    plan = json.loads(Path(path).read_text())
    if plan.get('format') != 1 or plan.get('shards') != 4 or plan.get('source_ref') != '23f25903':
        raise PlanError('Unsupported SQL plan')
    if plan.get('atomic_predecessors') != [[VERIFY, CHECKOUT]]:
        raise PlanError('SQL predecessor contract changed')
    if set(plan.get('databases', {})) != {'mysql', 'mariadb'}:
        raise PlanError('Both original database variants are required')
    for variant in plan['databases'].values():
        commands = variant['commands']
        if parse_commands('\n'.join(shlex.join(row) for row in commands)) != commands:
            raise PlanError('Invalid planned argv')
        if variant['command_count'] != len(commands) or variant['argv_sha256'] != digest(commands):
            raise PlanError('SQL command inventory differs')
        if variant['owners'] != owners_for(commands):
            raise PlanError('SQL ownership differs from the independent-unit round robin')
    return plan


def indexes_for(variant, shard):
    if type(shard) is not int or shard not in range(4):
        raise PlanError('SQL shard must be 0, 1, 2 or 3')
    return [index for index, owner in enumerate(variant['owners']) if owner == shard]


def validate_environment(env):
    if any(env.get(key) != DSN for key in ('MYSQL_DSN', 'ISSUERS_TEST_DSN')):
        raise PlanError('SQL workers require the two original isolated bb_test DSNs')
    if any(env.get(key) != 'root' for key in ('MYSQL_USER', 'ISSUERS_TEST_USER')):
        raise PlanError('SQL worker database users differ from the original workflow')
    if not env.get('MYSQL_PASSWORD') or env.get('MYSQL_PASSWORD') != env.get('ISSUERS_TEST_PASSWORD'):
        raise PlanError('SQL worker credentials are absent or inconsistent')


def emit(event, **values):
    print(event + ' ' + json.dumps(values, separators=(',', ':')), flush=True)


def execute_command(database, shard, index, argv, env, *, execute=subprocess.run):
    emit('CLI_START', database=database, shard=shard, index=index, argv=argv)
    started = time.monotonic()
    try:
        result = execute(argv, env=env, check=False)
        code = result.returncode if result.returncode >= 0 else 128 - result.returncode
    except OSError:
        code = 127
    emit('CLI_END', database=database, shard=shard, index=index, argv=argv,
         status=code, seconds=round(time.monotonic() - started, 3))
    return code


def run_shard(database, shard, source, receipt, *, env=None, plan_path=PLAN, execute=subprocess.run):
    receipt = Path(receipt)
    receipt.unlink(missing_ok=True)  # Never publish a stale success after a failed rerun.
    env = dict(os.environ if env is None else env)
    variant = load_plan(plan_path)['databases'][database]
    commands = parse_commands(source)
    if commands != variant['commands']:
        raise PlanError('Workflow argv/order/count differs from the preserved SQL plan')
    validate_environment(env)
    required = indexes_for(variant, shard)
    completed = []
    for index in required:
        argv = commands[index]
        code = execute_command(database, shard, index, argv, env, execute=execute)
        if code:
            return code
        completed.append({'index': index, 'argv': argv, 'status': code})
    if [row['index'] for row in completed] != required or not required:
        raise PlanError('Incomplete SQL shard')
    proof = {'format': 1, 'database': database, 'shard': shard, 'shards': 4,
             'argv_sha256': variant['argv_sha256'], 'commit': env.get('GITHUB_SHA', ''),
             'run_id': env.get('GITHUB_RUN_ID', ''), 'complete': True, 'completed': completed}
    receipt.parent.mkdir(parents=True, exist_ok=True)
    temporary = receipt.with_suffix('.tmp')
    temporary.write_text(json.dumps(proof, indent=2) + '\n')
    temporary.replace(receipt)
    emit('CLI_COMPLETE', database=database, shard=shard, commands=len(completed))
    return 0


def aggregate(database, receipts, *, env=None, plan_path=PLAN):
    env = dict(os.environ if env is None else env)
    if env.get('GATE_RESULT') != 'success':
        raise PlanError('Validation gate did not succeed')
    if env.get('GATE_RUN') == 'false' and env.get('SHARDS_RESULT') == 'skipped':
        print('Exact-tree proof accepted by the successful validation gate; SQL shards correctly skipped.')
        return 0
    if env.get('GATE_RUN') != 'true' or env.get('SHARDS_RESULT') != 'success':
        raise PlanError('All four SQL workers must succeed')
    variant = load_plan(plan_path)['databases'][database]
    receipts = Path(receipts)
    expected_files = {f'bb-sql-{database}-{shard}.json' for shard in range(4)}
    if {path.name for path in receipts.glob('*.json')} != expected_files:
        raise PlanError('Missing, repeated or unexpected SQL receipts')
    completed = []
    for shard in range(4):
        proof = json.loads((receipts / f'bb-sql-{database}-{shard}.json').read_text())
        identity = {'format': 1, 'database': database, 'shard': shard, 'shards': 4,
                    'argv_sha256': variant['argv_sha256'], 'commit': env.get('GITHUB_SHA', ''),
                    'run_id': env.get('GITHUB_RUN_ID', ''), 'complete': True}
        if any(proof.get(key) != value for key, value in identity.items()):
            raise PlanError('SQL receipt belongs to another source, worker or database')
        indexes = indexes_for(variant, shard)
        expected = [{'index': index, 'argv': variant['commands'][index], 'status': 0} for index in indexes]
        if proof.get('completed') != expected:
            raise PlanError('SQL worker did not complete its exact ordered argv')
        completed.extend(indexes)
    if sorted(completed) != list(range(len(variant['commands']))):
        raise PlanError('SQL union is not the complete original sequence exactly once')
    emit('CLI_COVERAGE', database=database, shards=4, commands=len(completed), status=0)
    return 0


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='action', required=True)
    run = commands.add_parser('run')
    run.add_argument('--database', choices=['mysql', 'mariadb'], required=True)
    run.add_argument('--shard', type=int, required=True)
    run.add_argument('--receipt', required=True)
    finish = commands.add_parser('aggregate')
    finish.add_argument('--database', choices=['mysql', 'mariadb'], required=True)
    finish.add_argument('--receipts', required=True)
    args = parser.parse_args()
    try:
        if args.action == 'run':
            return run_shard(args.database, args.shard, sys.stdin.read(), args.receipt)
        return aggregate(args.database, args.receipts)
    except (PlanError, KeyError, TypeError, json.JSONDecodeError, OSError) as error:
        print('BB SQL contract failed: ' + str(error), file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
