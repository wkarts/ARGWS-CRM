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
        self.assertEqual(commands[0], ['php', 'tests/issuers_original_administrator.php'])
        self.assertEqual(hashlib.sha256(json.dumps(commands[1:], separators=(',', ':')).encode()).hexdigest(),
                         'ce9d16a30581ef32234d4696afc1f3d98d6d17d86a0290d80d9e98e9a7b3e7cd')
        self.assertEqual(hashlib.sha256(json.dumps(commands, separators=(',', ':')).encode()).hexdigest(),
                         'a15ee1f915255e533a2ea0ae797f152e44e5dd9874ba68f398e0ce98794dfc99')

    def test_union_exact_once_and_order_preserved(self):
        commands = runner.load_commands()
        shards = [runner.select_commands(commands, i) for i in range(1, 5)]
        self.assertEqual([len(shard) for shard in shards], [118,118,118,118])
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
            self.assertEqual(runner.run([['php', 'a']] * 9, 1, evidence, execute, assignments=[1,2,3,4,1,2,3,4,1]), 9)
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
            self.assertEqual(runner.run(commands, 1, evidence, lambda argv, **kw: subprocess.CompletedProcess(argv, 0), assignments=[1,2,3,4,1,2,3,4,1]), 0)
            self.assertEqual([json.loads(line)['index'] for line in evidence.read_text().splitlines()], [0, 4, 8])
            def missing(*args, **kwargs):
                raise FileNotFoundError('synthetic')
            self.assertEqual(runner.run(commands, 1, evidence, missing, assignments=[1,2,3,4,1,2,3,4,1]), 127)

    def test_external_preflight_and_audit_run_exactly_once_per_php(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        expected = {
            'Validar sintaxe e controles HTTP': [
                ['php', 'tests/installed_master_proof.php'],
                ['php', 'tests/asaas_checkout_transaction_tables.php'],
                ['php', 'tests/efi_install_contract.php'],
                ['php', 'tests/efi_routes.php'],
                ['php', 'tests/efi/operational_contract.php'],
                ['php', 'tests/efi/controller_contract.php'],
                ['php', 'tests/efi/certificate_contract.php'],
                ['php', 'tests/efi_csrf_config.php'],
                ['php', 'tests/efi_native_autoload.php'],
                ['php', 'tests/efi/sdk_transport_contract.php'],
                ['php', 'tests/efi/intent_contract.php'],
                ['php', 'tests/efi_adoption_http.php'],
                ['php', 'tests/efi_checkout_preflight.php'],
                ['php', 'tests/efi/native_accounts_contract.php'],
                ['php', 'tests/efi/native_controller_contract.php'],
                ['php', 'tests/efi/native_proof_contract.php'],
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
                ['php', 'tests/companies/efi_adoption_mysql.php'],
                ['php', 'tests/companies/efi_operational_mysql.php'],
                ['php', 'tests/companies/efi_company_checkout.php'],
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
        self.assertEqual(len(combined), 499)
        self.assertEqual(len(set(map(tuple, combined))), 499)

    def test_operational_inventory_runs_once_for_the_matrix(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        step = source.split('      - name: Validar inventário operacional uma vez\n')[1].split('      - ')[0]
        self.assertEqual(re.findall(r'^        if: (.+)$', step, re.M), ["matrix.shard == 1 && matrix.php == '8.1'"])
        self.assertEqual(step.count('php scripts/company-surface-inventory.php --check'), 1)
        self.assertEqual(step.count('php scripts/company-surface-inventory.php --full'), 1)
        syntax = source.split('      - name: Validar sintaxe e controles HTTP\n')[1].split('      - ')[0]
        self.assertNotIn('company-surface-inventory.php', syntax)
        upload = source.split('          name: company-operational-surfaces\n')[0].rsplit('      - uses: actions/upload-artifact@v4\n', 1)[1]
        self.assertIn("if: matrix.shard == 1 && matrix.php == '8.1'", upload)

    def test_isolated_module_audit_precedes_long_registry_suite(self):
        source = (ROOT / '.github/workflows/issuer-foundation.yml').read_text()
        self.assertLess(source.index('      - name: Testar entradas legadas'),
                        source.index('      - name: Testar schema,'))
        audit = source.split('      - name: Testar entradas legadas')[1].split('      - name: Testar schema,')[0]
        self.assertIn('dbname=issuer_module_audit;', audit)
        self.assertIn('CREATE DATABASE issuer_module_audit', audit)
        self.assertIn('php tests/companies/module_version_column_mysql.php', audit)

    def test_module_column_fixture_matches_persistent_production_table(self):
        source = (ROOT / 'tests/companies/module_version_column_mysql.php').read_text()
        self.assertNotIn('CREATE TEMPORARY TABLE', source)
        self.assertNotIn('DROP TEMPORARY TABLE', source)
        self.assertNotIn('CREATE TABLE IF NOT EXISTS', source)
        self.assertIn('bin2hex(random_bytes(8))', source)
        self.assertIn("$metadata['Extra'] === ''", source)
        self.assertIn('information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', source)
        self.assertIn('if ($created) { $db->query("DROP TABLE IF EXISTS', source)
        self.assertEqual(source.count('$created = false;'), 2)
        self.assertIn('versionColumnSnapshot($db, $table) === $before', source)
        self.assertIn('versionColumnMetadata($db, $table) === $wide', source)

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

    def test_frozen_plan_is_reproducible_and_uses_both_versions(self):
        planner_spec = importlib.util.spec_from_file_location('planner', ROOT / 'scripts/ci/plan-company-registry-shards.py')
        planner = importlib.util.module_from_spec(planner_spec)
        planner_spec.loader.exec_module(planner)
        baseline = json.loads((ROOT / 'tests/ci/company-registry-timing-baseline.json').read_text())
        expected = json.loads(runner.PLAN.read_text())
        self.assertEqual(planner.build_plan(runner.load_commands(), baseline), expected)
        commands = runner.load_commands()
        historical = planner.historical_measured_commands(commands, expected['unmeasured'], baseline)
        old = planner.build_measured_plan(historical, baseline)
        retired = baseline['retired'][0]
        retained_assignments = [assignment for index, assignment in enumerate(old['assignments']) if index != retired['index']]
        self.assertEqual(expected['assignments'][1:], retained_assignments, 'all retained historical placements unchanged')
        self.assertEqual(old['assignments'][retired['index']], 2, 'retired command occupied shard2')
        self.assertEqual(expected['assignments'][0], 2, 'unmeasured command fills retired shard capacity')
        self.assertEqual(expected['retired'], baseline['retired'])
        self.assertEqual(hashlib.sha256((json.dumps(old, indent=2)+'\n').encode()).hexdigest(),
                         '16503a60a61942303257d72386271696d7c05a46c67aef4385041acac521ec6b')
        self.assertEqual([len(t) for t in baseline['milliseconds'].values()], [472,472], 'no invented timing')
        self.assertEqual(retired['argv'], ['php', 'tests/settings/calendar_controller.php'])
        self.assertNotIn(retired['argv'], commands)
        for invalid in [[], expected['unmeasured'] * 2, [{'argv':['php','tests/not_measured.php']}]]:
            with self.assertRaises(ValueError):
                planner.build_plan(commands, baseline, unmeasured=invalid)
        for invalid_commands in [commands + [['php','tests/unregistered.php']], commands[1:], commands[:1]+list(reversed(commands[1:]))]:
            with self.assertRaises(ValueError):
                planner.build_plan(invalid_commands, baseline)

        self.assertEqual(len(baseline['artifacts']), 8)
        for php in ['8.1', '8.5']:
            for shard in range(1, 5):
                artifacts = [a for a in baseline['artifacts'] if a['php'] == php and a['shard'] == shard]
                self.assertEqual(len(artifacts), 1)
                self.assertEqual(artifacts[0]['commands'], 118)
                self.assertEqual(sum(t for i, t in enumerate(baseline['milliseconds'][php]) if i % 4 == shard-1), artifacts[0]['milliseconds'])
                self.assertRegex(artifacts[0]['zip_sha256'], r'^[a-f0-9]{64}$')
        self.assertEqual(planner.balanced_assignment([10] * 8), [1,2,3,4,1,2,3,4])
        changed = json.loads(json.dumps(baseline))
        changed['manifest_sha256'] = 'invalid'
        with self.assertRaises(ValueError):
            planner.build_plan(runner.load_commands(), changed)

    def test_invalid_or_stale_plan_cannot_silently_change_coverage(self):
        commands = runner.load_commands()
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'plan.json'
            path.write_text(json.dumps({'manifest_sha256': 'wrong', 'assignments': []}))
            with self.assertRaises(ValueError):
                runner.load_assignment(commands, path)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'plan.json'
            invalid = json.loads(runner.PLAN.read_text())
            invalid['assignments'][1] = 1  # break 119/118/118/118 capacity
            path.write_text(json.dumps(invalid))
            with self.assertRaises(ValueError):
                runner.load_assignment(commands, path)
        for assignments in [None, [], [1] * (len(commands)-1), [5] * len(commands), [True] * len(commands)]:
            if assignments is None:
                continue
            with self.assertRaises(ValueError):
                runner.select_commands(commands, 1, assignments)

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
