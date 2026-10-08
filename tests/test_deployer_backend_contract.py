import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MANIFEST = (ROOT / "tools/argws-crm-deployer/Cargo.toml").read_text(encoding="utf-8")
LOCK = (ROOT / "tools/argws-crm-deployer/Cargo.lock").read_text(encoding="utf-8")
MAIN = (ROOT / "tools/argws-crm-deployer/src/main.rs").read_text(encoding="utf-8")
CORE = (ROOT / "tools/argws-crm-deployer/src/core.rs").read_text(encoding="utf-8")
GUI = (ROOT / "tools/argws-crm-deployer-gui/deployer_gui.py").read_text(encoding="utf-8")
WORKFLOW = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")
REQUIREMENTS = (ROOT / "tools/argws-crm-deployer-gui/requirements-build.txt").read_text(encoding="utf-8")


class DeployerBackendContractTest(unittest.TestCase):
    def test_rust_backend_is_cli_only_without_graphics_dependencies(self):
        for source in (MANIFEST, LOCK, MAIN):
            with self.subTest(source=source[:80]):
                self.assertNotRegex(source.lower(), r"eframe|egui|wgpu|glutin|opengl|vulkan")
        self.assertNotIn('gui =', MANIFEST)
        self.assertNotIn("gui_failure_message", MAIN)

    def test_python_gui_uses_standard_tk_and_calls_the_shared_cli(self):
        self.assertIn("import tkinter as tk", GUI)
        self.assertIn("from tkinter import", GUI)
        self.assertIn("subprocess.run(", GUI)
        self.assertIn("core::generate_stack", MAIN)
        self.assertIn("core::validate", MAIN)
        self.assertIn('["version"]', GUI)
        self.assertIn('"generate"', GUI)
        self.assertIn('"validate"', GUI)
        self.assertNotRegex(GUI.lower(), r"eframe|egui|wgpu|glutin|opengl|vulkan|pyopengl")
        self.assertIn("CREATE_NO_WINDOW", GUI)

    def test_business_core_remains_shared_and_independent_of_the_gui(self):
        self.assertNotIn("tkinter", CORE)
        self.assertNotIn("subprocess", CORE)
        self.assertIn("pub(crate) fn validate", CORE)
        self.assertIn("pub(crate) fn generate_stack", CORE)
        self.assertIn("fn run_interactive", MAIN)

    def test_cli_version_and_logs_do_not_capture_arguments(self):
        self.assertIn('"version"', MAIN)
        self.assertIn('"interactive" | "--interactive"', MAIN)
        self.assertIn("--log-file FILE", MAIN)
        self.assertIn('"outcome={} command={}"', MAIN)
        self.assertIn("fn append_operation_log_to(writer: &mut impl Write, command: &str, succeeded: bool)", MAIN)
        self.assertNotIn("write_all(args", MAIN)
        self.assertNotIn('println!("{args', MAIN)
        self.assertIn("fn smoke_test(cli: Path)", GUI)
        self.assertIn("def report_gui_failure(error: Exception)", GUI)

    def test_workflow_builds_and_smoke_tests_gui_and_cli(self):
        self.assertIn("PyInstaller==6.22.3", REQUIREMENTS)
        self.assertIn("cargo +1.90.0 test --locked", WORKFLOW)
        self.assertIn("--smoke-test", WORKFLOW)
        self.assertIn("--ui-smoke-test", WORKFLOW)
        self.assertIn("xvfb-run", WORKFLOW)
        self.assertIn("requirements-build.txt", WORKFLOW)
        self.assertIn("argws-crm-deployer-gui-win-x64.zip", WORKFLOW)
        self.assertIn("argws-crm-deployer-gui-linux-x64.zip", WORKFLOW)


if __name__ == "__main__":
    unittest.main()
