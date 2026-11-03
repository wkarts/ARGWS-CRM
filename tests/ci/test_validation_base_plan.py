import importlib.util,pathlib,tempfile,json,unittest
ROOT=pathlib.Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('base_plan',ROOT/'scripts/ci/validation-base-plan.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class BasePlanTests(unittest.TestCase):
 def setUp(self):self.receipts=json.loads((ROOT/'docs/ci/validation-images.json').read_text())
 def test_current_inputs_match_both_real_published_digests(self):
  for row in self.receipts:self.assertEqual(m.reusable_digest(row['php'],m.inputs_hash(),self.receipts),row['digest_reference'])
 def test_changed_inputs_require_rebuild(self):
  self.assertIsNone(m.reusable_digest('8.1','different',self.receipts))
 def test_explicit_refresh_requires_rebuild(self):
  self.assertIsNone(m.reusable_digest('8.1',m.inputs_hash(),self.receipts,True))
 def test_ambiguous_unverified_or_other_registry_proof_requires_rebuild(self):
  for rows in [self.receipts+self.receipts,[{**self.receipts[0],'smoke':'pending'}],[{**self.receipts[0],'digest_reference':'somewhere:latest'}]]:
   self.assertIsNone(m.reusable_digest('8.1',m.inputs_hash(),rows))
 def test_each_image_input_affects_the_identity(self):
  with tempfile.TemporaryDirectory() as folder:
   root=pathlib.Path(folder)
   for name in m.INPUTS:
    p=root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes((ROOT/name).read_bytes())
   self.assertEqual(m.inputs_hash(root),m.inputs_hash())
   for name in m.INPUTS:
    p=root/name;old=p.read_bytes();p.write_bytes(old+b'\n');self.assertNotEqual(m.inputs_hash(root),m.inputs_hash());p.write_bytes(old)
if __name__=='__main__':unittest.main()
