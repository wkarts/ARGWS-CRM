"""Versioned module migration contract for the CRM modules introduced by this work."""
import re
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MODULES = ("issuers", "pige_bridge")


def validate_module(root, name):
    module = root / "modules" / name
    if not module.is_dir():
        return []  # Independent PRs do not require each other's optional module.
    errors = []
    header = (module / (name + ".php")).read_text()
    version = re.search(r"^Version:\s*(\d+)\.(\d+)\.(\d+)\s*$", header, re.M)
    if not version:
        return [name + ": missing numeric module version"]
    level = "".join(version.groups())
    if len(level) != 3:
        errors.append(name + ": native sequential migration needs a three-digit version")
    migrations = module / "migrations"
    path = migrations / (level + "_version_" + level + ".php")
    if not migrations.is_dir() or not path.is_file():
        errors.append(name + ": missing migration matching header " + level)
    else:
        code = path.read_text()
        if not re.search(r"class\s+Migration_Version_" + level + r"\s+extends\s+App_module_migration\b", code):
            errors.append(name + ": migration must extend App_module_migration")
        if not re.search(r"function\s+up\s*\(", code):
            errors.append(name + ": migration needs up(), including a documented no-op")
    numbers = sorted(int(p.name[:3]) for p in migrations.glob("[0-9][0-9][0-9]_*.php"))
    if not numbers or numbers[0] != 101:
        errors.append(name + ": first migration must be 101 (module 1.0.1)")
    if numbers and numbers != list(range(numbers[0], numbers[-1] + 1)):
        errors.append(name + ": native sequential migrations cannot contain gaps")
    install = (module / "install.php").read_text()
    if re.search(r"\b(CREATE|ALTER|DROP|TRUNCATE)\s+TABLE\b|Schema::(?:statements|documentStatements)", install, re.I):
        errors.append(name + ": install.php must not own schema DDL")
    if "new App_module_migration(" not in install or "->to_latest()" not in install:
        errors.append(name + ": install.php must call the native migration executor")
    return errors


class ModuleMigrationConvention(unittest.TestCase):
    def test_present_modules_follow_convention(self):
        self.assertTrue(any((ROOT / "modules" / name).is_dir() for name in MODULES))
        self.assertEqual([], [error for name in MODULES for error in validate_module(ROOT, name)])

    def fixture(self, directory):
        root = Path(directory)
        module = root / "modules" / "issuers"
        (module / "migrations").mkdir(parents=True)
        (module / "issuers.php").write_text("<?php\n/*\nVersion: 1.0.1\n*/\n")
        (module / "install.php").write_text("<?php\n$m = new App_module_migration('issuers'); $m->to_latest();\n")
        (module / "migrations" / "101_version_101.php").write_text("<?php\nclass Migration_Version_101 extends App_module_migration { public function up() {} }\n")
        return root, module

    def test_noop_still_requires_versioned_file(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            self.assertEqual([], validate_module(root, "issuers"))
            (module / "migrations" / "101_version_101.php").unlink()
            self.assertTrue(validate_module(root, "issuers"))

    def test_core_migration_base_is_not_module_base(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            path = module / "migrations" / "101_version_101.php"
            path.write_text(path.read_text().replace("App_module_migration", "CI_Migration"))
            self.assertTrue(validate_module(root, "issuers"))

    def test_install_ddl_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            path = module / "install.php"
            path.write_text(path.read_text() + "$db->query('CREATE TABLE example(id INT)');")
            self.assertTrue(validate_module(root, "issuers"))

    def test_migration_sequence_gap_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            (module / "issuers.php").write_text("<?php\n/*\nVersion: 1.1.0\n*/\n")
            (module / "migrations" / "110_version_110.php").write_text("<?php\nclass Migration_Version_110 extends App_module_migration { public function up() {} }\n")
            self.assertTrue(any("gaps" in error for error in validate_module(root, "issuers")))

    def test_initial_011_and_100_are_rejected(self):
        for level, version in (("011", "0.1.1"), ("100", "1.0.0")):
            with self.subTest(level=level), tempfile.TemporaryDirectory() as directory:
                root, module = self.fixture(directory)
                (module / "migrations" / "101_version_101.php").unlink()
                (module / "issuers.php").write_text("<?php\n/*\nVersion: " + version + "\n*/\n")
                (module / "migrations" / (level + "_version_" + level + ".php")).write_text(
                    "<?php\nclass Migration_Version_" + level + " extends App_module_migration { public function up() {} }\n")
                self.assertTrue(any("first migration" in error for error in validate_module(root, "issuers")))

    def test_contiguous_102_is_accepted_without_100(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            (module / "issuers.php").write_text("<?php\n/*\nVersion: 1.0.2\n*/\n")
            (module / "migrations" / "102_version_102.php").write_text("<?php\nclass Migration_Version_102 extends App_module_migration { public function up() {} }\n")
            self.assertEqual([], validate_module(root, "issuers"))

    def test_later_version_cannot_omit_first_101(self):
        with tempfile.TemporaryDirectory() as directory:
            root, module = self.fixture(directory)
            (module / "issuers.php").write_text("<?php\n/*\nVersion: 1.0.2\n*/\n")
            (module / "migrations" / "101_version_101.php").rename(module / "migrations" / "102_version_102.php")
            (module / "migrations" / "102_version_102.php").write_text("<?php\nclass Migration_Version_102 extends App_module_migration { public function up() {} }\n")
            self.assertTrue(any("first migration" in error for error in validate_module(root, "issuers")))

    def test_absent_optional_module_is_not_dependency(self):
        with tempfile.TemporaryDirectory() as directory:
            self.assertEqual([], validate_module(Path(directory), "pige_bridge"))


if __name__ == "__main__":
    unittest.main()
