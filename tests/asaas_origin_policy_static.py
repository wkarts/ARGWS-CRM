from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
class GuardPolicy(unittest.TestCase):
 def test_manual_policy_requires_trusted_guard_and_reference(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyService.php').read_text()
  self.assertIn("if($originGuard===null||$originReference===null",s)
  self.assertLess(s.index('CompanyOriginPolicy::markManual'),s.index("$client->request('POST','payments'"))
  self.assertIn('if($enqueue)foreach',s)
  self.assertIn('CompanyOriginPolicy::assertAutomaticAllowed($this->db,$this->p,$origin)',s)
 def test_worker_and_ingress_deny_policy(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyService.php').read_text()
  self.assertIn("CompanyOriginPolicy::isManual($this->db,$this->p,(int)$origin['id'])",s)
  worker=(R/'modules/asaas/libraries/Asaas/CompanyReconciliation.php').read_text()
  self.assertIn("$service->assertAutomaticAllowed((int)$job['origin_id'])",worker)
 def test_instruments_have_no_settlement_writer(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyService.php').read_text().split('public function instruments(',1)[1].split('/** Public inbox',1)[0]
  self.assertNotIn('storeProof(',s);self.assertNotIn('enqueue',s);self.assertNotIn('INSERT ',s)
  self.assertIn('if($after!==$before)',s);self.assertIn('$this->guardedTransaction',s)
 def test_explicit_settlement_reuses_allowlisted_writer_and_never_http(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyService.php').read_text()
  body=s.split('public function reconcileExplicit(',1)[1].split('private function receiptClaimOwned',1)[0]
  self.assertIn('recordCompanyProviderReceipt',body);self.assertIn('CompanyReceivedReceipt::explicit',body)
  self.assertNotIn('->request(',body);self.assertNotIn('->query(',body)
  receipt=(R/'modules/asaas/libraries/Asaas/CompanyReceivedReceipt.php').read_text()
  self.assertIn('$source=new self($origin,$proof)',receipt)
  self.assertIn('confirmExplicitClaimInOwned',receipt)
  self.assertIn('asaas_module_158_required',s)
 def test_additive_no_backfill_or_remove(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyOriginPolicy.php').read_text()
  self.assertIn('CREATE TABLE IF NOT EXISTS',s)
  self.assertNotIn('DELETE FROM',s);self.assertNotIn('UPDATE ',s)
  self.assertTrue((R/'modules/asaas/migrations/157_version_157.php').exists())
 def test_inflight_completion_is_quarantined_not_reauthorized(self):
  s=(R/'modules/asaas/libraries/Asaas/CompanyService.php').read_text()
  body=s.split('private function retainDispatchedResult(',1)[1].split('private function storeProof(',1)[0]
  for required in ["'technical_only'=>true", "'quarantined'", 'CompanyOriginPolicy::assertManual', 'snapshot_hash', 'response_sha256'] :self.assertIn(required,body)
  for forbidden in ['->authorize(', '->module(', '->enqueue', 'recordCompanyProviderReceipt', 'received_observed'] :self.assertNotIn(forbidden,body)
if __name__=='__main__':unittest.main()
