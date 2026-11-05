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
        'manifests': [{'digest': 'sha256:' + digit*64, 'platform': {'os': 'linux', 'architecture': arch}}
                      for arch, digit in [('amd64', 'c'), ('arm64', 'd')]]}


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
        self.assertEqual(target, B.IMAGE + ':develop-beta', 'only authorized mutable tag')
        self.version = manifest()
        if self.interrupt: raise OSError('simulated interruption after tag write')
        return ''

    def manifest(self, ref):
        self.assertEqual(ref, B.CHANNEL)
        self.reads += 1
        return self.version

    def publish(self):
        return B.publish(VERSION, SHA, self, self.command, self.directory)

    def test_first_publication_writes_only_channel_with_provenance(self):
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

    def test_multiarch_index_rejects_duplicates_and_wrong_build_digests(self):
        self.version = manifest()
        self.version[1]['manifests'].append(self.version[1]['manifests'][0])
        with self.assertRaises(ValueError): self.publish()
        self.version = None
        original = self.command
        def changed(args):
            result = original(args)
            if args[0] == 'docker': self.version[1]['manifests'][0]['digest'] = 'sha256:'+'e'*64
            return result
        self.command = changed
        with self.assertRaisesRegex(ValueError, 'digests aprovados'): self.publish()

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
        for tag in ['latest', 'develop', '../config', '4', '4.0', '4.0.4', '4.0.3;cmd']:
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
        publish = source.split('  publish:')[1]
        self.assertIn('contents: write', publish)
        self.assertIn('GH_TOKEN: ${{ github.token }}', publish)
        self.assertNotIn('secrets.', publish)
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


class ReleaseTest(unittest.TestCase):
    def setUp(self):
        self.ref = None
        self.tag = None
        self.release = None
        self.writes = []
        self.fail_after = None
        self.tag_objects = {}

    def call(self, path, payload=None, method=None):
        if payload is None:
            if path == '': return {'full_name': B.REPO}
            if path == 'git/ref/tags/' + B.RELEASE_TAG: return self.ref
            if path.startswith('git/tags/'): return self.tag_objects[path.split('/')[-1]]
            if path == 'releases/tags/' + B.RELEASE_TAG: return self.release
            raise AssertionError(path)
        self.writes.append((path, payload, method))
        if path == 'git/tags':
            self.tag = {**payload, 'object': {'sha': payload['object'], 'type': 'commit'}}
            tag_sha = payload['object'][0]*39+'f'
            self.tag_objects[tag_sha] = self.tag
            result = {'sha': tag_sha}
        elif path == 'git/refs':
            self.assertIsNone(self.ref)
            self.ref = {'object': {'sha': payload['sha'], 'type': 'tag'}}
            result = self.ref
        elif path in ['releases', 'releases/42']:
            self.assertTrue(payload['prerelease'])
            self.assertEqual(payload['make_latest'], 'false')
            self.release = {**payload, 'id': 42, 'assets': [], 'html_url': 'https://example.invalid/beta'}
            result = self.release
        else: raise AssertionError(path)
        if self.fail_after == path: raise OSError('ambiguous response after write')
        return result

    def command(self, args, input_text=None):
        if args[:2] == ['git', 'ls-remote']: return SHA + '\t' + B.BRANCH
        if args[:2] == ['git', 'show']: return VERSION
        if args[:2] == ['git', 'merge-base']: return ''
        if args[:2] == ['git', 'mktag']:
            self.assertIn('tag '+B.RELEASE_TAG+'\n', input_text)
            metadata = json.loads(input_text.split('\n\n', 1)[1])
            tag_sha = metadata['sha'][0]*39+'f'
            self.tag_objects[tag_sha] = {'tag': B.RELEASE_TAG, 'object': {'type': 'commit', 'sha': metadata['sha']},
                                         'message': json.dumps(metadata, sort_keys=True)}
            return tag_sha
        if args[:2] == ['git', 'push']:
            self.assertEqual(args[2], '--force-with-lease=refs/tags/'+B.RELEASE_TAG+':'+self.ref['object']['sha'])
            self.ref = {'object': {'sha': args[-1].split(':')[0], 'type': 'tag'}}
            if self.fail_after == 'git-push': raise OSError('uncertain CAS result')
            return ''
        raise AssertionError(args)

    def publish(self):
        state = B.release_state(self, SHA, self.command)
        return B.publish_release(self, SHA, D, self.command, state)

    def test_create_and_retry_keep_one_release_and_tag(self):
        self.publish()
        self.assertEqual([w[0] for w in self.writes], ['git/tags', 'git/refs', 'releases'])
        self.writes.clear()
        self.publish()
        self.assertEqual(self.writes, [])

    def test_interrupted_ref_or_release_is_reconciled_without_replacement(self):
        for path in ['git/refs', 'releases']:
            self.setUp(); self.fail_after = path
            with self.assertRaises(OSError): self.publish()
            self.fail_after = None; self.writes.clear()
            self.publish()
            self.assertEqual([w[0] for w in self.writes], ['releases'] if path == 'git/refs' else [])

    def test_foreign_lightweight_tag_is_preserved(self):
        self.ref = {'object': {'type': 'commit', 'sha': SHA}}
        with self.assertRaisesRegex(ValueError, 'Colisão'): self.publish()
        self.assertEqual(self.writes, [])

    def test_stable_release_or_missing_marker_never_overwritten(self):
        self.publish()
        for key, value in [('prerelease', False), ('body', 'foreign'), ('target_commitish', 'main'), ('assets', [{}])]:
            saved = dict(self.release); self.release[key] = value; self.writes.clear()
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, 'Colisão'): self.publish()
            self.assertEqual(self.writes, []); self.release = saved

    def test_same_sha_different_digest_is_collision(self):
        self.publish()
        with self.assertRaisesRegex(ValueError, 'digest'):
            B.publish_release(self, SHA, 'sha256:'+'c'*64, self.command, B.release_state(self, SHA, self.command))

    def test_advance_cas_updates_dedicated_beta_tag_and_notes(self):
        self.publish(); self.writes.clear(); anchor = self.ref.copy()
        new_sha = 'e'*40
        calls = []
        def run(args, input_text=None):
            calls.append(args)
            if args[:2] == ['git', 'ls-remote']: return new_sha+'\t'+B.BRANCH
            return self.command(args, input_text)
        state = B.release_state(self, new_sha, run)
        B.publish_release(self, new_sha, 'sha256:'+'c'*64, run, state)
        self.assertNotEqual(self.ref, anchor)
        self.assertEqual([w[0] for w in self.writes], ['releases/42'])
        push = [c for c in calls if c[:2] == ['git', 'push']][0]
        self.assertEqual(push[2], '--force-with-lease=refs/tags/'+B.RELEASE_TAG+':'+anchor['object']['sha'])
        self.assertIn(['git', 'merge-base', '--is-ancestor', SHA, new_sha], calls)

    def test_cas_failure_never_updates_release(self):
        self.publish(); self.writes.clear()
        new_sha = 'e'*40
        def run(args, input_text=None):
            if args[:2] == ['git', 'ls-remote']: return new_sha+'\t'+B.BRANCH
            if args[:2] == ['git', 'push']: raise B.subprocess.CalledProcessError(1, args)
            return self.command(args, input_text)
        state = B.release_state(self, new_sha, run)
        with self.assertRaises(B.subprocess.CalledProcessError):
            B.publish_release(self, new_sha, D, run, state)
        self.assertEqual(self.release['target_commitish'], SHA)
        self.assertEqual(self.writes, [])

    def test_retry_after_uncertain_cas_reconciles_tag_before_notes(self):
        self.publish(); self.writes.clear(); self.fail_after = 'git-push'
        new_sha = 'e'*40
        def run(args, input_text=None):
            if args[:2] == ['git', 'ls-remote']: return new_sha+'\t'+B.BRANCH
            return self.command(args, input_text)
        with self.assertRaises(OSError):
            B.publish_release(self, new_sha, D, run, B.release_state(self, new_sha, run))
        self.fail_after = None; self.writes.clear()
        B.publish_release(self, new_sha, D, run, B.release_state(self, new_sha, run))
        self.assertEqual([w[0] for w in self.writes], ['releases/42'])
        self.assertEqual(self.release['target_commitish'], new_sha)

    def test_foreign_annotation_and_nonancestor_preserved(self):
        self.publish(); self.writes.clear()
        self.tag['message'] = '{}'
        with self.assertRaisesRegex(ValueError, 'proveniência'): self.publish()
        self.assertEqual(self.writes, [])
        self.tag['message'] = json.dumps(B.provenance(SHA), sort_keys=True)
        def run(args, input_text=None):
            if args[:2] == ['git', 'merge-base']: raise B.subprocess.CalledProcessError(1, args)
            return self.command(args, input_text)
        with self.assertRaises(B.subprocess.CalledProcessError): B.release_state(self, SHA, run)
        self.assertEqual(self.writes, [])

    def test_race_fails_before_writes(self):
        state = B.release_state(self, SHA, self.command)
        self.ref = {'object': {'type': 'commit', 'sha': SHA}}
        with self.assertRaises(ValueError): B.publish_release(self, SHA, D, self.command, state)
        self.assertEqual(self.writes, [])

    def test_external_token_execution_blocked(self):
        for env in [{}, {'GITHUB_ACTIONS':'true', 'GH_TOKEN':'external', 'GHCR_TOKEN':'other'}]:
            with self.assertRaises(ValueError): B.require_actions_token(env)
        B.require_actions_token({'GITHUB_ACTIONS':'true', 'GH_TOKEN':'synthetic', 'GHCR_TOKEN':'synthetic'})

class RealGitCASTest(unittest.TestCase):
    def test_annotated_object_lease_rejects_competing_tag_on_same_commit(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            def git(*args, input_text=None, check=True):
                return B.subprocess.run(['git', *args], cwd=root, input=input_text,
                    text=True, stdout=B.subprocess.PIPE, stderr=B.subprocess.PIPE, check=check)
            git('init', '--bare', str(root/'remote.git'))
            git('init')
            git('config', 'user.name', 'Synthetic test')
            git('config', 'user.email', 'test@example.invalid')
            git('commit', '--allow-empty', '-m', 'synthetic')
            sha = git('rev-parse', 'HEAD').stdout.strip()
            git('remote', 'add', 'origin', str(root/'remote.git'))
            def tag(timestamp):
                data = ('object '+sha+'\ntype commit\ntag '+B.RELEASE_TAG
                        +'\ntagger Synthetic <test@example.invalid> '+str(timestamp)+' +0000\n\n'
                        +json.dumps(B.provenance(sha), sort_keys=True)+'\n')
                return git('mktag', input_text=data).stdout.strip()
            first, competing, desired = tag(1), tag(2), tag(3)
            ref = 'refs/tags/'+B.RELEASE_TAG
            git('push', '--force-with-lease='+ref+':', 'origin', first+':'+ref)
            git('push', '--force-with-lease='+ref+':'+first, 'origin', competing+':'+ref)
            failed = git('push', '--force-with-lease='+ref+':'+first, 'origin', desired+':'+ref, check=False)
            self.assertNotEqual(failed.returncode, 0)
            self.assertEqual(git('ls-remote', 'origin', ref).stdout.strip(), competing+'\t'+ref)
            git('push', '--force-with-lease='+ref+':'+competing, 'origin', desired+':'+ref)
            self.assertEqual(git('ls-remote', 'origin', ref).stdout.strip(), desired+'\t'+ref)

if __name__ == '__main__':
    unittest.main()
