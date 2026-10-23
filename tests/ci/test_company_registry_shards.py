import hashlib
import importlib.util
import json
import re
import shlex
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('registry_shard', ROOT / 'scripts/ci/run-company-registry-shard.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


class RegistryShardsTest(unittest.TestCase):
    def test_exact_pre_sharding_inventory(self):
        # Snapshot extracted from 2da2dff1's full MySQL run block, including both
        # Python checks and --tracking variants. Update deliberately when adding tests.
        commands = runner.load_commands()
        self.assertEqual(len(commands), 472)
        self.assertEqual(hashlib.sha256(json.dumps(commands, separators=(',', ':')).encode()).hexdigest(),
                         '1565aaa69234abd5ab9d7eec11fbbd912a93fdb75e0dfd919de0b913e408c835')

    def test_union_exact_once_and_order_preserved(self):
        commands = runner.load_commands()
        shards = [runner.select_commands(commands, i) for i in range(1, 5)]
        self.assertEqual([len(shard) for shard in shards], [118] * 4)
        union = [entry for shard in shards for entry in shard]
        self.assertEqual(sorted(union), list(enumerate(commands)))
        self.assertEqual(len({index for index, _ in union}), len(commands))
        for shard in shards:
            self.assertEqual(shard, sorted(shard))
        with self.assertRaises(ValueError):
            runner.select_commands(commands, 0)

    def test_fail_fast_and_timings_without_shell(self):
        calls = []
        def execute(argv, **kwargs):
            calls.append((argv, kwargs))
            return subprocess.CompletedProcess(argv, 9)
        with tempfile.TemporaryDirectory() as directory:
            evidence = Path(directory) / 'timings.jsonl'
            self.assertEqual(runner.run([['php', 'a']] * 9, 1, evidence, execute), 9)
            self.assertEqual(len(calls), 1)
            self.assertFalse(calls[0][1]['check'])
            self.assertNotIn('shell', calls[0][1])
            record = json.loads(evidence.read_text())
            self.assertEqual(record['exit_code'], 9)
            self.assertEqual(record['index'], 0)
            self.assertGreaterEqual(record['seconds'], 0)

    def test_all_selected_commands_run_and_launch_failure_is_red(self):
        with tempfile.TemporaryDirectory() as directory:
            evidence = Path(directory) / 'timings.jsonl'
            commands = [['php', str(i)] for i in range(9)]
            self.assertEqual(runner.run(commands, 1, evidence, lambda argv, **kw: subprocess.CompletedProcess(argv, 0)), 0)
            self.assertEqual([json.loads(line)['index'] for line in evidence.read_text().splitlines()], [0, 4, 8])
            def missing(*args, **kwargs):
                raise FileNotFoundError('synthetic')
            self.assertEqual(runner.run(commands, 1, evidence, missing), 127)

    def test_external_preflight_and_audit_run_exactly_once_per_php(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        expected = {
            'Validar sintaxe e controles HTTP': [
                ['php', 'tests/issuers_controller.php'],
                ['php', 'tests/issuers_admin_upgrade_gate.php'],
                ['php', 'tests/issuers_integration_view.php'],
                ['php', 'tests/companies/email_template_editor.php'],
                ['php', 'tests/companies/portal_recovery_tokens.php'],
                ['php', 'tests/companies/portal_recovery_atomic.php'],
            ],
            'Testar entradas legadas Finance e Accounting em banco exclusivo': [
                ['php', 'tests/companies/legacy_financial_module_readers.php'],
                ['php', 'tests/companies/legacy_financial_module_tables.php'],
                ['php', 'tests/companies/legacy_financial_module_migrations.php'],
                ['php', 'tests/companies/module_version_column_mysql.php'],
            ],
        }
        external = []
        for name, commands in expected.items():
            self.assertEqual(source.count('      - name: ' + name + '\n'), 1)
            step = source.split('      - name: ' + name + '\n')[1].split('      - ')[0]
            self.assertEqual(re.findall(r'^        if: (.+)$', step, re.M), ['matrix.shard == 1'])
            actual = [shlex.split(line.strip()) for line in step.splitlines()
                      if line.strip().startswith('php tests/')]
            self.assertEqual(actual, commands, 'exact argv including any flags')
            external.extend(actual)
        all_external = [shlex.split(line.strip()) for line in source.splitlines()
                        if line.strip().startswith('php tests/')]
        self.assertEqual(all_external, external, 'no duplicated or moved external invocations')
        manifest_php = [command for command in runner.load_commands() if command[0] == 'php']
        self.assertEqual(len(manifest_php), 470)
        combined = manifest_php + external
        self.assertEqual(len(combined), 480)
        self.assertEqual(len(set(map(tuple, combined))), 480)

    def test_pinned_images_match_php_and_smoke_is_mandatory(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        shards = source.split('  registry-shards:\n')[1].split('  registry:\n')[0]
        images = re.findall(r"- php: '(8\.[15])'\n +image: (ghcr\.io/wkarts/argws-crm-ci-php@sha256:[a-f0-9]{64})", shards)
        self.assertEqual([php for php, image in images], ['8.1', '8.5'])
        # Verified build receipt: run40033441190; prevent swapped PHP images.
        self.assertEqual(dict(images), {
            '8.1': 'ghcr.io/wkarts/argws-crm-ci-php@sha256:c6246e2740c225e7db4f0db2b8ba8657fb72cb9638efdedcfe1a8853fc081a14',
            '8.5': 'ghcr.io/wkarts/argws-crm-ci-php@sha256:d050af83a621363bdc79e5868f85d7203e8b5fb03be6cd37fbb7c41500894677',
        })
        self.assertEqual(len(set(image for php, image in images)), 2)
        self.assertEqual(len(re.findall(r'^ +image: ghcr', shards, re.M)), 2)
        self.assertIn('      image: ${{ matrix.image }}', shards)
        self.assertIn('      options: --shm-size=1g', shards)
        self.assertIn('          EXPECTED_PHP: ${{ matrix.php }}', shards)
        smoke = shards.split('      - name: Validar ferramentas da imagem CI por digest\n')[1].split('      - ')[0]
        self.assertIn('        run: argws-ci-smoke', smoke)
        self.assertNotIn('        if:', smoke)
        self.assertNotIn('apt-get', shards)
        self.assertNotIn('docker-php-ext-install', shards)
        self.assertEqual(source.count('      - docker/ci/**'), 2)
        self.assertNotIn('credentials:', shards, 'public CI image must be pulled anonymously')
        self.assertNotIn('packages:', source, 'consumer needs no registry credential permission')

    def test_workflow_preserves_matrix_and_fail_closed_required_names(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        self.assertIn('  registry-shards:\n    needs: registry-plan', source)
        self.assertIn('        shard: [1, 2, 3, 4]', source)
        self.assertIn('      image: ${{ matrix.image }}', source)
        self.assertEqual(source.count("php: ['8.1', '8.5']"), 2)
        self.assertIn('  registry:\n    if: always()\n    needs: [registry-plan, registry-shards]', source)
        self.assertIn('test "$PLAN_RESULT" = success && test "$SHARDS_RESULT" = success', source)
        self.assertNotIn('continue-on-error', source)
        self.assertIn('  pull_request:', source)
        self.assertIn('  push:', source)
        self.assertEqual(source.count('      - tests/ci/**'), 2)


if __name__ == '__main__':
    unittest.main()
