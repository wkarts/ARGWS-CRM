import re
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
MANIFEST = (ROOT / "tools/argws-crm-deployer/Cargo.toml").read_text(encoding="utf-8")
LOCK = (ROOT / "tools/argws-crm-deployer/Cargo.lock").read_text(encoding="utf-8")
MAIN = (ROOT / "tools/argws-crm-deployer/src/main.rs").read_text(encoding="utf-8")
CORE = (ROOT / "tools/argws-crm-deployer/src/core.rs").read_text(encoding="utf-8")
GUI = (ROOT / "tools/argws-crm-deployer/src/gui.rs").read_text(encoding="utf-8")
WORKFLOW = (ROOT / ".github/workflows/deployer-release.yml").read_text(encoding="utf-8")


class DeployerBackendContractTest(unittest.TestCase):
    def test_gui_build_uses_pinned_wgpu_without_eframe_glow_feature(self):
        self.assertIn('version = "=0.31.1"', MANIFEST)
        self.assertIn('features = ["default_fonts", "wgpu", "x11"]', MANIFEST)
        self.assertNotIn('"glow"', MANIFEST)
        self.assertIn('version = "=24.0.0"', MANIFEST)
        for backend in ('"dx12"', '"vulkan-portability"', '"metal"', '"wgsl"'):
            self.assertIn(backend, MANIFEST)
        self.assertIn("options.renderer = eframe::Renderer::Wgpu", GUI)
        self.assertIn("Backends::DX12", GUI)
        self.assertIn("Backends::VULKAN", GUI)
        self.assertIn("Backends::METAL", GUI)

    def test_lockfile_pins_the_resolved_gui_stack(self):
        packages = LOCK.split("[[package]]")
        eframe = next(block for block in packages if re.search(r'name = "eframe"\nversion = "0\.31\.1"', block))
        wgpu = next(block for block in packages if re.search(r'name = "wgpu"\nversion = "24\.0\.0"', block))
        root_package = next(block for block in packages if 'name = "argws-crm-deployer"' in block)
        self.assertIn("egui-wgpu", eframe)
        self.assertIn('"wgpu"', root_package)
        self.assertIn("source = \"registry+https://github.com/rust-lang/crates.io-index\"", wgpu)

    def test_business_core_is_independent_and_shared_by_gui_and_cli(self):
        self.assertNotIn("eframe", CORE)
        self.assertNotIn("egui", CORE)
        self.assertIn("core::generate_stack", MAIN)
        self.assertIn("super::core::{generate_stack, validate, VERSION}", GUI)
        self.assertIn("pub(crate) fn validate", CORE)
        self.assertIn("pub(crate) fn generate_stack", CORE)

    def test_graphical_failure_is_logged_and_directs_user_to_cli(self):
        self.assertIn("record_gui_failure(&error)", MAIN)
        self.assertIn("show_gui_failure_dialog(&message)", MAIN)
        self.assertIn("Nenhuma implantação foi executada", MAIN)
        self.assertIn("argws-crm-deployer-win-x64.exe interactive", MAIN)
        self.assertIn("argws-crm-deployer-linux-x64 interactive", MAIN)
        self.assertIn("ExitCode::from(3)", MAIN)

    def test_cli_interactive_mode_and_operation_logs_do_not_store_arguments(self):
        self.assertIn('"interactive" | "--interactive"', MAIN)
        self.assertIn("--log-file FILE", MAIN)
        self.assertIn('"outcome={} command={}"', MAIN)
        self.assertIn("fn append_operation_log_to(writer: &mut impl Write, command: &str, succeeded: bool)", MAIN)
        self.assertNotIn("write_all(args", MAIN)
        self.assertNotIn("println!(\"{args", MAIN)

    def test_release_ci_locks_dependencies_checks_renderer_and_runs_cli(self):
        self.assertGreaterEqual(WORKFLOW.count("cargo +1.90.0 test --locked"), 4)
        self.assertGreaterEqual(WORKFLOW.count("cargo +1.90.0 build --locked"), 4)
        self.assertIn("cargo +1.90.0 tree --locked --features gui", WORKFLOW)
        self.assertIn("egui_glow|glutin", WORKFLOW)
        self.assertIn("mesa-vulkan-drivers", WORKFLOW)
        self.assertIn('xvfb-run -a "$exe" --gui', WORKFLOW)
        self.assertIn('"$exe" interactive', WORKFLOW)
        self.assertNotIn("LIBGL_ALWAYS_SOFTWARE", WORKFLOW)
        self.assertNotIn("libgl1-mesa-dev", WORKFLOW)


if __name__ == "__main__":
    unittest.main()
