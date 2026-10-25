'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {assertAttestedMaster} = require('./helpers/installed_master_proof.cjs');
const root = path.join(__dirname, '..');
const files = ['hr_http_fixture','hr_tokens_http_fixture','hr_cli_http_fixture','leads_http_fixture','purchases_http_fixture','task_recurring_http_fixture'];
const meta = {proof:'installation_owner_attestation',confirmation_reference:'test:explicit-owner-confirmation',primary_issuer_id:1};
function run(identity=[17,1,'owner_attestation'], audits=[[0,17,null,JSON.stringify(meta)]]) {
  assertAttestedMaster(query => JSON.stringify(query.includes('JSON_ARRAYAGG') ? audits : identity));
}
test('exact fixture identity and single durable maintenance audit pass', () => run());
test('wrong identities and arbitrary or historical source are rejected for this upgrade', () => {
  for (const identity of [[1,1,'owner_attestation'],[17,2,'owner_attestation'],[17,1,'installer'],[17,1,'legacy_designation'],[17,1,'other'],[null,1,'owner_attestation']]) assert.throws(()=>run(identity));
});
test('missing, duplicate, impersonated, foreign or unproven audit is rejected', () => {
  for(const audits of [null,[],[[0,17,null,meta],[0,17,null,meta]],[[17,17,null,meta]],[[0,42,null,meta]],[[0,17,1,meta]],[[0,17,null,{...meta,primary_issuer_id:2}]],[[0,17,null,{...meta,confirmation_reference:'other'}]],[[0,17,null,{...meta,proof:'installer'}]]]) assert.throws(()=>run(undefined,audits));
});
test('all six reused PHP consumers share exact audit gate; historical guard remains', () => {
  let canonical;
  for(const file of files) {
    const source=fs.readFileSync(path.join(root,'tests/companies',file+'.php'),'utf8');
    const block=source.match(/\/\/ BEGIN exact upgraded-fixture owner proof[\s\S]*?\/\/ END exact upgraded-fixture owner proof\./)?.[0];
    assert(block,file); canonical ??=block; assert.equal(block,canonical,file);
    assert.match(block,/\(int\)\$identity\['primary_issuer_id'\] !== 1/);
    assert.match(block,/getenv\('ISSUERS_BROWSER_DB_NAME'\) !== 'argws_upgrade_ci'/);
    assert.match(source,/\['installer', ?'legacy_designation', ?'owner_attestation'\]/);
    assert.match(source,/master_staff_id.*!==17|master_staff_id.*!== 17/);
  }
  assert.match(fs.readFileSync(path.join(root,'tests/issuers_master_resolution.php'),'utf8'),/source==='legacy_designation'/);
});
test('producer binds exact owner proof before both downstream browsers', () => {
  const producer=fs.readFileSync(path.join(root,'tests/companies_onboarding_browser.cjs'),'utf8');
  assert.match(producer,/'test:explicit-owner-confirmation'/);
  assert.match(producer,/'17:owner_attestation'/);
  const shell=fs.readFileSync(path.join(root,'tests/issuers_standalone_http_smoke.sh'),'utf8');
  for(const file of ['resume_439_native_staff_browser.cjs','companies/staff_browser.cjs']) {
    assert(shell.indexOf('node tests/companies_onboarding_browser.cjs') < shell.indexOf('node tests/'+file));
    assert.match(fs.readFileSync(path.join(root,'tests',file),'utf8'),/assertAttestedMaster\(sql\)/);
  }
});
