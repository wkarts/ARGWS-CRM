"""Read-only contracts for the dedicated, non-publishing CI build."""
import json
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]

class ValidationBaseTests(unittest.TestCase):
    def read(self, path):
        return (ROOT / path).read_text()

    def test_lock_is_dedicated_and_exact(self):
        manifest = json.loads(self.read('docker/ci/package.json'))
        lock = json.loads(self.read('docker/ci/package-lock.json'))
        self.assertTrue(manifest['private'])
        self.assertEqual(manifest['dependencies'], {'playwright': '1.55.1'})
        self.assertEqual(lock['packages']['']['dependencies'], manifest['dependencies'])
        self.assertEqual(set(lock['packages']), {'', 'node_modules/playwright', 'node_modules/playwright-core', 'node_modules/fsevents'})
        for name in ('playwright', 'playwright-core'):
            package = lock['packages']['node_modules/' + name]
            self.assertEqual(package['version'], '1.55.1')
            self.assertTrue(package['integrity'].startswith('sha512-'))
            self.assertTrue(package['resolved'].startswith('https://registry.npmjs.org/'))

    def test_build_context_cannot_copy_application(self):
        ignore = self.read('docker/ci/.dockerignore').splitlines()
        self.assertEqual(ignore, ['*', '!Dockerfile', '!package.json', '!package-lock.json', '!smoke.sh', '!smoke.cjs'])
        docker = self.read('docker/ci/Dockerfile')
        self.assertIn('npm ci --ignore-scripts', docker)
        self.assertIn('playwright/cli.js install --with-deps chromium', docker)
        self.assertIn('pdo_mysql mysqli gd mbstring', docker)
        self.assertNotRegex(docker, r'(?im)^COPY\s+\.\s')
        self.assertNotIn('ARGWS_DB', docker)

    def test_build_has_no_write_and_publish_requires_exact_authority(self):
        workflow = self.read('.github/workflows/ci-validation-base.yml')
        self.assertIn("php: ['8.1', '8.5']", workflow)
        self.assertIn('context: docker/ci', workflow)
        self.assertIn('push: false', workflow)
        self.assertIn('contents: read', workflow)
        self.assertIn('persist-credentials: false', workflow)
        build, publish = workflow.split('  publish:', 1)
        self.assertNotIn('packages: write', build)
        self.assertNotIn('secrets.', build)
        self.assertIn('packages: write', publish)
        self.assertIn('needs: build', publish)
        self.assertIn("github.repository == 'wkarts/ARGWS-CRM'", publish)
        self.assertNotIn('head_commit.message', workflow)
        self.assertNotIn('fix/crm-post-call-integration', workflow)
        self.assertIn("github.event_name == 'workflow_dispatch'", publish)
        self.assertIn('branches: [main]', workflow)
        self.assertIn('default: false', workflow)
        self.assertIn("inputs.publish == true", publish)
        self.assertLess(publish.index('argws-ci-smoke'), publish.index('docker login'))
        self.assertIn('docker load -i', publish)
        self.assertIn('GITHUB_RUN_ATTEMPT', publish)
        self.assertIn('digest_reference', publish)
        self.assertIn('must-verify-anonymous-pull', publish)
        self.assertNotIn('pull_request_target', workflow)
        self.assertNotIn('docker/login-action', workflow)
        self.assertIn('--network none', workflow)
        self.assertIn('argws-ci-smoke', workflow)

    def test_published_receipts_keep_real_digest_and_provenance(self):
        receipts = json.loads(self.read('docs/ci/validation-images.json'))
        self.assertEqual({r['php'] for r in receipts}, {'8.1', '8.5'})
        self.assertEqual(len(receipts), 2)
        self.assertEqual(len({r['digest_reference'] for r in receipts}), 2)
        for receipt in receipts:
            self.assertRegex(receipt['digest_reference'], r'^ghcr\.io/wkarts/argws-crm-ci-php@sha256:[a-f0-9]{64}$')
            self.assertRegex(receipt['inputs_sha256'], r'^[a-f0-9]{64}$')
            self.assertRegex(receipt['source_sha'], r'^[a-f0-9]{40}$')
            self.assertRegex(receipt['run_id'], r'^[0-9]+$')
            self.assertEqual(receipt['platform'], 'linux/amd64')
            self.assertEqual(receipt['smoke'], 'passed-before-login')
            self.assertEqual(receipt['visibility'], 'must-verify-anonymous-pull')

    def test_no_bootstrap_publish_path_survives(self):
        workflow = self.read('.github/workflows/ci-validation-base.yml')
        gate = "github.repository == 'wkarts/ARGWS-CRM' && github.event_name == 'workflow_dispatch' && inputs.publish == true"
        self.assertEqual(workflow.count(gate), 3)
        self.assertNotIn('publish approved validation bases', workflow)
        self.assertNotIn('TEMPORARY bootstrap', workflow)
        self.assertEqual(workflow.count('packages: write'), 1)

    def test_smoke_remains_fail_closed(self):
        smoke = self.read('docker/ci/smoke.sh')
        self.assertIn('EXPECTED_PHP:?', smoke)
        self.assertIn('extension_loaded', smoke)
        self.assertIn('JPEG Support', smoke)
        browser = self.read('docker/ci/smoke.cjs')
        self.assertIn('timeout: 30000', browser)
        self.assertIn("route => route.abort()", browser)
        self.assertIn('process.exitCode = 1', browser)
        self.assertIn('finally { await browser.close(); }', browser)

if __name__ == '__main__':
    unittest.main()
