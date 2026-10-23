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

    def test_workflow_does_not_publish_or_use_secrets(self):
        workflow = self.read('.github/workflows/ci-validation-base.yml')
        self.assertIn("php: ['8.1', '8.5']", workflow)
        self.assertIn('context: docker/ci', workflow)
        self.assertIn('push: false', workflow)
        self.assertIn('contents: read', workflow)
        self.assertIn('persist-credentials: false', workflow)
        self.assertNotIn('packages: write', workflow)
        self.assertNotIn('secrets.', workflow)
        self.assertNotIn('pull_request_target', workflow)
        self.assertNotIn('docker/login-action', workflow)
        self.assertIn('--network none', workflow)
        self.assertIn('argws-ci-smoke', workflow)

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
