"""Static inventory, not browser/PHP execution."""
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
class SettingsUI(unittest.TestCase):
 def test_native_grid_modal_and_actions(self):
  s=(R/'modules/pige_bridge/views/settings.php').read_text()
  for needle in ['pige-new','pige-edit','pige-disable','modal-dialog modal-lg','nav nav-tabs','pige-tab-connection','pige-tab-mapping','pige-tab-external','pige-tab-setup','Nenhuma integração cadastrada']:
   self.assertIn(needle,s)
  self.assertIn('JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT',s)
  for forbidden in ['secret_cipher','secret_hash','account_revision','scope_hash']:
   self.assertNotIn(forbidden,s)
 def test_write_guards_and_server_scope(self):
  s=(R/'modules/pige_bridge/controllers/Settings.php').read_text().split('public function disable()')[1].split('public function mapping()')[0]
  for needle in ['writeAllowed()','listed($caller)',"$owned['scope']",'null,false,$revision,$caller']:
   self.assertIn(needle,s)
  self.assertNotIn("value('issuer_id')",s)
 def test_revision_and_secret_reset(self):
  s=(R/'modules/pige_bridge/assets/settings.js').read_text()
  for needle in ['this.reset()','hidden.bs.modal',"input[type=\"password\"]",'connection_revision === record.revision',"'readOnly'",'window.confirm','pige-fixed-scope']:
   self.assertIn(needle,s)
 def test_migration_noop_and_version(self):
  s=(R/'modules/pige_bridge/migrations/109_version_109.php').read_text()
  self.assertIn('extends App_module_migration',s);self.assertIn('NOOP',s);self.assertNotIn('query(',s)
  self.assertIn('Version: 1.0.9',(R/'modules/pige_bridge/pige_bridge.php').read_text())
if __name__=='__main__':unittest.main()
