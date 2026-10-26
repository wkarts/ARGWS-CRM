import unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
class NativeGateway(unittest.TestCase):
 def test_registered_and_no_global_provider_credentials(self):
  module=(R/'modules/banco_brasil/banco_brasil.php').read_text()
  gateway=(R/'modules/banco_brasil/libraries/Banco_brasil_gateway.php').read_text()
  self.assertNotIn("register_payment_gateway('banco_brasil_gateway'",module)
  for channel,label in [('api','API'),('cnab','CNAB')]:
   self.assertIn("register_payment_gateway('banco_brasil_"+channel+"_gateway'",module)
   source=(R/('modules/banco_brasil/libraries/Banco_brasil_'+channel+'_gateway.php')).read_text()
   self.assertIn("setId('banco_brasil_"+channel+"')",source)
   self.assertIn('Banco do Brasil — '+label,source)
  self.assertIn("setId('banco_brasil')",gateway)
  for secret in ['client_secret','application_key','api_key']: self.assertNotIn(secret,gateway)
  self.assertIn("$setting['default_value']=0",gateway)
 def test_checkout_has_no_remote_or_financial_write(self):
  code=(R/'modules/banco_brasil/libraries/BancoBrasil/Checkout.php').read_text()
  for operation in ['->issue(', '->query(', '->cancel(', 'addPayment(', 'recordCompanyProviderReceipt(']: self.assertNotIn(operation,code)
  self.assertIn('invoiceIdentity($session,$issuer,true)',code)
  self.assertIn('permission_id=1',code)
  self.assertIn('instrument_revision',code)
  self.assertIn("'bb_checkout_stale'",code)
 def test_legacy_guard_precedes_every_invoice_read(self):
  code=(R/'application/models/Payments_model.php').read_text().split('public function process_payment(')[1]
  self.assertLess(code.index("['banco_brasil','banco_brasil_api','banco_brasil_cnab']"),code.index('$targetInvoiceId'))
  self.assertIn("set_alert('warning'",code.split('$targetInvoiceId')[0])
 def test_instrument_identity_and_uncertainty_are_revalidated(self):
  code=(R/'modules/banco_brasil/libraries/BancoBrasil/ApiService.php').read_text().split('public function checkoutInstrumentInOwnedTransaction')[1].split('public function overview')[0]
  for fragment in ["$snapshot['invoice']['clientid']", "$snapshot['invoice']['currency_id']", "status<>'validated'", "substr($result['codigoBarraNumerico'],19)", "substr($result['codigoBarraNumerico'],5,4)"]:self.assertIn(fragment,code)
 def test_browser_actions_are_explicit(self):
  code=(R/'modules/banco_brasil/controllers/Banco_brasil.php').read_text()
  for method in ['preview_checkout','publish_checkout']:
   body=code.split('public function '+method+'()')[1].split('public function ')[0]
   self.assertIn('$this->post()',body); self.assertIn("$this->issuer($issuer,'manage')",body)
  portal=(R/'application/controllers/Company_portal.php').read_text()
  self.assertLess(portal.index('hash_equals($csrf,$posted)'),portal.index("$action==='invoice_bb_checkout'"))
if __name__=='__main__':unittest.main()
