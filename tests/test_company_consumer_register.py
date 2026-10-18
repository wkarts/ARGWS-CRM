import contextlib
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SCRIPT=Path(__file__).resolve().parents[1]/'scripts/register-company-consumers.py'
spec=importlib.util.spec_from_file_location('consumer_register',SCRIPT)
register=importlib.util.module_from_spec(spec);spec.loader.exec_module(register)

class ConsumerRegisterTest(unittest.TestCase):
    def test_function_candidates_ignore_inline_comment_prose(self):
        source="// function if(get_option('x'))\n/* function prose(get_option('x')) */\npublic function read_it() { get_option('x'); }\n"
        self.assertEqual([m.group(1) for m in register.FUNCTION.finditer(source)], ['read_it'])

    def test_finite_register_stale_evidence_and_dependency_exclusion(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            files={'application/models/Example.php':'<?php\nfunction read_it(){get_option("x");}\nfunction write_it(){$this->db->update("x",[]);}',
                   'modules/example/models/Reader.php':'<?php\nfunction list_it(){$this->db->query("SELECT 1");}',
                   'modules/example/vendor/Foreign.php':'<?php\nfunction dependency(){get_option("x");}'}
            for path,data in files.items():
                file=root/path;file.parent.mkdir(parents=True,exist_ok=True);file.write_text(data)
            docs=root/'docs/companies';docs.mkdir(parents=True)
            digest=hashlib.sha256(files['application/models/Example.php'].encode()).hexdigest()
            evidence={'application/models/Example.php::read_it':{'source_sha256':digest,'tests':['synthetic'], 'exercised':['legacy_positive']},
                      'application/models/Example.php::write_it':{'source_sha256':'stale','tests':['old']}}
            (docs/'consumer-evidence.json').write_text(json.dumps(evidence))
            full=root/'full.json'
            with patch.object(register,'ROOT',root),patch('sys.argv',['register','--full-output',str(full)]),contextlib.redirect_stdout(io.StringIO()):register.main()
            result=json.loads(full.read_text());entries={e['consumer']:e for e in result['consumers']}
            self.assertEqual(len(entries),3)
            self.assertEqual(entries['application/models/Example.php::read_it']['state'],'boundary_tested')
            self.assertEqual(entries['application/models/Example.php::write_it']['state'],'pending')
            self.assertTrue(all(not e['full_operation_ready'] for e in entries.values()))
            summary=json.loads((docs/'consumer-register.json').read_text())
            self.assertEqual(summary['total_consumers'],3)
            self.assertEqual(len(summary['consumers']),1)
            self.assertEqual(len(summary['source_manifest_sha256']),64)

if __name__=='__main__':unittest.main()
