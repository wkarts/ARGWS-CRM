import importlib.util
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


class ReleaseVersionMetadataTest(unittest.TestCase):
    def test_version_update_does_not_change_schema_migration_level(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            newline = chr(10)
            (root / "VERSION").write_text("3.4.2" + newline, encoding="utf-8")
            (root / "application/config").mkdir(parents=True)
            (root / "application/config/constants.php").write_text(
                "define('ARGWS_VERSION', '3.4.2');" + newline, encoding="utf-8"
            )
            (root / "application/config/migration.php").write_text(
                "$config['migration_version'] = 342;" + newline, encoding="utf-8"
            )
            image_default = "$" + "{ARGWS_VERSION:-3.4.2}"
            (root / "compose.yaml").write_text(
                "image: ghcr.io/wkarts/argws-crm:" + image_default + newline, encoding="utf-8"
            )
            (root / "deploy/production").mkdir(parents=True)
            (root / "deploy/production/compose.yaml").write_text(
                "image: " + "$" + "{ARGWS_CRM_IMAGE:-ghcr.io/wkarts/argws-crm:3.4.2}" + newline,
                encoding="utf-8",
            )
            (root / "deploy/production/.env.example").write_text(
                "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:3.4.2" + newline,
                encoding="utf-8",
            )

            APPLIER.apply_release_version(root, "3.4.3")

            self.assertEqual((root / "VERSION").read_text(encoding="utf-8"), "3.4.3" + newline)
            self.assertIn("ARGWS_VERSION', '3.4.3'", (root / "application/config/constants.php").read_text())
            self.assertIn("ARGWS_VERSION:-3.4.3", (root / "compose.yaml").read_text())
            self.assertIn("argws-crm:3.4.3", (root / "deploy/production/compose.yaml").read_text())
            self.assertIn(
                "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:3.4.3",
                (root / "deploy/production/.env.example").read_text(),
            )
            self.assertIn("migration_version'] = 342", (root / "application/config/migration.php").read_text())

    def test_migration_version_is_independent_from_semver_product_release(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            newline = chr(10)
            (root / "VERSION").write_text("3.5.0" + newline, encoding="utf-8")
            (root / "application/config").mkdir(parents=True)
            (root / "application/config/constants.php").write_text(
                "define('ARGWS_VERSION', '3.5.0');" + newline, encoding="utf-8"
            )
            (root / "application/config/migration.php").write_text(
                "$config['migration_version'] = 342;" + newline, encoding="utf-8"
            )
            result = subprocess.run(
                ["bash", str(ROOT / "scripts/check-release-version.sh"), "v3.5.0"],
                cwd=root, text=True, capture_output=True, check=False,
            )
            self.assertEqual(result.returncode, 0, result.stderr)


if __name__ == "__main__":
    unittest.main()
