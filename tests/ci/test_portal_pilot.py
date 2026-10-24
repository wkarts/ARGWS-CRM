import copy
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('portal', ROOT/'tests/ci/portal_evidence.py')
portal = importlib.util.module_from_spec(spec)
spec.loader.exec_module(portal)

class PortalPilot(unittest.TestCase):
    def structure(self):
        return {key:{'count':1,'sha256':'a'*64} for key in portal.STRUCTURE_KEYS}
    def proof(self, mode='serial'):
        return {'source_revision':'a'*40, 'version':1,'mode':mode,'elapsed_seconds':20,
                'runtime':{'php':'8.1','mysql':'8.0'},
                'source_manifest':{name:'a'*64 for name in portal.SOURCE_FILES},
                'structure':self.structure(),'protected_tables':list(portal.SENTINELS),
                'events':[{'scenario':name,'result':'PASS','elapsed_seconds':1} for name in portal.SCENARIOS],
                'scenarios':{name:'a'*64 for name in portal.SCENARIOS},
                'sentinels':{name:2 for name in portal.SENTINELS},
                'files':{'uploads':{'before':1,'after':2},'config':{'before':1,'after':1}},
                'tables':{name:{'before':2,'after':2,'delta':0,'changed':False} for name in portal.SENTINELS} |
                         {'tblcompany_projects':{'before':2,'after':14,'delta':12,'changed':True}}}
    def test_comparator_rejects_omissions_state_and_coverage_drift(self):
        serial=self.proof(); pilot=self.proof('portal-pilot')
        self.assertEqual(portal.compare(serial,pilot)['result'],'FOOTPRINT_MATCH')
        for field in ('scenarios','sentinels','tables','files','structure','source_manifest','runtime','events'):
            broken=copy.deepcopy(pilot);broken[field]={}
            with self.subTest(field=field),self.assertRaises(AssertionError):portal.compare(serial,broken)
        broken=copy.deepcopy(pilot);broken['tables']['tblcompany_projects']['delta']=11
        with self.assertRaises(AssertionError):portal.compare(serial,broken)
    def test_record_requires_populated_preserved_state_and_all_six_completions(self):
        state={'runtime':{'php':'8.1','mysql':'8.0'},'structure':self.structure(),'tables':{name:{'count':2,'sha256':'old','schema':'same'} for name in portal.SENTINELS},
               'files':{'config':{'cfg':{'bytes':2,'sha256':'same'}},'uploads':{'opaque':{'bytes':2,'sha256':'same'}}}}
        with tempfile.TemporaryDirectory() as tmp:
            path=Path(tmp)
            for filename in ('before','after'):(path/(filename+'.json')).write_text(json.dumps(state))
            events=[{'scenario':name,'elapsed_seconds':1,'result':'PASS'} for name in portal.SCENARIOS]
            (path/'scenarios.jsonl').write_text('\n'.join(map(json.dumps,events)))
            self.assertEqual(portal.record(path,'serial',6)['elapsed_seconds'],6)
            for change in ('empty','mutated','file','omitted'):
                bad=copy.deepcopy(state); current=events
                if change=='empty':bad['tables'][portal.SENTINELS[0]]['count']=0
                if change=='mutated':bad['tables'][portal.SENTINELS[0]]['sha256']='new'
                if change=='file':bad['files']['uploads']={}
                if change=='omitted':current=events[:-1]
                (path/'after.json').write_text(json.dumps(bad))
                (path/'scenarios.jsonl').write_text('\n'.join(map(json.dumps,current)))
                with self.subTest(change=change),self.assertRaises(AssertionError):portal.record(path,'serial',6)
    def test_matching_counts_explicitly_do_not_prove_semantic_equivalence(self):
        result=portal.compare(self.proof(),self.proof('portal-pilot'))
        self.assertFalse(result['semantic_equivalence_proven'])
        self.assertFalse(result['serial_removal_authorized'])
        # Content is not compared cross-run: equal footprint cannot authorize a cut.
        self.assertIn('changed content',result['scope'])
    def test_wrong_changed_content_and_new_files_cannot_be_promoted_to_equivalence(self):
        state={'runtime':{'php':'8.1','mysql':'8.0'},'structure':self.structure(),
               'tables':{name:{'count':2,'sha256':'old','schema':'same'} for name in portal.SENTINELS},
               'files':{'uploads':{},'config':{'cfg':{'bytes':2,'sha256':'same'}}}}
        state['tables']['tblcompany_tasks']={'count':2,'sha256':'original-owner-amount','schema':'same'}
        proofs=[]
        for mode in ('serial','portal-pilot'):
            with tempfile.TemporaryDirectory() as tmp:
                path=Path(tmp);after=copy.deepcopy(state)
                after['tables']['tblcompany_tasks']['sha256']='different-owner-amount-'+mode
                after['files']['uploads']['new-file']={'bytes':3,'sha256':'different-bytes-'+mode}
                (path/'before.json').write_text(json.dumps(state));(path/'after.json').write_text(json.dumps(after))
                (path/'scenarios.jsonl').write_text('\n'.join(json.dumps({'scenario':name,'elapsed_seconds':1,'result':'PASS'}) for name in portal.SCENARIOS))
                proofs.append(portal.record(path,mode,6))
        result=portal.compare(*proofs)
        self.assertEqual(result['result'],'FOOTPRINT_MATCH')
        self.assertFalse(result['semantic_equivalence_proven'])
        self.assertFalse(result['serial_removal_authorized'])
    def test_existing_required_check_aggregates_parallel_jobs_fail_closed(self):
        workflow=(ROOT/'.github/workflows/deployment-integrity.yml').read_text()
        self.assertIn('  serial:\n',workflow)
        self.assertIn('  validate:\n    needs: [serial, portal-pilot]\n    if: always()',workflow)
        self.assertIn('test "$SERIAL_RESULT" = success && test "$PILOT_RESULT" = success',workflow)
        pilot=workflow.split('  portal-pilot:',1)[1].split('  validate:',1)[0]
        self.assertNotIn('needs:',pilot)
        self.assertIn('ISSUER_SMOKE_MODE: portal-pilot',pilot)
        self.assertIn('portal_evidence.py compare',workflow)
        self.assertNotIn('continue-on-error',workflow)
    def test_same_chain_in_original_position_and_default_remains_full_serial(self):
        smoke=(ROOT/'tests/issuers_standalone_http_smoke.sh').read_text()
        chain=(ROOT/'tests/ci/portal_chain.sh').read_text()
        commands=[line.split()[1] for line in chain.splitlines() if line.startswith('node tests/')]
        self.assertEqual(commands,portal.SCENARIOS)
        self.assertEqual(smoke.count('source tests/ci/portal_chain.sh'),1)
        expanded=smoke.replace('source tests/ci/portal_chain.sh',chain)
        self.assertEqual(sum(line.startswith('node ') for line in expanded.splitlines()),100)
        self.assertIn('${ISSUER_SMOKE_MODE:-serial}',smoke)
        self.assertLess(smoke.index('node tests/companies/contract_discussions_browser.cjs'),smoke.index('source tests/ci/portal_chain.sh'))
        self.assertLess(smoke.index('source tests/ci/portal_chain.sh'),smoke.index('node tests/companies/task_billing_http_browser.cjs'))
        self.assertIn('node tests/companies/hr_tokens_browser.cjs',smoke)
        self.assertEqual(chain.count('portal_done tests/'),6)
        self.assertLess(chain.index('portal_snapshot before'),chain.index('node tests/'))
        self.assertGreater(chain.index('portal_snapshot after'),chain.rindex('portal_done tests/'))

if __name__=='__main__':unittest.main()
