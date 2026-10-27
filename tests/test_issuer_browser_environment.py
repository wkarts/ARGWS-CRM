"""Exercise the real smoke command blocks with a clean Bash environment."""
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest


class BrowserArtifactCollectionTest(unittest.TestCase):
    """Exercise upload predicates against producer outcomes, including prior failure."""

    def test_uploads_follow_their_producers_and_keep_partial_failure_evidence(self):
        root = Path(__file__).resolve().parents[1]
        workflow = (root / ".github/workflows/deployment-integrity.yml").read_text()
        steps = re.split(r"(?m)^      - ", workflow)[1:]
        evaluate = r"""
const vm = require('node:vm');
const {expression, id} = JSON.parse(process.argv[1]);
const outcomes = ['success', 'failure', 'skipped', 'cancelled', ''];
const results = Object.fromEntries(outcomes.map(outcome => {
  const jobSucceeded = outcome === 'success';
  const explicitStatus = /\b(?:always|success|failure|cancelled)\s*\(/.test(expression);
  const condition = vm.runInNewContext(expression, {
    steps: {[id]: {outcome}}, always: () => true,
    success: () => jobSucceeded, failure: () => !jobSucceeded,
    cancelled: () => outcome === 'cancelled',
  }, {timeout: 1000});
  return [outcome, Boolean((explicitStatus || jobSucceeded) && condition)];
}));
process.stdout.write(JSON.stringify(results));
"""
        cases = {
            "company-shell-synthetic-ui": ("node tests/company_shell_browser.cjs", "ignore"),
            "company-identity-presentation": ("node tests/company_identity_browser.cjs", "warn"),
            "client-identity-presentation": ("node tests/companies/client_identity_browser.cjs", "error"),
            "asaas-native-settings-presentation": ("node tests/asaas_settings_tabs.browser.cjs", "error"),
            "issuer-native-browser": ("bash tests/docker_provision_smoke.sh", "ignore"),
        }
        for artifact, (command, missing_policy) in cases.items():
            with self.subTest(artifact=artifact):
                uploads = [step for step in steps
                           if re.search(r"(?m)^          name: " + re.escape(artifact) + r"$", step)]
                self.assertEqual(len(uploads), 1, "each artifact needs exactly one upload")
                upload = uploads[0]
                expression = re.search(r"(?m)^        if: (.+)$", upload).group(1)
                ids = set(re.findall(r"steps\.([A-Za-z0-9_]+)\.outcome", expression))
                self.assertEqual(len(ids), 1, "upload must depend on its own producer")
                producer_id = ids.pop()
                producers = [step for step in steps
                             if re.search(r"(?m)^        id: " + re.escape(producer_id) + r"$", step)]
                self.assertEqual(len(producers), 1)
                self.assertIn(command, producers[0])
                self.assertLess(steps.index(producers[0]), steps.index(upload))
                result = subprocess.run(
                    ["node", "-e", evaluate, json.dumps({"expression": expression, "id": producer_id})],
                    cwd=root, text=True, capture_output=True, check=True,
                )
                self.assertEqual(json.loads(result.stdout), {
                    "success": True, "failure": True, "skipped": False,
                    "cancelled": False, "": False,
                })
                self.assertRegex(upload, r"(?m)^          if-no-files-found: " + missing_policy + r"$")


class PhpErrorClassificationTest(unittest.TestCase):
    def test_exception_diagnostics_are_bounded_and_do_not_expose_values(self):
        root = Path(__file__).resolve().parents[1]
        secret = 'sensitive-synthetic-password-and-token'
        diagnostic = (
            'PHP=8.3.35 memory_limit=128M\n'
            'ERROR --> Severity: error --> Exception: issuer_portal_mail_write_unconfirmed '
            + secret + ' https://user.invalid/private?token=' + secret
            + ' /app/application/services/NativeCompanySettingsController.php 82\n'
            'ERROR --> Severity: error --> Exception: ' + secret
            + ' /app/application/not-present-secret.php 19\n'
            'ERROR --> Severity: Warning --> ' + secret
            + ' /app/application/../../secret.php 1\n'
            'non-error payload ' + secret + '\n'
        )
        result = subprocess.run(
            ['python3', 'tests/ci/classify_php_error_log.py'], cwd=root,
            input=diagnostic, text=True, capture_output=True, check=True,
        )
        self.assertEqual(result.stdout.splitlines(), [
            'PHP=8.3.35 memory_limit=128M',
            'kind=exception location=application/services/NativeCompanySettingsController.php:82 '
            'code=issuer_portal_mail_write_unconfirmed',
            'kind=exception location=unavailable code=unclassified',
            'kind=php_error location=unavailable code=unclassified',
        ])
        self.assertNotIn(secret, result.stdout)
        self.assertNotIn('https://', result.stdout)
        self.assertNotIn('not-present-secret', result.stdout)
        self.assertEqual(result.stderr, '')

    def test_disposable_logging_and_classifier_are_in_existing_preflight(self):
        root = Path(__file__).resolve().parents[1]
        script = (root / 'tests/issuers_standalone_http_smoke.sh').read_text()
        workflow = (root / '.github/workflows/deployment-integrity.yml').read_text()
        self.assertIn('python3 -m unittest tests/test_issuer_browser_environment.py', workflow)
        self.assertIn('python3 tests/ci/classify_php_error_log.py', script)
        self.assertLess(script.index('if (APP_DB_NAME !== "argws_upgrade_ci")'),
                        script.index('node tests/companies_portal_browser.cjs'))
        self.assertLess(script.index('define(\\"APP_LOG_THRESHOLD\\", 1)'),
                        script.index('node tests/companies_portal_browser.cjs'))


class BrowserEnvironmentTest(unittest.TestCase):
    def test_fixtures_are_copied_into_a_test_free_runtime_before_php(self):
        # Execute the actual browser prelude. The model starts with no /app/tests,
        # as .dockerignore requires; unlike permissive local adapters, cp cannot
        # create its destination parent and PHP cannot read host-only fixtures.
        node = r"""
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const browser=process.argv[1],omitCopy=process.argv[2]==='omit';
// Resolve real local helpers relative to the browser fixture, not node -e.
// Docker and Playwright remain intercepted below to preserve this isolation test.
const fixtureRequire=require('node:module').createRequire(path.resolve(browser));
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
const localProcess={env:environment,exitCode:0,cwd:()=>process.cwd()};
const context={process:localProcess,console:{error(){}},Buffer,URL,
 require(name){
  if(name==='node:child_process')return {execFileSync:docker};
  if(name==='playwright')return {chromium:{async launch(){reachedBrowser=true;throw Error('stop after fixture');}}};
  return fixtureRequire(name);
 }};
vm.runInNewContext(fs.readFileSync(browser,'utf8'),context,{filename:browser});
setImmediate(()=>{if(!reachedBrowser||fixtureRuns!==1){console.error('fixture setup failed before browser');process.exitCode=1;}});
"""
        root = Path(__file__).resolve().parents[1]
        for browser in ('project_portal_tasks', 'portal_task_write', 'expense_billing',
                        'task_billing_http', 'task_timesheets_http',
                        'project_billing_rate_http', 'project_fixed_billing_http'):
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
legacy=argws-crm-legacy-999
ISSUER_SMOKE_DATABASE=argws-crm-provision-db-999
ISSUER_SMOKE_DB_USER=synthetic_user
ISSUER_SMOKE_DB_PASSWORD=synthetic_password
work="{directory}"
""".replace("{directory}",directory)
            for index, (path, block) in enumerate(blocks):
                with self.subTest(command=path, position=index):
                    result = subprocess.run(["bash", "-c", setup + block], env=environment,
                                            text=True, capture_output=True, check=True)
                    actual = json.loads(result.stdout)
                    fresh = index == 0 or path == "tests/companies/hr_cli_http.cjs"
                    self.assertEqual(actual["URL"], "http://127.0.0.1:" + ("48111" if fresh else "48222"))
                    self.assertEqual(actual["PASSWORD"], "synthetic-browser-17")
                    self.assertEqual(actual["DB_CONTAINER"], "argws-crm-provision-db-999")
                    self.assertEqual(actual["DB_NAME"], "argws_legacy_ci" if fresh else "argws_upgrade_ci")
                    self.assertEqual(actual["DB_USER"], "synthetic_user")
                    self.assertEqual(actual["DB_PASSWORD"], "synthetic_password")
                    if path != "tests/issuers_browser_smoke.cjs":
                        self.assertEqual(actual["APP_CONTAINER"], "argws-crm-legacy-999" if fresh else "argws-crm-upgrade-999")


if __name__ == "__main__":
    unittest.main()
