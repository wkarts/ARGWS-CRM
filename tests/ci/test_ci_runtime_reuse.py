"""Cache inputs may save setup work; they must not substitute for validation."""
import pathlib,subprocess,tempfile,os,unittest
ROOT=pathlib.Path(__file__).resolve().parents[2]
class RuntimeReuseTests(unittest.TestCase):
 def test_prebuilt_smoke_requires_the_exact_source_tree(self):
  source=(ROOT/'tests/docker_provision_smoke.sh').read_text()
  block=source[source.index('if [ "${ARGWS_SMOKE_PREBUILT_IMAGE'):source.index('docker run --rm --entrypoint /bin/sh')]
  with tempfile.TemporaryDirectory() as folder:
   folder=pathlib.Path(folder)
   for name,text in {'git':'#!/bin/sh\necho expected\n','docker':'#!/bin/sh\necho "$TEST_IMAGE_TREE"\n'}.items():
    path=folder/name;path.write_text(text);path.chmod(0o755)
   env={**os.environ,'PATH':str(folder)+':'+os.environ['PATH'],'ARGWS_SMOKE_PREBUILT_IMAGE':'1'}
   for tree,expected in [('expected',0),('stale',1),('',1)]:
    result=subprocess.run(['bash','-ec','image=synthetic\n'+block],env={**env,'TEST_IMAGE_TREE':tree},capture_output=True,text=True)
    self.assertEqual(result.returncode,expected,result.stderr)
 def test_browser_cache_does_not_remove_install_or_actual_tests(self):
  source=(ROOT/'.github/workflows/deployment-integrity.yml').read_text()
  self.assertIn("chromium-${{ runner.os }}-${{ runner.arch }}-${{ hashFiles('docker/ci/package-lock.json') }}",source)
  self.assertIn('playwright" install --with-deps chromium',source)
  self.assertIn('run: bash tests/docker_provision_smoke.sh',source)
  self.assertIn('load: true',source);self.assertIn('push: false',source)
  self.assertNotIn('cache-hit',source)
 def test_no_message_only_job_blocks_registry_shards(self):
  source=(ROOT/'.github/workflows/issuer-foundation.yml').read_text()
  self.assertNotIn('registry-plan',source)
  self.assertIn('test "$SHARDS_RESULT" = success',source)
  self.assertIn('needs: [validation-gate, registry-shards]',source)
if __name__=='__main__':unittest.main()
