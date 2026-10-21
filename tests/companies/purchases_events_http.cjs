'use strict';

// Directed HTTP gate for the complete, company-bound AP event reader.
// The main GUI/portal/browser gate must finish first; no native login runs here.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {request} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const url = process.env.ISSUERS_BROWSER_URL;
const database = process.env.ISSUERS_BROWSER_DB_NAME;
const artifacts = process.env.ISSUERS_BROWSER_ARTIFACTS;
const local = process.env.ISSUERS_BROWSER_LOCAL_APP;
const php = process.env.ISSUERS_BROWSER_PHP;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert(['argws_purchase_ci', 'argws_upgrade_ci', 'argws_fresh_ci', 'argws_crm_ci'].includes(database));
assert(artifacts && path.isAbsolute(artifacts));
if (local) {
  assert(path.isAbsolute(local) && php && path.isAbsolute(php));
  assert.equal(database, 'argws_purchase_ci');
} else assert.match(app || '', /^argws-crm-(upgrade|fresh|purchase)-\d+$/);
fs.mkdirSync(artifacts, {recursive: true});

function fixture(...args) {
  return JSON.parse(local
    ? execFileSync(php, [path.join(local, 'tests/companies/purchases_http_fixture.php'), ...args.map(String)], {encoding: 'utf8', env: {...process.env, ISSUERS_BROWSER_DB_NAME: database}})
    : execFileSync('docker', ['exec', '-e', 'ISSUERS_BROWSER_DB_NAME=' + database, app, 'php', 'tests/companies/purchases_http_fixture.php', ...args.map(String)], {encoding: 'utf8'}));
}

(async () => {
  const previous = JSON.parse(fs.readFileSync(process.env.ISSUERS_PURCHASE_PRIOR_PROOF || path.join(artifacts, 'purchases-proof.json'), 'utf8'));
  assert.equal(previous.result, 'PASS', 'The original full browser must complete before the directed event reader gate');
  const a = previous.owners.a.issuer, b = previous.owners.b.issuer;
  const ta = fixture('issue', a, 'purchases:read'), tb = fixture('issue', b, 'purchases:read');
  const before = fixture('transport');
  const proof = {result: 'RUNNING', before, responses: [], scenarios: []};
  async function call(token, resource, method = 'GET') {
    const context = await request.newContext();
    try {
      assert.deepEqual((await context.storageState()).cookies, []);
      const response = await context.fetch(url + '/company_purchases_api/index/' + resource, {method, headers: token ? {Authorization: 'Bearer ' + token.secret} : {}});
      const names = response.headersArray().filter(header => header.name.toLowerCase() === 'set-cookie').map(header => header.value.slice(0, header.value.indexOf('=')));
      assert(names.every(name => name === before.csrf_cookie_name));
      const body = await response.body();
      proof.responses.push({method, resource, token_company: token === ta ? a : token === tb ? b : null, status: response.status(), body_bytes: body.length, cookie_names: names});
      return {status: response.status(), body};
    } finally { await context.dispose(); }
  }
  try {
    const ownA = await call(ta, 'events');
    assert.equal(ownA.status, 200, 'Advertised AP event list is positively usable');
    const ownB = await call(tb, 'events');
    assert.equal(ownB.status, 200);
    const listA = JSON.parse(ownA.body).data.items, listB = JSON.parse(ownB.body).data.items;
    assert(listA.length >= 2 && listB.length >= 2);
    assert(listA.every(event => Number(event.issuer_id) === a));
    assert(listB.every(event => Number(event.issuer_id) === b));
    assert(!listA.some(event => listB.some(other => Number(event.id) === Number(other.id))));
    const id = Number(listA[0].id), detail = await call(ta, 'events/' + id);
    assert.equal(detail.status, 200);
    assert.equal(Number(JSON.parse(detail.body).data.id), id);
    assert.equal((await call(tb, 'events/' + id)).status, 403);
    for (const resource of ['events', 'events/' + id]) {
      const head = await call(ta, resource, 'HEAD');
      assert.equal(head.status, 200);
      assert.equal(head.body.length, 0);
    }
    const page = await call(ta, 'events?after=' + id);
    assert.equal(page.status, 200);
    assert(JSON.parse(page.body).data.items.every(event => Number(event.id) > id && Number(event.issuer_id) === a));
    assert.equal((await call(null, 'events')).status, 401);
    assert.equal((await call(ta, 'events?issuer_id=' + b)).status, 422);
    const after = fixture('transport');
    assert.deepEqual(after, before, 'Every native session remains byte-identical during stateless event reads');
    proof.result = 'PASS';
    proof.after = after;
    proof.scenarios = ['nonempty event lists in A and B', 'owned detail and cross-company denial', 'HEAD without body and owned cursor pagination', 'missing bearer and forged issuer rejected', 'fresh cookie jar per request and unchanged native sessions'];
  } catch (error) {
    proof.result = 'FAIL'; proof.error = error.stack; throw error;
  } finally {
    fixture('revoke', a, ta.token_id); fixture('revoke', b, tb.token_id);
    fs.writeFileSync(path.join(artifacts, 'purchases-events-proof.json'), JSON.stringify(proof, null, 2) + '\n');
    console.log(JSON.stringify({result: proof.result, scenarios: proof.scenarios, responses: proof.responses}));
  }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
