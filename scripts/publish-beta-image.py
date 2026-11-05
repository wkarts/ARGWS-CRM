#!/usr/bin/env python3
"""Continuous develop-beta publisher. Registry/API reads fail closed.

Only the develop-beta image and the dedicated beta Git tag/release are writable.
"""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

from release_policy import file_reader, validate_tree, validate_distribution

REPO = 'wkarts/ARGWS-CRM'
IMAGE = 'ghcr.io/wkarts/argws-crm'
BRANCH = 'refs/heads/develop-beta'
BETA_VERSION = '4.0.3'
CHANNEL = 'develop-beta'
RELEASE_TAG = 'v4.0.3-beta'
MARKER = 'argws-beta-publication-v1'
DIGEST = re.compile(r'sha256:[0-9a-f]{64}\Z')
ACCEPT = ', '.join(['application/vnd.oci.image.index.v1+json',
    'application/vnd.docker.distribution.manifest.list.v2+json',
    'application/vnd.oci.image.manifest.v1+json',
    'application/vnd.docker.distribution.manifest.v2+json'])


class Registry:
    def __init__(self):
        query = urllib.parse.urlencode({'service': 'ghcr.io', 'scope': 'repository:wkarts/argws-crm:pull'})
        headers = {}
        token = os.environ.get('GHCR_TOKEN')
        if token:
            credentials = os.environ.get('GITHUB_ACTOR', '') + ':' + token
            headers['Authorization'] = 'Basic ' + base64.b64encode(credentials.encode()).decode()
        request = urllib.request.Request('https://ghcr.io/token?' + query, headers=headers)
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                self.token = json.load(response)['token']
        except (OSError, ValueError, KeyError) as error:
            raise ValueError('Não foi possível autenticar leitura GHCR; publicação bloqueada.') from error

    def manifest(self, reference):
        if not re.fullmatch(r'(?:develop-beta|4\.0\.3|sha256:[0-9a-f]{64})', reference):
            raise ValueError('Referência de imagem fora da allowlist.')
        request = urllib.request.Request('https://ghcr.io/v2/wkarts/argws-crm/manifests/' + reference,
            headers={'Authorization': 'Bearer ' + self.token, 'Accept': ACCEPT})
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                content = response.read()
                digest = 'sha256:' + hashlib.sha256(content).hexdigest()
                if response.headers.get('Docker-Content-Digest') != digest:
                    raise ValueError('Digest GHCR ausente ou divergente.')
                return digest, json.loads(content)
        except urllib.error.HTTPError as error:
            # Authentication, rate limiting, proxy/network errors are NOT absence.
            try:
                codes = [item.get('code') for item in json.loads(error.read()).get('errors', [])]
            except (ValueError, AttributeError, TypeError):
                codes = []
            if error.code == 404 and codes == ['MANIFEST_UNKNOWN']:
                return None
            raise ValueError('Falha ao consultar GHCR; publicação bloqueada (HTTP %s).' % error.code) from error
        except (OSError, ValueError) as error:
            raise ValueError('Falha ao verificar manifesto GHCR; publicação bloqueada.') from error


def context(env):
    sha = env.get('GITHUB_SHA', '')
    if (env.get('GITHUB_EVENT_NAME') != 'push' or env.get('GITHUB_REF') != BRANCH
            or env.get('GITHUB_REPOSITORY') != REPO or not re.fullmatch('[0-9a-f]{40}', sha)):
        raise ValueError('Publicação somente por push em wkarts/ARGWS-CRM:develop-beta.')
    return sha


def require_current(sha, run):
    output = run(['git', 'ls-remote', 'origin', BRANCH]).strip()
    if output != sha + '\t' + BRANCH:
        raise ValueError('Build obsoleto: develop-beta avançou; tags preservadas.')


def existing_beta(manifest, version):
    if manifest is None:
        return None
    digest, doc = manifest
    annotations = doc.get('annotations', {})
    sha = annotations.get('org.opencontainers.image.revision', '')
    platforms = {(m.get('platform', {}).get('os'), m.get('platform', {}).get('architecture'))
                 for m in doc.get('manifests', [])}
    if (not DIGEST.fullmatch(digest) or not re.fullmatch('[0-9a-f]{40}', sha)
            or annotations.get('org.opencontainers.image.version') != version
            or annotations.get('io.argws.channel') != 'develop-beta'
            or len(doc.get('manifests', [])) != 2
            or any(not DIGEST.fullmatch(m.get('digest', '')) for m in doc.get('manifests', []))
            or platforms != {('linux', 'amd64'), ('linux', 'arm64')}):
        raise ValueError('Colisão com imagem sem proveniência beta válida; preservada.')
    return digest, sha


def sources(directory, version, sha):
    result = []
    for arch in ['amd64', 'arm64']:
        doc = json.loads((directory / (arch + '.json')).read_text())
        digest = doc.get('digest', '')
        if (not isinstance(digest, str) or not DIGEST.fullmatch(digest)
                or doc.get('sha') != sha or doc.get('version') != version or doc.get('arch') != arch):
            raise ValueError('Artefato de build divergente do SHA/versão/arquitetura.')
        result.append(IMAGE + '@' + digest)
    if len(set(result)) != 2:
        raise ValueError('Esperados dois digests de arquiteturas distintas.')
    return result


def publish(version, sha, registry, run, directory):
    if version != BETA_VERSION:
        raise ValueError('Ciclo beta limitado à versão 4.0.3; revisar política antes de mudar.')
    refs = sources(directory, version, sha)
    require_current(sha, run)
    previous = existing_beta(registry.manifest(CHANNEL), version)
    if previous and previous[1] != sha:
        # A later/foreign/non-ancestor image must never be replaced by an old run.
        run(['git', 'merge-base', '--is-ancestor', previous[1], sha])
    if existing_beta(registry.manifest(CHANNEL), version) != previous:
        raise ValueError('Tag mudou durante a publicação; escrita interrompida.')
    require_current(sha, run)
    if previous is None or previous[1] != sha:
        run(['docker', 'buildx', 'imagetools', 'create',
             '--annotation', 'index:org.opencontainers.image.revision=' + sha,
             '--annotation', 'index:org.opencontainers.image.version=' + version,
             '--annotation', 'index:io.argws.channel=develop-beta',
             '-t', IMAGE + ':' + CHANNEL, *refs])
    confirmed_manifest = registry.manifest(CHANNEL)
    confirmed = existing_beta(confirmed_manifest, version)
    if confirmed is None or confirmed[1] != sha:
        raise ValueError('Digest/SHA publicado não confirmado; consultar estado antes de repetir.')
    if previous is None or previous[1] != sha:
        published_refs = {IMAGE + '@' + m['digest'] for m in confirmed_manifest[1]['manifests']}
        if published_refs != set(refs):
            raise ValueError('Índice publicado diverge dos digests aprovados; consultar estado.')
    # No alias update, rollback, deletion or write to any other tag.
    result = {'image': IMAGE + ':' + CHANNEL, 'digest': confirmed[0], 'sha': sha,
              'previous_digest': previous[0] if previous else None,
              'previous_sha': previous[1] if previous else None}
    print(json.dumps(result, sort_keys=True))
    return result


class GitHub:
    """No automatic write retries: ambiguous outcomes must be reconciled by GET."""
    def call(self, path, payload=None, method=None):
        token = os.environ.get('GH_TOKEN', '')
        request = urllib.request.Request('https://api.github.com/repos/' + REPO + ('/' + path if path else ''),
            data=json.dumps(payload).encode() if payload is not None else None,
            method=method or ('POST' if payload is not None else 'GET'),
            headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json',
                     'Content-Type': 'application/json', 'X-GitHub-Api-Version': '2022-11-28'})
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                return json.load(response)
        except urllib.error.HTTPError as error:
            if error.code == 404 and request.method == 'GET':
                # Repository access is checked separately before interpreting absence.
                return None
            raise ValueError('GitHub HTTP %s; consultar estado antes de repetir.' % error.code) from error


def require_actions_token(env):
    if env.get('GITHUB_ACTIONS') != 'true' or not env.get('GH_TOKEN') or env.get('GH_TOKEN') != env.get('GHCR_TOKEN'):
        raise ValueError('Release exige o mesmo github.token efêmero de Actions; token externo não suportado.')


def provenance(sha, digest=None):
    value = {'publisher': MARKER, 'repository': REPO, 'channel': CHANNEL,
             'version': BETA_VERSION, 'sha': sha}
    if digest is not None:
        value['digest'] = digest
    return value


def release_state(api, sha, run):
    repo = api.call('')
    if not repo or repo.get('full_name') != REPO:
        raise ValueError('Acesso ao repositório não confirmado.')
    ref = api.call('git/ref/tags/' + RELEASE_TAG)
    release = api.call('releases/tags/' + RELEASE_TAG)
    anchor = None
    if ref:
        obj = ref.get('object', {})
        if obj.get('type') != 'tag':
            raise ValueError('Colisão: tag beta não é anotada; preservada.')
        tag = api.call('git/tags/' + obj['sha'])
        anchor = (tag or {}).get('object', {}).get('sha', '')
        if (not re.fullmatch('[0-9a-f]{40}', anchor) or tag.get('tag') != RELEASE_TAG
                or tag.get('object', {}).get('type') != 'commit'
                or tag.get('message', '').strip() != json.dumps(provenance(anchor), sort_keys=True)):
            raise ValueError('Colisão: proveniência da tag beta inválida; preservada.')
        run(['git', 'merge-base', '--is-ancestor', anchor, sha])
        if run(['git', 'show', anchor + ':VERSION']).strip() != BETA_VERSION:
            raise ValueError('Versão da âncora beta divergente.')
    if release:
        if (not anchor or release.get('prerelease') is not True or release.get('draft') is not False
                or release.get('tag_name') != RELEASE_TAG
                or release.get('name') != 'ARGWS CRM 4.0.3 beta' or release.get('assets')):
            raise ValueError('Colisão: release não pertence ao canal beta; preservada.')
        try:
            body = release['body']
            metadata = json.loads(body.split('<!-- ' + MARKER + ' ', 1)[1].split(' -->', 1)[0])
            previous_sha, digest = metadata['sha'], metadata['digest']
            if (metadata != provenance(previous_sha, digest) or not re.fullmatch('[0-9a-f]{40}', previous_sha)
                    or not DIGEST.fullmatch(digest) or release.get('target_commitish') != previous_sha):
                raise ValueError('metadata')
        except (KeyError, IndexError, TypeError, ValueError) as error:
            raise ValueError('Colisão: notas sem proveniência beta válida.') from error
        run(['git', 'merge-base', '--is-ancestor', previous_sha, anchor])
        run(['git', 'merge-base', '--is-ancestor', previous_sha, sha])
    return {'ref': ref, 'release': release, 'anchor': anchor}


def publish_release(api, sha, digest, run, expected):
    if not DIGEST.fullmatch(digest):
        raise ValueError('Digest multiarch inválido.')
    require_current(sha, run)
    if release_state(api, sha, run) != expected:
        raise ValueError('Estado GitHub mudou; repetir preflight antes de escrever.')
    old = expected['release']
    if old:
        metadata = json.loads(old['body'].split('<!-- ' + MARKER + ' ', 1)[1].split(' -->', 1)[0])
        if metadata['sha'] == sha and metadata['digest'] != digest:
            raise ValueError('Mesmo SHA com digest divergente; preservar release e investigar.')
    anchor = expected['anchor']
    if anchor != sha:
        tag = api.call('git/tags', {'tag': RELEASE_TAG, 'message': json.dumps(provenance(sha), sort_keys=True),
                                  'object': sha, 'type': 'commit'})
        if anchor is None:
            # Atomic create-only: collision never overwrites a foreign reference.
            api.call('git/refs', {'ref': 'refs/tags/' + RELEASE_TAG, 'sha': tag['sha']})
        else:
            # CAS against the annotated tag OBJECT, not merely its peeled commit.
            # Fetch only the newly created object; never replace a local stable tag.
            run(['git', 'fetch', '--no-tags', 'origin', tag['sha']])
            require_current(sha, run)
            run(['git', 'push', '--force-with-lease=refs/tags/' + RELEASE_TAG + ':'
                 + expected['ref']['object']['sha'], 'origin', tag['sha'] + ':refs/tags/' + RELEASE_TAG])
        observed = release_state(api, sha, run)
        if observed['anchor'] != sha or observed['release'] != expected['release']:
            raise ValueError('Tag/release mudou durante CAS; consultar estado antes de repetir.')
    require_current(sha, run)
    body = ('Canal contínuo develop-beta. Versão da aplicação: 4.0.3.\n\n'
            'Imagem: `' + IMAGE + ':' + CHANNEL + '`\n\n'
            'SHA atual: `' + sha + '`\n\nDigest multiarch: `' + digest + '`\n\n'
            'A tag Git beta acompanha este SHA; use o SHA/digest para referências fixas. '
            'Código atual: https://github.com/' + REPO + '/tree/' + sha + '\n\n'
            '<!-- ' + MARKER + ' ' + json.dumps(provenance(sha, digest), sort_keys=True) + ' -->')
    payload = {'tag_name': RELEASE_TAG, 'target_commitish': sha, 'name': 'ARGWS CRM 4.0.3 beta',
               'body': body, 'draft': False, 'prerelease': True, 'make_latest': 'false'}
    old = expected['release']
    if old and old.get('body') == body:
        return old['html_url']
    path = 'releases/' + str(old['id']) if old else 'releases'
    api.call(path, payload, method='PATCH' if old else 'POST')
    confirmed = release_state(api, sha, run)['release']
    if not confirmed or confirmed.get('body') != body:
        raise ValueError('Release não confirmada; consultar estado antes de repetir.')
    return confirmed['html_url']


def command(args):
    result = subprocess.run(args, check=True, text=True, stdout=subprocess.PIPE)
    return result.stdout


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--publish', action='store_true', help='Sem flag, somente preflight de leitura.')
    parser.add_argument('--digests', type=Path, default=Path('/tmp/beta-digests'))
    args = parser.parse_args()
    try:
        sha = context(os.environ)
        if command(['git', 'rev-parse', 'HEAD']).strip() != sha:
            raise ValueError('Checkout não corresponde ao SHA do evento.')
        info = validate_tree(file_reader(Path.cwd()))
        validate_distribution(file_reader(Path.cwd()), info['version'])
        registry = Registry()
        require_current(sha, command)
        if info['version'] != BETA_VERSION:
            raise ValueError('Versão fora do ciclo beta autorizado.')
        existing_beta(registry.manifest(CHANNEL), info['version'])
        github = GitHub()
        state = release_state(github, sha, command)
        if args.publish:
            require_actions_token(os.environ)
            result = publish(info['version'], sha, registry, command, args.digests)
            Path('beta-publication.json').write_text(json.dumps(result, indent=2) + '\n')
            result['release_url'] = publish_release(github, sha, result['digest'], command, state)
            Path('beta-publication.json').write_text(json.dumps(result, indent=2) + '\n')
        else:
            print('Preflight beta aprovado; nenhuma escrita GHCR/GitHub.')
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        raise SystemExit(str(error)) from error


if __name__ == '__main__':
    main()
