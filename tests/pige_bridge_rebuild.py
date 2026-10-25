"""Fresh reconstruction evidence; static/vector checks are not PHP execution."""
import hashlib,hmac,json,pathlib,shutil,subprocess,unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
class Rebuild(unittest.TestCase):
 def test_signed_diagnostic_vector(self):
  f=json.loads((ROOT/'tests/fixtures/pige-crm-diagnostics-v1.json').read_text());e=json.loads(f['raw_body'])
  self.assertEqual(e['event_type'],'connection.test');self.assertEqual(set(e['data']),{'nonce'})
  self.assertEqual(json.dumps(e,ensure_ascii=False,separators=(',',':')),f['raw_body'])
  message='\n'.join([f['timestamp'],e['event_id'],'POST',f['path'],f['raw_body']]).encode()
  self.assertEqual('sha256='+hmac.new(f['secret'].encode(),message,hashlib.sha256).hexdigest(),f['signature'])
 def test_diagnostic_has_no_model_effect(self):
  s=(ROOT/'modules/pige_bridge/controllers/Bridge.php').read_text()
  self.assertLess(s.index('if($diagnostic)return'),s.index("$this->load->model('pige_bridge/pige_bridge_model')"))
  self.assertIn("'payment_effect_applied'=>false",s)
 def test_csrf_paths_are_exact(self):
  s=(ROOT/'modules/pige_bridge/config/csrf_exclude_uris.php').read_text()
  self.assertNotIn('settings',s.split('return',1)[1]);self.assertNotIn('.*',s)
 @unittest.skipUnless(shutil.which('php'),'PHP absent; controller runtime NOT RUN')
 def test_php_diagnostic(self):
  subprocess.run(['php',str(ROOT/'tests/pige_bridge_diagnostics.php')],check=True)
if __name__=='__main__':unittest.main()
