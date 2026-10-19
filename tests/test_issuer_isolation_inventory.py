import importlib.util
from pathlib import Path
import tempfile
import unittest

SPEC = importlib.util.spec_from_file_location('audit', Path(__file__).resolve().parents[1] / 'scripts/audit-issuer-isolation.py')
AUDIT = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(AUDIT)


class InventoryTests(unittest.TestCase):
    def fixture(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        root = Path(temporary.name)
        (root / 'application/models').mkdir(parents=True)
        (root / 'application/models/Invoices.php').write_text("<?php $this->db->get(db_prefix() . 'invoices');")
        return root

    def test_detects_direct_invoice_sql(self):
        found = AUDIT.discover(self.fixture())
        self.assertEqual(['application/models/Invoices.php'], list(found))
        self.assertIn('invoice_table', next(iter(found.values()))['categories'])

    def test_detects_explicit_client_scope_guards(self):
        root = self.fixture()
        (root / 'application/models/Vault.php').write_text("<?php ClientIssuerScope::isLegacyClient($db, 'p_', $id);")
        found = AUDIT.discover(root)
        self.assertIn('customer_privacy', found['application/models/Vault.php']['categories'])

    def test_detects_explicit_portal_identity_service(self):
        root = self.fixture()
        (root / 'application/models/PortalIdentity.php').write_text("<?php final class PortalClientIdentity {}")
        found = AUDIT.discover(root)
        self.assertIn('customer_privacy', found['application/models/PortalIdentity.php']['categories'])

    def test_new_boundaries_fail_check(self):
        found = AUDIT.discover(self.fixture())
        self.assertIn('Uninventoried boundary', AUDIT.inventory_errors(found, {})[0])

    def test_changes_invalidate_coverage(self):
        root = self.fixture()
        saved = AUDIT.discover(root)
        saved['application/models/Invoices.php']['state'] = 'closed_legacy'
        saved['application/models/Invoices.php']['evidence'] = 'SQL test'
        with (root / 'application/models/Invoices.php').open('a') as file:
            file.write(" $this->db->get(db_prefix() . 'clients');")
        current = AUDIT.discover(root)
        self.assertTrue(AUDIT.inventory_errors(current, saved))
        self.assertEqual('unreviewed', AUDIT.reconcile(current, saved)['application/models/Invoices.php']['state'])

    def test_coverage_needs_evidence(self):
        found = AUDIT.discover(self.fixture())
        saved = {key: dict(value, state='closed_scoped') for key, value in found.items()}
        self.assertIn('Review evidence missing', AUDIT.inventory_errors(found, saved)[0])

    def test_inventory_never_opens_operational_gate(self):
        self.assertTrue(AUDIT.ready_errors({'operational_rollout':'blocked', 'files':{}}))
        self.assertIn('blocked rollout only', AUDIT.ready_errors({'operational_rollout':'enabled', 'files':{}})[0])

    def test_exclusions_are_explicit(self):
        root = self.fixture()
        (root / 'application/vendor').mkdir()
        (root / 'application/vendor/Library.php').write_text("<?php 'invoices';")
        self.assertEqual(1, len(AUDIT.discover(root)))

if __name__ == '__main__':
    unittest.main()
