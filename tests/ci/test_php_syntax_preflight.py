import importlib.util,pathlib,unittest
s=importlib.util.spec_from_file_location('syntax',pathlib.Path(__file__).resolve().parents[2]/'scripts/ci/check-php-syntax.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class SyntaxTests(unittest.TestCase):
 def test_container_checkout_uses_exact_nonpersistent_safe_directory(self):
  root=pathlib.Path('/__w/ARGWS-CRM/ARGWS-CRM');calls=[]
  def run(args,**kwargs):calls.append((args,kwargs));return b'application/a.php\0modules/x/vendor/lib.php\0tests/t.php\0assets/x.js\0'
  self.assertEqual(m.tracked_sources(root,run),['application/a.php','tests/t.php'])
  self.assertEqual(calls[0][0][:4],['git','-c','safe.directory='+str(root),'ls-files']);self.assertEqual(calls[0][1]['cwd'],root)
  self.assertNotIn('--global',calls[0][0]);self.assertNotIn('safe.directory=*',calls[0][0])
if __name__=='__main__':unittest.main()
