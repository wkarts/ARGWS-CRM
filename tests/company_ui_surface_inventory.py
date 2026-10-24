#!/usr/bin/env python3
"""Static inventory gate only; never renders PHP, reads configuration values or calls APIs."""
import json
import re
import tempfile
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'tests/fixtures/company_ui_surfaces.json'

def discover_views(root):
    return set().union(*(set(root.glob(pattern)) for pattern in (
        'application/views/admin/companies/**/*.php',
        'application/views/admin/issuers/**/*.php',
        'application/views/admin/includes/company_*.php',
        'application/views/admin/settings/company_scoped.php',
        'application/views/admin/settings/presentation*.php',
        'application/views/admin/settings/includes/company*.php',
        'application/views/admin/emails/company_scoped.php',
        'modules/pige_bridge/views/**/*.php',
    )))

def discover_controllers(root):
    return set().union(*(set(root.glob(pattern)) for pattern in (
        'application/controllers/admin/Company_*.php',
        'application/controllers/admin/Smtp_oauth_*.php',
        'application/controllers/admin/Issuers.php',
        'application/controllers/Argws_provisioning.php',
        'modules/pige_bridge/controllers/**/*.php',
    )))

def assert_coverage(actual, recorded, label):
    assert len(recorded) == len(set(recorded)), f'{label}: duplicate entries'
    assert actual == set(recorded), f'{label}: uninventoried={sorted(actual-set(recorded))}; removed={sorted(set(recorded)-actual)}'

def controls(source):
    # Literal markup only: PHP-generated controls require renderer/browser coverage.
    literal = re.sub(r'<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>', '', source)
    result = {}
    for tag in ['table', 'button', 'input', 'select', 'textarea']:
        tags = re.findall('<'+tag+r'\b[^>]*>', literal, re.I)
        visible = [t for t in tags if not (tag == 'input' and re.search(r'type\s*=\s*[\"\'](?:hidden|checkbox|radio|file|submit|button|reset|color|range)[\"\']', t, re.I))]
        result[tag] = {'literal_tags': len(tags), 'classless_presentation_candidates': sum(not re.search(r'\bclass\s*=', t, re.I) for t in visible)}
    return result

def run():
    data = json.loads(MANIFEST.read_text())
    relative = lambda paths: {str(p.relative_to(ROOT)) for p in paths}
    assert_coverage(relative(discover_views(ROOT)), [r['path'] for r in data['views']], 'views')
    assert_coverage(relative(discover_controllers(ROOT)), [r['path'] for r in data['controllers']], 'controllers')
    for row in data['views']:
        path = ROOT / row['path']; text = path.read_text(errors='replace'); kind = row['kind']
        assert len(row['reason']) > 35, f'{path}: explain layout contract'
        # Shared shell evolves in its own fidelity tests; retain its baseline counts as audit data, not a frozen shell implementation.
        if kind != 'shell-part':
            assert controls(text) == row['literal_controls'], f'{path}: controls/table inventory changed; inspect native presentation and update evidence'
        if kind == 'company-shell':
            assert 'layout_head.php' in text and 'layout_tail.php' in text, f'{path}: missing paired company shell'
            assert 'init_head(' not in text and 'init_tail(' not in text, f'{path}: do not hydrate legacy shell in scoped page'
        elif kind == 'native-shell':
            assert 'init_head(' in text and 'init_tail(' in text, f'{path}: missing paired native shell'
        elif kind == 'modal-document':
            assert '<!doctype html>' in text.lower() and 'company-onboarding-modal-body' in text
            assert 'assets/plugins/bootstrap/css/bootstrap.min.css' in text and 'company_context_script.php' in text
            assert 'layout_head.php' not in text and 'init_head(' not in text
        elif kind == 'shell-part':
            assert path.name in ['layout_head.php', 'layout_tail.php']
        elif kind in ['partial', 'unresolved-fragment']:
            assert 'init_head(' not in text and 'layout_head.php' not in text, f'{path}: fragment changed to page; review contract'
            assert kind != 'unresolved-fragment' or row['reason'].startswith('GAP:'), f'{path}: unresolved ownership must remain explicit'
        else:
            raise AssertionError(f'{path}: unrecognized layout contract {kind}')
        if kind != 'unresolved-fragment':
            assert row['owners'], f'{path}: shared or routed owner missing'
        for owner in row['owners']:
            owner_path = ROOT / owner
            assert owner_path.is_file(), f'{path}: owner missing: {owner}'
            owner_source = owner_path.read_text(errors='replace')
            ref = row['path'].removeprefix('application/views/').removesuffix('.php')
            ref = re.sub(r'^modules/([^/]+)/views/', r'\1/', ref)  # Native HMVC view reference.
            dynamic_settings = row['path'].endswith('settings/includes/company.php') and "$group['view']" in owner_source
            assert ref in owner_source or path.name in owner_source or dynamic_settings, f'{path}: stale owner evidence {owner}'
    for row in data['controllers']:
        source = (ROOT / row['path']).read_text(errors='replace')
        refs = sorted(set(re.findall(r"load->view\(\s*['\"]([^'\"]+)", source)))
        assert refs == row['view_refs'], f"{row['path']}: view dispatch changed; review inventory (including formerly non-UI endpoints)"
        assert re.findall(r'public\s+function\s+(\w+)\s*\(', source) == row['public_methods'], f"{row['path']}: public route inventory changed"
        assert row['reason'], f"{row['path']}: missing endpoint classification"
        for ref in refs:
            view_file = ROOT / 'application/views' / (ref+'.php')
            if row['path'].startswith('modules/'):
                module = row['path'].split('/')[1]
                if ref.startswith(module+'/'):
                    view_file = ROOT / 'modules' / module / 'views' / (ref[len(module)+1:]+'.php')
            assert view_file.is_file(), f'{ref}: referenced view missing'
    # Shared presentation components must be discovered, including newly added ones.
    with tempfile.TemporaryDirectory() as directory:
        synthetic = Path(directory)
        for name in ['presentation_layout.php', 'presentation_future.php']:
            file = synthetic / 'application/views/admin/settings' / name
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('<?php // synthetic discovery fixture')
        found = {p.name for p in discover_views(synthetic)}
        assert found == {'presentation_layout.php', 'presentation_future.php'}, 'shared presentation discovery failed open'
    # Regression check: an added secret/settings view cannot evade discovery/approval.
    try:
        assert_coverage({'existing.php', 'new_secret_settings.php'}, ['existing.php'], 'synthetic-new-view')
    except AssertionError:
        pass
    else:
        raise AssertionError('new-view gate failed open')
    print(f"Static UI inventory PASS: {len(data['views'])} views, {len(data['controllers'])} controllers; kinds={dict(Counter(r['kind'] for r in data['views']))}")
    print('Not a PHP render, route authorization, asset load, browser or visual-fidelity test. Unresolved fragments remain documented gaps.')

if __name__ == '__main__':
    run()
