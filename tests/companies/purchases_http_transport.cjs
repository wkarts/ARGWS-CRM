'use strict';

// Runs after purchases_browser.cjs in the same disposable native fixture.
// Every authenticated HTTP request gets a new empty cookie jar. A separate
// negative request sends only the native CSRF cookie and must remain anonymous.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {request} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

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
} else {
  assert.match(app || '', /^argws-crm-(upgrade|fresh|purchase)-\d+$/);
}

function fixture(...args) {
  const env = {...process.env, ISSUERS_BROWSER_DB_NAME: database};
  const output = local
    ? execFileSync(php, [path.join(local, 'tests/companies/purchases_http_fixture.php'), ...args.map(String)], {encoding: 'utf8', env})
    : execFileSync('docker', ['exec', '-e', 'ISSUERS_BROWSER_DB_NAME=' + database, app, 'php', 'tests/companies/purchases_http_fixture.php', ...args.map(String)], {encoding: 'utf8'});
  return JSON.parse(output);
}

(async () => {
  const previous = JSON.parse(fs.readFileSync(path.join(artifacts, 'purchases-proof.json'), 'utf8'));
  assert.equal(previous.result, 'PASS', 'The full GUI/portal/API matrix must pass first');
  const issuer = previous.owners.a.issuer;
  const order = previous.owners.a.order;
  const token = fixture('issue', issuer);
  const before = fixture('transport');
  const key = crypto.randomBytes(32).toString('hex');
  const today = new Date().toISOString().slice(0, 10);
  const evidence = {result: 'RUNNING', scenarios: [], before, cookie_names: []};
  let csrfOnlyState = null;

  async function call(method, resource, data, {bearer = true, csrfOnly = false, idempotency = key} = {}) {
    const context = await request.newContext(csrfOnly ? {storageState: csrfOnlyState} : {});
    try {
      const initial = await context.storageState();
      if (csrfOnly) {
        assert(initial.cookies.length > 0);
        assert(initial.cookies.every(cookie => cookie.name === before.csrf_cookie_name));
        assert.equal(bearer, false);
      } else {
        assert.deepEqual(initial.cookies, [], 'Each bearer request starts without any cookies');
      }
      const headers = bearer ? {Authorization: 'Bearer ' + token.secret} : {};
      if (method === 'POST') headers['Idempotency-Key'] = idempotency;
      const response = await context.fetch(url + '/company_purchases_api/index/' + resource, {method, headers, ...(data ? {data} : {})});
      const names = response.headersArray().filter(header => header.name.toLowerCase() === 'set-cookie')
        .map(header => header.value.slice(0, header.value.indexOf('=')));
      assert(names.every(name => name === before.csrf_cookie_name), 'No authentication or session cookie is emitted');
      const after = await context.storageState();
      assert(after.cookies.every(cookie => cookie.name === before.csrf_cookie_name));
      if (!csrfOnlyState && after.cookies.length) csrfOnlyState = after;
      evidence.cookie_names.push(...names);
      return {status: response.status(), body: await response.body()};
    } finally {
      await context.dispose();
    }
  }

  try {
    assert.equal((await call('GET', 'orders/' + order)).status, 200);
    const head = await call('HEAD', 'orders/' + order);
    assert.equal(head.status, 200);
    assert.equal(head.body.length, 0);
    const input = {action: 'create', input: {title: 'Independent cookie-free HTTP proof', needed_date: today, currency: 'BRL', lines: [{description: 'Transport proof', unit: 'UN', quantity: '1.000'}]}};
    const created = await call('POST', 'requests', input);
    assert.equal(created.status, 200);
    const id = JSON.parse(created.body).data.result;
    assert(Number.isSafeInteger(id) && id > 0);
    const replay = await call('POST', 'requests', input);
    assert.equal(replay.status, 200);
    assert.equal(JSON.parse(replay.body).data.result, id);
    assert.equal((await call('GET', 'requests/' + id)).status, 200);
    assert.equal((await call('GET', 'orders/' + order, null, {bearer: false})).status, 401);
    assert(csrfOnlyState, 'Observe the native CSRF response before exercising its isolated negative case');
    assert.equal((await call('GET', 'orders/' + order, null, {bearer: false, csrfOnly: true})).status, 401);
    assert.equal((await call('POST', 'requests', input, {bearer: false, csrfOnly: true, idempotency: crypto.randomBytes(32).toString('hex')})).status, 401);
    fixture('revoke', issuer, token.token_id);
    assert.equal((await call('GET', 'orders/' + order)).status, 401);
    const after = fixture('transport');
    assert.deepEqual(after, before, 'The complete native session table remains byte-identical across all requests');
    evidence.result = 'PASS';
    evidence.after = after;
    evidence.request_id = id;
    evidence.cookie_names = [...new Set(evidence.cookie_names)];
    evidence.scenarios = ['GET/HEAD/POST/replay with independent empty jars', 'HEAD carries no response body', 'no bearer is denied with or without the native CSRF cookie', 'revoked token is denied without ambient cookies', 'all native sessions unchanged'];
  } catch (error) {
    evidence.result = 'FAIL';
    evidence.error = error.stack;
    throw error;
  } finally {
    fixture('revoke', issuer, token.token_id);
    fs.writeFileSync(path.join(artifacts, 'purchases-transport-proof.json'), JSON.stringify(evidence, null, 2) + '\n');
    console.log(JSON.stringify({result: evidence.result, scenarios: evidence.scenarios, cookie_names: [...new Set(evidence.cookie_names)]}));
  }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
