import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")
RELEASE_WORKFLOW = (ROOT / ".github/workflows/release-packages.yml").read_text(encoding="utf-8")
REQUIREMENTS = (ROOT / "tools/argws-crm-deployer-gui/requirements-build.txt").read_text(encoding="utf-8")


class DeployerReleaseContractTest(unittest.TestCase):
    def test_cli_gui_and_complete_gui_bundles_are_attached_to_the_same_release(self):
        for asset in (
            "argws-crm-deployer-win-x64.exe",
            "argws-crm-deployer-linux-x64",
            "argws-crm-deployer-gui-win-x64.exe",
            "argws-crm-deployer-gui-linux-x64",
            "argws-crm-deployer-gui-win-x64.zip",
            "argws-crm-deployer-gui-linux-x64.zip",
        ):
            with self.subTest(asset=asset):
                self.assertIn(asset, WORKFLOW)
                self.assertIn(asset, RELEASE_WORKFLOW)
        self.assertIn("release:\n    types: [published]", WORKFLOW)
        self.assertIn('gh release upload "$tag"', WORKFLOW)
        self.assertIn("--clobber", WORKFLOW)
        self.assertNotIn('gh release create "v', WORKFLOW)

    def test_main_push_does_not_rebuild_assets_from_the_previous_release(self):
        self.assertIn("push:\n    branches: [develop, main]", WORKFLOW)
        self.assertIn("[rebuild deployer]", WORKFLOW)
        self.assertIn("refs/heads/main", WORKFLOW)
        self.assertIn("release_tag=", WORKFLOW)
        self.assertIn("release:\n    types: [published]", WORKFLOW)
        self.assertIn("workflow_dispatch:", WORKFLOW)

    def test_dispatch_uses_current_packager_and_release_tag_for_binaries(self):
        prepare = WORKFLOW.split("  prepare-assets:", 1)[1].split("  publish:", 1)[0]
        checkout = prepare.split("      - uses: actions/checkout@v5", 1)[1].split("      - uses: actions/download-artifact", 1)[0]
        self.assertIn("github.sha }}", checkout)
        self.assertNotIn("inputs.release_tag", checkout)
        build = WORKFLOW.split("  build:", 1)[1].split("  build-gui:", 1)[0]
        gui = WORKFLOW.split("  build-gui:", 1)[1].split("  prepare-assets:", 1)[0]
        for job in (build, gui):
            self.assertIn("github.event_name == 'workflow_dispatch' && inputs.release_tag", job)
    def test_develop_keeps_one_fixed_continuous_prerelease(self):
        self.assertIn('tag="argws-crm-develop"', WORKFLOW)
        self.assertIn('gh release create "$tag"', WORKFLOW)
        self.assertIn("--prerelease", WORKFLOW)
        self.assertIn("--clobber", WORKFLOW)

    def test_checksums_manifest_and_release_gate_cover_gui_bundles(self):
        self.assertIn("SHA256SUMS-Deployment.txt", WORKFLOW)
        self.assertIn('"interface": "gui"', WORKFLOW)
        self.assertIn('"interface": "gui-x11"', WORKFLOW)
        self.assertIn("guiBundles", WORKFLOW)
        self.assertIn("SHA256SUMS-Deployment.txt", RELEASE_WORKFLOW)
        self.assertIn("argws-crm-deployer-release.json", RELEASE_WORKFLOW)

    def test_no_graphics_backend_is_built_or_published(self):
        self.assertNotIn("--features gui", WORKFLOW)
        self.assertNotIn("egui_glow", WORKFLOW)
        self.assertIn("PyInstaller==6.22.3", REQUIREMENTS)


if __name__ == "__main__":
    unittest.main()
