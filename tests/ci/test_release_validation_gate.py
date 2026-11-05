import importlib.util,pathlib,re,unittest
s=importlib.util.spec_from_file_location('release_gate',pathlib.Path(__file__).resolve().parents[2]/'scripts/ci/verify-release-validation.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class PublicationTests(unittest.TestCase):
 def rows(self):return [{'id':i+1,'path':p,'status':'completed','conclusion':'success','html_url':'https://github.com/example/runs/'+str(i+1)}for i,p in enumerate(m.REQUIRED)]
 def test_all_required_pass(self):self.assertEqual(m.outcome(self.rows()),'success')
 def test_missing_never_passes(self):self.assertEqual(m.outcome(self.rows()[:-1]),'pending')
 def test_cancelled_never_passes(self):r=self.rows();r[0]['conclusion']='cancelled';self.assertEqual(m.outcome(r),'failure')
 def test_later_failure_wins(self):r=self.rows();r.append(dict(r[0],id=100,conclusion='failure'));self.assertEqual(m.outcome(r),'failure')
 def test_skipped_neutral_and_unknown_terminal_results_never_pass(self):
  for result in ('skipped','neutral','timed_out','action_required','stale','startup_failure',None):
   with self.subTest(result=result):
    rows=self.rows();rows[0]['conclusion']=result;self.assertEqual(m.outcome(rows),'failure')
 def test_queued_rerun_does_not_reuse_prior_success(self):
  rows=self.rows();rows.append(dict(rows[0],id=100,status='queued',conclusion=None));self.assertEqual(m.outcome(rows),'pending')
 def simulate(self,snapshots):
  self.now=0;self.messages=[];self.calls=[]
  def get(route):
   self.calls.append(route)
   return {'workflow_runs':snapshots(self.now)}
  def sleep(seconds):self.now+=seconds
  return m.wait_for_validation(get,'current-sha',clock=lambda:self.now,sleep=sleep,report=self.messages.append)
 def test_reported_merge_missing_bb_fails_after_five_not_ninety_minutes(self):
  rows=[r for r in self.rows() if not r['path'].endswith('/banco-brasil.yml')]
  with self.assertRaisesRegex(SystemExit,'banco-brasil.yml'):
   self.simulate(lambda now:rows)
  self.assertEqual(self.now,300)
  self.assertIn('missing (no push run on this SHA)','\n'.join(self.messages))
  self.assertGreaterEqual(len(self.messages),6)
  self.assertTrue(all('event=push&head_sha=current-sha&' in route for route in self.calls))
 def test_missing_run_can_appear_within_grace(self):
  self.assertEqual(self.simulate(lambda now:self.rows() if now>=40 else self.rows()[:-1]),0)
  self.assertEqual(self.now,40)
 def test_actual_queued_runs_can_exceed_discovery_grace(self):
  queued=[dict(r,status='queued',conclusion=None) for r in self.rows()]
  self.assertEqual(self.simulate(lambda now:self.rows() if now>=360 else queued),0)
  self.assertEqual(self.now,360)
 def test_failure_is_immediate_and_has_workflow_url(self):
  rows=self.rows();rows[0]['conclusion']='failure'
  with self.assertRaisesRegex(SystemExit,'https://github.com/example/runs/1'):
   self.simulate(lambda now:rows)
  self.assertEqual(self.now,0)
 def test_running_work_is_reported_and_timeout_remains_fail_closed(self):
  rows=[dict(r,status='in_progress',conclusion=None) for r in self.rows()]
  with self.assertRaisesRegex(SystemExit,'evidence is incomplete'):
   self.simulate(lambda now:rows)
  self.assertEqual(self.now,5400)
  self.assertIn('in_progress/pending','\n'.join(self.messages))
 def test_approved_runs_exit_without_sleep(self):
  self.assertEqual(self.simulate(lambda now:self.rows()),0);self.assertEqual(self.now,0)

class RequiredWorkflowTriggers(unittest.TestCase):
 def test_every_required_workflow_is_scheduled_for_all_main_changes(self):
  root=pathlib.Path(__file__).resolve().parents[2]
  for path in m.REQUIRED:
   text=(root/path).read_text()
   for event in ('pull_request','push'):
    with self.subTest(workflow=path,event=event):
     block=re.search(r'^  '+event+r':\n((?:^    .*\n|^      .*\n)+)',text,re.M)
     self.assertIsNotNone(block,'required workflow event must exist')
     self.assertRegex(block.group(1),r'branches:\s*\[[^\]]*\bmain\b')
     self.assertNotRegex(block.group(1),r'\b(?:paths|paths-ignore|branches-ignore):','unconditional release requirement cannot be path-filtered')
if __name__=='__main__':unittest.main()
