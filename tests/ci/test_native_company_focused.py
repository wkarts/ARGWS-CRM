import importlib.util,json,pathlib,tempfile,unittest
from unittest.mock import patch
ROOT=pathlib.Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('focused',ROOT/'scripts/ci/run-native-company-focused.py')
focused=importlib.util.module_from_spec(spec);spec.loader.exec_module(focused)
class FocusedContract(unittest.TestCase):
 def test_real_manifest(self):
  for phase in ('sqlite','node','mysql','browser'):
   self.assertTrue(focused.commands(phase))
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
if __name__=='__main__':unittest.main()
