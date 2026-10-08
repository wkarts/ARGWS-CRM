import importlib.util
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("package_release", ROOT / "scripts/package-release.py")
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)


class PackagePrivacyRulesTest(unittest.TestCase):
    def test_secrets_and_per_installation_uploads_are_excluded(self):
        excluded = [
            ".env",
            ".env.production",
            "deploy/develop/compose.yaml",
            "deploy/production/.env.example",
            "tools/argws-crm-deployer/src/main.rs",
            "docker/provision.php",
            "docker/entrypoint.sh",
            "application/config/app-config.php",
            "modules/finance/uploads/ofx/statement.ofx",
            "modules/si_custom_theme/uploads/bg_img_admin_login.png",
            "modules/timesheets/uploads/staff_qrcodes/1.png",
            "modules/invoices_builder/uploads/qrcodes/customer.png",
            "uploads/contracts/client-document.pdf",
        ]
        for file_path in excluded:
            with self.subTest(file_path=file_path):
                self.assertFalse(PACKAGE.should_include(file_path))

    def test_generic_templates_placeholders_and_upload_guards_are_kept(self):
        included = [
            "uploads/contracts/.htaccess",
            "uploads/contracts/index.html",
            "compose.yaml",
            "container.env.example",
            "modules/accounting/uploads/file_sample/Sample_import_account_file_en.xlsx",
            "modules/hr_profile/uploads/sample_file/Sample_import_hrm_staff_file_en.xlsx",
            "modules/purchase/uploads/approval/approved.png",
            "modules/timesheets/uploads/timesheets/import_timesheets.xlsx",
        ]
        for file_path in included:
            with self.subTest(file_path=file_path):
                self.assertTrue(PACKAGE.should_include(file_path))

    def test_runtime_directories_keep_only_guard_files(self):
        self.assertTrue(PACKAGE.should_include("application/logs/index.html"))
        self.assertFalse(PACKAGE.should_include("application/logs/log-2026.php"))
        self.assertTrue(PACKAGE.should_include("application/cache/index.html"))
        self.assertFalse(PACKAGE.should_include("application/cache/session-cache"))

    def test_generated_python_cache_files_are_excluded(self):
        self.assertFalse(PACKAGE.should_include("tests/__pycache__/suite.cpython-312.pyc"))
        self.assertFalse(PACKAGE.should_include(".pytest_cache/v/cache/nodeids"))


if __name__ == "__main__":
    unittest.main()
