import importlib.util
import hashlib
import json
import re
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts'))
import release_policy as POLICY


def load_script(name, relative):
    spec = importlib.util.spec_from_file_location(name, ROOT / relative)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


PLANNER = load_script('release_version_planner', 'scripts/plan-release-version.py')
APPLIER = load_script('release_version_applier', 'scripts/apply-release-version.py')
CHECKER = load_script('release_version_checker', 'scripts/check-release-version.py')


def fixture(root, level=394, version=None):
    version = version or POLICY.version_for_id(level)
    files = {
        'VERSION': version + '\n',
        'application/config/constants.php': "<?php define('ARGWS_VERSION', '" + version + "');\n",
        'application/config/migration.php': "<?php $config['migration_version'] = " + str(level) + ";\n",
        'compose.yaml': 'image: ghcr.io/wkarts/argws-crm:${ARGWS_VERSION:-' + version + '}\n',
        'deploy/production/compose.yaml': 'image: ${ARGWS_CRM_IMAGE:-ghcr.io/wkarts/argws-crm:' + version + '}\n',
        'deploy/production/.env.example': 'ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:' + version + '\n',
        'container.env.example': 'ARGWS_VERSION=' + version + '\n',
    }
    for path, content in files.items():
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content)
    for number in range(101, level + 1):
        target = root / POLICY.migration_path(number)
        target.parent.mkdir(parents=True, exist_ok=True)
        if not target.exists():
            target.write_text(APPLIER.migration_marker_source(number))


class NativeReleaseMetadataTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        fixture(self.root)

    def test_native_rollover_and_overflow(self):
        for level, version in [(393, '3.9.3'), (399, '3.9.9'), (400, '4.0.0'), (401, '4.0.1')]:
            self.assertEqual(POLICY.version_for_id(level), version)
            self.assertEqual(POLICY.id_for_version(version), level)
        for value in ('3.11.0', '3.9.10', '10.0.0', '03.9.5', '0.0.0'):
            with self.subTest(value=value), self.assertRaises(ValueError):
                POLICY.id_for_version(value)
        with self.assertRaises(ValueError):
            POLICY.version_for_id(1000)

    def test_generator_advances_exactly_one_and_replays_without_touching_history(self):
        old = (self.root / POLICY.migration_path(394)).read_bytes()
        APPLIER.apply_release_version(self.root, '3.9.5')
        APPLIER.apply_release_version(self.root, '3.9.5')
        self.assertEqual(CHECKER.check(self.root, 'v3.9.5')['migration'], 395)
        self.assertEqual((self.root / POLICY.migration_path(394)).read_bytes(), old)
        self.assertFalse((self.root / POLICY.migration_path(396)).exists())

    def test_generator_rolls_399_to_400(self):
        fixture(self.root, 399)
        APPLIER.apply_release_version(self.root, '4.0.0')
        self.assertEqual(CHECKER.check(self.root)['migration'], 400)

    def test_arbitrary_downgrade_skip_and_multidigit_are_rejected_without_writes(self):
        before = (self.root / 'VERSION').read_bytes()
        for target in ('3.9.3', '3.9.6', '3.11.2'):
            with self.subTest(target=target), self.assertRaises(ValueError):
                APPLIER.apply_release_version(self.root, target)
            self.assertEqual((self.root / 'VERSION').read_bytes(), before)

    def test_unknown_legacy_alias_does_not_authorize_transition(self):
        fixture(self.root, 394, '3.11.1')
        with self.assertRaisesRegex(ValueError, 'Alias não comprovado'):
            APPLIER.apply_release_version(self.root, '3.9.5')

    def test_real_pending_migration_is_preserved(self):
        target = self.root / POLICY.migration_path(395)
        content = APPLIER.migration_marker_source(395).replace('// Release marker:', '// Real schema migration:')
        target.write_text(content)
        APPLIER.apply_release_version(self.root, '3.9.5')
        self.assertEqual(target.read_text(), content)

    def test_replay_cannot_recreate_missing_current_history(self):
        (self.root / POLICY.migration_path(394)).unlink()
        with self.assertRaises(ValueError):
            APPLIER.apply_release_version(self.root, '3.9.4')

    def test_checker_rejects_gap_constant_mismatch_and_fake_class(self):
        path = self.root / POLICY.migration_path(347)
        path.unlink()
        with self.assertRaisesRegex(ValueError, 'Lacuna'):
            CHECKER.check(self.root)
        fixture(self.root)
        (self.root / 'application/config/constants.php').write_text("define('ARGWS_VERSION', '3.11.1');")
        with self.assertRaises(ValueError):
            CHECKER.check(self.root)
        fixture(self.root)
        (self.root / POLICY.migration_path(394)).write_text("<?php /* class Migration_Version_394 extends CI_Migration { public function up() {} } */")
        with self.assertRaises(ValueError):
            CHECKER.check(self.root)

    def test_distribution_tags_and_duplicate_settings_are_checked(self):
        (self.root / 'container.env.example').write_text('ARGWS_VERSION=3.9.4\nARGWS_VERSION=3.11.1\n')
        with self.assertRaises(ValueError):
            CHECKER.check(self.root)

    def test_published_alias_allowlist_is_explicit(self):
        self.assertEqual({a['version']: a['migration'] for a in POLICY.aliases()}, {
            '3.8.1': 382, '3.8.2': 383, '3.10.0': 391, '3.10.1': 392, '3.11.0': 393, '3.11.1': 394})
        for alias in POLICY.aliases():
            self.assertRegex(alias['commit'], r'^[0-9a-f]{40}$')
            self.assertRegex(alias['tree'], r'^[0-9a-f]{40}$')
            self.assertRegex(alias['tag_object'], r'^[0-9a-f]{40}$')
            self.assertEqual(len(alias['sha256']), 4)

    def test_full_and_incremental_archives_validate_actual_bytes(self):
        base = {str(p.relative_to(self.root)): p.read_bytes() for p in self.root.rglob('*') if p.is_file()}
        APPLIER.apply_release_version(self.root, '3.9.5')
        full = self.root / 'full.zip'
        delta = self.root / 'delta.zip'
        paths = [p for p in self.root.rglob('*') if p.is_file()]
        with zipfile.ZipFile(full, 'w') as archive, zipfile.ZipFile(delta, 'w') as overlay:
            for p in paths:
                relative = str(p.relative_to(self.root))
                archive.write(p, relative)
                if base.get(relative) != p.read_bytes():
                    overlay.write(p, relative)
        self.assertEqual(POLICY.validate_archive(full, expected_version='3.9.5')['migration'], 395)
        self.assertEqual(POLICY.validate_archive(delta, base_read=base.__getitem__)['migration'], 395)
        with self.assertRaises(ValueError):
            POLICY.validate_archive(full, expected_version='3.11.1')
        bad = self.root / 'bad.zip'
        with zipfile.ZipFile(bad, 'w') as archive:
            for p in paths:
                relative = str(p.relative_to(self.root))
                if relative != POLICY.migration_path(395):
                    archive.write(p, relative)
        with self.assertRaises(ValueError):
            POLICY.validate_archive(bad)


class NativeReleaseGitTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.git('init', '-q')
        self.git('config', 'user.email', 'synthetic@example.invalid')
        self.git('config', 'user.name', 'Synthetic fixture')
        fixture(self.root)
        self.base = self.commit('baseline')

    def git(self, *args):
        return POLICY.git(self.root, *args).decode().strip()

    def commit(self, message):
        self.git('add', '.')
        self.git('commit', '-qm', message)
        return self.git('rev-parse', 'HEAD')

    def advance(self, version='3.9.5'):
        APPLIER.apply_release_version(self.root, version)
        return self.commit('docs: a release still advances native core')

    def alias(self):
        fixture(self.root, 394, '3.11.1')
        commit = self.commit('historical alias')
        self.git('tag', '-a', 'v3.11.1', '-m', 'published alias')
        paths = list(POLICY.METADATA_PATHS) + [POLICY.migration_path(394)]
        return {'version': '3.11.1', 'migration': 394, 'tag': 'v3.11.1',
                'tag_object': self.git('rev-parse', 'v3.11.1'), 'commit': commit,
                'tree': self.git('rev-parse', 'HEAD^{tree}'),
                'sha256': {p: hashlib.sha256((self.root / p).read_bytes()).hexdigest() for p in paths}}

    def test_proven_alias_transition_and_id_order_choose_correct_incremental_base(self):
        self.git('tag', 'v3.9.4')
        # Do not keep two stable versions for the same native ID.
        self.git('tag', '-d', 'v3.9.4')
        alias = self.alias()
        with patch.object(POLICY, 'aliases', return_value=[alias]):
            with self.assertRaises(ValueError):
                APPLIER.apply_release_version(self.root, '3.9.5')
            head = self.advance('4.0.1')
            plan = PLANNER.plan_release(self.root)
            self.assertEqual((plan['version'], plan['previous_tag']), ('4.0.1', 'v3.11.1'))
            self.assertEqual(POLICY.check_delta(self.root, alias['commit'], head)['migration'], 401)
            self.git('tag', '-a', 'v4.0.1', '-m', 'canonical')
            replay = PLANNER.plan_release(self.root)
            self.assertEqual(replay['bump'], 'resume')
            self.assertEqual(replay['previous_tag'], 'v3.11.1')

    def test_alias_same_text_with_different_bytes_or_commit_is_rejected(self):
        alias = self.alias()
        with patch.object(POLICY, 'aliases', return_value=[alias]):
            self.assertEqual(POLICY.git_metadata(self.root, 'refs/tags/v3.11.1')['migration'], 394)
            (self.root / 'README.md').write_text('changed tree')
            newer = self.commit('different source')
            with self.assertRaises(ValueError):
                POLICY.git_metadata(self.root, newer)
            (self.root / 'application/config/constants.php').write_text("<?php define('ARGWS_VERSION', '3.11.1'); // forged\n")
            with self.assertRaises(ValueError):
                APPLIER.apply_release_version(self.root, '3.9.5')

    def test_replaced_annotated_alias_tag_is_rejected(self):
        alias = self.alias()
        self.git('tag', '-d', 'v3.11.1')
        self.git('tag', '-a', 'v3.11.1', '-m', 'replacement')
        with patch.object(POLICY, 'aliases', return_value=[alias]), self.assertRaises(ValueError):
            POLICY.release_tags(self.root)

    def test_docs_or_module_only_without_core_increment_fails(self):
        for path in ('README.md', 'modules/example/example.php'):
            target = self.root / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text('changed')
        head = self.commit('module version alone')
        with self.assertRaisesRegex(ValueError, 'exatamente o próximo'):
            POLICY.check_delta(self.root, self.base, head)

    def test_history_edit_and_mode_change_fail(self):
        self.advance()
        old = self.root / POLICY.migration_path(393)
        old.write_text(old.read_text() + '// modified')
        head = self.commit('rewrite applied history')
        with self.assertRaisesRegex(ValueError, 'histórica'):
            POLICY.check_delta(self.root, self.base, head)

    def test_original_head_not_composed_tree_is_checked(self):
        # A correct working tree cannot disguise an unchanged original PR head.
        APPLIER.apply_release_version(self.root, '3.9.5')
        with self.assertRaises(ValueError):
            POLICY.check_delta(self.root, self.base, self.base)

    def test_base_branch_only_changes_are_not_attributed_to_pr(self):
        head = self.advance()
        self.git('checkout', '-qb', 'other-base', self.base)
        (self.root / 'base-only.txt').write_text('independent')
        base_tip = self.commit('independent base change')
        self.assertEqual(POLICY.check_delta(self.root, base_tip, head)['migration'], 395)

    def test_stale_second_pr_cannot_reuse_401_already_published_by_current_base(self):
        alias = self.alias()
        with patch.object(POLICY, 'aliases', return_value=[alias]):
            first = self.advance('4.0.1')
            self.git('checkout', '-qb', 'second-pr', alias['commit'])
            APPLIER.apply_release_version(self.root, '4.0.1')
            (self.root / 'second-pr.txt').write_text('different code, same NOOP bytes')
            second = self.commit('stale second PR also claims 401')
            with self.assertRaisesRegex(ValueError, 'base atual avançou'):
                POLICY.check_delta(self.root, first, second)

    def test_collision_and_unknown_legacy_tag_fail_closed(self):
        self.git('tag', 'v3.9.4')
        self.advance()
        self.git('tag', 'v3.9.5')
        (self.root / 'README.md').write_text('later change')
        self.commit('same version other commit')
        with self.assertRaisesRegex(ValueError, 'Tag já existe'):
            PLANNER.plan_release(self.root)
        self.git('tag', 'v3.11.9')
        with self.assertRaises(ValueError):
            POLICY.release_tags(self.root)

    def test_alias_copied_to_unrelated_develop_lineage_is_not_authority(self):
        alias = self.alias()
        blobs = {p: (self.root / p).read_bytes() for p in alias['sha256']}
        self.git('checkout', '-qb', 'develop', self.base)
        for path, value in blobs.items():
            (self.root / path).write_bytes(value)
        self.commit('old bot copied metadata without release ancestry')
        with patch.object(POLICY, 'aliases', return_value=[alias]), self.assertRaisesRegex(ValueError, 'ancestral'):
            APPLIER.apply_release_version(self.root, '4.0.1')

    def test_transition_requires_all_intermediate_migrations_and_future_is_plus_one(self):
        alias = self.alias()
        with patch.object(POLICY, 'aliases', return_value=[alias]):
            head = self.advance('4.0.1')
            self.assertEqual(POLICY.check_delta(self.root, alias['commit'], head)['migration'], 401)
            (self.root / POLICY.migration_path(398)).unlink()
            broken = self.commit('missing intermediate')
            with self.assertRaises(ValueError):
                POLICY.check_delta(self.root, alias['commit'], broken)
            self.git('reset', '--hard', head)
            with self.assertRaises(ValueError):
                APPLIER.apply_release_version(self.root, '4.0.3')
            APPLIER.apply_release_version(self.root, '4.0.2')
            next_head = self.commit('next release')
            self.assertEqual(POLICY.check_delta(self.root, head, next_head)['migration'], 402)


class ReleaseWorkflowContractTest(unittest.TestCase):
    def test_no_semver_sort_or_title_bump_and_canonical_is_latest(self):
        workflow = (ROOT / '.github/workflows/release-packages.yml').read_text()
        for forbidden in ('sort -V', '--force', 'PR_LABELS', 'force_bump', 'release-sync'):
            self.assertNotIn(forbidden, workflow)
        self.assertIn('scripts/plan-release-version.py --head', workflow)
        self.assertIn('needs.plan-version.outputs.previous', workflow)
        self.assertIn('gh release edit "$RELEASE_TAG" --latest', workflow)
        self.assertIn('--latest --generate-notes', workflow)
        self.assertIn('-t "${repo}:${version}"', workflow)
        self.assertIn('-t "${repo}:latest"', workflow)
        container = (ROOT / '.github/workflows/container-publish.yml').read_text()
        deployer = (ROOT / '.github/workflows/deployer-release.yml').read_text()
        self.assertNotIn('sort -V', container)
        self.assertNotIn('--latest "$latest" --baseline', container)
        self.assertNotIn('sort_by(ltrimstr', deployer)
        self.assertIn('["releaseTag"]', deployer)

    def test_core_gate_uses_composed_tools_but_original_delta(self):
        source = (ROOT / '.github/workflows/core-release-policy.yml').read_text()
        for required in ('ref: ${{ github.sha }}', 'fetch-depth: 0', 'pull_request.base.sha',
                         'pull_request.head.sha', '--base "$PR_BASE_SHA" --head "$PR_HEAD_SHA"',
                         'native_migration_tracking.php', 'edited'):
            self.assertIn(required, source)
        self.assertNotIn('apply-release-version.py', source)

    def test_version_display_and_downgrade_status_remain_canonical(self):
        source = (ROOT / 'application/views/admin/settings/includes/update.php').read_text()
        self.assertIn("wordwrap((string) $latest_version, 1, '.', true)", source)
        self.assertIn('Versão incluída no código', source)
        self.assertIn('$current_version > $latest_version', source)
        CHECKER.check(ROOT)


class StableReleaseDeployerAssetsTest(unittest.TestCase):
    def test_stable_release_dispatches_and_waits_for_deployer_build(self):
        workflow = (ROOT / ".github/workflows/release-packages.yml").read_text(encoding="utf-8")
        for required in (
            "gh workflow run .github/workflows/deployer-release.yml",
            "--event workflow_dispatch --branch main",
            '[[ "$previous_run" =~ ^[0-9]+$ ]]',
            '--jq "[.[] | select(.databaseId > $previous_run)]',
            '-f release_tag="$RELEASE_TAG"',
            'gh run watch "$run_id" --repo "$GH_REPOSITORY" --exit-status',
            "argws-crm-deployer-gui-linux-x64",
        ):
            with self.subTest(required=required):
                self.assertIn(required, workflow)

    def test_deployer_dispatch_runs_asset_jobs_after_skipped_validation(self):
        workflow = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")
        self.assertIn("pull_request:\n    branches: [main, develop]", workflow)
        self.assertIn("push:\n    branches: [develop, main]", workflow)
        self.assertIn("release:\n    types: [published]", workflow)
        self.assertIn("workflow_dispatch:", workflow)
        self.assertIn("[rebuild deployer]", workflow)
        self.assertIn("refs/heads/main", workflow)
        self.assertIn("always() &&", workflow)
        self.assertIn("needs.build.result == 'success'", workflow)
        self.assertIn("needs.build-gui.result == 'success'", workflow)
        self.assertIn("if: always() && needs.prepare-assets.result == 'success'", workflow)

    def test_deployer_release_contains_cli_and_gui_for_windows_and_linux(self):
        workflow = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")
        for asset in (
            "argws-crm-deployer-win-x64.exe",
            "argws-crm-deployer-linux-x64",
            "argws-crm-deployer-gui-win-x64.exe",
            "argws-crm-deployer-gui-linux-x64",
        ):
            with self.subTest(asset=asset):
                self.assertIn(asset, workflow)
        self.assertIn('gh release upload "$tag"', workflow)
        self.assertNotIn("--argjson", workflow)


if __name__ == "__main__":
    unittest.main()
