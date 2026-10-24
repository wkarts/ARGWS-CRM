"""Configuration surface and secret-handling inventory only; NOT PHP/MySQL execution."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
class ConfigurationInventory(unittest.TestCase):
 def test_scope_and_mapping_are_explicit(self):
  s=(ROOT/'modules/pige_bridge/libraries/PigeBridgeConnections.php').read_text()
  self.assertIn("['school_id','issuer_id','account_id','environment']",s)
  self.assertIn("['enabled','actor_id','currency_id']",s)
  self.assertNotIn('$_SESSION',s)
  for forbidden in ['curl_', 'file_get_contents(', 'InvoicePaymentWriter', '->create(']: self.assertNotIn(forbidden,s)
 def test_schema_has_uniqueness_and_default_off(self):
  s=(ROOT/'modules/pige_bridge/migrations/102_version_102.php').read_text()
  for required in ['UNIQUE KEY scope_hash(scope_hash)','UNIQUE KEY secret_hash(secret_hash)','enabled TINYINT NOT NULL DEFAULT 0','secret_cipher TEXT NOT NULL','pige_connection_destructive_downgrade_not_supported']: self.assertIn(required,s)
  self.assertNotIn('DROP TABLE',s)
 def test_admin_keeps_native_scope_and_secret_not_echoed(self):
  s=(ROOT/'modules/pige_bridge/controllers/Settings.php').read_text();v=(ROOT/'modules/pige_bridge/views/settings.php').read_text()
  self.assertIn('extends AdminController',s);self.assertIn('hash_equals($token,$posted)',s)
  self.assertIn('init_head()',v);self.assertIn('init_tail()',v);self.assertIn('name="_company_context"',v)
  self.assertNotIn("['secret_cipher']",v);self.assertNotIn("['secret']",v)
 def test_validation_suites_exist(self):
  for name in ['pige_bridge_connections.php','pige_bridge_connections_mysql.php','pige_bridge_admin.php','pige_bridge_migration.php']: self.assertTrue((ROOT/'tests'/name).is_file())
if __name__=='__main__':unittest.main()
