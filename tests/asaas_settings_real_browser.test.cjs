'use strict';
const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const {environment, localRequest, financialMutation} = require('./companies/asaas_settings_real_browser.cjs');
const fixture = {
  ISSUERS_BROWSER_URL: 'http://127.0.0.1:48321',
  ISSUERS_BROWSER_DB_CONTAINER: 'argws-crm-provision-db-999',
  ISSUERS_BROWSER_APP_CONTAINER: 'argws-crm-upgrade-999',
  ISSUERS_BROWSER_DB_NAME: 'argws_upgrade_ci',
  ISSUERS_BROWSER_PASSWORD: 'synthetic-test-only',
  ISSUERS_BROWSER_DB_USER: 'synthetic_test',
  ISSUERS_BROWSER_DB_PASSWORD: 'synthetic-test-only',
};
test('accepts only the explicit disposable CI environment', () => {
  assert.equal(environment(fixture).url, fixture.ISSUERS_BROWSER_URL);
  assert.match(environment(fixture).output, /asaas-real-settings$/);
});
test('rejects nonlocal, ambiguous or invalid browser origins', () => {
  for (const url of ['https://production.example', 'http://localhost:8080', 'http://127.0.0.1:80/path', 'http://127.0.0.1:80?x=1', 'http://127.0.0.1:65536', 'http://127.0.0.1:80@external.example']) {
    assert.throws(() => environment({...fixture, ISSUERS_BROWSER_URL: url}));
  }
});
test('rejects other database/container targets or missing synthetic credentials', () => {
  for (const [key, value] of Object.entries({ISSUERS_BROWSER_DB_CONTAINER: 'production-db', ISSUERS_BROWSER_APP_CONTAINER: 'production-app',
    ISSUERS_BROWSER_DB_NAME: 'argws_crm', ISSUERS_BROWSER_PASSWORD: '', ISSUERS_BROWSER_DB_USER: '', ISSUERS_BROWSER_DB_PASSWORD: ''})) {
    assert.throws(() => environment({...fixture, [key]: value}));
  }
});
test('browser requests stay on the exact admitted loopback origin', () => {
  assert.ok(localRequest(fixture.ISSUERS_BROWSER_URL + '/admin/settings', fixture.ISSUERS_BROWSER_URL));
  for (const url of ['https://api.asaas.com/v3', 'https://api-sandbox.asaas.com/v3', 'http://127.0.0.1:48322/admin', 'http://127.0.0.1:48321.evil.example/', 'invalid']) {
    assert.equal(localRequest(url, fixture.ISSUERS_BROWSER_URL), false);
  }
});
test('presentation cannot submit settings or financial mutations', () => {
  for (const url of ['/admin/settings?group=payment_gateways', '/admin/asaas/rotate_integration_token', '/admin/company_asaas', '/asaas/gateways/callback/integration/example', '/company_asaas_callback/integration/example']) {
    assert.ok(financialMutation(fixture.ISSUERS_BROWSER_URL + url, 'POST'));
    assert.equal(financialMutation(fixture.ISSUERS_BROWSER_URL + url, 'GET'), false);
  }
  assert.equal(financialMutation(fixture.ISSUERS_BROWSER_URL + '/admin/authentication', 'POST'), false);
  assert.equal(financialMutation(fixture.ISSUERS_BROWSER_URL + '/admin/company_context', 'POST'), false);
});
test('real browser runs after Asaas native installation, with existing artifact capture', () => {
  const shell = fs.readFileSync(__dirname + '/issuers_standalone_http_smoke.sh', 'utf8');
  const native = shell.indexOf('node tests/companies_asaas_browser.cjs');
  const real = shell.indexOf('node tests/companies/asaas_settings_real_browser.cjs');
  assert.ok(native >= 0 && real > native);
  assert.match(fs.readFileSync(__dirname + '/../.github/workflows/deployment-integrity.yml', 'utf8'), /path: \/tmp\/issuer-browser-evidence/);
});
