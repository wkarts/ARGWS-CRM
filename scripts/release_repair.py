"""Read-only provenance for rebuilding an existing release, never a new release."""
from release_policy import STABLE_TAG, commit_id, file_reader, git, is_ancestor, release_tags

# This was a repository bootstrap, not an installed CRM version. Keep the exact
# historical base; never use missing metadata as a generic validation bypass.
BOOTSTRAP_EVIDENCE = {
    'tag': 'v3.4.2',
    'commit': '47a4517978880eebb92f6c6d128b4f151b3a324b',
    'tree': '4c2ae53535072fb407b35c9d42a7f15aca689728',
    'tag_object': '7abf9e6ebf3cefb45de60012e9d5918bec8ef237',
    'base': '6a8803b6f4f18f3462f738a3ca1e78c24c12e5f8',
}


def plan_repair(root, tag):
    if not STABLE_TAG.fullmatch(tag):
        raise ValueError('Informe uma tag estável existente no formato vX.Y.Z.')
    # Verify all stable tags in main ancestry, including exact legacy evidence.
    releases = release_tags(root)
    matches = [i for i, release in enumerate(releases) if release['tag'] == tag]
    if len(matches) != 1:
        raise ValueError('A tag solicitada não é uma release verificada do histórico atual.')
    index = matches[0]
    target = releases[index]
    previous = releases[index - 1] if index else None
    source_tree = git(root, 'rev-parse', target['commit'] + '^{tree}').decode().strip()
    tag_object = git(root, 'rev-parse', 'refs/tags/' + tag).decode().strip()
    if previous:
        base_ref, base_sha, base_kind = previous['ref'], previous['commit'], 'release'
    else:
        base_ref = file_reader(root)('.github/first-release-base.txt').decode().strip()
        proven = {'tag': tag, 'commit': target['commit'], 'tree': source_tree,
                  'tag_object': tag_object, 'base': base_ref}
        if proven != BOOTSTRAP_EVIDENCE:
            raise ValueError('A base bootstrap deve ser a evidência histórica exata da primeira release.')
        base_sha = commit_id(root, base_ref)
        if base_sha != BOOTSTRAP_EVIDENCE['base'] or not is_ancestor(root, base_sha, target['commit']):
            raise ValueError('Base bootstrap sem SHA/ancestralidade comprovados.')
        base_kind = 'bootstrap'
    return {
        'tag': tag, 'version': target['version'], 'migration': target['migration'],
        'source_sha': target['commit'],
        'source_tree': source_tree, 'tag_object': tag_object,
        'base_ref': base_ref, 'base_sha': base_sha, 'base_kind': base_kind,
    }
