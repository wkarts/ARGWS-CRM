'use strict';
const { selectCompanyOption } = require('../helpers/company_selector_driver.cjs');
// Actual authenticated CRM/HTTP/MySQL and native assets. No HTML fixture or provider calls.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

function environment(env) {
  assert.match(env.ISSUERS_BROWSER_URL || '', /^http:\/\/127\.0\.0\.1:[1-9]\d{0,4}$/);
  assert.ok(Number(new URL(env.ISSUERS_BROWSER_URL).port) <= 65535, 'disposable loopback port');
  assert.match(env.ISSUERS_BROWSER_DB_CONTAINER || '', /^argws-crm-provision-db-\d+$/);
  assert.match(env.ISSUERS_BROWSER_APP_CONTAINER || '', /^argws-crm-upgrade-\d+$/);
  assert.equal(env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
  for (const key of ['ISSUERS_BROWSER_PASSWORD', 'ISSUERS_BROWSER_DB_USER', 'ISSUERS_BROWSER_DB_PASSWORD']) {
    assert.ok(typeof env[key] === 'string' && env[key].length > 0, 'synthetic CI credential is required: ' + key);
  }
  return {url: env.ISSUERS_BROWSER_URL, db: env.ISSUERS_BROWSER_DB_CONTAINER,
    output: path.join(env.ISSUERS_BROWSER_ARTIFACTS || '/tmp/issuer-browser-evidence', 'asaas-real-settings')};
}
function localRequest(requestUrl, origin) {
  try { return new URL(requestUrl).origin === origin; } catch { return false; }
}
function financialMutation(requestUrl, method) {
  return !['GET', 'HEAD'].includes(method) && /^\/(?:admin\/(?:asaas|company_asaas|settings)(?:\/|$)|asaas\/|company_asaas_callback\/)/.test(new URL(requestUrl).pathname);
}
// Read-only geometry only: never inspect text, form values, arbitrary classes or URLs.
function overflowGeometry(document, window) {
  const width = window.innerWidth;
  const round = number => Math.round(number * 100) / 100;
  const selectors = ['#header', '#header > nav', '#header > .hide-menu', '#header > nav > .tw-flex', '#header .company-selector', '#header .company-selector-form', '#header .bootstrap-select', '#top_search', '#search_input', '#header .mobile-menu', '#wrapper', '#settings-form', '#online_payments_asaas_tab', '#asaas-settings-panel-authentication', '#online_payments_asaas_tab .input-group', '#asaas-api-url-production', '#asaas-api-url-sandbox'];
  const describe = element => {
    const box = element.getBoundingClientRect(), style = window.getComputedStyle(element);
    return {left: round(box.left), right: round(box.right), width: round(box.width), clientWidth: element.clientWidth, scrollWidth: element.scrollWidth,
      display: style.display, minWidth: style.minWidth, flexShrink: style.flexShrink, overflowX: style.overflowX};
  };
  const regions = selectors.flatMap(selector => Array.from(document.querySelectorAll(selector)).slice(0, 2).map(element => ({selector, ...describe(element)})));
  const offenders = Array.from(document.querySelectorAll('body *')).filter(element => {
    const rect = element.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && rect.right > width + 1 && window.getComputedStyle(element).visibility !== 'hidden';
  }).map(element => {
    const tag = element.tagName.toLowerCase();
    const region = selectors.find(selector => element.matches(selector)) || selectors.slice().reverse().find(selector => element.closest(selector)) || '[other]';
    return {tag: ['div','form','input','select','button','span','ul','li','a','fieldset','legend','label','p','section','nav'].includes(tag) ? tag : '[other]', region, ...describe(element)};
  }).sort((a, b) => b.right - a.right).slice(0, 20);
  return {viewport: width, pageWidth: document.documentElement.scrollWidth, regions, offenders};
}
async function run(env = process.env) {
  const config = environment(env);
  const {chromium} = require('playwright');
  function sql(query) {
    return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', config.db, 'mysql', '--protocol=tcp', '--host=127.0.0.1',
      '--user=' + env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', query],
    {env: {...env, MYSQL_PWD: env.ISSUERS_BROWSER_DB_PASSWORD}, encoding: 'utf8'}).trim();
  }
  // The preceding company Asaas browser installs/replays the actual shipped native migration.
  const version = sql("SELECT installed_version FROM tblmodules WHERE module_name='asaas' AND active=1");
  const header = fs.readFileSync(path.resolve(__dirname, '../../modules/asaas/asaas.php'), 'utf8').match(/^Version:\s*(\d\.\d\.\d)\s*$/m);
  assert.ok(header && Number(header[1].replaceAll('.', '')) >= 154 && version === header[1], 'actual native Asaas154+ tracking must already be confirmed');
  assert.equal(sql("SELECT value FROM tbloptions WHERE name='paymentmethod_asaas_profile_schema'"), '1', 'profiles were seeded by the native migration');
  const primary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
  assert.match(primary, /^[1-9]\d*$/);
  const staff = sql("SELECT staffid FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1");
  assert.match(staff, /^[1-9]\d*$/);
  const hashes = (profilesOnly = false) => sql("SELECT CONCAT(name,':',SHA2(value,256)) FROM tbloptions WHERE name LIKE 'paymentmethod_asaas_" + (profilesOnly ? 'profile_' : '') + "%' ORDER BY name");
  const profilesBefore = hashes(true);
  let settingsBefore = null;
  fs.mkdirSync(config.output, {recursive: true});
  let browser;
  const errors = [], mutations = [];
  let externalRequestsBlocked = 0;
  let checks = 0, phase = 'launch browser', currentWidth = null, currentSection = null, layout = null;
  function check(condition, message) { phase = message; assert.ok(condition, message); checks++; }
  async function submit(page, button) {
    await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded', timeout: 15000}), button.click()]);
  }
  try {
    browser = await chromium.launch({headless: true});
    for (const width of [1280, 390]) {
      currentWidth = width; currentSection = null; layout = null;
      const context = await browser.newContext({viewport: {width, height: 900}});
      context.setDefaultTimeout(10000);
      await context.route('**/*', route => {
        const request = route.request();
        if (!localRequest(request.url(), config.url)) { externalRequestsBlocked++; return route.abort(); }
        if (financialMutation(request.url(), request.method())) { mutations.push(request.method()); return route.abort(); }
        return route.continue();
      });
      try {
        const page = await context.newPage();
        const nativeFonts = [];
        page.on('response', response => { if (response.request().resourceType() === 'font' && response.url().includes('/assets/plugins/font-awesome/webfonts/')) nativeFonts.push(response.status()); });
        page.on('pageerror', error => errors.push(error.message));
        check((await page.goto(config.url + '/admin/authentication')).status() === 200, 'actual login is available');
        await page.locator('input[name=email]').fill('admin-ci@example.invalid');
        await page.locator('input[name=password]').fill(env.ISSUERS_BROWSER_PASSWORD);
        await submit(page, page.locator('button[type=submit]'));
        check(!page.url().includes('/authentication'), 'synthetic administrator authenticated');
        await page.goto(config.url + '/admin/');
        await selectCompanyOption(page, primary);

        const response = await page.goto(config.url + '/admin/settings?group=payment_gateways');
        check(response.status() === 200, 'actual primary payment settings render');
        await page.locator('#header').waitFor();
        check(await page.locator('.company-selector').getAttribute('data-company-current') === primary, 'native header confirms the selected primary company');
        check(await page.locator('script[src*="assets/builds/common"]').count() > 0, 'real common asset bundle is loaded');
        const pane = page.locator('#online_payments_asaas_tab');
        await page.waitForFunction(() => document.getElementById('online_payments_asaas_tab')?.getAttribute('data-asaas-tabs') === 'ready', null, {timeout: 10000});
        await page.locator('a[data-toggle=tab][href="#online_payments_asaas_tab"]').click();
        check(await pane.isVisible(), 'active primary Asaas gateway is visible');
        check(await page.evaluate(() => typeof window.Waypoint === 'function' && typeof window.delay === 'function' && typeof jQuery.fn.tab === 'function' && typeof jQuery.fn.horizontalTabs === 'function'), 'all native tab dependencies are loaded');
        check(await pane.locator('[data-asaas-settings-admin="1"][data-asaas-profile-ready="1"]').count() === 1, 'real server metadata confirms admin and migration readiness');
        if (settingsBefore === null) settingsBefore = hashes();
        const productionKey = pane.locator('[name="settings[paymentmethod_asaas_api_key]"]');
        const sandboxKey = pane.locator('[name="settings[paymentmethod_asaas_api_key_sandbox]"]');
        check(await productionKey.getAttribute('type') === 'password' && await sandboxKey.getAttribute('type') === 'password', 'both API credentials are masked before screenshots');
        const tabs = ['Geral', 'Autenticação', 'Webhook e operações'];
        const sections = ['general', 'authentication', 'operations'];
        check(JSON.stringify(await pane.locator('.nav-tabs-horizontal > li > a').allTextContents()) === JSON.stringify(tabs), 'three native tabs use PT-BR labels in the original order');
        const labels = (await pane.locator('label').allTextContents()).map(label => label.trim());
        check(labels.includes('Chave da API — Produção') && labels.includes('Chave da API — Homologação'), 'real API labels distinguish production and homologation');
        check(!labels.some(label => /profile_(?:production|sandbox)_|paymentmethod_asaas_|settings_paymentmethod_/.test(label)), 'real labels never expose synthetic fixture/translation keys');
        const selector = page.locator('#asaas-operation-environment');
        const selected = await selector.inputValue();
        check(['0', '1'].includes(selected), 'single persisted environment selector is valid');
        check(await pane.locator('[name="settings[paymentmethod_asaas_sandbox]"]').count() === 1, 'there is exactly one active-environment selector');
        for (const environment of ['production', 'sandbox']) {
          const field = page.locator('#asaas-api-url-' + environment);
          check(await field.inputValue() === (environment === 'production' ? 'https://api.asaas.com/v3' : 'https://api-sandbox.asaas.com/v3'), 'actual SDK API URL: ' + environment);
          check(await field.getAttribute('readonly') !== null, 'API URL is read-only: ' + environment);
          check(await pane.locator('#asaas-settings-panel-general [data-asaas-profile="' + environment + '"]').count() === 1, 'real independent payment profile: ' + environment);
        }
        check(await pane.evaluate(el => el.querySelector('[data-asaas-webhook-admin-url]').getBoundingClientRect().bottom <= el.querySelector('.horizontal-scrollable-tabs').getBoundingClientRect().top), 'native heading and separator keep management link clear of tabs');
        phase = 'await native tab fonts and local scroll controller';
        await page.evaluate(() => document.fonts.ready);
        await pane.locator('[data-asaas-scroll-ready="1"]').waitFor();
        const tabGeometry = () => pane.evaluate(el => {
          const nav = el.querySelector('.nav-tabs-horizontal');
          const arrow = direction => {
            const item=el.querySelector('.arrow-'+direction), box=item.getBoundingClientRect();
            const handlers=window.jQuery?._data(item,'events')?.click || [];
            return {visible:box.width>0&&box.height>0, disabled:item.classList.contains('disabled'), nativeHandlers:handlers.filter(h=>h.namespace==='horizontalTabs').length};
          };
          return {scrollWidth:nav.scrollWidth, clientWidth:nav.clientWidth, scrollLeft:nav.scrollLeft, wrapperWidth:el.querySelector('.horizontal-scrollable-tabs').getBoundingClientRect().width, itemWidths:Array.from(nav.children, item=>item.getBoundingClientRect().width), edgePadding:[getComputedStyle(nav.firstElementChild).paddingLeft,getComputedStyle(nav.lastElementChild).paddingRight].map(parseFloat), left:arrow('left'), right:arrow('right')};
        });
        if (width === 390 && await pane.locator('.nav-tabs-horizontal').evaluate(el => el.scrollWidth > el.clientWidth)) {
          const scrollable = pane.locator('.nav-tabs-horizontal');
          check(await scrollable.evaluate(el => el.scrollWidth >= el.clientWidth), 'real mobile tabs exercise overflow');
          try {
            phase = 'click real mobile right arrow'; layout = await tabGeometry();
            await pane.locator('.arrow-right').click();
            phase = 'await real mobile right scroll'; layout = await tabGeometry();
            await page.waitForFunction(() => document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft > 0, null, {timeout: 10000});
            phase = 'click real mobile left arrow'; layout = await tabGeometry();
            await pane.locator('.arrow-left').click();
            phase = 'await real mobile left scroll'; layout = await tabGeometry();
            await page.waitForFunction(() => document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft === 0, null, {timeout: 10000});
            check(await scrollable.evaluate(el => el.scrollLeft === 0), 'real native arrows scroll right and return left');
          } catch(error) {
            layout = await tabGeometry();
            await page.screenshot({path:path.join(config.output,'390-arrow-failure-masked.png'),fullPage:true,
              mask:[page.locator('input,textarea,select,pre,code,#header')]}).catch(()=>{});
            throw error;
          }
        }
        const iconsLoaded = await page.evaluate(async () => {
          await document.fonts.ready;
          const icons = Array.from(document.querySelectorAll('#online_payments_asaas_tab .scroller > i'));
          return icons.length === 2 && icons.every(icon => {
            const glyph = getComputedStyle(icon, '::before').content;
            return glyph !== 'none' && glyph !== 'normal' && glyph !== '""' && /Font Awesome/.test(getComputedStyle(icon).fontFamily);
          }) && Array.from(document.fonts).some(font => /Font Awesome/.test(font.family) && font.status === 'loaded');
        });
        check(iconsLoaded && nativeFonts.some(status => status === 200), 'native arrow glyphs and local Font Awesome webfont are loaded');
        const formData = () => page.locator('#settings-form').evaluate(form => JSON.stringify(Array.from(new FormData(form).entries()).filter(([name]) => name.startsWith('settings[paymentmethod_asaas_'))));
        const before = await formData();
        const completeSettingsBefore = await page.locator('#settings-form').evaluate(form => JSON.stringify(Array.from(new FormData(form).entries())));
        for (const section of sections) {
          currentSection = section;
          const operationsResponse = section === 'operations' ? page.waitForResponse(response => {
            const url = new URL(response.url());
            return url.origin === config.url && url.pathname.endsWith('/admin/asaas') && url.searchParams.get('panel') === '1' && response.request().method() === 'GET';
          }) : null;
          await page.locator('#asaas-settings-tab-' + section).click();
          if (operationsResponse) {
            check((await operationsResponse).status() === 200, 'real webhook partial GET succeeds');
            await page.locator('#asaas-settings-panel-operations [data-asaas-operation-content]').waitFor({state: 'visible'});
            await page.locator('#asaas-settings-panel-operations').getByRole('heading', {name: 'Webhooks de pagamento Asaas', exact: true}).waitFor();
            check(await page.locator('#settings-form form').count() === 0, 'real operations never nests forms in settings');
            check(await page.locator('#settings-form').evaluate(form => JSON.stringify(Array.from(new FormData(form).entries()))) === completeSettingsBefore, 'operation CSRF and IDs never leak into real settings POST');
            check(await page.locator('#asaas-settings-panel-operations button[type=submit]').evaluateAll(buttons => buttons.length > 0 && buttons.every(button => button.form && !button.form.closest('#settings-form'))), 'real action buttons belong to independent forms');
          }
          check(await page.locator('#asaas-settings-panel-' + section).isVisible(), 'real tab is visible: ' + section);
          check(await pane.locator(':scope > .tab-content > .tab-pane.active').count() === 1, 'one inner panel remains active');
          check(await page.locator('#asaas-settings-tab-' + section).getAttribute('aria-selected') === 'true' && await page.locator('#asaas-settings-tab-' + section).getAttribute('tabindex') === '0', 'selected tab exposes native accessibility state');
          check(await formData() === before, 'navigation preserves native Asaas form controls');
          check(new URL(await page.locator('#settings-form').getAttribute('action')).searchParams.get('active_tab') === 'online_payments_asaas_tab', 'native save target preserves the parent gateway');
          layout = await page.evaluate("(" + overflowGeometry.toString() + ")(document, window)");
          if (layout.pageWidth > width + 1) {
            fs.writeFileSync(path.join(config.output, width + '-' + section + '-overflow.json'), JSON.stringify(layout, null, 2));
            await page.screenshot({path: path.join(config.output, width + '-' + section + '-overflow.png'), fullPage: true, mask: [page.locator('input[type="password"], [data-asaas-secret-input], [data-asaas-secret], input[name*="token" i], input[id*="token" i], input[name*="api_key" i]')]});
          }
          check(layout.pageWidth <= width + 1, 'real settings have no page horizontal overflow');
          await page.screenshot({path: path.join(config.output, width + '-' + section + '.png'), fullPage: true});
        }
        const operationPanel = page.locator('#asaas-settings-panel-operations');
        for (const destination of [
          {button: 'Transações e conciliação', suffix: '/admin/asaas/payment_origins', heading: 'Asaas: origem e pendências', artifact: 'origins'},
          {button: 'Webhooks e eventos', suffix: '/admin/asaas', heading: 'Webhooks de pagamento Asaas', artifact: 'webhooks'},
        ]) {
          const responsePromise = page.waitForResponse(response => {
            const url = new URL(response.url());
            return url.origin === config.url && url.pathname.endsWith(destination.suffix) && url.searchParams.get('panel') === '1' && response.request().method() === 'GET';
          });
          await operationPanel.getByRole('button', {name: destination.button, exact: true}).click();
          check((await responsePromise).status() === 200, 'real read-only operation GET: ' + destination.artifact);
          await operationPanel.getByRole('heading', {name: destination.heading, exact: true}).waitFor();
          await page.waitForFunction(() => !document.getElementById('asaas-settings-panel-operations').hasAttribute('aria-busy'));
          check(await page.locator('#settings-form form').count() === 0, 'no nested forms after real navigation: ' + destination.artifact);
          check(await page.locator('#settings-form').evaluate(form => JSON.stringify(Array.from(new FormData(form).entries()))) === completeSettingsBefore, 'complete settings payload unchanged: ' + destination.artifact);
          check(await selector.inputValue() === selected, 'operation navigation leaves environment unchanged');
          check(hashes(true) === profilesBefore && hashes() === settingsBefore, 'real GET leaves stored settings and keys unchanged');
          await page.screenshot({path: path.join(config.output, width + '-operations-' + destination.artifact + '.png'), fullPage: true,
            mask: [page.locator('input[type="password"], [data-asaas-secret-input], input[name*="token" i], input[id*="token" i], input[name*="api_key" i]')]});
        }
        await page.locator('#asaas-settings-tab-authentication').click();
        check(await page.locator('#asaas-settings-panel-authentication [role=status]').filter({hasText: 'A URL pública configurada precisa ser HTTPS válida.'}).count() === 1,
          'HTTP CI origin reports the actual HTTPS requirement without fabricating a callback');
        check(await pane.locator('input[id^="asaas-settings-webhook-url-"]').count() === 0, 'invalid canonical base never renders a webhook URL');
        await page.locator('#asaas-settings-tab-authentication').press('Home');
        check(await page.locator('#asaas-settings-tab-general').evaluate(el => el === document.activeElement), 'Home keyboard navigation focuses first tab');
        await page.keyboard.press('ArrowLeft');
        check(await page.locator('#asaas-settings-tab-operations').evaluate(el => el === document.activeElement), 'keyboard navigation wraps');
        await page.keyboard.press('Home');
        check(await productionKey.getAttribute('type') === 'password' && await sandboxKey.getAttribute('type') === 'password', 'both API credentials stay masked through navigation');
        // Unsaved synthetic browser input only: no stored key is read, changed or sent to a provider.
        await page.locator('#asaas-settings-tab-authentication').click();
        await page.locator('#asaas-profile-authentication-production-tab').click();
        await productionKey.fill('synthetic-unsaved-key');
        const keyId = await productionKey.getAttribute('id');
        await pane.locator('[data-asaas-reveal="' + keyId + '"]').click();
        check(await productionKey.getAttribute('type') === 'text' && await sandboxKey.getAttribute('type') === 'password', 'explicit reveal affects only the chosen environment');
        await page.locator('#asaas-settings-tab-general').click();
        check(await productionKey.getAttribute('type') === 'password', 'leaving the tab conceals the key again');
        await page.locator('#asaas-settings-tab-authentication').click();
        await selector.selectOption('');
        await page.locator('a[data-toggle=tab][href="#payment_modes_general"]').click();
        check(!(await selector.evaluate(el => el.reportValidity())), 'native invalid selection is rejected without submitting');
        check(await pane.isVisible() && await page.locator('#asaas-settings-panel-authentication').isVisible(), 'validation reveals the real parent and child tabs');
        check(await selector.evaluate(el => el === document.activeElement), 'native validation focuses the revealed environment selector');
        await selector.selectOption(selected);
        page.once('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
        await page.reload({waitUntil: 'domcontentloaded'});
        await page.locator('a[data-toggle=tab][href="#online_payments_asaas_tab"]').click();
        check(await productionKey.inputValue() !== 'synthetic-unsaved-key', 'reload discards the unsaved synthetic input');
      } finally { await context.close(); }
    }
    check(errors.length === 0, 'real settings emitted no pageerrors');
    check(mutations.length === 0, 'presentation test attempted no settings/financial mutation');
    check(hashes(true) === profilesBefore, 'navigation never rewrites either persisted environment profile');
    check(hashes() === settingsBefore, 'navigation/reveal/reload never persist any API key or environment selector change');
    fs.writeFileSync(path.join(config.output, 'coverage.json'), JSON.stringify({surface: 'authenticated real CRM settings', widths: [1280, 390], checks,
      nativeVersion: version, screenshots: 10, webhook: 'HTTP canonical base correctly rejected; positive HTTPS browser case not covered', externalBrowserRequestsBlocked: externalRequestsBlocked, financialMutationAttempts: mutations.length}, null, 2));
    console.log('Asaas real authenticated settings: ' + checks + ' checks passed; native PT-BR/URLs/masking/tabs/keyboard/mobile screenshots. HTTP callback base correctly refused; no positive HTTPS browser or provider claim.');
  } catch (error) {
    // Only our fixed step labels/counts leave the browser; raw errors can carry secret values.
    console.error(JSON.stringify({test: 'asaas-real-settings', phase, checks, width: currentWidth, section: currentSection, layout, pageerrors: errors.length, blockedMutations: mutations.length}));
    throw error;
  } finally {
    if (browser) await browser.close();
  }
}
module.exports = {environment, localRequest, financialMutation, overflowGeometry};
if (require.main === module) run().catch(() => {
  // Playwright failures may include field values, CSRF/context URLs or browser credentials.
  console.error('Asaas real settings browser failed. Inspect the sanitized CI diagnostics; no secret-bearing browser state was logged.');
  process.exitCode = 1;
});
