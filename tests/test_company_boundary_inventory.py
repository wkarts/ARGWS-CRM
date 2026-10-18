import importlib.util
import tempfile
import unittest
from pathlib import Path
spec = importlib.util.spec_from_file_location('inventory', Path(__file__).resolve().parents[1] / 'scripts/inventory-company-boundaries.py')
module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)

class InventoryTests(unittest.TestCase):
    def test_sources_and_dependency_not_silently_omitted(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            samples = {'application/models/X.php': '<?php $db->query($sql); get_option("smtp_password");',
                       'modules/bank/hooks.php': '<?php hooks()->add_action("cron", $callback);',
                       'modules/bank/vendor/sdk.php': '<?php remote();',
                       'application/views/a.php': '<?php echo download($clientid);'}
            for path, text in samples.items():
                target = root / path; target.parent.mkdir(parents=True, exist_ok=True); target.write_text(text)
            result = module.inventory(root)
            self.assertEqual(4, result['files']); self.assertFalse(result['operational_ready'])
            self.assertEqual(4, result['unresolved_files'])
            dependency = next(e for e in result['entries'] if '/vendor/' in e['path'])
            self.assertEqual('dependency_boundary', dependency['category'])
            self.assertEqual('unreviewed', dependency['status'])
            model = next(e for e in result['entries'] if '/models/' in e['path'])
            self.assertIn('database', model['matches'][0]['kinds']); self.assertIn('settings', model['matches'][0]['kinds'])
            review = {e['path']: dict(sha256=e['sha256'], decision='scoped', tests=['synthetic'], rationale='fixture') for e in result['entries']}
            self.assertTrue(module.inventory(root, review)['operational_ready'])
            (root / 'application/models/X.php').write_text('<?php new_unreviewed_call();')
            updated = module.inventory(root, review)
            self.assertFalse(updated['operational_ready']); self.assertEqual(1, updated['unresolved_files'])
            self.assertTrue(next(e for e in updated['entries'] if '/models/' in e['path'])['stale_review'])
    def test_empty_or_incomplete_review_never_ready(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); self.assertFalse(module.inventory(root)['operational_ready'])
            (root / 'application').mkdir(); (root / 'application/x.php').write_text('<?php return 1;')
            item = module.inventory(root)['entries'][0]
            self.assertFalse(module.inventory(root, {item['path']: {'sha256': item['sha256'], 'decision': 'scoped'}})['operational_ready'])

if __name__ == '__main__': unittest.main()
