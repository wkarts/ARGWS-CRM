"""Static perimeter only; does not substitute for the PHP/controller/MySQL fixtures."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
MODULE=ROOT/'modules/gerencianet_gateway'
class EfiStructure(unittest.TestCase):
 def test_sdk_is_deferred_and_modular(self):
  controller=(MODULE/'controllers/Gerencia_net.php').read_text()
  library=(MODULE/'libraries/Gerencianet_gateway.php').read_text()
  self.assertIn("require_once dirname(__DIR__) . '/services/EfiSdkClient.php'",controller)
  self.assertIn("/vendor/autoload.php",(MODULE/'services/EfiSdkClient.php').read_text())
  self.assertNotIn('ob_start()',library)
  self.assertNotIn('Gerencianet::getInstance',controller)
  self.assertIn('private function fetch_payment',controller)
  self.assertNotIn("'tblinvoices'",controller)
 def test_old_unbound_metadata_is_not_a_payment_origin(self):
  source=(MODULE/'controllers/Gerencia_net.php').read_text()
  self.assertNotIn('pixListCharges',source)
  self.assertNotIn('fazerPagamento',source)
  self.assertIn("pendingPix($options)",source)
  ledger=(MODULE/'services/EfiChargeLedger.php').read_text()
  self.assertIn('efi_charge_reconciliation_required',ledger)
  self.assertIn('account_hash=?',ledger)
 def test_browser_posts_and_pix_hash(self):
  for view in ['html.php','gateway_page.php']:
   source=(MODULE/'views'/view).read_text()
   self.assertIn('/callback_check_pix_pago/${txid}/${invoice_id}/${hash}',source)
   self.assertIn('efi_checkout_csrf',source)
   self.assertIn('...efiRequestTokens',source)
  source=(MODULE/'views/gerencia_net.php').read_text()
  self.assertIn('efi_checkout_csrf',source)
  self.assertIn('certificate_password',source)
 def test_intent_precedes_every_remote_creation(self):
  source=(MODULE/'controllers/Gerencia_net.php').read_text()
  self.assertLess(source.index("reserve('card'"),source.index('createOneStepCharge('))
  self.assertLess(source.index("reserve('pix'"),source.index('pixCreateImmediateCharge('))
  self.assertLess(source.index("reserve('boleto'"),source.index('createCharge('))
  self.assertLess(source.index('boletoPayment($permit'),source.index('definePayMethod('))
  intent=(MODULE/'services/EfiChargeIntent.php').read_text()
  self.assertIn('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',intent)
  self.assertIn('trans_commit()',intent)
  for view in ['html.php','gateway_page.php']:
   self.assertIn("generatePix.on('click'",(MODULE/'views'/view).read_text())
 def test_certificate_no_webroot_storage(self):
  source=(MODULE/'controllers/Gerencia_net.php').read_text()
  self.assertNotIn("$dir = './media",source)
  self.assertNotIn('openssl_pkey_export_to_file',source)
  self.assertIn('EfiCertificateStore::seal',source)
  self.assertIn('$this->requireAdmin(); $this->requirePost();',source)
if __name__=='__main__':unittest.main()
