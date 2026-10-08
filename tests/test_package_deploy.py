import importlib.util
import tempfile
import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("package_deploy", ROOT / "scripts/package-deploy.py")
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)


class DeploymentArchiveTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        files = {
            "deploy/README.md": "Instruções",
            "deploy/migrate-storage.sh": "#!/bin/sh\n",
            "deploy/develop/compose.yaml": "image: ghcr.io/wkarts/argws-crm:develop\n",
            "deploy/develop/.env.example": "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:develop\n",
            "deploy/production/compose.yaml": "image: " + "$" + "{ARGWS_CRM_IMAGE:-ghcr.io/wkarts/argws-crm:3.4.2}\n",
            "deploy/production/.env.example": "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:3.4.2\n",
            "deploy/production/.env": "MYSQL_PASSWORD=secret\n",
        }
        for relative, contents in files.items():
            path = self.root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(contents, encoding="utf-8")

    def tearDown(self):
        self.temp.cleanup()

    def test_stable_archive_contains_deploy_tree_and_pins_requested_release(self):
        archive_path = PACKAGE.package_deploy(self.root, self.root / "out", version="3.5.0")
        self.assertEqual(archive_path.name, "ARGWS-CRM-deploy-3.5.0.zip")
        with zipfile.ZipFile(archive_path) as archive:
            self.assertEqual(set(archive.namelist()), {
                "deploy/README.md",
                "deploy/migrate-storage.sh",
                "deploy/develop/compose.yaml",
                "deploy/develop/.env.example",
                "deploy/production/compose.yaml",
                "deploy/production/.env.example",
            })
            self.assertIsNone(archive.testzip())
            self.assertIn("argws-crm:3.5.0", archive.read("deploy/production/compose.yaml").decode())
            self.assertIn(
                "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:3.5.0",
                archive.read("deploy/production/.env.example").decode(),
            )
            self.assertIn("argws-crm:develop", archive.read("deploy/develop/compose.yaml").decode())

    def test_develop_archive_keeps_channel_and_excludes_dotenv_secrets(self):
        archive_path = PACKAGE.package_deploy(self.root, self.root / "out", develop=True)
        self.assertEqual(archive_path.name, "ARGWS-CRM-deploy-develop.zip")
        with zipfile.ZipFile(archive_path) as archive:
            self.assertIn("argws-crm:3.4.2", archive.read("deploy/production/compose.yaml").decode())
            self.assertFalse(any(Path(name).name == ".env" for name in archive.namelist()))

    def test_rejects_invalid_version_and_ambiguous_channel(self):
        with self.assertRaises(ValueError):
            PACKAGE.package_deploy(self.root, self.root / "out", version="03.5.0")
        with self.assertRaises(ValueError):
            PACKAGE.package_deploy(self.root, self.root / "out", version="3.5.0", develop=True)


if __name__ == "__main__":
    unittest.main()
