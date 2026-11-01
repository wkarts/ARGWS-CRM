import importlib.util,pathlib,datetime,unittest,copy
s=importlib.util.spec_from_file_location('gate',pathlib.Path(__file__).resolve().parents[2]/'scripts/ci/validation-gate.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class GateTests(unittest.TestCase):
 def setUp(self):
  self.now=datetime.datetime(2026,11,1,tzinfo=datetime.timezone.utc);self.repo='wkarts/ARGWS-CRM';self.pr={'number':52,'merged':True,'merge_commit_sha':'merge','base':{'ref':'main'},'head':{'sha':'head','repo':{'full_name':self.repo}}};self.run={'id':1,'path':'flow.yml','event':'pull_request','conclusion':'success','head_sha':'head','head_repository':{'full_name':self.repo},'updated_at':'2026-10-31T23:00:00Z'}
 def allowed(self,tree='tree',head_tree='tree'):return m.proof_allowed(self.pr,self.run,self.repo,'main','merge',tree,head_tree,self.now)
 def test_identical_tree(self):self.assertTrue(self.allowed())
 def test_changed_merge_tree(self):self.assertFalse(self.allowed(head_tree='other'))
 def test_failed_proof(self):self.run['conclusion']='failure';self.assertFalse(self.allowed())
 def test_other_commit(self):self.run['head_sha']='other';self.assertFalse(self.allowed())
 def test_unmerged(self):self.pr['merged']=False;self.assertFalse(self.allowed())
 def test_fork(self):self.pr['head']['repo']['full_name']='other/repo';self.assertFalse(self.allowed())
 def test_old_proof(self):self.run['updated_at']='2026-10-29T23:00:00Z';self.assertFalse(self.allowed())
 def test_latest_failure_not_hidden_by_earlier_success(self):
  fail=dict(self.run,id=2,conclusion='failure');self.assertEqual(m.latest([self.run,fail],'flow.yml')['conclusion'],'failure')
if __name__=='__main__':unittest.main()
