"""Exercise the real smoke command blocks with a clean Bash environment."""
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest


class BrowserEnvironmentTest(unittest.TestCase):
    def test_fixtures_are_copied_into_a_test_free_runtime_before_php(self):
        # Execute the actual browser prelude. The model starts with no /app/tests,
        # as .dockerignore requires; unlike permissive local adapters, cp cannot
        # create its destination parent and PHP cannot read host-only fixtures.
        node = r"""
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const browser=process.argv[1],omitCopy=process.argv[2]==='omit';
const directories=new Set(['/app']),files=new Set();let reachedBrowser=false,fixtureRuns=0;
function docker(binary,args){
  if(binary!=='docker')throw Error('unexpected binary');
  if(args[0]==='exec'&&args[2]==='mkdir'){
    for(const directory of args.slice(4)){
      let parent='';for(const part of directory.split('/').filter(Boolean)){
        parent+='/'+part;directories.add(parent);
      }
    }return '';
  }
  if(args[0]==='cp'){
    if(omitCopy)return '';
    const destination=args[2].split(':')[1];
    if(!directories.has(path.posix.dirname(destination)))throw Error('cp parent missing');
    if(!fs.existsSync(args[1]))throw Error('host fixture missing');
    files.add(destination);return '';
  }
  const php=args.indexOf('php');
  if(args[0]==='exec'&&php>0){
    if(!files.has('/app/'+args[php+1]))throw Error('fixture not copied');
    if(!files.has('/app/tests/support/BbPdoDatabase.php'))throw Error('fixture helper not copied');
    fixtureRuns++;return '{}';
  }
  throw Error('unexpected Docker operation '+JSON.stringify(args));
}
const environment={ISSUERS_BROWSER_URL:'http://127.0.0.1:48095',
 ISSUERS_BROWSER_DB_CONTAINER:'argws-crm-provision-db-999',
 ISSUERS_BROWSER_APP_CONTAINER:'argws-crm-upgrade-999',ISSUERS_BROWSER_DB_NAME:'argws_upgrade_ci'};
const localProcess={env:environment,exitCode:0};
const context={process:localProcess,console:{error(){}},Buffer,URL,
 require(name){
  if(name==='node:child_process')return {execFileSync:docker};
  if(name==='playwright')return {chromium:{async launch(){reachedBrowser=true;throw Error('stop after fixture');}}};
  return require(name);
 }};
vm.runInNewContext(fs.readFileSync(browser,'utf8'),context,{filename:browser});
setImmediate(()=>{if(!reachedBrowser||fixtureRuns!==1){console.error('fixture setup failed before browser');process.exitCode=1;}});
"""
        root = Path(__file__).resolve().parents[1]
        for browser in ('project_portal_tasks', 'portal_task_write', 'expense_billing'):
            with self.subTest(browser=browser):
                command = ['node', '-e', node, 'tests/companies/' + browser + '_browser.cjs']
                green = subprocess.run(command, cwd=root, text=True, capture_output=True)
                self.assertEqual(green.returncode, 0, green.stderr)
                red = subprocess.run(command + ['omit'], cwd=root, text=True, capture_output=True)
                self.assertNotEqual(red.returncode, 0, 'missing fixture copies must fail')
                self.assertIn('fixture setup failed before browser', red.stderr)

    def test_every_browser_receives_its_disposable_environment(self):
        script = Path(__file__).with_name("issuers_standalone_http_smoke.sh")
        blocks, pending = [], []
        for line in script.read_text().splitlines():
            pending.append(line)
            if line.endswith("\\"):
                continue
            block = "\n".join(pending)
            if re.fullmatch(r"node tests/[A-Za-z0-9_/]+\.cjs", line):
                blocks.append((line.split()[1], block))
            pending = []
        self.assertGreater(len(blocks), 2)
        keys = ["URL", "PASSWORD", "DB_CONTAINER", "DB_NAME", "DB_USER", "DB_PASSWORD", "APP_CONTAINER"]
        environment = {k: v for k, v in os.environ.items()
                       if not k.startswith(("ISSUERS_BROWSER_", "ISSUER_SMOKE_"))}
        with tempfile.TemporaryDirectory() as directory:
            node = Path(directory) / "node"
            node.write_text("#!/usr/bin/env python3\nimport json,os\n"
                            "print(json.dumps({k:os.environ.get('ISSUERS_BROWSER_'+k) for k in "
                            + repr(keys) + "}))\n")
            node.chmod(0o755)
            environment["PATH"] = directory + os.pathsep + environment["PATH"]
            setup = """set -eu
url=http://127.0.0.1:48111
upgrade_url=http://127.0.0.1:48222
upgrade=argws-crm-upgrade-999
ISSUER_SMOKE_DATABASE=argws-crm-provision-db-999
ISSUER_SMOKE_DB_USER=synthetic_user
ISSUER_SMOKE_DB_PASSWORD=synthetic_password
"""
            for index, (path, block) in enumerate(blocks):
                with self.subTest(command=path, position=index):
                    result = subprocess.run(["bash", "-c", setup + block], env=environment,
                                            text=True, capture_output=True, check=True)
                    actual = json.loads(result.stdout)
                    self.assertEqual(actual["URL"], "http://127.0.0.1:" + ("48111" if index == 0 else "48222"))
                    self.assertEqual(actual["PASSWORD"], "synthetic-browser-17")
                    self.assertEqual(actual["DB_CONTAINER"], "argws-crm-provision-db-999")
                    self.assertEqual(actual["DB_NAME"], "argws_legacy_ci" if index == 0 else "argws_upgrade_ci")
                    self.assertEqual(actual["DB_USER"], "synthetic_user")
                    self.assertEqual(actual["DB_PASSWORD"], "synthetic_password")
                    if path != "tests/issuers_browser_smoke.cjs":
                        self.assertEqual(actual["APP_CONTAINER"], "argws-crm-upgrade-999")


if __name__ == "__main__":
    unittest.main()
