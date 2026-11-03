import importlib.util,pathlib,unittest,json,tempfile
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('round',pathlib.Path(__file__).resolve().parents[1]/'scripts/ci/pr-validation-round.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class RoundTests(unittest.TestCase):
 def test_only_known_validation_families_finish_current_diagnostics(self):
  for path in m.DIAGNOSTIC_WORKFLOWS:
   self.assertTrue(m.diagnostic_run({'path':path+'@refs/pull/52/merge'}))
  self.assertFalse(m.diagnostic_run({'path':'.github/workflows/release-packages.yml'}))
  self.assertFalse(m.diagnostic_run({'path':'.github/workflows/unreviewed.yml'}))
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
 def test_http_failure_retains_queued_sql_diagnostics_and_cancels_obsolete(self):
  rows=[self.run_row(1,path='.github/workflows/deployment-integrity.yml'),self.run_row(2,status='queued',path='.github/workflows/issuer-foundation.yml'),self.run_row(3,head_sha='old',path='.github/workflows/issuer-foundation.yml'),self.run_row(4,path='.github/workflows/unreviewed.yml')]
  retained={r['id'] for r in rows if m.diagnostic_run(r)}
  self.assertEqual(m.cancellation_plan(rows,self.pr,self.repo,99,True,retained),[3,4])
 def test_main_detects_failed_child_of_queued_matrix(self):
  pr={**self.pr,'state':'open','head':{'sha':'new','ref':'branch','repo':{'full_name':self.repo}}}
  core=self.run_row(1,status='queued',path='.github/workflows/issuer-foundation.yml',workflow_id=7,conclusion=None)
  class FakeApi:
   calls=[]
   def __init__(self,*args):pass
   def runs(self,*args,**kwargs):return [core]
   def call(self,path,method='GET'):
    self.calls.append((path,method))
    if path=='/pulls/52':return pr
    if '/jobs?' in path:return {'jobs':[{'name':'SQL shard','conclusion':'failure'}]}
    raise AssertionError('Unexpected request '+path)
  with tempfile.NamedTemporaryFile(mode='w') as event:
   json.dump({'pull_request':pr},event);event.flush()
   with patch.dict(m.os.environ,{'GITHUB_REPOSITORY':self.repo,'GITHUB_RUN_ID':'99','GITHUB_EVENT_PATH':event.name,'GH_TOKEN':'synthetic'}),patch.object(m,'Api',FakeApi),patch('builtins.print'):
    self.assertEqual(m.main(),1)
  self.assertTrue(any('/actions/runs/1/jobs?' in path for path,_ in FakeApi.calls))
  self.assertFalse(any(method=='POST' for _,method in FakeApi.calls),'failed queued diagnostic matrix must finish without cancellation')
if __name__=='__main__':unittest.main()
