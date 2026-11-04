'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/deployment-integrity.yml'),'utf8');
test('required deployment validation starts for every main PR and push',()=>{
 for(const event of ['pull_request','push']) {
  const trigger=workflow.match(new RegExp('^  '+event+':\\n((?:^    .*\\n)+)','m'));
  assert.ok(trigger,'missing required event: '+event);
  assert.match(trigger[1],/branches:\s*\[[^\]]*\bmain\b/);
  assert.doesNotMatch(trigger[1],/\b(?:paths|paths-ignore|branches-ignore):/);
 }
});
test('cheap packaging and security contracts run before browser and Rust setup',()=>{
 for(const command of ['tests/test_actions_cache_cleanup.py','tests/test_deployer_release_contract.py','tests/test_package_release.py','tests/test_package_deploy.py']) {
  const execution='        run: python3 -m unittest '+command;
  assert.equal(workflow.split('\n').filter(line=>line===execution).length,1,'exactly one execution: '+command);
  const position=workflow.indexOf(execution);
  const phpSetup=workflow.indexOf('- uses: shivammathur/setup-php@v2');
  const browserSetup=workflow.indexOf('playwright" install --with-deps chromium');
  const rustSetup=workflow.indexOf('actions-rust-lang/setup-rust-toolchain@v1');
  assert.ok(position>=0 && position<phpSetup && position<browserSetup && position<rustSetup,'contract must run before tool setup: '+command);
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
test('real MySQL batch proof runs after database health and before application provisioning',()=>{
 const shell=fs.readFileSync(path.join(__dirname,'docker_provision_smoke.sh'),'utf8');
 const command='node tests/installed_master_proof.mysql.cjs';
 assert.equal(shell.split(command).length-1,1);
 const position=shell.indexOf(command);
 assert(position>shell.indexOf('if [ "$healthy" -ne 1 ]'));
 assert(position<shell.indexOf('docker run -d --name "$web"'));
 const block=shell.slice(shell.lastIndexOf('ISSUERS_BROWSER_DB_CONTAINER=',position),position);
 for(const key of ['CONTAINER="$database"','NAME="$database_name"','USER="$database_user"','PASSWORD="$database_password"']) assert(block.includes('ISSUERS_BROWSER_DB_'+key));
 assert.match(shell,/^set -euo pipefail$/m);
 assert.doesNotMatch(shell.slice(position,position+command.length+20),/\|\| true/);
});
