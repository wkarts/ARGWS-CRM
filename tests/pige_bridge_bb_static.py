"""BB bridge structural evidence, NOT PHP/HTTP/DB execution."""
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
class BBBridge(unittest.TestCase):
 def test_status_is_local_only(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeBbCharges.php').read_text().split('public function status(',1)[1]
  for bad in ['IssuerTransaction::run','->issue(', '->query(', '->recordCompanyProviderReceipt(']:self.assertNotIn(bad,s)
  self.assertIn("'payment_effect_applied'=>false",s)
 def test_native_services_explicit(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeBbCharges.php').read_text()
  for required in ['ApiCreditedReceipt::explicit','checkoutInstrumentInOwnedTransaction',"'bridge_manual'", "['recovery_id']!==''", "['billing_type']!=='BOLETO'", 'pige_bb_payer_denied']:self.assertIn(required,s)
  self.assertNotIn('$_SESSION',s)
 def test_router_does_not_trust_payload_provider(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeChargeRouter.php').read_text()
  self.assertIn("$snapshot['mapping']['provider']",s);self.assertNotIn("$event['data']['provider']",s)
 def test_config_disallows_undefined_policies(self):
  s=(R/'modules/pige_bridge/libraries/PigeBridgeExternalConfiguration.php').read_text()
  for field in ['interest_policy','discount_policy','receipt_window_policy','discount_assignment_policy','profile_row']:self.assertIn(field,s)
if __name__=='__main__':unittest.main()
