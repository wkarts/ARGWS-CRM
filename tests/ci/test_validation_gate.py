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
class BrowserPreflightTests(unittest.TestCase):
 def jobs(self):return [{'name':name,'status':'completed','conclusion':'success'} for name in ['node-contracts','Native browser preflight (8.1)','Native browser preflight (8.5)']]
 def test_all_three_required(self):self.assertEqual(m.preflight_state(self.jobs()),'success')
 def test_node_only_never_opens_database_gate(self):self.assertEqual(m.preflight_state(self.jobs()[:1]),'pending')
 def test_browser_results_without_node_never_open_database_gate(self):self.assertEqual(m.preflight_state(self.jobs()[1:]),'pending')
 def test_node_failure_closes_gate_while_independent_browsers_are_running(self):
  jobs=self.jobs();jobs[0]['conclusion']='failure'
  for job in jobs[1:]:job['status']='in_progress';job['conclusion']=None
  self.assertEqual(m.preflight_state(jobs),'failure')
 def test_browser_failure_closes_gate(self):
  jobs=self.jobs();jobs[1]['conclusion']='failure';self.assertEqual(m.preflight_state(jobs),'failure')
 def test_skipped_is_not_success(self):
  jobs=self.jobs();jobs[2]['conclusion']='skipped';self.assertEqual(m.preflight_state(jobs),'failure')
 def test_running_is_pending(self):
  jobs=self.jobs();jobs[2]['status']='in_progress';jobs[2]['conclusion']=None;self.assertEqual(m.preflight_state(jobs),'pending')
if __name__=='__main__':unittest.main()
