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
test('overflow diagnostics report dimensions without reading form values or page content', () => {
  const {overflowGeometry} = require('./companies/asaas_settings_real_browser.cjs');
  const element = {
    tagName: 'INPUT', clientWidth: 230, scrollWidth: 230,
    getBoundingClientRect: () => ({left: 332.125, right: 562.125, width: 230, height: 38}),
    matches: selector => selector === '#search_input', closest: () => null,
  };
  for (const key of ['value', 'textContent', 'innerHTML', 'outerHTML', 'href', 'className', 'id']) {
    Object.defineProperty(element, key, {get() { throw new Error('secret-bearing property read'); }});
  }
  const document = {documentElement: {scrollWidth: 562}, querySelectorAll: selector => selector === 'body *' || selector === '#search_input' ? [element] : []};
  const window = {innerWidth: 390, getComputedStyle: () => ({display: 'block', visibility: 'visible', minWidth: 'auto', flexShrink: '1', overflowX: 'clip'})};
  const layout = overflowGeometry(document, window);
  assert.equal(layout.viewport, 390);
  assert.equal(layout.pageWidth, 562);
  assert.deepEqual(layout.offenders[0], {tag: 'input', region: '#search_input', left: 332.13, right: 562.13, width: 230, clientWidth: 230, scrollWidth: 230, display: 'block', minWidth: 'auto', flexShrink: '1', overflowX: 'clip'});
  assert.equal(layout.regions.length, 1);
});
test('real overflow gate covers each tab and preserves viewport assertion and masked evidence', () => {
  const runner = fs.readFileSync(__dirname + '/companies/asaas_settings_real_browser.cjs', 'utf8');
  assert.match(runner, /check\(layout\.pageWidth <= width \+ 1, 'real settings have no page horizontal overflow'\)/);
  assert.ok(runner.includes('input[type="password"], [data-asaas-secret-input], [data-asaas-secret], input[name*="token" i], input[id*="token" i], input[name*="api_key" i]'));
  assert.match(runner, /mask: \[page\.locator/);
  assert.ok(runner.indexOf('layout = await page.evaluate') > runner.indexOf('for (const section of sections)'));
});
test('dynamic scroll initializes once after native ready and regression uses real native code',()=>{
 const moduleSource=fs.readFileSync('modules/asaas/assets/js/settings-tabs.js','utf8');
 assert.match(moduleSource,/wrapper\.className = 'panel-full-width-tabs mtop15 mbot15'/,'dynamic wrapper cannot be double-scanned before its own ready callback');
 assert.match(moduleSource,/root\.jQuery\(function \(\) \{[\s\S]*?wrapper\.classList\.add\('horizontal-scrollable-tabs'\);[\s\S]*?root\.jQuery\(wrapper\)\.horizontalTabs\(\)/);
 const fixtureSource=fs.readFileSync('tests/asaas_native_scroll.browser.cjs','utf8');
 for(const contract of ['assets/js/main.js','native-before-insertion','native-ready-after-insertion','gateway initially hidden','unique native','desktop right arrow hidden','scrollLeft>0','scrollLeft===0','new FormData(form)']) assert.ok(fixtureSource.includes(contract),contract);
 assert.doesNotMatch(fixtureSource,/force:\s*true|scrollLeft\s*=(?!=)|\.fn\.horizontalTabs\s*=/,'native scrolling cannot be mocked or forced');
 const realSource=fs.readFileSync('tests/companies/asaas_settings_real_browser.cjs','utf8');
 for(const phase of ['click real mobile right arrow','await real mobile right scroll','click real mobile left arrow','await real mobile left scroll','390-arrow-failure-masked.png']) assert.ok(realSource.includes(phase));
 const workflow=fs.readFileSync('.github/workflows/deployment-integrity.yml','utf8');
 const focused=workflow.indexOf('node tests/asaas_native_scroll.browser.cjs'),real=workflow.indexOf('bash tests/docker_provision_smoke.sh');
 assert.ok(focused>=0&&real>focused,'native focused regression precedes long provision/BB flow');
});
test('native padding overflow regression preserves spacing and real click coverage',()=>{
 const moduleSource=fs.readFileSync('modules/asaas/assets/js/settings-tabs.js','utf8');
 for(const contract of ['nav.firstElementChild.style.paddingLeft = navStyle.paddingLeft','nav.lastElementChild.style.paddingRight = navStyle.paddingRight',"nav.style.paddingLeft = '0px'","nav.style.paddingRight = '0px'"]) assert.ok(moduleSource.includes(contract),contract);
 const fixtureSource=fs.readFileSync('tests/asaas_native_scroll.browser.cjs','utf8');
 for(const contract of ['column:358,font:16','column:280,font:16','column:358,font:18','real threshold: item widths alone fit','real threshold: native padding creates overflow','native total spacing is preserved','link hit area is unchanged','arrows visible at real overflow',"['authentication','general']"]) assert.ok(fixtureSource.includes(contract),contract);
 assert.doesNotMatch(moduleSource,/\.scroller.*(?:\.show\(|style\.display)/,'Asaas cannot override native arrow visibility');
});
