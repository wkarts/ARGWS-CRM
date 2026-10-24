"""Fail-closed pilot evidence: hashes only; no SQL, row values or secret-bearing paths."""
import hashlib
import json
import pathlib
import sys
import subprocess
import re

SCENARIOS = [f'tests/companies/{name}_browser.cjs' for name in (
    'project_portal_tasks', 'portal_task_write', 'project_milestones',
    'project_timesheets', 'project_gantt', 'project_contact_files')]
SOURCE_FILES = SCENARIOS + ['tests/docker_provision_smoke.sh', 'tests/issuers_standalone_http_smoke.sh', 'tests/ci/portal_chain.sh'] + sorted(str(path) for path in pathlib.Path('tests/helpers').glob('*.cjs'))
STRUCTURE_KEYS = {'tables', 'indexes', 'keys', 'checks', 'references', 'views', 'triggers'}
# Existing invoices/credits/receipts are produced by the unchanged real GUI prefix.
PROTECTED_PREFIXES = ('tblcompany_credit_', 'tblcompany_payment_', 'tblcompany_financial_', 'tblcompany_expense', 'tblcompany_invoice_', 'tblcompany_bank_receipt_')
SENTINELS = ('tblissuers_installation_identity', 'tblinvoices', 'tblcreditnotes', 'tblinvoicepaymentrecords')

def record(directory, mode, elapsed):
    assert mode in ('serial', 'portal-pilot')
    directory = pathlib.Path(directory)
    events = [json.loads(line) for line in (directory / 'scenarios.jsonl').read_text().splitlines()]
    assert [event['scenario'] for event in events] == SCENARIOS, 'missing or reordered scenario'
    assert all(event['result'] == 'PASS' and event['elapsed_seconds'] >= 0 for event in events)
    before = json.loads((directory / 'before.json').read_text())
    after = json.loads((directory / 'after.json').read_text())
    assert set(before['files']) == set(after['files']) == {'uploads', 'config'}, 'filesystem roots missing'
    assert before['files']['config'], 'empty config census'
    assert before['runtime'] == after['runtime'], 'runtime drift'
    assert before['structure'] == after['structure'], 'indexes, keys, views or triggers changed'
    protected = set(SENTINELS) | {name for name in before['tables'] if name.startswith(PROTECTED_PREFIXES)}
    for table in protected:
        assert before['tables'][table] == after['tables'][table], f'financial preservation changed: {table}'
    for table in SENTINELS:
        assert before['tables'][table]['count'] > 0, f'empty preservation sentinel: {table}'
        assert before['tables'][table] == after['tables'][table], f'preservation changed: {table}'
    for root, files in before['files'].items():
        for key, value in files.items():
            assert after['files'][root].get(key) == value, f'preexisting file changed in {root}'
    assert set(before['tables']) == set(after['tables']), 'portal block unexpectedly changed table inventory'
    for table in before['tables']:
        assert before['tables'][table]['schema'] == after['tables'][table]['schema'], f'schema changed: {table}'
    footprint = {table: {'before': before['tables'][table]['count'], 'after': value['count'],
                        'delta': value['count'] - before['tables'][table]['count'],
                        'changed': value != before['tables'][table]}
                 for table, value in after['tables'].items()}
    # Source manifest makes a successful report evidence of all six unmodified commands.
    manifest = {name: hashlib.sha256(pathlib.Path(name).read_bytes()).hexdigest() for name in SOURCE_FILES}
    sources = {name: hashlib.sha256(pathlib.Path(name).read_bytes()).hexdigest() for name in SCENARIOS}
    result = {'source_revision': subprocess.check_output(['git','rev-parse','HEAD'], text=True).strip(), 'version': 1, 'mode': mode, 'elapsed_seconds': int(elapsed), 'scenarios': sources,
              'sentinels': {table: before['tables'][table]['count'] for table in SENTINELS},
              'tables': footprint, 'events': events, 'runtime': before['runtime'], 'source_manifest': manifest,
              'structure': before['structure'], 'protected_tables': sorted(protected),
              'files': {root: {'before': len(before['files'][root]), 'after': len(files)}
                        for root, files in after['files'].items()}}
    validate_proof(result)
    (directory / 'proof.json').write_text(json.dumps(result, indent=2) + '\n')
    return result

def validate_proof(proof):
    assert re.fullmatch(r'[a-f0-9]{40}', proof['source_revision']), 'invalid source revision'
    assert set(proof['runtime']) == {'php', 'mysql'} and all(isinstance(value, str) and re.match(r'^\d+\.\d+', value) for value in proof['runtime'].values()), 'runtime missing'
    assert set(proof['source_manifest']) == set(SOURCE_FILES), 'prefix/helper manifest missing'
    assert all(re.fullmatch(r'[a-f0-9]{64}', value) for value in proof['source_manifest'].values()), 'invalid source hash'
    assert proof['scenarios'] == {name: proof['source_manifest'][name] for name in SCENARIOS}, 'scenario source evidence missing'
    assert set(proof['structure']) == STRUCTURE_KEYS, 'schema census incomplete'
    for value in proof['structure'].values():
        assert type(value['count']) is int and value['count'] >= 0 and re.fullmatch(r'[a-f0-9]{64}', value['sha256']), 'invalid structure evidence'
    assert proof['structure']['tables']['count'] > 0 and proof['structure']['indexes']['count'] > 0
    assert set(proof['sentinels']) == set(SENTINELS) and all(type(n) is int and n > 0 for n in proof['sentinels'].values()), 'populated sentinels missing'
    assert set(proof['files']) == {'uploads', 'config'}, 'filesystem roots missing'
    assert proof['files']['config']['before'] > 0
    assert all(value['after'] >= value['before'] >= 0 for value in proof['files'].values()), 'file census invalid'
    assert set(SENTINELS) <= set(proof['tables']) and set(SENTINELS) <= set(proof['protected_tables'])
    assert [event['scenario'] for event in proof['events']] == SCENARIOS and all(event['result'] == 'PASS' and event['elapsed_seconds'] >= 0 for event in proof['events']), 'completion evidence missing'
    assert type(proof['elapsed_seconds']) is int and proof['elapsed_seconds'] >= 0


def compare(serial, pilot):
    validate_proof(serial)
    validate_proof(pilot)
    assert serial['mode'] == 'serial' and pilot['mode'] == 'portal-pilot'
    assert serial['source_revision'] == pilot['source_revision'], 'source revision differs'
    assert serial['runtime'] == pilot['runtime'], 'runtime mismatch'
    assert serial['source_manifest'] == pilot['source_manifest'], 'prefix/helper source mismatch'
    assert serial['structure'] == pilot['structure'], 'structural schema mismatch'
    assert serial['protected_tables'] == pilot['protected_tables'], 'protected inventory mismatch'
    assert serial['version'] == pilot['version'] == 1
    assert list(serial['scenarios']) == list(pilot['scenarios']) == SCENARIOS
    assert serial['scenarios'] == pilot['scenarios'], 'scenario source differs'
    assert serial['sentinels'] == pilot['sentinels'], 'populated prefix sentinel counts differ'
    assert serial['files'] == pilot['files'], 'filesystem footprint differs'
    assert set(serial['tables']) == set(pilot['tables']), 'table inventory differs'
    # Exact fingerprints are checked within each run. Cross-run random tokens/timestamps differ.
    # Session/access logging counts are observational, not an equality claim.
    volatile = {'tblsessions', 'tblactivity_log', 'tbluser_auto_login'}
    for table in serial['tables']:
        if table not in volatile:
            assert serial['tables'][table] == pilot['tables'][table], f'table footprint differs: {table}'
    return {'result': 'FOOTPRINT_MATCH', 'semantic_equivalence_proven': False, 'serial_removal_authorized': False,
            'scope': 'same-prefix pilot footprint only; changed content and filesystem semantics need review',
            'serial_seconds': serial['elapsed_seconds'], 'pilot_seconds': pilot['elapsed_seconds'],
            'observational_tables': {table: {'serial': serial['tables'].get(table), 'pilot': pilot['tables'].get(table)} for table in sorted(volatile)}}

if __name__ == '__main__':
    if sys.argv[1] == 'record':
        record(*sys.argv[2:])
    elif sys.argv[1] == 'compare':
        print(json.dumps(compare(*(json.loads(pathlib.Path(p).read_text()) for p in sys.argv[2:]))))
    else:
        raise SystemExit('unknown operation')
