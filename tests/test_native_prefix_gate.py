import importlib.util
import tempfile
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('native_prefix_gate', ROOT / 'scripts/check_native_prefixes.py')
GATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GATE)

class NativePrefixGate(unittest.TestCase):
    def test_repository_has_no_company_prefix_basename(self):
        self.assertEqual(GATE.violations(ROOT), [])

    def test_gate_rejects_case_insensitive_runtime_tombstone_name(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / 'tests').mkdir()
            (root / 'tests' / 'Company_legacy.php').write_text('<?php // Removed')
            self.assertEqual(GATE.violations(root), ['tests/Company_legacy.php'])

    def test_gate_excludes_runtime_upload_content_only(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / 'uploads' / 'invoices').mkdir(parents=True)
            (root / 'uploads' / 'invoices' / 'company_private.bin').write_bytes(b'x')
            self.assertEqual(GATE.violations(root), [])

if __name__ == '__main__':
    unittest.main()
