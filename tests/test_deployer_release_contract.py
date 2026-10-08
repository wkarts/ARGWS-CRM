import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")


class DeployerReleaseContractTest(unittest.TestCase):
    def test_gui_and_cli_assets_are_built_and_attached_to_the_same_release(self):
        for asset in (
            "argws-crm-deployer-win-x64.exe",
            "argws-crm-deployer-linux-x64",
            "argws-crm-deployer-gui-win-x64.exe",
            "argws-crm-deployer-gui-linux-x64",
        ):
            with self.subTest(asset=asset):
                self.assertIn(asset, WORKFLOW)
        self.assertIn("release:\n    types: [published]", WORKFLOW)
        self.assertIn('gh release upload "$tag"', WORKFLOW)
        self.assertIn("--clobber", WORKFLOW)
        self.assertNotIn('gh release create "v', WORKFLOW)

    def test_develop_uses_one_fixed_continuous_prerelease(self):
        self.assertIn('tag="argws-crm-develop"', WORKFLOW)
        self.assertIn('gh release create "$tag"', WORKFLOW)
        self.assertIn("--prerelease", WORKFLOW)
        self.assertIn("--clobber", WORKFLOW)

    def test_checksums_and_manifest_include_gui_assets(self):
        self.assertIn("SHA256SUMS-Deployment.txt", WORKFLOW)
        self.assertIn('"interface": "gui"', WORKFLOW)
        self.assertIn('"interface": "gui-x11"', WORKFLOW)


if __name__ == "__main__":
    unittest.main()
