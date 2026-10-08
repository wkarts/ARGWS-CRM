import importlib.util
import re
import subprocess
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def load_script(name, relative):
    spec = importlib.util.spec_from_file_location(name, ROOT / relative)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


PLANNER = load_script("release_version_planner", "scripts/plan-release-version.py")
APPLIER = load_script("release_version_applier", "scripts/apply-release-version.py")


class SemanticReleasePlanTest(unittest.TestCase):
    def test_first_release_uses_configured_argws_baseline(self):
        self.assertEqual(
            PLANNER.plan_release("", "3.4.2", title="feat(argws-crm): primeira versão"),
            {"version": "3.4.2", "bump": "initial", "previous": None},
        )

    def test_auto_uses_conventional_pr_title(self):
        self.assertEqual(PLANNER.plan_release("v3.4.2", "3.4.2", title="fix(login): corrigir sessão")["version"], "3.4.3")
        self.assertEqual(PLANNER.plan_release("v3.4.2", "3.4.2", title="feat(crm): novo recurso")["version"], "3.5.0")
        self.assertEqual(PLANNER.plan_release("v3.4.2", "3.4.2", title="feat(api)!: alterar contrato")["version"], "4.0.0")

    def test_version_labels_and_manual_override_select_bump(self):
        self.assertEqual(PLANNER.plan_release("3.4.2", "3.4.2", labels="version:major")["version"], "4.0.0")
        self.assertEqual(PLANNER.plan_release("3.4.2", "3.4.2", title="fix: correção", force="minor")["version"], "3.5.0")

    def test_pending_or_interrupted_release_reuses_version(self):
        self.assertEqual(
            PLANNER.plan_release("v3.4.2", "3.5.0", title="feat: manter"),
            {"version": "3.5.0", "bump": "resume", "previous": "3.4.2"},
        )
        self.assertEqual(PLANNER.plan_release("v3.4.2", "3.4.2", resume=True)["bump"], "resume")

    def test_conflicting_version_labels_and_stale_baseline_are_rejected(self):
        with self.assertRaises(ValueError):
            PLANNER.plan_release("v3.4.2", "3.4.2", labels="version:minor,version:major")
        with self.assertRaises(ValueError):
            PLANNER.plan_release("v3.5.0", "3.4.2")


class ReleaseWorkflowTagRegexTest(unittest.TestCase):
    def test_semver_tag_filters_match_existing_release_tags(self):
        workflow = (ROOT / ".github/workflows/release-packages.yml").read_text(encoding="utf-8")
        patterns = re.findall(r"grep -E '([^']+)'", workflow)
        semver_patterns = [pattern for pattern in patterns if pattern.startswith("^v")]
        self.assertEqual(len(semver_patterns), 2)
        for pattern in semver_patterns:
            with self.subTest(pattern=pattern):
                matcher = re.compile(pattern)
                self.assertIsNotNone(matcher.fullmatch("v3.4.2"))
                self.assertIsNotNone(matcher.fullmatch("v3.5.0"))
                self.assertIsNone(matcher.fullmatch("v3.4"))

class ReleaseVersionMetadataTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        (self.root / "application/config").mkdir(parents=True)
        (self.root / "deploy/production").mkdir(parents=True)
        (self.root / "container.env.example").write_text("ARGWS_VERSION=3.4.2\n", encoding="utf-8")
        (self.root / "application/migrations").mkdir(parents=True)
        self._write_fixture_version("3.4.2", 342)

    def tearDown(self):
        self.temp.cleanup()

    def _write_fixture_version(self, version, migration, fill_history=True):
        (self.root / "VERSION").write_text(version + "\n", encoding="utf-8")
        (self.root / "application/config/constants.php").write_text(
            "define('ARGWS_VERSION', '" + version + "');\n", encoding="utf-8"
        )
        (self.root / "application/config/migration.php").write_text(
            "$config['migration_version'] = " + str(migration) + ";\n", encoding="utf-8"
        )
        if fill_history:
            for level in range(101, migration + 1):
                (self.root / "application/migrations" / f"{level}_version_{level}.php").write_text(
                    "<?php\nclass Migration_Version_" + str(level) + " extends CI_Migration {}\n",
                    encoding="utf-8",
                )
        image_default = "$" + "{ARGWS_VERSION:-" + version + "}"
        (self.root / "compose.yaml").write_text(
            "services:\n"
            "  storage-init:\n"
            "    image: ghcr.io/wkarts/argws-crm:" + image_default + "\n"
            "  web:\n"
            "    image: ghcr.io/wkarts/argws-crm:" + image_default + "\n",
            encoding="utf-8",
        )
        production_default = "$" + "{ARGWS_CRM_IMAGE:-ghcr.io/wkarts/argws-crm:" + version + "}"
        (self.root / "deploy/production/compose.yaml").write_text(
            "services:\n"
            "  storage-init:\n"
            "    image: " + production_default + "\n"
            "  web:\n"
            "    image: " + production_default + "\n",
            encoding="utf-8",
        )
        (self.root / "deploy/production/.env.example").write_text(
            "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:" + version + "\n", encoding="utf-8"
        )

    def test_each_new_release_advances_semver_migration_and_keeps_history(self):
        old_migration = self.root / "application/migrations/342_version_342.php"
        old_source = old_migration.read_text(encoding="utf-8")
        APPLIER.apply_release_version(self.root, "3.5.0")
        self.assertEqual((self.root / "VERSION").read_text(encoding="utf-8"), "3.5.0\n")
        self.assertIn("ARGWS_VERSION', '3.5.0'", (self.root / "application/config/constants.php").read_text())
        self.assertIn("migration_version'] = 350", (self.root / "application/config/migration.php").read_text())
        self.assertEqual((self.root / "container.env.example").read_text(encoding="utf-8"), "ARGWS_VERSION=3.5.0\n")
        root_compose = (self.root / "compose.yaml").read_text(encoding="utf-8")
        production_compose = (self.root / "deploy/production/compose.yaml").read_text(encoding="utf-8")
        self.assertEqual(root_compose.count("${ARGWS_VERSION:-3.5.0}"), 2)
        self.assertEqual(production_compose.count("ghcr.io/wkarts/argws-crm:3.5.0}"), 2)
        for migration in range(343, 351):
            marker = self.root / f"application/migrations/{migration}_version_{migration}.php"
            self.assertTrue(marker.exists(), f"migration intermediária ausente: {migration}")
            self.assertIn(f"class Migration_Version_{migration} extends CI_Migration", marker.read_text(encoding="utf-8"))
        marker_source = (self.root / "application/migrations/350_version_350.php").read_text(encoding="utf-8")
        self.assertIn("function up(): void", marker_source)
        self.assertIn("function down(): void", marker_source)
        self.assertEqual(old_migration.read_text(encoding="utf-8"), old_source)

    def test_existing_real_migration_is_preserved(self):
        real_migration = self.root / "application/migrations/345_version_345.php"
        real_source = (
            "<?php\\nclass Migration_Version_345 extends CI_Migration { "
            "public function up() { $this->db->query('SELECT 1'); } "
            "public function down() {} }\\n"
        )
        real_migration.write_text(real_source, encoding="utf-8")
        APPLIER.apply_release_version(self.root, "3.5.0")
        self.assertEqual(real_migration.read_text(encoding="utf-8"), real_source)

    def test_release_application_is_idempotent_and_does_not_increment_twice(self):
        APPLIER.apply_release_version(self.root, "3.5.0")
        APPLIER.apply_release_version(self.root, "3.5.0")
        self.assertIn("migration_version'] = 350", (self.root / "application/config/migration.php").read_text())
        self.assertTrue((self.root / "application/migrations/350_version_350.php").exists())
        self.assertFalse((self.root / "application/migrations/351_version_351.php").exists())

    def test_next_patch_advances_to_its_semver_migration(self):
        APPLIER.apply_release_version(self.root, "3.5.0")
        APPLIER.apply_release_version(self.root, "3.5.1")
        self.assertIn("migration_version'] = 351", (self.root / "application/config/migration.php").read_text())
        self.assertTrue((self.root / "application/migrations/351_version_351.php").exists())

    def test_multi_digit_semver_keeps_migration_levels_monotonic(self):
        self._write_fixture_version("3.9.10", 3910, fill_history=False)
        (self.root / "application/migrations/342_version_342.php").unlink()
        (self.root / "application/migrations/3910_version_3910.php").write_text(
            "<?php\nclass Migration_Version_3910 extends CI_Migration {}\n", encoding="utf-8"
        )
        APPLIER.apply_release_version(self.root, "3.10.0")
        self.assertIn("migration_version'] = 3911", (self.root / "application/config/migration.php").read_text())
        self.assertTrue((self.root / "application/migrations/3911_version_3911.php").exists())

    def test_release_checker_requires_the_configured_migration_file(self):
        APPLIER.apply_release_version(self.root, "3.5.0")
        result = subprocess.run(
            ["bash", str(ROOT / "scripts/check-release-version.sh"), "v3.5.0"],
            cwd=self.root, text=True, capture_output=True, check=False,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        (self.root / "application/migrations/347_version_347.php").unlink()
        result = subprocess.run(
            ["bash", str(ROOT / "scripts/check-release-version.sh"), "v3.5.0"],
            cwd=self.root, text=True, capture_output=True, check=False,
        )
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("lacuna", result.stderr.lower())
        self.assertIn("350", result.stderr)


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
        self.assertIn("push:\n    branches: [develop]", workflow)
        self.assertIn("release:\n    types: [published]", workflow)
        self.assertIn("workflow_dispatch:", workflow)
        self.assertNotIn("refs/heads/main", workflow)
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
