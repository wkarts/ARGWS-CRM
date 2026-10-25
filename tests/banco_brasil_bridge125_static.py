"""Structural regression evidence, not a substitute for PHP/MySQL execution."""
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
B=R/'modules/banco_brasil'
class Bridge125(unittest.TestCase):
 def test_native_release(self):
  self.assertIn('Version: 1.2.7',(B/'banco_brasil.php').read_text())
  self.assertIn('ApiOriginPolicy::ensure',(B/'migrations/125_version_125.php').read_text())
 def test_policy_is_persistent(self):
  s=(B/'libraries/BancoBrasil/ApiOriginPolicy.php').read_text()
  for token in ['ENGINE=InnoDB','UNIQUE KEY origin_reference','bridge_manual','IssuerTransaction::assertOwned','hash_equals'] : self.assertIn(token,s)
  for token in ['DELETE FROM','DROP TABLE','UPDATE ']:self.assertNotIn(token,s)
 def test_guard_propagation(self):
  s=(B/'libraries/BancoBrasil/ApiService.php').read_text()
  for token in ['$projection,$prepared,$originGuard,$guardTables,$originReference','ApiOriginPolicy::mark','$run(static fn()=>null)','$guardTables,$originGuard','array_merge($tables,$guardTables'] :self.assertIn(token,s)
  self.assertNotIn('private $originGuard',s)
 def test_quarantined_technical_response(self):
  s=(B/'libraries/BancoBrasil/ApiService.php').read_text()
  for token in ["'technical_only'=>true","$state='unknown'",'technical_result_quarantined','bb_api_technical_origin_changed',"$status,$technicalResult",'strlen($json)>32768']:self.assertIn(token,s)
  self.assertLess(s.index('self::technicalResult($response,$projection'),s.index('if($originReference!==null)$activityGuard();'))
 def test_native_explicit_writer(self):
  s=(B/'libraries/BancoBrasil/ApiCreditedReceipt.php').read_text()
  for token in ['public static function explicit','confirmProof','hash_equals($c[\'proofHash\']','receiptOriginInOwnedTransaction','valorPagamentoParcialTitulo','time()-300']:self.assertIn(token,s)
  self.assertNotIn('client->',s)
if __name__=='__main__':unittest.main()
