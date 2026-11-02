import contextlib
import copy
import fnmatch
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import types
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts/ci/bb-sql-run.py'
spec = importlib.util.spec_from_file_location('bb_sql_run', SCRIPT)
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)

# Captured from the original SQL run blocks at23f25903, before this workflow edit.
BASELINE = {
    'mysql': (60, '1475759050377c507437e315976b4030efacd282322adfa938b26529165a119c'),
    'mariadb': (72, 'd8486021eaa2b371f723d54ddb50f046a2eb7b20446215fafc2293da5e5c9384'),
}


def job(source, name):
    match = re.search(r'^  ' + re.escape(name) + r':\n(.*?)(?=^  [A-Za-z][\w-]*:\n|\Z)', source, re.M | re.S)
    if not match:
        raise AssertionError('Missing job: ' + name)
    return match.group(1)


def sql_source(block):
    match = re.search(r"<<'BB_SQL_COMMANDS'\n(.*?)^          BB_SQL_COMMANDS$", block, re.M | re.S)
    if not match:
        raise AssertionError('Missing complete original SQL command block')
    return match.group(1)


def environment(database='mysql'):
    password = 'root' if database == 'mysql' else 'synthetic-ci-password'
    return dict(MYSQL_DSN=runner.DSN, ISSUERS_TEST_DSN=runner.DSN,
                MYSQL_USER='root', ISSUERS_TEST_USER='root', MYSQL_PASSWORD=password,
                ISSUERS_TEST_PASSWORD=password, GITHUB_SHA='same-source', GITHUB_RUN_ID='test-run',
                GATE_RESULT='success', GATE_RUN='true', SHARDS_RESULT='success')


class SqlPlanTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.plan = runner.load_plan()
        cls.workflow = (ROOT / '.github/workflows/banco-brasil.yml').read_text()

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.directory = Path(self.temp.name)

    def source(self, database):
        return sql_source(job(self.workflow, database + '-shards'))

    def receipt(self, database, shard):
        return self.directory / f'bb-sql-{database}-{shard}.json'

    def run_shard(self, database, shard, execute=None, **kwargs):
        execute = execute or (lambda argv, **unused: types.SimpleNamespace(returncode=0))
        with contextlib.redirect_stdout(io.StringIO()):
            return runner.run_shard(database, shard, self.source(database), self.receipt(database, shard),
                                    env=kwargs.pop('env', environment(database)), execute=execute, **kwargs)

    def all_receipts(self, database='mysql'):
        for shard in range(4):
            self.assertEqual(self.run_shard(database, shard), 0)

    def test_original_baseline_counts_and_exact_argv_digests(self):
        for database, (count, digest) in BASELINE.items():
            with self.subTest(database=database):
                commands = runner.parse_commands(self.source(database))
                self.assertEqual(len(commands), count)
                self.assertEqual(runner.digest(commands), digest)
                self.assertEqual(commands, self.plan['databases'][database]['commands'])
                self.assertTrue(all((ROOT / argv[1]).is_file() for argv in commands))

    def test_round_robin_units_unique_owners_local_order_and_total_union(self):
        for database, variant in self.plan['databases'].items():
            observed = []
            unit = index = 0
            while index < len(variant['commands']):
                width = 2 if variant['commands'][index] == runner.VERIFY else 1
                self.assertEqual(variant['owners'][index:index + width], [unit % 4] * width)
                index += width
                unit += 1
            for shard in range(4):
                actual = []
                def execute(argv, env, check):
                    actual.append(argv)
                    self.assertEqual(env, environment(database))
                    self.assertFalse(check)
                    return types.SimpleNamespace(returncode=0)
                self.assertEqual(self.run_shard(database, shard, execute), 0)
                indexes = runner.indexes_for(variant, shard)
                self.assertEqual(indexes, sorted(indexes))
                self.assertEqual(actual, [variant['commands'][i] for i in indexes])
                observed.extend(indexes)
            self.assertEqual(sorted(observed), list(range(len(variant['commands']))))
            self.assertEqual(len(observed), len(set(observed)))

    def test_checkout_verifier_is_a_local_predecessor_and_existing_requires_remain(self):
        for database, variant in self.plan['databases'].items():
            first = variant['commands'].index(runner.VERIFY)
            self.assertEqual(variant['commands'][first + 1], runner.CHECKOUT)
            self.assertEqual(variant['owners'][first], variant['owners'][first + 1])
        result = subprocess.run([sys.executable, str(ROOT / runner.VERIFY[1])], cwd=ROOT,
                                capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_mismatch_extra_missing_and_reordered_commands_fail_before_execution(self):
        original = runner.parse_commands(self.source('mysql'))
        mutations = [original[:-1], original + [original[0]], original[1:] + original[:1]]
        changed = copy.deepcopy(original)
        changed[0].append('--unapproved-argument')
        mutations.append(changed)
        for rows in mutations:
            called = []
            with self.assertRaises(runner.PlanError):
                runner.run_shard('mysql', 0, '\n'.join(' '.join(row) for row in rows),
                                 self.receipt('mysql', 0), env=environment(), execute=lambda *a, **k: called.append(a))
            self.assertEqual(called, [])

    def test_shell_injection_is_not_an_additional_command(self):
        for source in ['echo skipped', 'php tests/a.php; true', 'php tests/a.php && exit 0']:
            with self.assertRaises(runner.PlanError):
                runner.run_shard('mysql', 0, source, self.receipt('mysql', 0), env=environment())

    def test_bad_database_environment_fails_without_executing_or_printing_credentials(self):
        for key in ['MYSQL_DSN', 'ISSUERS_TEST_DSN', 'MYSQL_USER', 'ISSUERS_TEST_USER',
                    'MYSQL_PASSWORD', 'ISSUERS_TEST_PASSWORD']:
            env = environment()
            env[key] = 'DO_NOT_LOG_THIS_VALUE'
            called = []
            with self.assertRaises(runner.PlanError) as error:
                self.run_shard('mysql', 0, lambda *a, **k: called.append(a), env=env)
            self.assertNotIn('DO_NOT_LOG_THIS_VALUE', str(error.exception))
            self.assertEqual(called, [])

    def test_failure_signal_and_missing_executable_preserve_status_and_no_success_receipt(self):
        for returncode, expected in [(17, 17), (-15, 143), (-2, 130)]:
            calls = []
            self.receipt('mysql', 0).write_text('stale success')
            def execute(argv, **unused):
                calls.append(argv)
                return types.SimpleNamespace(returncode=returncode)
            self.assertEqual(self.run_shard('mysql', 0, execute), expected)
            self.assertEqual(len(calls), 1)
            self.assertFalse(self.receipt('mysql', 0).exists())
        def absent(*unused, **kwargs):
            raise FileNotFoundError()
        self.assertEqual(self.run_shard('mysql', 0, absent), 127)

    def test_logs_record_exact_argv_for_each_started_and_finished_command(self):
        log = io.StringIO()
        with contextlib.redirect_stdout(log):
            runner.run_shard('mariadb', 3, self.source('mariadb'), self.receipt('mariadb', 3),
                             env=environment('mariadb'), execute=lambda *a, **k: types.SimpleNamespace(returncode=0))
        starts = [json.loads(line.split(' ', 1)[1]) for line in log.getvalue().splitlines() if line.startswith('CLI_START ')]
        ends = [json.loads(line.split(' ', 1)[1]) for line in log.getvalue().splitlines() if line.startswith('CLI_END ')]
        self.assertEqual([(x['index'], x['argv']) for x in starts], [(x['index'], x['argv']) for x in ends])
        self.assertTrue(all(x['status'] == 0 for x in ends))
        self.assertNotIn('synthetic-ci-password', log.getvalue())

    def test_all_receipts_prove_complete_union_for_both_database_variants(self):
        for database in ['mysql', 'mariadb']:
            folder = self.directory / database
            folder.mkdir()
            for shard in range(4):
                self.run_shard(database, shard)
                self.receipt(database, shard).replace(folder / self.receipt(database, shard).name)
            with contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(runner.aggregate(database, folder, env=environment(database)), 0)

    def test_missing_partial_duplicate_wrong_database_source_and_argv_fail_closed(self):
        self.all_receipts()
        path = self.receipt('mysql', 2)
        original = json.loads(path.read_text())
        mutations = []
        for field, value in [('database', 'mariadb'), ('shard', 0), ('shards', 3), ('complete', False),
                             ('commit', 'another-tree'), ('run_id', 'another-run'), ('argv_sha256', 'different')]:
            value_row = copy.deepcopy(original)
            value_row[field] = value
            mutations.append(value_row)
        for records in [original['completed'][:-1], original['completed'] * 2, list(reversed(original['completed']))]:
            changed = copy.deepcopy(original)
            changed['completed'] = records
            mutations.append(changed)
        changed = copy.deepcopy(original)
        changed['completed'][0]['argv'].append('--different')
        mutations.append(changed)
        changed = copy.deepcopy(original)
        changed['completed'][0]['status'] = 1
        mutations.append(changed)
        for changed in mutations:
            path.write_text(json.dumps(changed))
            with self.assertRaises(runner.PlanError):
                runner.aggregate('mysql', self.directory, env=environment())
        path.unlink()
        with self.assertRaises(runner.PlanError):
            runner.aggregate('mysql', self.directory, env=environment())
        path.write_text(json.dumps(original))
        (self.directory / 'duplicate.json').write_text(json.dumps(original))
        with self.assertRaises(runner.PlanError):
            runner.aggregate('mysql', self.directory, env=environment())

    def test_failed_cancelled_skipped_or_incomplete_gate_never_turns_green(self):
        self.all_receipts()
        for gate in ['failure', 'cancelled', 'skipped', '', 'success']:
            for shards in ['failure', 'cancelled', 'skipped', '']:
                for run in ['true', 'false', '']:
                    if gate == 'success' and shards == 'skipped' and run == 'false':
                        continue
                    env = environment() | dict(GATE_RESULT=gate, GATE_RUN=run, SHARDS_RESULT=shards)
                    with self.assertRaises(runner.PlanError):
                        runner.aggregate('mysql', self.directory, env=env)
        with contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(runner.aggregate('mysql', self.directory / 'no-receipts',
                             env=environment() | dict(GATE_RUN='false', SHARDS_RESULT='skipped')), 0)
        with self.assertRaises(runner.PlanError):
            runner.aggregate('mysql', self.directory, env=environment() | dict(GATE_RUN='false'))

    def test_plan_rejects_wrong_ownership_digest_and_shard_values(self):
        bad = copy.deepcopy(self.plan)
        bad['databases']['mysql']['owners'][0] = 1
        path = self.directory / 'plan.json'
        path.write_text(json.dumps(bad))
        with self.assertRaises(runner.PlanError):
            runner.load_plan(path)
        for shard in [-1, 4, True, '0']:
            with self.assertRaises(runner.PlanError):
                runner.indexes_for(self.plan['databases']['mysql'], shard)

    def test_workflow_preserves_services_php_environment_and_four_isolated_workers(self):
        for database, image, password, health in [
            ('mysql', 'mysql:8.4', 'root', 'mysqladmin ping -proot'),
            ('mariadb', 'mariadb:11.4', 'synthetic-ci-password', 'mariadb-admin ping -psynthetic-ci-password'),
        ]:
            worker = job(self.workflow, database + '-shards')
            self.assertIn('needs: validation-gate', worker)
            self.assertIn("if: ${{ needs.validation-gate.outputs.run == 'true' }}", worker)
            self.assertIn('max-parallel: 4', worker)
            self.assertIn('fail-fast: false', worker)
            self.assertIn('shard: [0, 1, 2, 3]', worker)
            self.assertIn('    services:\n      ' + database + ':', worker)
            self.assertIn('image: ' + image, worker)
            self.assertIn(health, worker)
            self.assertIn('--health-interval=10s --health-timeout=5s --health-retries=10', worker)
            self.assertIn("ports: ['3306:3306']", worker)
            self.assertIn("php-version: '8.3'", worker)
            self.assertIn('extensions: gd, pdo_mysql, mysqli, curl, mbstring, bcmath', worker)
            self.assertNotIn('ghcr.io', worker)
            for key in ['MYSQL_DSN', 'ISSUERS_TEST_DSN']:
                self.assertIn(key + ': ' + runner.DSN, worker)
            for key in ['MYSQL_USER', 'ISSUERS_TEST_USER']:
                self.assertIn(key + ': root', worker)
            for key in ['MYSQL_PASSWORD', 'ISSUERS_TEST_PASSWORD']:
                self.assertIn(key + ': ' + password, worker)
            self.assertIn('actions/upload-artifact@v4', worker)
            self.assertIn('if-no-files-found: error', worker)
            aggregate = job(self.workflow, database)
            self.assertIn('    name: ' + database + '\n', aggregate)
            self.assertIn('if: ${{ always() }}', aggregate)
            self.assertIn('needs: [validation-gate, ' + database + '-shards]', aggregate)
            self.assertIn('actions/download-artifact@v4', aggregate)
            self.assertIn('SHARDS_RESULT: ${{ needs.' + database + '-shards.result }}', aggregate)
            self.assertIn('GATE_RESULT: ${{ needs.validation-gate.result }}', aggregate)
            self.assertIn('GATE_RUN: ${{ needs.validation-gate.outputs.run }}', aggregate)
            self.assertIn('bb-sql-run.py aggregate --database ' + database, aggregate)

    def test_path_filters_start_bb_and_shared_native_preflight(self):
        native = (ROOT / '.github/workflows/native-company-experience.yml').read_text()
        files = ['scripts/ci/bb-sql-run.py', 'scripts/ci/bb-sql-plan.json', 'tests/ci/test_bb_sql_plan.py']
        for source in [self.workflow, native]:
            filters = re.findall(r'paths: \[([^\n]+)\]', source)
            self.assertEqual(len(filters), 2)
            for path_filter in filters:
                patterns = re.findall(r"'([^']+)'", path_filter)
                for path in files:
                    self.assertTrue(any(fnmatch.fnmatchcase(path, pattern) for pattern in patterns), path)
        self.assertIn("python3 -m unittest discover -s tests/ci -p 'test_*.py'", native)


if __name__ == '__main__':
    unittest.main()
