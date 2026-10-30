"""Structural guards only, not PHP/MySQL or external execution."""
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
class External(unittest.TestCase):
 def test_status_no_mutation_or_transport(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeCharges.php').read_text().split('public function status(',1)[1]
  for bad in ['IssuerTransaction::run','->observe(', '->issue(', '->reconcileExplicit(', '->instruments(', 'UPDATE ', 'INSERT ']:self.assertNotIn(bad,s)
  self.assertIn("'payment_effect_applied'=>false",s)
 def test_native_services_and_policy(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeCharges.php').read_text()
  for required in ['->observe(', '->reconcileExplicit(', '->instruments(', '->issue(', "'manual_only'", 'true,$v[\'request_key\']', 'pige_payer_proof_changed']:self.assertIn(required,s)
  self.assertNotIn('->register(',s);self.assertNotIn('$_SESSION',s)
 def test_revision_and_scope_binding(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeExternalConfiguration.php').read_text()
  for required in ['connection_revision','account_revision',"['account_id']!==$scope['account_id']", "['environment']!==$scope['environment']", "'payments','create'"] :self.assertIn(required,s)
 def test_three_independent_default_off_gates(self):
  s=(R/'modules/pige_bridge/controllers/Bridge.php').read_text()
  for name in ['INVOICES','EXTERNAL','RECONCILE']:self.assertIn('APP_PIGE_BRIDGE_'+name+'_ENABLED!==true',s)
if __name__=='__main__':unittest.main()
