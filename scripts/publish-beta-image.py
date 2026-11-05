#!/usr/bin/env python3
"""Future develop-beta publisher. Registry reads fail closed; writes use Buildx.

Only the version tag and develop-beta can be written. No Git tags/releases.
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
        if not re.fullmatch(r'(?:[0-9]\.[0-9]\.[0-9]|develop-beta|sha256:[0-9a-f]{64})', reference):
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


def approved(manifest, version, sha):
    if manifest is None:
        return None
    digest, doc = manifest
    annotations = doc.get('annotations', {})
    expected = {'org.opencontainers.image.revision': sha,
                'org.opencontainers.image.version': version,
                'io.argws.channel': 'develop-beta'}
    platforms = {(m.get('platform', {}).get('os'), m.get('platform', {}).get('architecture'))
                 for m in doc.get('manifests', [])}
    if (not DIGEST.fullmatch(digest) or any(annotations.get(k) != v for k, v in expected.items())
            or platforms != {('linux', 'amd64'), ('linux', 'arm64')}):
        raise ValueError('Colisão: tag versionada existente não pertence ao mesmo build beta aprovado; preservada.')
    return digest


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
    if not re.fullmatch(r'[0-9]\.[0-9]\.[0-9]', version):
        raise ValueError('Versão nativa inválida.')
    refs = sources(directory, version, sha)
    require_current(sha, run)
    existing = registry.manifest(version)
    digest = approved(existing, version, sha)
    if digest is None:
        # Recheck immediately before creation; never overwrite an existing version.
        existing = registry.manifest(version)
        digest = approved(existing, version, sha)
        if digest is None:
            require_current(sha, run)
            run(['docker', 'buildx', 'imagetools', 'create',
                 '--annotation', 'index:org.opencontainers.image.revision=' + sha,
                 '--annotation', 'index:org.opencontainers.image.version=' + version,
                 '--annotation', 'index:io.argws.channel=develop-beta',
                 '-t', IMAGE + ':' + version, *refs])
            digest = approved(registry.manifest(version), version, sha)
            if digest is None:
                raise ValueError('Tag versionada não confirmada; canal não atualizado.')
    require_current(sha, run)
    if approved(registry.manifest(version), version, sha) != digest:
        raise ValueError('Tag versionada mudou durante publicação; canal preservado.')
    # A single index source is copied byte-for-byte by Buildx.
    run(['docker', 'buildx', 'imagetools', 'create', '-t', IMAGE + ':develop-beta', IMAGE + '@' + digest])
    channel = registry.manifest('develop-beta')
    if channel is None or channel[0] != digest:
        raise ValueError('Canal beta não aponta para o digest versionado esperado.')
    print('GHCR beta verificado: ' + version + ' e develop-beta = ' + digest)


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
        approved(registry.manifest(info['version']), info['version'], sha)
        if args.publish:
            publish(info['version'], sha, registry, command, args.digests)
        else:
            print('Preflight beta aprovado; nenhuma escrita GHCR.')
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        raise SystemExit(str(error)) from error


if __name__ == '__main__':
    main()
