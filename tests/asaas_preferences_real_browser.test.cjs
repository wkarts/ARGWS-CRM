'use strict';
// Guards only. These unit tests do not claim to execute the authenticated browser integration.
const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const {environment, requestDecision, readOnlyQuery, run} = require('./companies/asaas_preferences_real_browser.cjs');
const fixture = {
  ISSUERS_BROWSER_URL: 'http://127.0.0.1:48321',
  ISSUERS_BROWSER_DB_CONTAINER: 'argws-crm-provision-db-999',
  ISSUERS_BROWSER_APP_CONTAINER: 'argws-crm-upgrade-999',
  ISSUERS_BROWSER_DB_NAME: 'argws_upgrade_ci',
  ISSUERS_BROWSER_PASSWORD: 'synthetic-test-only',
  ISSUERS_BROWSER_DB_USER: 'synthetic_test',
  ISSUERS_BROWSER_DB_PASSWORD: 'synthetic-test-only',
};
const url = pathname => fixture.ISSUERS_BROWSER_URL + pathname;
const decision = (pathname, method = 'GET', permittedPost = null) => requestDecision(url(pathname), method, fixture.ISSUERS_BROWSER_URL, permittedPost);

test('uses the existing disposable CI contract with separate phase evidence', () => {
  assert.equal(environment(fixture).phase, 'selected');
  assert.match(environment(fixture).output, /asaas-real-preferences-selected$/);
  assert.equal(environment({...fixture, ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE: 'unselected'}).phase, 'unselected');
  assert.match(environment({...fixture, ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE: 'unselected'}).output, /asaas-real-preferences-unselected$/);
  assert.equal(typeof run, 'function', 'existing native installer can invoke the pre-fixture phase');
});

test('rejects missing or unsafe loopback/DB/container identity before browser launch', () => {
  for (const [key, value] of Object.entries({
    ISSUERS_BROWSER_URL: 'https://production.example', ISSUERS_BROWSER_DB_CONTAINER: 'production-db',
    ISSUERS_BROWSER_APP_CONTAINER: 'production-app', ISSUERS_BROWSER_DB_NAME: 'argws_crm',
    ISSUERS_BROWSER_PASSWORD: '', ISSUERS_BROWSER_DB_USER: '', ISSUERS_BROWSER_DB_PASSWORD: '',
    ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE: 'seed',
  })) assert.throws(() => environment({...fixture, [key]: value}));
  for (const origin of ['http://localhost:48321', 'http://127.0.0.1:65536', 'http://127.0.0.1:48321/admin', 'http://127.0.0.1:48321?token=synthetic', 'http://127.0.0.1:48321@outside.invalid']) {
    assert.throws(() => environment({...fixture, ISSUERS_BROWSER_URL: origin}));
  }
});

test('permits only exact native GET pages and local static assets', () => {
  for (const pathname of ['/admin/authentication', '/admin/authentication/admin', '/admin', '/admin/', '/admin/company_context', '/admin/company_asaas', '/admin/company_asaas?invoice=12', '/admin/company_asaas#company-asaas-profile-production', '/assets/js/company-context-tabs.js?v=4.0.4', '/assets/plugins/bootstrap/css/bootstrap.min.css', '/assets/plugins/inter/Inter-Regular.woff2', '/uploads/company/synthetic-logo.png']) {
    assert.equal(decision(pathname), 'allow', pathname);
    assert.equal(decision(pathname, 'HEAD'), 'allow', pathname);
  }
});

test('blocks all external origins including both providers, another port and userinfo', () => {
  for (const target of ['https://api.asaas.com/v3', 'https://api-sandbox.asaas.com/v3', 'http://127.0.0.1:48322/admin/company_asaas', 'https://127.0.0.1:48321/admin', 'http://127.0.0.1:48321.evil.invalid/admin', 'http://user:secret@127.0.0.1:48321/admin', 'invalid', 'data:text/html,synthetic', 'file:///tmp/synthetic.html']) {
    assert.equal(requestDecision(target, 'GET', fixture.ISSUERS_BROWSER_URL), 'external');
  }
});

test('requires a temporary exact login/context POST permit', () => {
  for (const pathname of ['/admin/authentication', '/admin/authentication/admin', '/admin/company_context/select']) {
    assert.equal(decision(pathname, 'POST'), 'mutation');
    assert.equal(decision(pathname, 'POST', pathname), 'allow');
    assert.equal(decision(pathname, 'PATCH', pathname), 'mutation');
    assert.equal(decision(pathname + '/extra', 'POST', pathname), 'mutation');
  }
  assert.equal(decision('/admin/company_context/select', 'POST', '/admin/authentication'), 'mutation');
});

test('cannot grant write permission to financial/settings routes by passing their path', () => {
  for (const pathname of ['/admin/company_asaas', '/admin/settings', '/admin/asaas/rotate_integration_token', '/admin/company_invoices', '/asaas/gateways/callback/integration/synthetic', '/company_asaas_callback/integration/synthetic']) {
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE']) {
      assert.equal(decision(pathname, method, pathname), 'mutation');
      assert.equal(decision(pathname, method, '/admin/authentication'), 'mutation');
    }
  }
});

test('blocks non-GET method overrides and ambiguous paths before they reach CRM', () => {
  for (const pathname of ['/admin/company_asaas?environment=production', '/admin/company_asaas?action=issue', '/admin/company_asaas?_method=GET', '/ADMIN/company_asaas', '/admin//company_asaas', '/admin/%63ompany_asaas', '/admin/company_asaas/', '/admin/company_asaas/issue']) {
    assert.equal(decision(pathname, 'POST'), 'mutation');
  }
  for (const pathname of ['/admin/%63ompany_asaas', '/admin/company_asaas/issue', '/admin/company_asaas/', '/admin//company_asaas', '/ADMIN/company_asaas']) assert.equal(decision(pathname), 'navigation');
});

test('GET cannot invoke provider callback, cron, module mutation or uploaded PHP', () => {
  for (const pathname of ['/cron/index', '/company_asaas_callback/integration/synthetic', '/admin/asaas/rotate_integration_token', '/admin/modules/activate/asaas', '/uploads/synthetic.php', '/modules/asaas/run.php', '/assets/script.php.js/execute', '/admin/company_context/select']) {
    assert.equal(decision(pathname), 'navigation');
  }
});

test('read-only SQL wrapper admits only one non-locking evidence query', () => {
  assert.equal(readOnlyQuery('SELECT id FROM tblinvoices ORDER BY id'), 'START TRANSACTION READ ONLY; SELECT id FROM tblinvoices ORDER BY id; COMMIT;');
  assert.equal(readOnlyQuery('SHOW TABLES'), 'START TRANSACTION READ ONLY; SHOW TABLES; COMMIT;');
  for (const statement of ['UPDATE tblasaas_company_preferences SET active_environment=NULL', 'DELETE FROM tblinvoices', 'INSERT INTO tblmodules VALUES (1)', 'DROP TABLE tblinvoices', 'CALL fixture_seed()', 'SELECT 1; DELETE FROM tblinvoices', 'SELECT 1 -- comment', 'SELECT /* comment */ 1', 'SELECT id FROM tblinvoices FOR UPDATE', 'SELECT id FROM tblinvoices LOCK IN SHARE MODE', "SELECT id INTO OUTFILE '/tmp/unsafe' FROM tblinvoices", "SELECT id INTO DUMPFILE '/tmp/unsafe' FROM tblinvoices", 'SELECT\u00001']) {
    assert.throws(() => readOnlyQuery(statement));
  }
});

test('integration uses actual UI and existing native state without a false HTML/response fixture', () => {
  const source = fs.readFileSync(__dirname + '/companies/asaas_preferences_real_browser.cjs', 'utf8');
  assert.match(source, /businessSnapshot\(\) === before/);
  assert.match(source, /newContext\(\{viewport: \{width, height: 900\}/);
  assert.match(source, /for \(const width of \[1280, 390\]\)/);
  assert.match(source, /SELECT id FROM tblinvoices WHERE issuer_id=/);
  assert.match(source, /native155\+ tracking matches the shipped module/);
  for (const forbidden of [/\.setContent\s*\(/, /\.fulfill\s*\(/, /\.request\.(?:post|put|patch|delete)\s*\(/, /asaasFixtureSelectEnvironment\s*\(/, /execFileSync\('php'/, /execFileSync\('docker',\s*\['cp'/]) assert.doesNotMatch(source, forbidden);
});


test('real phases surround account preparation and Docker receives the new shared helper', () => {
  const source = fs.readFileSync(__dirname + '/companies_asaas_browser.cjs', 'utf8');
  const native = source.indexOf("app,'php','tests/companies/asaas_install_http_fixture.php'");
  const unselected = source.indexOf("ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'unselected'");
  const prepare = source.indexOf("for(const [issuer,letter]of");
  const selected = source.indexOf("ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'selected'");
  assert.ok(native > 0 && native < unselected && unselected < prepare && prepare < selected);
  for (const file of ['companies_asaas_browser.cjs', 'companies_asaas_remote_browser.cjs', 'companies_asaas_checkout_browser.cjs', 'companies/asaas_refund_browser.cjs']) {
    assert.match(fs.readFileSync(__dirname + '/' + file, 'utf8'), /asaas_preferences_fixture\.php/);
  }
});
