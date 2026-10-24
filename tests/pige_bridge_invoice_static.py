"""Source invariants only: not a replacement for PHP/MySQL CI."""
from pathlib import Path
import unittest
ROOT=Path(__file__).resolve().parents[1]
class InvoiceSource(unittest.TestCase):
 def test_status_is_select_only(self):
  code=(ROOT/'modules/pige_bridge/libraries/PigeBridgeInvoices.php').read_text()
  status=code.split('public function status(',1)[1]
  for forbidden in ['IssuerTransaction::run','->create(', 'UPDATE ', 'INSERT ', 'DELETE ', 'curl_', 'enqueue', 'Asaas', 'ApiService']:
   self.assertNotIn(forbidden,status)
  self.assertIn('company_invoice_requests',status)
 def test_uses_authoritative_writer_guard(self):
  code=(ROOT/'modules/pige_bridge/libraries/PigeBridgeInvoices.php').read_text()
  self.assertIn('(new CompanyInvoices(',code);self.assertIn('fn()=>$this->assertOrigin(',code)
  self.assertIn("'secret_hash'",code);self.assertIn('assertMappingInOwned',code)
  self.assertNotIn('$_SESSION',code);self.assertNotIn('->recordCompanyManual(',code)
 def test_schema_preserves_prior_receipts(self):
  code=(ROOT/'modules/pige_bridge/migrations/103_version_103.php').read_text()
  self.assertNotIn('DROP TABLE',code);self.assertNotIn('pige_bridge_requests',code)
  for key in ['connection_charge(connection_key,charge_id)','connection_event(connection_key,event_id)','request_id(request_id)']:
   self.assertIn(key,code)
 def test_default_off_and_exact_routes(self):
  code=(ROOT/'modules/pige_bridge/controllers/Bridge.php').read_text()
  self.assertIn("APP_PIGE_BRIDGE_INVOICES_ENABLED!==true",code)
  self.assertIn('$status?$service->status(',code)
if __name__=='__main__':unittest.main()
