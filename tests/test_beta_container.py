import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
import urllib.error

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts'))
spec = importlib.util.spec_from_file_location('beta', ROOT / 'scripts/publish-beta-image.py')
B = importlib.util.module_from_spec(spec)
spec.loader.exec_module(B)
SHA = 'a' * 40
VERSION = '4.0.3'
D = 'sha256:' + 'b' * 64


def manifest(sha=SHA):
    return D, {'annotations': {'org.opencontainers.image.revision': sha,
        'org.opencontainers.image.version': VERSION, 'io.argws.channel': 'develop-beta'},
        'manifests': [{'platform': {'os': 'linux', 'architecture': arch}} for arch in ['amd64', 'arm64']]}


class PublicationTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.directory = Path(self.tmp.name)
        for arch, digit in [('amd64', 'c'), ('arm64', 'd')]:
            (self.directory / (arch + '.json')).write_text(json.dumps({
                'sha': SHA, 'version': VERSION, 'arch': arch, 'digest': 'sha256:' + digit * 64}))
        self.writes = []
        self.remote = SHA
        self.version = None
        self.reads = 0
        self.ancestor = True
        self.interrupt = False

    def command(self, args):
        if args[:2] == ['git', 'ls-remote']:
            return self.remote + '\t' + B.BRANCH + '\n'
        if args[:2] == ['git', 'merge-base']:
            if not self.ancestor: raise B.subprocess.CalledProcessError(1, args)
            return ''
        self.writes.append(args)
        target = args[args.index('-t') + 1]
        self.assertEqual(target, B.IMAGE + ':4.0.3', 'only authorized mutable tag')
        self.version = manifest()
        if self.interrupt: raise OSError('simulated interruption after tag write')
        return ''

    def manifest(self, ref):
        self.assertEqual(ref, VERSION)
        self.reads += 1
        return self.version

    def publish(self):
        return B.publish(VERSION, SHA, self, self.command, self.directory)

    def test_first_publication_writes_only_403_with_provenance(self):
        result = self.publish()
        self.assertEqual(len(self.writes), 1)
        self.assertEqual(result['sha'], SHA)
        self.assertEqual(result['digest'], D)
        self.assertIsNone(result['previous_digest'])

    def test_forward_beta_update_records_previous_digest_and_sha(self):
        self.version = ('sha256:'+'e'*64, manifest('f'*40)[1])
        result = self.publish()
        self.assertEqual(result['previous_digest'], 'sha256:'+'e'*64)
        self.assertEqual(result['previous_sha'], 'f'*40)
        self.assertEqual(result['sha'], SHA)
        self.assertEqual(len(self.writes), 1)

    def test_nonancestor_image_is_never_overwritten(self):
        self.version = manifest('e'*40); self.ancestor = False
        with self.assertRaises(B.subprocess.CalledProcessError): self.publish()
        self.assertEqual(self.writes, [])

    def test_unannotated_stable_image_is_preserved(self):
        self.version = D, {'manifests': []}
        with self.assertRaisesRegex(ValueError, 'Colisão'): self.publish()
        self.assertEqual(self.writes, [])

    def test_retry_same_sha_does_not_rebuild_or_retag(self):
        self.version = manifest()
        self.publish()
        self.assertEqual(self.writes, [])

    def test_retry_after_interruption_recovers_without_overwriting(self):
        self.interrupt = True
        with self.assertRaises(OSError): self.publish()
        self.interrupt = False; self.writes.clear()
        self.publish()
        self.assertEqual(self.writes, [])

    def test_obsolete_build_cannot_roll_tag_back(self):
        self.remote = 'e'*40
        with self.assertRaisesRegex(ValueError, 'obsoleto'): self.publish()
        self.assertEqual(self.writes, [])

    def test_registry_change_detected_before_write(self):
        def racing(ref):
            self.reads += 1
            return None if self.reads == 1 else manifest('e'*40)
        self.manifest = racing
        with self.assertRaisesRegex(ValueError, 'Tag mudou'): self.publish()
        self.assertEqual(self.writes, [])

    def test_branch_advance_after_reads_blocks_write(self):
        original = self.manifest
        def advancing(ref):
            value = original(ref)
            self.remote = 'e'*40
            return value
        self.manifest = advancing
        with self.assertRaisesRegex(ValueError, 'obsoleto'): self.publish()
        self.assertEqual(self.writes, [])

    def test_corrupt_or_foreign_artifacts_fail_before_write(self):
        for key, value in [('sha', 'e'*40), ('version', '4.0.2'), ('arch', 'arm64'), ('digest', 'latest')]:
            p = self.directory / 'amd64.json'
            saved = p.read_text()
            data = json.loads(saved); data[key] = value; p.write_text(json.dumps(data))
            with self.subTest(key=key), self.assertRaises(ValueError): self.publish()
            self.assertEqual(self.writes, [])
            p.write_text(saved)

    def test_other_version_requires_new_decision(self):
        with self.assertRaisesRegex(ValueError, 'Ciclo beta'):
            B.publish('4.0.4', SHA, self, self.command, self.directory)
        self.assertEqual(self.writes, [])

    def test_publish_context_rejects_pr_tags_forks_main_and_develop(self):
        good = dict(GITHUB_EVENT_NAME='push', GITHUB_REF=B.BRANCH, GITHUB_REPOSITORY=B.REPO, GITHUB_SHA=SHA)
        self.assertEqual(B.context(good), SHA)
        for key, value in [('GITHUB_EVENT_NAME', 'pull_request'), ('GITHUB_EVENT_NAME', 'workflow_dispatch'),
            ('GITHUB_REF', 'refs/heads/main'), ('GITHUB_REF', 'refs/heads/develop'),
            ('GITHUB_REF', 'refs/tags/v4.0.3'), ('GITHUB_REPOSITORY', 'fork/ARGWS-CRM'), ('GITHUB_SHA', 'abc')]:
            with self.subTest(value=value), self.assertRaises(ValueError): B.context({**good, key:value})


class RegistryReadTest(unittest.TestCase):
    def setUp(self):
        self.registry = B.Registry.__new__(B.Registry)
        self.registry.token = 'synthetic'

    def test_only_explicit_manifest_unknown_is_absence(self):
        for status, code in [(404, 'MANIFEST_UNKNOWN'), (401, 'UNAUTHORIZED'), (403, 'DENIED'),
                             (429, 'TOOMANYREQUESTS'), (500, 'UNKNOWN'), (404, 'NAME_UNKNOWN')]:
            body = json.dumps({'errors':[{'code':code}]}).encode()
            error = urllib.error.HTTPError('https://ghcr.io/synthetic', status, 'synthetic', {}, io.BytesIO(body))
            with self.subTest(status=status, code=code), patch.object(B.urllib.request, 'urlopen', side_effect=error):
                if code == 'MANIFEST_UNKNOWN': self.assertIsNone(self.registry.manifest(VERSION))
                else:
                    with self.assertRaises(ValueError): self.registry.manifest(VERSION)

    def test_transport_failure_is_not_absence(self):
        with patch.object(B.urllib.request, 'urlopen', side_effect=OSError('network')):
            with self.assertRaises(ValueError): self.registry.manifest(VERSION)

    def test_manifest_digest_is_verified(self):
        data = json.dumps(manifest()[1]).encode()
        response = io.BytesIO(data)
        response.headers = {'Docker-Content-Digest': 'sha256:' + hashlib.sha256(data).hexdigest()}
        with patch.object(B.urllib.request, 'urlopen', return_value=response):
            digest, doc = self.registry.manifest(VERSION)
            self.assertEqual(digest, 'sha256:' + hashlib.sha256(data).hexdigest())
        response = io.BytesIO(data); response.headers = {'Docker-Content-Digest': D}
        with patch.object(B.urllib.request, 'urlopen', return_value=response):
            with self.assertRaises(ValueError): self.registry.manifest(VERSION)

    def test_other_tags_rejected_before_network(self):
        for tag in ['latest', 'develop', 'develop-beta', '../config', '4', '4.0', '4.0.4', '4.0.3;cmd']:
            with self.subTest(tag=tag), self.assertRaises(ValueError): self.registry.manifest(tag)


class WorkflowTest(unittest.TestCase):
    def test_pr_does_not_login_or_publish_and_push_requires_all_gates(self):
        source = (ROOT / '.github/workflows/beta-container.yml').read_text()
        pr = source.split('  build-pr:')[1].split('  build-push:')[0]
        self.assertIn('push: false', pr)
        self.assertIn('load: true', pr)
        self.assertNotIn('login-action', pr)
        self.assertNotIn('packages: write', pr)
        self.assertNotIn('workflow_dispatch', source)
        self.assertEqual(source.count('branches: [develop-beta]'), 2)
        self.assertIn('needs: [validate, build-push]', source)
        self.assertIn("cancel-in-progress: ${{ github.event_name == 'pull_request' }}", source)
        for gate in ['check-release-version.sh', 'test_beta_container.py', 'native_migration_tracking.php',
                     'native_pwa.browser.cjs', 'smoke-beta-image.sh']:
            self.assertIn(gate, source)
        for forbidden in [':latest', ':develop"', 'gh release', 'git tag', 'continue-on-error']:
            self.assertNotIn(forbidden, source)

    def test_image_smoke_uses_php_without_entrypoint_database_or_network(self):
        source = (ROOT / 'scripts/smoke-beta-image.sh').read_text()
        for marker in ['--network none', '--entrypoint php', 'EXPECTED_VERSION', 'migration_version',
                       'define("BASEPATH"', 'define("APPPATH"', 'pwa.php']:
            self.assertIn(marker, source)


if __name__ == '__main__':
    unittest.main()
