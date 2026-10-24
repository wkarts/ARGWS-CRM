'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/deployment-integrity.yml'),'utf8');
test('cheap packaging and security contracts run before browser and Rust setup',()=>{
 for(const command of ['tests/test_actions_cache_cleanup.py','tests/test_deployer_release_contract.py','tests/test_package_release.py','tests/test_package_deploy.py']) {
  assert.equal(workflow.split(command).length-1,3,'two path filters and one execution: '+command);
  assert.ok(workflow.indexOf('run: python3 -m unittest '+command)<workflow.indexOf('- uses: shivammathur/setup-php@v2'));
 }
 assert.ok(workflow.indexOf('actions-rust-lang/setup-rust-toolchain@v1')>workflow.indexOf('run: bash tests/docker_provision_smoke.sh'));
 assert.match(workflow,/cargo \+1\.90\.0 test --manifest-path tools\/argws-crm-deployer\/Cargo.toml/);
});
test('browser tooling uses the dedicated lockfile, not application build dependencies',()=>{
 assert.match(workflow,/cp docker\/ci\/package\.json docker\/ci\/package-lock\.json "\$RUNNER_TEMP\/ci-browser\/"/);
 assert.match(workflow,/npm ci --prefix "\$RUNNER_TEMP\/ci-browser" --ignore-scripts/);
 assert.match(workflow,/"\$RUNNER_TEMP\/ci-browser\/node_modules\/\.bin\/playwright" install --with-deps chromium/);
 assert.match(workflow,/NODE_PATH=\$RUNNER_TEMP\/ci-browser\/node_modules/);
 assert.doesNotMatch(workflow,/npm install|npx playwright|continue-on-error/);
});
