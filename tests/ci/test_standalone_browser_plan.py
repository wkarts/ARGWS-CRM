import importlib.util,json,pathlib,copy,shlex,subprocess,tempfile,os,unittest
ROOT=pathlib.Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('browser_plan',ROOT/'scripts/ci/standalone-browser-plan.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class BrowserPlanTests(unittest.TestCase):
 def test_long_workers_leave_capacity_for_all_http_groups(self):
  import re
  def section(file,name):
   source=(ROOT/file).read_text();return re.search(r'^  '+name+r':\n(.*?)(?=^  [\w-]+:\n|\Z)',source,re.M|re.S).group(1)
  core=section('.github/workflows/issuer-foundation.yml','registry-shards')
  bb=[section('.github/workflows/banco-brasil.yml',db+'-shards') for db in ['mysql','mariadb']]
  slots=sum(int(re.search(r'max-parallel: (\d+)',job).group(1)) for job in [core,*bb])
  focused=section('.github/workflows/native-company-experience.yml','focused')
  # Tooling and HTTP are separate phases; only the monitor overlaps both.
  focused_slots=min(len(re.findall(r"- php: '[^']+'",focused)),int(re.search(r'max-parallel: (\d+)',focused).group(1)))
  slots+=max(len(self.plan['groups']),1)+focused_slots+1
  self.assertLessEqual(slots,20,'long SQL matrices cannot occupy every runner before HTTP starts')
  http=section('.github/workflows/deployment-integrity.yml','http')
  self.assertRegex(http,r'group: \[tasks-domains, support,')
  self.assertIn('needs: [validation-gate, prepare, tooling]',http)
  self.assertIn("needs.tooling.result == 'success'",http)
 def setUp(self):
  self.source=m.SOURCE.read_text();self.plan=json.loads(m.MANIFEST.read_text())
 def test_every_original_invocation_is_owned_exactly_once_and_ordered(self):
  m.validate(self.plan,self.source)
  owners=self.plan['common']+[i for g in self.plan['groups'].values() for i in g['owned']]
  self.assertEqual(sorted(owners),list(range(103)))
  self.assertEqual(self.plan['invocations'][0]['argv'],self.plan['invocations'][2]['argv'])
  self.assertNotEqual(self.plan['invocations'][0]['database'],self.plan['invocations'][2]['database'])
  self.assertEqual(self.plan['invocations'][56]['argv'][0],'--test')
 def test_missing_duplicate_new_commands_and_unknown_groups_fail_closed(self):
  p=copy.deepcopy(self.plan);p['groups']['sales-pdf']['owned'].pop()
  with self.assertRaises(ValueError):m.validate(p,self.source)
  p=copy.deepcopy(self.plan);p['groups']['sales-pdf']['owned'].append(21)
  with self.assertRaises(ValueError):m.validate(p,self.source)
  with self.assertRaises(ValueError):m.validate(self.plan,self.source+'\nnode tests/new-browser.cjs\n')
  with self.assertRaises(ValueError):m.selected(self.plan,'typo')
 def test_shared_state_dependencies_replay_the_real_original_browsers(self):
  dependencies={17:[16],30:[13,14],34:[9,33],75:list(range(46,55)),76:list(range(55,62)),79:[76],86:[33,34,85],97:[96],98:[96],100:[99]}
  for group in self.plan['groups']:
   selected=m.selected(self.plan,group)
   for child,parents in dependencies.items():
    if child in selected:
     self.assertTrue(set(parents)<=selected,(group,child,parents))
     self.assertTrue(all(parent<child for parent in parents))
 def test_all_company_timer_partitions_are_required_on_independent_workers(self):
  rows=self.plan['invocations'];script='tests/settings/task_timer_auto_stop_browser.cjs'
  pieces=[(i,row['argv'])for i,row in enumerate(rows)if row['argv'][0]==script]
  self.assertEqual([argv[1:]for _,argv in pieces],[['--owner','a'],['--owner','b'],['--owner','primary']])
  self.assertEqual(len({name for i,_ in pieces for name,g in self.plan['groups'].items()if i in g['owned']}),3)
  for i,_ in pieces:
   owner=next(name for name,g in self.plan['groups'].items()if i in g['owned'])
   self.assertNotIn(90,m.selected(self.plan,owner),'self-contained timer partition does not repeat earlier task/calendar browsers')
  changed=copy.deepcopy(self.plan);changed['invocations'][101]['argv'][-1]='a'
  with self.assertRaisesRegex(ValueError,'partition'):m.validate(changed,self.source.replace(script+' --owner b',script+' --owner a'))
  changed=copy.deepcopy(self.plan);changed['groups']['timers-primary']['owned'].remove(102);changed['groups']['timers-b']['owned'].append(102);del changed['groups']['timers-primary']
  with self.assertRaisesRegex(ValueError,'distinct isolated'):m.validate(changed,self.source)
 def test_self_contained_transition_timer_has_its_own_reader_in_sales_worker(self):
  self.assertIn(90,self.plan['groups']['sales-pdf']['owned'])
  self.assertFalse(set([84,85,86,89])&m.selected(self.plan,'sales-pdf'))
  section=self.source.split('#449_SHARD_FIXTURE_BEGIN\n',1)[1].split('#449_SHARD_FIXTURE_END',1)[0]
  owns=next(line for line in self.source.splitlines()if line.startswith('owns_shell()'))
  for group in [*self.plan['groups'],'all']:
   with tempfile.TemporaryDirectory()as folder:
    root=pathlib.Path(folder);log=root/'docker.log'
    body='set -euo pipefail\nwork='+shlex.quote(folder)+'\nupgrade=argws-crm-upgrade-42\nbrowser_group='+shlex.quote(group)+'\n'+owns+'\n'
    body+='docker(){ printf "%s\\n" "$*" >> '+shlex.quote(str(log))+'; }\n'+section
    result=subprocess.run(['bash','-c',body],cwd=ROOT,capture_output=True,text=True)
    self.assertEqual(result.returncode,0,result.stderr)
    prepared=group in {'sales-pdf','all'};shim=root/'task449-php'
    self.assertEqual(shim.exists(),prepared)
    if prepared:
     self.assertTrue(os.access(shim,os.X_OK));self.assertIn('ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',shim.read_text());self.assertIn('task_timer_http_fixture.php',log.read_text())
 def test_independent_recovery_ticket_and_task_policy_have_balanced_owners(self):
  for index,owner in [(29,'expenses'),(32,'settings'),(64,'providers'),(72,'settings'),(73,'providers'),(83,'settings'),(84,'banking'),(89,'providers')]:
   self.assertIn(index,self.plan['groups'][owner]['owned'])
  self.assertFalse(set([33,34,84,85,86])&m.selected(self.plan,'providers'))
  section=self.source.split('#448_SHARD_FIXTURE_BEGIN\n',1)[1].split('#448_SHARD_FIXTURE_END',1)[0]
  owns=next(line for line in self.source.splitlines()if line.startswith('owns_shell()'))
  for group in [*self.plan['groups'],'all']:
   with tempfile.TemporaryDirectory()as folder:
    root=pathlib.Path(folder);log=root/'docker.log'
    body='set -euo pipefail\nwork='+shlex.quote(folder)+'\nupgrade=argws-crm-upgrade-42\nbrowser_group='+shlex.quote(group)+'\n'+owns+'\n'
    body+='docker(){ printf "%s\\n" "$*" >> '+shlex.quote(str(log))+'; }\n'+section
    result=subprocess.run(['bash','-c',body],cwd=ROOT,capture_output=True,text=True)
    self.assertEqual(result.returncode,0,result.stderr)
    prepared=group in {'providers','all'};shim=root/'task448-php'
    self.assertEqual(shim.exists(),prepared)
    if prepared:
     self.assertTrue(os.access(shim,os.X_OK));self.assertIn('task_operations_http_fixture.php',log.read_text())
 def test_real_timer_fixture_preparation_runs_in_every_partition_worker(self):
  section=self.source.split('#451_SHARD_FIXTURE_BEGIN\n',1)[1].split('#451_SHARD_FIXTURE_END',1)[0]
  owns=next(line for line in self.source.splitlines()if line.startswith('owns_shell()'))
  for group in [*self.plan['groups'],'all']:
   with tempfile.TemporaryDirectory()as folder:
    root=pathlib.Path(folder);log=root/'docker.log'
    body='set -euo pipefail\nwork='+shlex.quote(folder)+'\nupgrade=argws-crm-upgrade-42\nbrowser_group='+shlex.quote(group)+'\n'+owns+'\n'
    body+='docker(){ printf "%s\\n" "$*" >> '+shlex.quote(str(log))+'; }\n'+section
    result=subprocess.run(['bash','-c',body],cwd=ROOT,capture_output=True,text=True)
    self.assertEqual(result.returncode,0,result.stderr)
    prepared=group in {'timers-a','timers-b','timers-primary','all'};shim=root/'task451-php'
    self.assertEqual(shim.exists(),prepared)
    if prepared:
     self.assertTrue(os.access(shim,os.X_OK));self.assertIn('ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',shim.read_text());self.assertIn('task_timer_stop_http_fixture.php',log.read_text())
 def test_workflow_isolates_stacks_and_requires_every_group(self):
  source=(ROOT/'.github/workflows/deployment-integrity.yml').read_text()
  self.assertIn('fail-fast: false',source)
  self.assertIn('ARGWS_BROWSER_GROUP: ${{ matrix.group }}',source)
  http=source.split('  http:\n',1)[1].split('  validate:',1)[0]
  self.assertIn('    timeout-minutes: 60',http)
  self.assertIn('name: issuer-native-browser-${{ matrix.group }}',source)
  self.assertIn('needs: [validation-gate, prepare, http, tooling]',source)
  self.assertIn('test "$HTTP" = success',source)
  self.assertIn('test "$TOOLING" = success',source)
  self.assertIn('docker save argws-crm-provision-smoke:ci',source)
  self.assertIn('docker load -i',source)
  for group in self.plan['groups']:self.assertIn(group,source)
 def test_required_gate_rejects_incomplete_or_failed_groups(self):
  import textwrap
  source=(ROOT/'.github/workflows/deployment-integrity.yml').read_text()
  body=textwrap.dedent(source.rsplit('        run: |',1)[1])
  good={**os.environ,'GATE':'success','RUN':'true','PREPARE':'success','HTTP':'success','TOOLING':'success'}
  def run(env):return subprocess.run(['bash','-ec',body],env=env,capture_output=True).returncode
  self.assertEqual(run(good),0)
  for name in ['GATE','PREPARE','HTTP','TOOLING']:
   for state in ['failure','cancelled','skipped','']:
    self.assertNotEqual(run({**good,name:state}),0,(name,state))
  self.assertEqual(run({**good,'RUN':'false','PREPARE':'skipped','HTTP':'skipped','TOOLING':'skipped'}),0)
  self.assertNotEqual(run({**good,'RUN':'false'}),0)
 def runtime(self,group,fail='',omit=False):
  with tempfile.TemporaryDirectory() as folder:
   root=pathlib.Path(folder);node=root/'node';node.write_text('#!/bin/sh\nprintf "%s\\n" "$*" >> "$FAKE_NODE_LOG"\nif [ "$*" = "$FAIL_COMMAND" ]; then exit 7; fi\n');node.chmod(0o755)
   header=self.source[self.source.index('browser_group='):self.source.index('suffix="$$"')]
   lines=self.source.splitlines();calls=[]
   for index,line in enumerate(lines):
    if not line.startswith('node '):continue
    start=index
    while start>0 and lines[start-1].rstrip().endswith('\\'):start-=1
    calls.append('\n'.join(lines[start:index+1]))
   self.assertNotIn('ISSUERS_BROWSER_DB_NAME',calls[56])
   if omit:calls=calls[:-1]
   fixtures='work='+shlex.quote(str(root))+'\nurl=http://127.0.0.1:1234\nupgrade_url=$url\nupgrade=argws-crm-upgrade-42\nlegacy=argws-crm-legacy-42\nISSUER_SMOKE_DATABASE=argws-crm-provision-db-42\nISSUER_SMOKE_DB_USER=synthetic\nISSUER_SMOKE_DB_PASSWORD=synthetic\n'
   sanitizer=self.source[self.source.index('sanitize_smoke_failure_log() {'):self.source.index('collect_smoke_failure() {')]
   body='set -euo pipefail\n'+fixtures+header+'\n'+sanitizer+'\n'+'\n'.join(calls)+'\n'
   body+='[[ "$browser_index" = "${#browser_commands[@]}" && "$browser_completed" = "$browser_required" ]]\n'
   env={**os.environ,'PATH':str(root)+':'+os.environ['PATH'],'ARGWS_BROWSER_GROUP':group,'ISSUERS_BROWSER_ARTIFACTS':str(root/'evidence'),'FAKE_NODE_LOG':str(root/'calls'),'FAIL_COMMAND':fail,'GITHUB_STEP_SUMMARY':str(root/'summary.md')}
   result=subprocess.run(['bash','-c',body],cwd=ROOT,env=env,capture_output=True,text=True)
   actual=(root/'calls').read_text().splitlines() if (root/'calls').exists() else []
   summary=(root/'summary.md').read_text().splitlines()
   self.assertEqual(len([line for line in summary if line.startswith('| ')]),len(actual)+1,'timing summary records only invocations actually executed by this worker')
   return result,actual
 def test_real_bash_selector_executes_only_the_full_ordered_plan_of_each_group(self):
  for group in self.plan['groups']:
   result,actual=self.runtime(group)
   self.assertEqual(result.returncode,0,result.stderr)
   selected=m.selected(self.plan,group)
   self.assertEqual(actual,[' '.join(row['argv']) for i,row in enumerate(self.plan['invocations']) if i in selected])
 def test_bash_propagates_failure_and_rejects_incomplete_run(self):
  result,_=self.runtime('sales-pdf',fail='tests/settings_document_pdf_browser.cjs')
  self.assertEqual(result.returncode,7,result.stderr)
  result,_=self.runtime('sales-pdf',omit=True)
  self.assertNotEqual(result.returncode,0)
if __name__=='__main__':unittest.main()
