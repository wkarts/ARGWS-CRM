"""One native CRM release contract. Read-only; only apply-release-version writes markers.

The three-digit native migration identifier is the release order. Published aliases
are immutable evidence, never a generic SemVer downgrade exception.
"""
from __future__ import annotations
import hashlib
import json
import re
import subprocess
from pathlib import Path

POLICY_DIR = Path(__file__).resolve().parent
METADATA_PATHS = ('VERSION', 'application/config/constants.php', 'application/config/migration.php')
STABLE_TAG = re.compile(r'v[1-9][0-9]*\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\Z')
MIGRATION_PATH = re.compile(r'application/migrations/([0-9]{3})_version_\1\.php\Z')


def version_for_id(level):
    if type(level) is not int or not 101 <= level <= 999:
        raise ValueError('O executor nativo exige ID entre 101 e 999; revise o contrato antes de exceder 999.')
    return '.'.join(str(level))


def id_for_version(version):
    if not re.fullmatch(r'[1-9]\.[0-9]\.[0-9]', version):
        raise ValueError('Versão canônica do CRM deve ter um dígito por componente: X.Y.Z.')
    level = int(version.replace('.', ''))
    version_for_id(level)
    return level


def migration_path(level):
    return f'application/migrations/{level}_version_{level}.php'


def php_without_comments(source):
    # Keep quoted strings intact: comment-looking text inside a string is data.
    return re.sub(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"|//[^\n]*|\#[^\n]*|/\*[\s\S]*?\*/",
                  lambda m: m[0] if m[0].startswith(('\"', "'")) else ' ', source)


def validate_marker(data, level):
    source = php_without_comments(data.decode('utf-8'))
    source = re.sub(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"", ' ', source)
    if not re.search(r'\bclass\s+Migration_Version_' + str(level) + r'\s+extends\s+CI_Migration\b', source):
        raise ValueError(f'Classe nativa inválida na migration {level}.')
    if not re.search(r'\bpublic\s+function\s+up\s*\(\s*\)', source):
        raise ValueError(f'Migration {level} não possui public up().')


def aliases():
    return json.loads((POLICY_DIR / 'legacy-release-aliases.json').read_text())['aliases']


def metadata(read, allow_legacy=False):
    blobs = {path: read(path) for path in METADATA_PATHS}
    version = blobs['VERSION'].decode().strip()
    constants = php_without_comments(blobs[METADATA_PATHS[1]].decode())
    configured = re.findall(r"\bdefine\s*\(\s*['\"]ARGWS_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", constants)
    levels = re.findall(r"\$config\s*\[\s*['\"]migration_version['\"]\s*\]\s*=\s*([0-9]+)\s*;", php_without_comments(blobs[METADATA_PATHS[2]].decode()))
    if configured != [version] or len(levels) != 1:
        raise ValueError('VERSION, ARGWS_VERSION e migration_version devem ser únicos e coerentes.')
    level = int(levels[0])
    expected = version_for_id(level)
    marker = read(migration_path(level))
    validate_marker(marker, level)
    result = {'version': version, 'migration': level, 'alias': None}
    if version == expected:
        return result
    if allow_legacy:
        blobs[migration_path(level)] = marker
        for entry in aliases():
            if entry['version'] == version and entry['migration'] == level and all(
                hashlib.sha256(blobs[path]).hexdigest() == digest for path, digest in entry['sha256'].items()
            ):
                result['alias'] = entry
                return result
    raise ValueError(f'Versão {version} diverge da migration {level}: esperado {expected}. Alias não comprovado.')


def file_reader(root):
    def read(path):
        file = root / path
        if file.is_symlink() or not file.is_file():
            raise ValueError(f'Arquivo regular ausente: {path}.')
        return file.read_bytes()
    return read


def validate_tree(read, allow_legacy=False, history=True):
    info = metadata(read, allow_legacy)
    if history:
        for level in range(101, info['migration'] + 1):
            try:
                # Historical migrations may predate the public-up convention.
                source = php_without_comments(read(migration_path(level)).decode())
            except (OSError, KeyError, ValueError) as exc:
                raise ValueError(f'Lacuna na sequência 101–{info["migration"]}: migration {level}.') from exc
            if not re.search(r'\bclass\s+Migration_Version_' + str(level) + r'\s+extends\s+CI_Migration\b', source):
                raise ValueError(f'Classe inválida no histórico da migration {level}.')
    return info


def git(root, *args):
    process = subprocess.run(['git', '-C', str(root), *args], capture_output=True)
    if process.returncode:
        raise ValueError(f'Histórico Git indisponível para {args[0]}; busque os commits e tags completos.')
    return process.stdout


def commit_id(root, ref):
    if not re.fullmatch(r'[a-zA-Z0-9_./-]+', ref) or ref.startswith('-'):
        raise ValueError('Referência Git inválida.')
    return git(root, 'rev-parse', '--verify', ref + '^{commit}').decode().strip()


def is_ancestor(root, older, newer):
    result = subprocess.run(['git', '-C', str(root), 'merge-base', '--is-ancestor', older, newer], capture_output=True)
    if result.returncode not in (0, 1):
        raise ValueError('Histórico Git incompleto para verificar ancestralidade.')
    return result.returncode == 0


def git_files(root, commit, directory='application/migrations'):
    records = git(root, 'ls-tree', '-r', '-z', commit, '--', directory).split(b'\0')
    files = {}
    for record in filter(None, records):
        meta, path = record.split(b'\t', 1)
        mode, kind, oid = meta.decode().split()
        files[path.decode()] = (mode, kind, oid)
    return files


def git_reader(root, commit):
    def read(path):
        entries = git_files(root, commit, path)
        if path not in entries or entries[path][0] not in ('100644', '100755') or entries[path][1] != 'blob':
            raise ValueError(f'Arquivo Git regular ausente: {path}.')
        return git(root, 'show', commit + ':' + path)
    return read


def git_metadata(root, ref, allow_legacy=True):
    commit = commit_id(root, ref)
    info = metadata(git_reader(root, commit), allow_legacy)
    if info['alias']:
        alias = info['alias']
        if commit != alias['commit'] or git(root, 'rev-parse', commit + '^{tree}').decode().strip() != alias['tree']:
            raise ValueError(f'Alias {info["version"]} não corresponde ao commit/árvore publicados.')
        if ref.startswith('refs/tags/') and git(root, 'rev-parse', ref).decode().strip() != alias['tag_object']:
            raise ValueError(f'O objeto da tag histórica {ref} foi substituído.')
    return dict(info, commit=commit, ref=ref)


def release_tags(root, head='HEAD'):
    head = commit_id(root, head)
    releases = []
    for tag in git(root, 'tag', '--list', 'v*').decode().splitlines():
        if not STABLE_TAG.fullmatch(tag):
            continue
        info = git_metadata(root, 'refs/tags/' + tag)
        if tag != 'v' + info['version']:
            raise ValueError(f'Tag {tag} não corresponde ao conteúdo publicado.')
        # A stable release outside this ancestry must never become an overlay base.
        if not is_ancestor(root, info['commit'], head):
            raise ValueError(f'Release {tag} fora da ancestralidade do commit promovido.')
        releases.append(dict(info, tag=tag))
    releases.sort(key=lambda item: item['migration'])
    for older, newer in zip(releases, releases[1:]):
        if older['migration'] == newer['migration'] or not is_ancestor(root, older['commit'], newer['commit']):
            raise ValueError('Tags estáveis possuem ID duplicado ou histórico incompatível.')
    return releases


def validate_distribution(read, version):
    expected = {
        'compose.yaml': '${ARGWS_VERSION:-' + version + '}',
        'deploy/production/compose.yaml': 'ghcr.io/wkarts/argws-crm:' + version + '}',
        'deploy/production/.env.example': 'ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:' + version,
        'container.env.example': 'ARGWS_VERSION=' + version,
    }
    for path, marker in expected.items():
        source = read(path).decode()
        if marker not in source:
            raise ValueError(f'Metadados de distribuição incoerentes: {path}.')
        if path.endswith('compose.yaml'):
            values = re.findall(r'ghcr\.io/wkarts/argws-crm:(?:\$\{ARGWS_VERSION:-)?([0-9.]+)', source)
            if not values or any(value != version for value in values):
                raise ValueError(f'Tags de distribuição divergentes: {path}.')
        else:
            variable = 'ARGWS_VERSION' if path == 'container.env.example' else 'ARGWS_CRM_IMAGE'
            values = re.findall(r'(?m)^' + variable + r'=(.*)$', source)
            if len(values) != 1 or values[0] != marker.split('=', 1)[1]:
                raise ValueError(f'Configuração duplicada ou divergente: {path}.')


def target_levels(source, target):
    """Only the reviewed 394→401 transition may skip a native release number."""
    if source.get('alias'):
        if (source['version'], source['migration'], target) != ('3.11.1', 394, 401):
            raise ValueError('A única transição de alias permitida é 3.11.1/394 → 4.0.1/401, com 395–401 completas.')
        return range(395, 402)
    if target != source['migration'] + 1:
        raise ValueError('Toda PR de release, inclusive docs/CI/módulos, exige exatamente o próximo ID nativo.')
    return range(target, target + 1)


def check_delta(root, base, head):
    if not all(re.fullmatch(r'[0-9a-f]{40}', ref) for ref in (base, head)):
        raise ValueError('Informe os SHAs completos de base/head do evento da PR.')
    base, head = commit_id(root, base), commit_id(root, head)
    ancestor = git(root, 'merge-base', base, head).decode().strip()
    if not ancestor:
        raise ValueError('Merge-base ausente.')
    before = metadata(git_reader(root, ancestor), allow_legacy=True)
    current_base = metadata(git_reader(root, base), allow_legacy=True)
    if (current_base['migration'], current_base['version']) != (before['migration'], before['version']):
        raise ValueError('A base atual avançou o núcleo após o merge-base; sincronize a PR e gere o próximo ID disponível.')
    if before['alias']:
        # A PR baseline may be a later source commit with unchanged alias metadata.
        alias = before['alias']
        verified = git_metadata(root, alias['commit'])
        if not is_ancestor(root, verified['commit'], ancestor):
            raise ValueError('Alias da base sem ancestralidade comprovada.')
    after = validate_tree(git_reader(root, head))
    validate_distribution(git_reader(root, head), after['version'])
    expected_new = {migration_path(level) for level in target_levels(before, after['migration'])}
    previous_files = git_files(root, ancestor)
    base_files = git_files(root, base)
    current_files = git_files(root, head)
    for path, identity in list(previous_files.items()) + list(base_files.items()):
        if path.endswith('.php') and current_files.get(path) != identity:
            raise ValueError(f'Migration histórica alterada, removida ou renomeada: {path}.')
    for new_path in expected_new:
        if new_path in previous_files or new_path in base_files or new_path not in current_files:
            raise ValueError('A release precisa de novos arquivos de migration, não arquivos futuros preexistentes.')
        validate_marker(git_reader(root, head)(new_path), int(MIGRATION_PATH.fullmatch(new_path)[1]))
    for path in current_files.keys() - previous_files.keys():
        if path.endswith('.php') and path not in expected_new:
            raise ValueError(f'Migration adicional inesperada: {path}.')
    return after


def validate_archive(path, base_read=None, expected_version=None):
    import zipfile
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)) or archive.testzip() is not None:
            raise ValueError('ZIP inválido ou com caminhos duplicados.')
        def read(name):
            if name in names:
                item = archive.getinfo(name)
                if (item.external_attr >> 16) & 0o170000 == 0o120000:
                    raise ValueError('Metadados do pacote não podem ser links simbólicos.')
                return archive.read(name)
            if base_read:
                return base_read(name)
            raise ValueError(f'Arquivo ausente no pacote: {name}.')
        info = validate_tree(read)
        if expected_version is not None and info['version'] != expected_version:
            raise ValueError('Versão do ZIP diverge do nome/contrato do pacote.')
        return info
