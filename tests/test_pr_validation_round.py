import importlib.util,pathlib,unittest
spec=importlib.util.spec_from_file_location('round',pathlib.Path(__file__).resolve().parents[1]/'scripts/ci/pr-validation-round.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class RoundTests(unittest.TestCase):
 def test_only_known_validation_families_finish_current_failed_shards(self):
  for path in m.DIAGNOSTIC_WORKFLOWS:
   self.assertTrue(m.retains_failed_shards({'path':path+'@refs/pull/52/merge'},[{'conclusion':'failure'}]))
   self.assertFalse(m.retains_failed_shards({'path':path},[{'conclusion':'success'}]))
  self.assertFalse(m.retains_failed_shards({'path':'.github/workflows/release-packages.yml'},[{'conclusion':'failure'}]))
  self.assertFalse(m.retains_failed_shards({'path':'.github/workflows/unreviewed.yml'},[{'conclusion':'failure'}]))
 def setUp(self):self.pr={'number':52,'head':{'sha':'new'}};self.repo='wkarts/ARGWS-CRM'
 def run_row(self,id=1,**kw):return dict({'id':id,'event':'pull_request','head_sha':'new','status':'in_progress','head_repository':{'full_name':self.repo},'pull_requests':[{'number':52}]},**kw)
 def plan(self,rows,failed=False):return m.cancellation_plan(rows,self.pr,self.repo,99,failed)
 def test_old_only_before_failure(self):self.assertEqual(self.plan([self.run_row(1,head_sha='old'),self.run_row(2)]),[1])
 def test_failure_cancels_current_and_old(self):self.assertEqual(self.plan([self.run_row(1),self.run_row(2,head_sha='old')],True),[1,2])
 def test_other_pr_never(self):self.assertEqual(self.plan([self.run_row(pull_requests=[{'number':53}])],True),[])
 def test_fork_never(self):self.assertEqual(self.plan([self.run_row(head_repository={'full_name':'other/fork'})],True),[])
 def test_push_release_dispatch_never(self):
  for event in ['push','release','workflow_dispatch','workflow_run']:self.assertEqual(self.plan([self.run_row(event=event)],True),[])
 def test_completed_and_self_never(self):self.assertEqual(self.plan([self.run_row(99),self.run_row(status='completed')],True),[])
 def test_replaced_same_sha_run_is_not_a_false_failure(self):
  runs=[{'id':1,'workflow_id':8,'conclusion':'cancelled'},{'id':2,'workflow_id':8,'conclusion':'success'},{'id':3,'workflow_id':9,'conclusion':'success'}]
  self.assertEqual([r['id'] for r in m.latest_workflow_runs(runs)],[2,3])
 def test_missing_identity_never(self):self.assertEqual(self.plan([self.run_row(pull_requests=[])],True),[])
 def test_current_diagnostic_workflow_continues_but_other_failures_are_not_hidden(self):
  rows=[self.run_row(1),self.run_row(2)]
  self.assertEqual(m.cancellation_plan(rows,self.pr,self.repo,99,True,{1}),[2])
 def test_superseded_diagnostics_are_always_cancelled(self):
  self.assertEqual(m.cancellation_plan([self.run_row(1,head_sha='old')],self.pr,self.repo,99,True,{1}),[1])
if __name__=='__main__':unittest.main()
