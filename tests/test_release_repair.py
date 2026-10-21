import contextlib
import importlib.util
import io
import json
import subprocess
import sys
import tarfile
import tempfile
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts'))
import release_policy as POLICY
import release_repair as REPAIR
from tests.test_release_version import fixture

SPEC = importlib.util.spec_from_file_location('package_repair_under_test', ROOT / 'scripts/package-release.py')
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)


class HistoricalRepairTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.work = Path(self.temp.name)
        self.repo = self.work / 'repo'
        self.repo.mkdir()
        self.git('init', '-q')
        self.git('config', 'gc.auto', '0')
        self.git('config', 'maintenance.auto', 'false')
        self.git('config', 'user.email', 'synthetic@example.invalid')
        self.git('config', 'user.name', 'Synthetic fixture')
        (self.repo / 'README.md').write_text('repository bootstrap\n')
        self.bootstrap = self.commit('bootstrap without CRM metadata')
        (self.repo / '.github').mkdir()
        (self.repo / '.github/first-release-base.txt').write_text(self.bootstrap + '\n')
        fixture(self.repo, 342)
        self.first = self.commit('first canonical')
        self.git('tag', 'v3.4.2')
        self.bootstrap_evidence = {
            'tag': 'v3.4.2', 'commit': self.first,
            'tree': self.git('rev-parse', self.first + '^{tree}'),
            'tag_object': self.git('rev-parse', 'refs/tags/v3.4.2'),
            'base': self.bootstrap,
        }
        self.bootstrap_patch = patch.object(REPAIR, 'BOOTSTRAP_EVIDENCE', self.bootstrap_evidence)
        self.bootstrap_patch.start()
        self.addCleanup(self.bootstrap_patch.stop)
        fixture(self.repo, 382, '3.8.1')
        (self.repo / 'README.md').write_text('historical content\n')
        self.second = self.commit('historical alias')
        self.git('tag', '-a', 'v3.8.1', '-m', 'published alias')
        import hashlib
        paths = list(POLICY.METADATA_PATHS) + [POLICY.migration_path(382)]
        self.alias = {
            'version': '3.8.1', 'migration': 382, 'tag': 'v3.8.1',
            'tag_object': self.git('rev-parse', 'refs/tags/v3.8.1'),
            'commit': self.second, 'tree': self.git('rev-parse', 'HEAD^{tree}'),
            'sha256': {p: hashlib.sha256((self.repo / p).read_bytes()).hexdigest() for p in paths},
        }
        fixture(self.repo, 401)
        self.commit('current canonical tools context')
        self.git('tag', 'v4.0.1')
        self.alias_patch = patch.object(POLICY, 'aliases', return_value=[self.alias])
        self.alias_patch.start()
        self.addCleanup(self.alias_patch.stop)
        self.root_patch = patch.object(PACKAGE, 'ROOT', self.repo)
        self.root_patch.start()
        self.addCleanup(self.root_patch.stop)

    def git(self, *args):
        return POLICY.git(self.repo, *args).decode().strip()

    def commit(self, message):
        self.git('add', '.')
        self.git('commit', '-qm', message)
        return self.git('rev-parse', 'HEAD')

    def extract(self, ref, name):
        folder = self.work / name
        folder.mkdir(exist_ok=True)
        raw = subprocess.check_output(['git', '-C', str(self.repo), 'archive', ref])
        with tarfile.open(fileobj=io.BytesIO(raw)) as archive:
            archive.extractall(folder, filter='data')
        return folder

    def run_package(self, tag, *args):
        source = self.extract(tag, 'source')
        output = self.work / 'output'
        argv = ['package-release.py', '--source-dir', str(source), '--repair-tag', tag,
                '--output-dir', str(output), *map(str, args)]
        with patch.object(sys, 'argv', argv), contextlib.redirect_stdout(io.StringIO()):
            PACKAGE.main()
        return source, output

    def test_first_release_builds_full_and_overlay_from_exact_bootstrap(self):
        plan = REPAIR.plan_repair(self.repo, 'v3.4.2')
        self.assertEqual(plan['base_kind'], 'bootstrap')
        self.assertEqual(plan['base_sha'], self.bootstrap)
        baseline = self.extract(self.bootstrap, 'base')
        self.assertFalse((baseline / 'VERSION').exists())
        _, output = self.run_package('v3.4.2', '--base-dir', baseline)
        self.assertEqual(sorted(p.name for p in output.iterdir()),
                         ['ARGWS-CRM-3.4.2-SHA256SUMS.txt', 'ARGWS-CRM-3.4.2-full.zip',
                          'ARGWS-CRM-3.4.2-update.zip'])
        for suffix in ('full', 'update'):
            self.assertEqual(POLICY.validate_archive(output / f'ARGWS-CRM-3.4.2-{suffix}.zip',
                base_read=POLICY.file_reader(baseline))['migration'], 342)
        with zipfile.ZipFile(output / 'ARGWS-CRM-3.4.2-update.zip') as archive:
            self.assertIn(self.bootstrap, archive.read('UPDATE-MANIFEST.md').decode())

    def test_first_release_requires_verified_bootstrap_for_overlay(self):
        with self.assertRaises(SystemExit):
            self.run_package('v3.4.2')
        self.assertFalse((self.work / 'output').exists())

    def test_bootstrap_configuration_or_tag_replacement_is_rejected(self):
        path = self.repo / '.github/first-release-base.txt'
        path.write_text(self.first + '\n')
        with self.assertRaisesRegex(ValueError, 'evidência histórica exata'):
            REPAIR.plan_repair(self.repo, 'v3.4.2')
        path.write_text(self.bootstrap + '\n')
        self.git('tag', '-d', 'v3.4.2')
        self.git('tag', '-a', 'v3.4.2', self.first, '-m', 'replacement')
        with self.assertRaisesRegex(ValueError, 'evidência histórica exata'):
            REPAIR.plan_repair(self.repo, 'v3.4.2')

    def test_bootstrap_without_ancestry_is_rejected(self):
        orphan = self.git('commit-tree', self.git('rev-parse', self.bootstrap + '^{tree}'), '-m', 'unrelated')
        (self.repo / '.github/first-release-base.txt').write_text(orphan + '\n')
        with patch.object(REPAIR, 'BOOTSTRAP_EVIDENCE', dict(self.bootstrap_evidence, base=orphan)):
            with self.assertRaisesRegex(ValueError, 'ancestralidade'):
                REPAIR.plan_repair(self.repo, 'v3.4.2')

    def test_tampered_bootstrap_bytes_are_rejected(self):
        baseline = self.extract(self.bootstrap, 'base')
        path = baseline / 'README.md'
        original = path.read_bytes()
        for mutation in ('bytes', 'mode'):
            with self.subTest(mutation=mutation):
                if mutation == 'bytes':
                    path.write_text('tampered')
                else:
                    path.chmod(0o755)
                with self.assertRaisesRegex(ValueError, 'Conteúdo/modo'):
                    self.run_package('v3.4.2', '--base-dir', baseline)
                self.assertFalse((self.work / 'output').exists())
                path.write_bytes(original)
                path.chmod(0o644)

    def test_proven_alias_packages_full_and_overlay_without_injected_tools(self):
        baseline = self.extract('v3.4.2', 'base')
        source, output = self.run_package('v3.8.1', '--base-dir', baseline)
        self.assertFalse((source / 'scripts').exists())
        for suffix in ('full', 'update'):
            archive = output / f'ARGWS-CRM-3.8.1-{suffix}.zip'
            base_read = POLICY.file_reader(baseline) if suffix == 'update' else None
            info = POLICY.validate_archive(archive, base_read=base_read, allow_legacy=True)
            self.assertEqual((info['version'], info['migration']), ('3.8.1', 382))
            with self.assertRaises(ValueError):
                POLICY.validate_archive(archive, base_read=base_read)

    def test_current_release_uses_legacy_base_by_native_id(self):
        plan = REPAIR.plan_repair(self.repo, 'v4.0.1')
        self.assertEqual(plan['base_ref'], 'refs/tags/v3.8.1')
        self.assertEqual(plan['base_sha'], self.second)
        baseline = self.extract('v3.8.1', 'base')
        _, output = self.run_package('v4.0.1', '--base-dir', baseline)
        self.assertEqual(POLICY.validate_archive(output / 'ARGWS-CRM-4.0.1-update.zip',
            base_read=POLICY.file_reader(baseline))['migration'], 401)

    def test_replaced_alias_tag_is_rejected(self):
        self.git('tag', '-d', 'v3.8.1')
        self.git('tag', '-a', 'v3.8.1', self.second, '-m', 'replacement object')
        with self.assertRaisesRegex(ValueError, 'objeto da tag'):
            REPAIR.plan_repair(self.repo, 'v3.8.1')

    def test_source_bytes_paths_and_modes_are_proven(self):
        source = self.extract('v3.8.1', 'source')
        readme = source / 'README.md'
        original = readme.read_bytes()
        for mutation in ('bytes', 'mode', 'injected_tool'):
            with self.subTest(mutation=mutation):
                if mutation == 'bytes':
                    readme.write_text('unpublished content')
                elif mutation == 'mode':
                    readme.chmod(0o755)
                else:
                    (source / 'scripts').mkdir()
                    (source / 'scripts/package-release.py').write_text('new tooling')
                with self.assertRaises(ValueError):
                    PACKAGE.verify_repair_tree(source, self.second, PACKAGE.current_files(source))
                readme.write_bytes(original)
                readme.chmod(0o644)

    def test_wrong_base_or_missing_metadata_fails_before_output(self):
        baseline = self.extract('v4.0.1', 'base')
        with self.assertRaises(ValueError):
            self.run_package('v3.8.1', '--base-dir', baseline)
        self.assertFalse((self.work / 'output').exists())

    def test_default_packaging_still_rejects_alias_without_verified_repair(self):
        source = self.extract('v3.8.1', 'source')
        with patch.object(sys, 'argv', ['package-release.py', '--source-dir', str(source),
                '--output-dir', str(self.work / 'output')]), self.assertRaises(ValueError):
            PACKAGE.main()
        self.assertFalse((self.work / 'output').exists())

    def test_normal_packaging_does_not_require_repair_helper(self):
        import shutil
        normal = self.work / 'normal'
        normal.mkdir()
        fixture(normal, 101)
        baseline = self.work / 'normal-base'
        shutil.copytree(normal, baseline)
        fixture(normal, 102)
        (normal / 'scripts').mkdir()
        for name in ('package-release.py', 'release_policy.py', 'legacy-release-aliases.json'):
            shutil.copy2(ROOT / 'scripts' / name, normal / 'scripts' / name)
        baseline_zip = self.work / 'normal-base.zip'
        with zipfile.ZipFile(baseline_zip, 'w') as archive:
            for path in baseline.rglob('*'):
                if path.is_file():
                    archive.write(path, path.relative_to(baseline))
        for flag, value in [('--base-dir', baseline), ('--base-zip', baseline_zip)]:
            with self.subTest(flag=flag):
                output = self.work / ('normal-output-' + flag[2:])
                subprocess.run([sys.executable, str(normal / 'scripts/package-release.py'),
                                flag, str(value), '--output-dir', str(output)],
                               check=True, capture_output=True)
                self.assertFalse((normal / 'scripts/release_repair.py').exists())
                for suffix in ('full', 'update'):
                    self.assertEqual(POLICY.validate_archive(output / f'ARGWS-CRM-1.0.2-{suffix}.zip',
                        base_read=POLICY.file_reader(baseline))['migration'], 102)

    def test_missing_base_metadata_still_fails_outside_proven_bootstrap(self):
        source = self.extract('v3.4.2', 'source')
        baseline = self.extract(self.bootstrap, 'base')
        with patch.object(sys, 'argv', ['package-release.py', '--source-dir', str(source),
                '--base-dir', str(baseline), '--output-dir', str(self.work / 'output')]):
            with self.assertRaisesRegex(ValueError, 'Arquivo regular ausente: VERSION'):
                PACKAGE.main()
        self.assertFalse((self.work / 'output').exists())

    def test_workflow_retains_triggers_and_publication_contract(self):
        text = (ROOT / '.github/workflows/repair-release-assets.yml').read_text()
        self.assertIn('  push:', text)
        self.assertIn('  workflow_dispatch:', text)
        self.assertIn('contents: write', text)
        self.assertIn('--clobber', text)
        self.assertIn('gh release upload', text)
        self.assertIn('gh release edit', text)
        self.assertNotIn('install -D scripts/package-release.py', text)
        self.assertNotIn('working-directory: ${{ runner.temp }}/argws-release-source', text)
        self.assertIn('--source-dir', text)
        self.assertIn('--repair-tag', text)
        self.assertNotIn('      - scripts/release_policy.py', text)
        self.assertNotIn('      - scripts/legacy-release-aliases.json', text)


if __name__ == '__main__':
    unittest.main()
