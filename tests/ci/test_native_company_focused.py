import importlib.util,json,pathlib,re,tempfile,unittest
from unittest.mock import patch
ROOT=pathlib.Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('focused',ROOT/'scripts/ci/run-native-company-focused.py')
focused=importlib.util.module_from_spec(spec);spec.loader.exec_module(focused)
class FocusedContract(unittest.TestCase):
 def test_real_manifest(self):
  seen=set()
  for phase in ('sqlite','node','mysql','browser'):
   commands=focused.commands(phase);self.assertTrue(commands)
   current={tuple(command) for command in commands}
   self.assertTrue(seen.isdisjoint(current),f'duplicate focused command across phases: {seen&current}')
   seen.update(current)
 def test_rejects_unsafe_or_duplicate_commands(self):
  good=['php','tests/companies/native_table_order.php']
  for rows in ([good,good],[['bash','tests/companies/native_table_order.php']],[['php','../outside.php']],[['php','tests/missing.php']]):
   with self.subTest(rows=rows),tempfile.TemporaryDirectory() as folder:
    file=pathlib.Path(folder)/'manifest.json';file.write_text(json.dumps({'format':1,'sqlite':rows}))
    with patch.object(focused,'MANIFEST',file),self.assertRaises(ValueError):focused.commands('sqlite')
 def test_first_failure_stops_and_records_incomplete(self):
  with tempfile.TemporaryDirectory() as folder,patch.object(focused,'commands',return_value=[['node','tests/first.cjs'],['node','tests/second.cjs']]),patch.object(focused.sys,'argv',['runner','node']),patch.dict(focused.os.environ,{'NATIVE_COMPANY_EVIDENCE':folder}),patch.object(focused.subprocess,'run') as run:
   run.return_value=type('Result',(),{'stdout':'synthetic failure\n','returncode':1})()
   self.assertEqual(focused.main(),1);self.assertEqual(run.call_count,1)
   receipt=json.loads((pathlib.Path(folder)/'node.json').read_text());self.assertFalse(receipt['complete']);self.assertFalse(receipt['success'])
 def test_php_warning_is_failure_even_with_exit_zero(self):
  with tempfile.TemporaryDirectory() as folder,patch.object(focused,'commands',return_value=[['php','tests/first.php']]),patch.object(focused.sys,'argv',['runner','sqlite']),patch.dict(focused.os.environ,{'NATIVE_COMPANY_EVIDENCE':folder,'ISSUERS_TEST_DSN':''}),patch.object(focused.subprocess,'run') as run:
   run.return_value=type('Result',(),{'stdout':'Warning: synthetic warning\n','returncode':0})()
   self.assertEqual(focused.main(),1);receipt=json.loads((pathlib.Path(folder)/'sqlite.json').read_text());self.assertTrue(receipt['records'][0]['php_diagnostics']);self.assertFalse(receipt['success'])
 def test_mysql_requires_exact_disposable_service(self):
  with patch.object(focused,'commands',return_value=[['php','tests/first.php']]),patch.object(focused.sys,'argv',['runner','mysql']),patch.dict(focused.os.environ,{'ISSUERS_TEST_DSN':'mysql:host=elsewhere;dbname=production'}),self.assertRaises(ValueError):focused.main()
 def test_sqlite_nested_coverage_is_only_suppressed_for_first_static_require(self):
  with tempfile.TemporaryDirectory() as folder:
   root=pathlib.Path(folder);(root/'tests').mkdir()
   files={'parent.php':'<?php echo "parent";',
          'child.php':"<?php\nrequire __DIR__.'/parent.php';\necho 'child';",
          'grandchild.php':"<?php /* fixture */\nrequire_once(__DIR__.'/child.php');",
          'conditional.php':"<?php if (false) require __DIR__.'/parent.php';",
          'dynamic.php':"<?php require $path;",
          'cycle_a.php':"<?php require __DIR__.'/cycle_b.php';",
          'cycle_b.php':"<?php require __DIR__.'/cycle_a.php';"}
   for name,source in files.items():(root/'tests'/name).write_text(source)
   selected=[['php','tests/'+name] for name in files]
   with patch.object(focused,'ROOT',root):retained,covered=focused.sqlite_plan(selected)
   self.assertEqual({c[-1] for c in retained},{'tests/grandchild.php','tests/conditional.php','tests/dynamic.php','tests/cycle_a.php','tests/cycle_b.php'})
   self.assertEqual(covered,{'tests/child.php':'tests/grandchild.php','tests/parent.php':'tests/grandchild.php'})
   # If the child is changed to a conditional inclusion, the parent must run
   # independently again without a hand-maintained list of skipped tests.
   (root/'tests'/'child.php').write_text("<?php if (true) require __DIR__.'/parent.php';")
   with patch.object(focused,'ROOT',root):retained,covered=focused.sqlite_plan(selected)
   self.assertIn(['php','tests/parent.php'],retained)
   self.assertNotIn('tests/parent.php',covered)
 def test_domain_owned_checks_are_not_repeated_by_focused_manifest(self):
  manifest={command[-1] for phase in ('sqlite','node','mysql','browser') for command in focused.commands(phase)}
  owners={
   'tests/companies/bb_channel_preferences.php':'.github/workflows/banco-brasil.yml',
   'tests/crm_ui_language.php':'.github/workflows/crm-ui-policy.yml',
   'tests/native_shell_controls.test.cjs':'.github/workflows/company-native-ui.yml',
   'tests/native_sidebar_toggle.test.cjs':'.github/workflows/company-native-ui.yml',
   'tests/native_header_overflow.test.cjs':'.github/workflows/native-header-layout.yml',
  }
  for test,workflow in owners.items():
   with self.subTest(test=test):
    self.assertNotIn(test,manifest)
    self.assertIn(test,(ROOT/workflow).read_text())
 def test_focused_manifest_has_no_unreviewed_cross_workflow_execution(self):
  manifest={command[-1] for phase in ('sqlite','node','mysql','browser') for command in focused.commands(phase)}
  executions={}
  for workflow in sorted((ROOT/'.github/workflows').glob('*.yml')):
   lines=workflow.read_text().splitlines();blocks=[];index=0
   while index<len(lines):
    match=re.match(r'^(\s*)run:\s*(.*)$',lines[index])
    if not match:index+=1;continue
    indent=len(match.group(1));body=match.group(2);index+=1
    if body in ('|','>','|-','>-'):
     chunk=[]
     while index<len(lines) and (not lines[index].strip() or len(lines[index])-len(lines[index].lstrip())>indent):
      chunk.append(lines[index]);index+=1
     body='\n'.join(chunk)
    blocks.append(body)
   for test in set(re.findall(r'(?<![A-Za-z0-9_./-])(tests/[A-Za-z0-9_./-]+\.(?:php|py|cjs|js|sh))','\n'.join(blocks))):
    executions.setdefault(test,[]).append(workflow.name)
  overlap={test:executions[test] for test in sorted(manifest&set(executions))}
  # Two Node tests intentionally execute once with each supported PHP runtime;
  # the two invoice tests use SQLite here and real MySQL in their domain gate.
  self.assertEqual(overlap,{
   'tests/companies/native_file_message_ui.test.cjs':['native-company-experience.yml'],
   'tests/invoice_issuer_discovery.php':['invoice-issuer-scope.yml'],
   'tests/invoice_issuer_http_files.php':['invoice-issuer-scope.yml'],
   'tests/native_company/invoice_calculation_differential.test.cjs':['native-company-experience.yml'],
  })
 def test_registry_overlap_is_database_differential_only(self):
  focused_commands={tuple(command) for phase in ('sqlite','node','mysql','browser') for command in focused.commands(phase)}
  registry={tuple(command) for command in json.loads((ROOT/'tests/ci/company-registry-commands.json').read_text())}
  # These contracts deliberately run once against SQLite in the focused gate
  # and once against MySQL in the full registry. No command may overlap from
  # the focused MySQL/Node/browser phases.
  sqlite_overlap={
   ('php','tests/companies/asaas_checkout_portal.php'),
   ('php','tests/companies/client_imports_controller.php'),
   ('php','tests/companies/contract_discussions_controller.php'),
   ('php','tests/companies/contracts_controller.php'),
   ('php','tests/companies/invoice_drafts.php'),
   ('php','tests/companies/portal_operations_controller.php'),
   ('php','tests/native_context.php'),
   ('php','tests/issuers_core_upgrade.php'),
   ('php','tests/settings/native_assets.php'),
   ('php','tests/settings/google_mail_worker.php'),
  }
  self.assertEqual(focused_commands&registry,sqlite_overlap)
  self.assertTrue(sqlite_overlap.issubset({tuple(command) for command in focused.commands('sqlite')}))
 def test_workflow_does_common_work_once_and_cancels_only_superseded_ref(self):
  workflow=(ROOT/'.github/workflows/native-company-experience.yml').read_text()
  self.assertIn('group: native-company-experience-${{ github.event.pull_request.number || github.ref }}',workflow)
  self.assertIn('cancel-in-progress: true',workflow)
  self.assertEqual(workflow.count('python3 tests/ci/test_native_company_focused.py'),1)
  self.assertIn('- name: Validate focused manifest and action census once',workflow)
  self.assertLess(workflow.index('- name: Validate focused manifest and action census once'),workflow.index('- name: Test shared native UI contracts without browser'))
  self.assertEqual(workflow.count('scripts/native-experience-inventory.php'),1)
  self.assertNotIn('scripts/company-surface-inventory.php',workflow)
  self.assertNotIn('scripts/inventory-company-boundaries.py',workflow)
  self.assertNotIn('tests/native_ui_surface_inventory.py',workflow)
  self.assertRegex(workflow,r"(?s)- uses: actions/setup-node@v4\s+if: matrix\.php == '8\.1'")
if __name__=='__main__':unittest.main()
