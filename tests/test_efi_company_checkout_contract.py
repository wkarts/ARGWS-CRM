"""Static boundaries only. Runtime behavior is covered by PHP/MySQL CI, not this test."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[1]
class CompanyCheckoutStructure(unittest.TestCase):
    def test_no_legacy_gateway_or_global_credentials(self):
        for name in ('CompanyCheckout','CompanyReceivedReceipt','CompanyTransport'):
            text=(ROOT/f'modules/gerencianet_gateway/services/{name}.php').read_text()
            for forbidden in ('get_option(', 'decryptSetting(', 'getSetting(', 'Payments_model', 'new \\Gerencianet_gateway'):
                self.assertNotIn(forbidden,text)
    def test_explicit_writer_identity(self):
        text=(ROOT/'application/services/InvoicePaymentWriter.php').read_text()
        self.assertIn("'Efi\\\\CompanyReceivedReceipt'],true)",text)
        self.assertIn("'Efi\\\\CompanyReceivedReceipt'=>['efi_company_received'",text)
    def test_legacy_guard_untouched(self):
        text=(ROOT/'modules/gerencianet_gateway/services/EfiChargeLedger.php').read_text()
        self.assertIn('AND i.issuer_id IS NULL',text)
        self.assertIn("$rows[0]['issuer_id'] !== null",text)
    def test_dispatch_fences_and_receipt_no_network(self):
        text=(ROOT/'modules/gerencianet_gateway/services/CompanyCheckout.php').read_text()
        self.assertLess(text.index("'reserved','dispatched'"),text.index("$client->request('createCharge'"))
        self.assertLess(text.index("'provider_created','payment_dispatched'"),text.index("$client->request('definePayMethod'"))
        receipt=(ROOT/'modules/gerencianet_gateway/services/CompanyReceivedReceipt.php').read_text()
        self.assertNotIn('->request(',receipt)
        self.assertIn('IssuerTransaction::assertOwned',receipt)
        self.assertIn("$origin['kind']!=='pix'",receipt)
    def test_instrument_not_raw_html(self):
        text=(ROOT/'modules/gerencianet_gateway/services/CompanyCheckout.php').read_text()
        self.assertIn("'visualizacao.gerencianet.com.br'",text)
        self.assertNotIn('imagemQrcode',text)
        self.assertIn("['pix_copy_paste'=>$code]",text)
if __name__=='__main__':unittest.main()
