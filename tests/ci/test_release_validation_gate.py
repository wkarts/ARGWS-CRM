import importlib.util,pathlib,unittest
s=importlib.util.spec_from_file_location('release_gate',pathlib.Path(__file__).resolve().parents[2]/'scripts/ci/verify-release-validation.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class PublicationTests(unittest.TestCase):
 def rows(self):return [{'id':i+1,'path':p,'conclusion':'success'}for i,p in enumerate(m.REQUIRED)]
 def test_all_required_pass(self):self.assertEqual(m.outcome(self.rows()),'success')
 def test_missing_never_passes(self):self.assertEqual(m.outcome(self.rows()[:-1]),'pending')
 def test_cancelled_never_passes(self):r=self.rows();r[0]['conclusion']='cancelled';self.assertEqual(m.outcome(r),'failure')
 def test_later_failure_wins(self):r=self.rows();r.append(dict(r[0],id=100,conclusion='failure'));self.assertEqual(m.outcome(r),'failure')
if __name__=='__main__':unittest.main()
