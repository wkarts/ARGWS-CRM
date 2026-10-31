'use strict';
// Guards only. These unit tests do not claim to execute the authenticated browser integration.
const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const {NATIVE_TAB_ASSETS, nativeTabAssetFacts, environment, requestDecision, readOnlyQuery, safePath, requestScope, bodyCategory, responseFacts, blockedRequests, run} = require('./companies/asaas_preferences_real_browser.cjs');
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
  for (const pathname of ['/admin/authentication', '/admin/authentication/admin', '/admin', '/admin/', '/admin/settings/asaas', '/admin/settings/asaas?invoice=12', '/admin/settings/asaas#company-asaas-profile-production', '/assets/js/native-admin-tabs.js?v=4.0.4', '/assets/builds/vendor-admin.css', '/assets/plugins/inter/Inter-Regular.woff2', '/uploads/company/synthetic-logo.png']) {
    assert.equal(decision(pathname), 'allow', pathname);
    assert.equal(decision(pathname, 'HEAD'), 'allow', pathname);
  }
  assert.equal(decision('/admin/company_context'), 'navigation', 'removed parallel company page is never an allowed landing route');
});

test('blocks all external origins including both providers, another port and userinfo', () => {
  for (const target of ['https://api.asaas.com/v3', 'https://api-sandbox.asaas.com/v3', 'http://127.0.0.1:48322/admin/settings/asaas', 'https://127.0.0.1:48321/admin', 'http://127.0.0.1:48321.evil.invalid/admin', 'http://user:secret@127.0.0.1:48321/admin', 'invalid', 'data:text/html,synthetic', 'file:///tmp/synthetic.html']) {
    assert.equal(requestDecision(target, 'GET', fixture.ISSUERS_BROWSER_URL), 'external');
  }
});

test('requires a temporary exact login/context POST permit', () => {
  for (const pathname of ['/admin/authentication', '/admin/authentication/admin', '/admin/issuers/select']) {
    assert.equal(decision(pathname, 'POST'), 'mutation');
    assert.equal(decision(pathname, 'POST', pathname), 'allow');
    assert.equal(decision(pathname, 'PATCH', pathname), 'mutation');
    assert.equal(decision(pathname + '/extra', 'POST', pathname), 'mutation');
  }
  assert.equal(decision('/admin/issuers/select', 'POST', '/admin/authentication'), 'mutation');
  assert.equal(decision('/admin/company_context/select', 'POST', '/admin/company_context/select'), 'mutation', 'removed page cannot authorize company selection');
});

test('cannot grant write permission to financial/settings routes by passing their path', () => {
  for (const pathname of ['/admin/settings/asaas', '/admin/settings', '/admin/asaas/rotate_integration_token', '/admin/company_invoices', '/asaas/gateways/callback/integration/synthetic', '/company_asaas_callback/integration/synthetic']) {
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE']) {
      assert.equal(decision(pathname, method, pathname), 'mutation');
      assert.equal(decision(pathname, method, '/admin/authentication'), 'mutation');
    }
  }
});

test('blocks non-GET method overrides and ambiguous paths before they reach CRM', () => {
  for (const pathname of ['/admin/settings/asaas?environment=production', '/admin/settings/asaas?action=issue', '/admin/settings/asaas?_method=GET', '/ADMIN/company_asaas', '/admin//company_asaas', '/admin/%63ompany_asaas', '/admin/settings/asaas/', '/admin/settings/asaas/issue']) {
    assert.equal(decision(pathname, 'POST'), 'mutation');
  }
  for (const pathname of ['/admin/%63ompany_asaas', '/admin/settings/asaas/issue', '/admin/settings/asaas/', '/admin//company_asaas', '/ADMIN/company_asaas']) assert.equal(decision(pathname), 'navigation');
});

test('GET cannot invoke provider callback, cron, module mutation or uploaded PHP', () => {
  for (const pathname of ['/cron/index', '/company_asaas_callback/integration/synthetic', '/admin/asaas/rotate_integration_token', '/admin/modules/activate/asaas', '/uploads/synthetic.php', '/modules/asaas/run.php', '/assets/script.php.js/execute', '/admin/issuers/select']) {
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
  assert.equal(safePath(url('/admin/settings/asaas?token=' + secret + '#' + secret), fixture.ISSUERS_BROWSER_URL), '/admin/settings/asaas');
  assert.equal(safePath(url('/private/' + secret), fixture.ISSUERS_BROWSER_URL), '[other-local]');
  assert.equal(safePath('https://outside.invalid/' + secret, fixture.ISSUERS_BROWSER_URL), '[external]');
  assert.equal(safePath('http://user:' + secret + '@127.0.0.1:48321/admin', fixture.ISSUERS_BROWSER_URL), '[external]');
  assert.equal(bodyCategory('Call to undefined function _l() ' + secret), 'translation-helper-missing');
  assert.equal(bodyCategory('Histórico de reembolso indisponível para conferência ' + secret), 'refund-history-unavailable');
  assert.equal(bodyCategory(secret), 'unclassified');
  const previous = {url: () => url('/private/' + secret), redirectedFrom: () => null};
  const request = {method: () => 'GET', redirectedFrom: () => previous};
  const response = {status: () => 500, url: () => url('/admin/settings/asaas?token=' + secret), request: () => request,
    headers: () => ({location: '/admin/authentication?token=' + secret}), text: async () => 'Call to undefined function _l() ' + secret};
  const facts = await responseFacts(response, fixture.ISSUERS_BROWSER_URL);
  assert.deepEqual(facts, {status: 500, path: '/admin/settings/asaas', method: 'GET', redirects: ['[other-local]'], location: '/admin/authentication', body: 'translation-helper-missing'});
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
  for (const [target, document] of [[url('/admin/settings/asaas'), url('/admin/')], [url('/admin/tasks/table'), url('/admin/settings/asaas?invoice=12')]]) {
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
  for (let index = 0; index < 50; index++) blocked.record('navigation', url('/private/synthetic-' + index), 'GET', url('/admin/settings/asaas'), origin);
  assert.equal(blocked.total.navigation, 50);
  assert.equal(blocked.presentation.navigation, 50);
  assert.deepEqual(blocked.evidence, [{scope: 'presentation', decision: 'navigation', method: 'GET', path: '[other-local]'}]);
  for (const method of ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE']) {
    for (const result of ['mutation', 'external', 'navigation']) blocked.record(result, url('/admin/settings/asaas'), method, url('/admin/settings/asaas'), origin);
  }
  assert.equal(blocked.evidence.length, 12);
  assert.throws(() => blocked.record('allow', url('/admin'), 'GET', url('/admin'), origin), /invalid request decision/);
});

test('direct company choice is preauthorized once before its native POST and cleared after navigation', async () => {
  const {withNativeSessionPermit,consumeSessionDecision}=require('./companies/asaas_preferences_real_browser.cjs');
  const permit={path:null}, events=[];
  const form={count:async()=>1,getAttribute:async name=>name==='action'?url('/admin/issuers/select'):'post'};
  assert.equal(consumeSessionDecision(url('/admin/issuers/select'),'POST',fixture.ISSUERS_BROWSER_URL,permit),'mutation');
  const result=await withNativeSessionPermit(form,fixture.ISSUERS_BROWSER_URL,permit,async()=>{
    events.push('genuine choice click');
    assert.equal(permit.path,'/admin/issuers/select','permit precedes submitter click');
    assert.equal(consumeSessionDecision(url('/admin/settings/asaas'),'POST',fixture.ISSUERS_BROWSER_URL,permit),'mutation');
    assert.equal(consumeSessionDecision('https://outside.invalid/admin/issuers/select','POST',fixture.ISSUERS_BROWSER_URL,permit),'external');
    assert.equal(consumeSessionDecision(url('/admin/issuers/select'),'POST',fixture.ISSUERS_BROWSER_URL,permit),'allow');
    assert.equal(consumeSessionDecision(url('/admin/issuers/select'),'POST',fixture.ISSUERS_BROWSER_URL,permit),'mutation','second POST is refused before navigation completes');
    events.push('GET navigation'); return 'response';
  });
  assert.equal(result,'response');assert.equal(permit.path,null);assert.deepEqual(events,['genuine choice click','GET navigation']);
  const source=fs.readFileSync(__dirname+'/companies/asaas_preferences_real_browser.cjs','utf8');
  assert.match(source,/submitSession\(page\.locator\('\.company-selector-form'\), \(\) => selectCompanyOption\(page, owner\.issuer\)\)/);
  assert.doesNotMatch(source,/await selectCompanyOption\(page, owner\.issuer\);\s*await submitSession/);
});
test('session permit rejects wrong forms and is revoked when actual interaction fails', async () => {
  const {withNativeSessionPermit}=require('./companies/asaas_preferences_real_browser.cjs');
  for(const [action,method,count] of [[url('/admin/settings/asaas'),'post',1],['https://outside.invalid/admin/issuers/select','post',1],[url('/admin/issuers/select?override=1'),'post',1],[url('/admin/issuers/select'),'get',1],[url('/admin/issuers/select'),'post',2]]) {
    const permit={path:null},form={count:async()=>count,getAttribute:async name=>name==='action'?action:method};
    await assert.rejects(()=>withNativeSessionPermit(form,fixture.ISSUERS_BROWSER_URL,permit,async()=>{throw Error('must not click');}));
    assert.equal(permit.path,null);
  }
  const permit={path:null},form={count:async()=>1,getAttribute:async name=>name==='action'?url('/admin/issuers/select'):'post'};
  await assert.rejects(()=>withNativeSessionPermit(form,fixture.ISSUERS_BROWSER_URL,permit,async()=>{throw Error('native choice failed');}),/native choice failed/);
  assert.equal(permit.path,null);
});
test('all executable browser suites use immediate choice without a second helper submission', () => {
  const path=require('node:path');
  const files=dir=>fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?files(path.join(dir,entry.name)):[path.join(dir,entry.name)]);
  const formSubmissions=[];
  for(const file of files(__dirname).filter(file=>file.endsWith('.cjs')&&!file.endsWith('.test.cjs'))) {
    const source=fs.readFileSync(file,'utf8');
    for(const match of source.matchAll(/await\s+\w+\([^;\n]*\.locator\(['"]\.company-selector-form['"]\)[^;\n]*\);/g)) {
      formSubmissions.push([path.relative(__dirname,file).split(path.sep).join('/'),match[0]]);
    }
  }
  assert.deepEqual(formSubmissions,[['companies/asaas_preferences_real_browser.cjs',"await submitSession(page.locator('.company-selector-form'), () => selectCompanyOption(page, owner.issuer));"]], 'the only form helper is the permit wrapper around the genuine choice, never a second confirmation');
  const purchases=fs.readFileSync(__dirname+'/companies/purchases_browser.cjs','utf8');
  assert.match(purchases,/await selectCompanyOption\(p, String\(issuer\)\);await p\.waitForLoadState\('networkidle'\)/,'existing post-navigation settling is preserved');
});


test('real preferences asset contract matches native layout before expensive integration', () => {
  const head=fs.readFileSync('application/views/admin/includes/native_company_layout_head.php','utf8');
  const tail=fs.readFileSync('application/views/admin/includes/native_company_layout_tail.php','utf8');
  assert.ok(head.includes("'"+NATIVE_TAB_ASSETS.css+"'"));
  assert.ok(tail.includes("'"+NATIVE_TAB_ASSETS.script+"'"));
  assert.ok(!head.includes("'assets/plugins/bootstrap/css/bootstrap.min.css'"),'no obsolete standalone CSS assumption');
  const css=fs.readFileSync(NATIVE_TAB_ASSETS.css,'utf8');
  assert.match(css,/\.tab-content>\.tab-pane\{display:none\}/);
  assert.match(css,/\.tab-content>\.active\{display:block\}/);
  assert.ok(css.includes('.nav-tabs'));
  assert.equal(decision('/'+NATIVE_TAB_ASSETS.css+'?v=synthetic'),'allow');
  assert.equal(decision('/'+NATIVE_TAB_ASSETS.script+'?v=synthetic'),'allow');
  const workflow=fs.readFileSync('.github/workflows/deployment-integrity.yml','utf8');
  const preflight=workflow.indexOf('node --test tests/asaas_preferences_real_browser.test.cjs');
  const integration=workflow.indexOf('bash tests/docker_provision_smoke.sh');
  assert.ok(preflight>=0 && integration>preflight,'native asset contract runs before Docker integration');
});

test('native assets probe fails closed on absent CSS, inaccessible rules or missing plugin', () => {
  const rules=[{selectorText:'.nav-tabs > li',style:{}},{selectorText:'.tab-content > .tab-pane',style:{display:'none'}},{selectorText:'.tab-content > .active',style:{display:'block'}}];
  const doc=(links=[{sheet:{cssRules:rules}}],scripts=[{}])=>({querySelectorAll:selector=>selector.startsWith('link')?links:scripts});
  const jquery={fn:{tab(){}}};
  assert.deepEqual(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc(),jquery),{stylesheet:true,tabStyles:true,plugin:true});
  assert.equal(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc([]),jquery).stylesheet,false);
  assert.equal(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc([{sheet:{cssRules:[]}}]),jquery).tabStyles,false);
  assert.equal(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc(),{}).plugin,false);
  assert.equal(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc(undefined,[]),jquery).plugin,false);
  const unreadable={get sheet(){throw new Error('synthetic CSSOM rejection');}};
  assert.deepEqual(nativeTabAssetFacts(NATIVE_TAB_ASSETS,doc([unreadable]),jquery),{stylesheet:false,tabStyles:false,plugin:true});
});
