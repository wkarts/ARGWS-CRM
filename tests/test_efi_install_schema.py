"""Fresh installation and upgrade use byte-equivalent Efí/adoption table definitions."""
import re
import unittest
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

class EfiSchema(unittest.TestCase):
    def test_seed_matches_actual_migration_definitions(self):
        migration = (ROOT / 'application/migrations/473_version_473.php').read_text()
        seed = (ROOT / 'install/database.sql').read_text()
        tables = re.findall(r'CREATE TABLE IF NOT EXISTS `\{\$table\}` \(.*?COLLATE=utf8mb4_bin', migration, re.S)
        self.assertEqual(len(tables), 3)
        for ddl, name in zip(tables, ['tblmodule_version_adoptions', 'tblefi_charge_bindings', 'tblefi_charge_intents']):
            expected = ddl.replace('CREATE TABLE IF NOT EXISTS', 'CREATE TABLE').replace('{$table}', name)
            actual = re.findall(r'CREATE TABLE `' + name + r'` \(.*?;', seed, re.S)
            self.assertEqual(len(actual), 1)
            self.assertEqual(re.sub(r'\s+', ' ', actual[0][:-1]), re.sub(r'\s+', ' ', expected))
    def test_history_is_not_rebuilt_by_core_migration(self):
        source = (ROOT / 'application/migrations/473_version_473.php').read_text()
        self.assertNotRegex(source, r'(?i)\b(?:UPDATE|DELETE|TRUNCATE|DROP TABLE)\b')
        self.assertIn('UNIQUE KEY provider_identity (kind,sandbox,account_hash,provider_id)', source)
        self.assertIn('UNIQUE KEY module_plan (module_name,plan_id)', source)
        self.assertIn('UNIQUE KEY invoice_intent (invoice_id)', source)

if __name__ == '__main__': unittest.main()
