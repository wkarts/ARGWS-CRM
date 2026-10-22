'use strict';
// Guards only. These unit tests do not claim to execute the authenticated browser integration.
const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const {environment, requestDecision, readOnlyQuery, safePath, requestScope, bodyCategory, responseFacts, blockedRequests, run} = require('./companies/asaas_preferences_real_browser.cjs');
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


test('HTTP diagnostics contain only status and allowlisted paths/categories', async () => {
  const secret = 'synthetic-private-value';
  assert.equal(safePath(url('/admin/company_asaas?token=' + secret + '#' + secret), fixture.ISSUERS_BROWSER_URL), '/admin/company_asaas');
  assert.equal(safePath(url('/private/' + secret), fixture.ISSUERS_BROWSER_URL), '[other-local]');
  assert.equal(safePath('https://outside.invalid/' + secret, fixture.ISSUERS_BROWSER_URL), '[external]');
  assert.equal(safePath('http://user:' + secret + '@127.0.0.1:48321/admin', fixture.ISSUERS_BROWSER_URL), '[external]');
  assert.equal(bodyCategory('Call to undefined function _l() ' + secret), 'translation-helper-missing');
  assert.equal(bodyCategory('Histórico de reembolso indisponível para conferência ' + secret), 'refund-history-unavailable');
  assert.equal(bodyCategory(secret), 'unclassified');
  const previous = {url: () => url('/private/' + secret), redirectedFrom: () => null};
  const request = {method: () => 'GET', redirectedFrom: () => previous};
  const response = {status: () => 500, url: () => url('/admin/company_asaas?token=' + secret), request: () => request,
    headers: () => ({location: '/admin/authentication?token=' + secret}), text: async () => 'Call to undefined function _l() ' + secret};
  const facts = await responseFacts(response, fixture.ISSUERS_BROWSER_URL);
  assert.deepEqual(facts, {status: 500, path: '/admin/company_asaas', method: 'GET', redirects: ['[other-local]'], location: '/admin/authentication', body: 'translation-helper-missing'});
  assert.ok(!JSON.stringify(facts).includes(secret));
  assert.ok(!JSON.stringify(facts).includes('?'));
  assert.deepEqual(await responseFacts(null, fixture.ISSUERS_BROWSER_URL), {status: null, path: '[no-response]'});
  assert.equal((await responseFacts({...response, text: async () => {throw new Error(secret);}}, fixture.ISSUERS_BROWSER_URL)).body, 'unavailable');
});

test('blocked dashboard traffic stays blocked and counted without becoming a presentation mutation', () => {
  const blocked = blockedRequests();
  const origin = fixture.ISSUERS_BROWSER_URL;
  const target = url('/admin/tasks/table?token=synthetic-private-value');
  assert.equal(requestDecision(target, 'POST', origin), 'mutation');
  assert.equal(requestScope(target, url('/admin/'), origin), 'session');
  blocked.record('mutation', target, 'POST', url('/admin/'), origin);
  blocked.record('external', 'https://outside.invalid/private', 'GET', url('/admin/'), origin);
  assert.deepEqual(blocked.total, {external: 1, mutation: 1, navigation: 0, pageerrors: 0});
  assert.deepEqual(blocked.presentation, {external: 0, mutation: 0, navigation: 0});
  assert.doesNotThrow(() => blocked.assertNoPresentationMutation());
  assert.ok(!JSON.stringify(blocked.evidence).includes('synthetic-private-value'));
});

test('any non-session mutation from the Asaas page still fails the presentation gate', () => {
  const origin = fixture.ISSUERS_BROWSER_URL;
  for (const [target, document] of [[url('/admin/company_asaas'), url('/admin/')], [url('/admin/tasks/table'), url('/admin/company_asaas?invoice=12')]]) {
    const blocked = blockedRequests();
    blocked.record('mutation', url('/admin/tasks/table'), 'POST', url('/admin/'), origin);
    assert.equal(requestDecision(target, 'POST', origin), 'mutation');
    assert.equal(requestScope(target, document, origin), 'presentation');
    blocked.record('mutation', target, 'POST', document, origin);
    assert.equal(blocked.total.mutation, 2, 'the session total was not reset');
    assert.equal(blocked.presentation.mutation, 1);
    assert.throws(() => blocked.assertNoPresentationMutation(), /presentation attempted a non-session mutation/);
  }
});

test('blocked request evidence is bounded, deduplicated and cannot disclose arbitrary paths', () => {
  const blocked = blockedRequests(), origin = fixture.ISSUERS_BROWSER_URL;
  for (let index = 0; index < 50; index++) blocked.record('navigation', url('/private/synthetic-' + index), 'GET', url('/admin/company_asaas'), origin);
  assert.equal(blocked.total.navigation, 50);
  assert.equal(blocked.presentation.navigation, 50);
  assert.deepEqual(blocked.evidence, [{scope: 'presentation', decision: 'navigation', method: 'GET', path: '[other-local]'}]);
  for (const method of ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE']) {
    for (const result of ['mutation', 'external', 'navigation']) blocked.record(result, url('/admin/company_asaas'), method, url('/admin/company_asaas'), origin);
  }
  assert.equal(blocked.evidence.length, 12);
  assert.throws(() => blocked.record('allow', url('/admin'), 'GET', url('/admin'), origin), /invalid request decision/);
});
