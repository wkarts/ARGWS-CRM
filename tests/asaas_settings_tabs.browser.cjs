'use strict';
// Synthetic native-CSS/Bootstrap browser coverage. No account, API or production data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.ASAAS_PLAYWRIGHT_MODULE || 'playwright');
const {html} = require('./support/AsaasSettingsFixture.cjs');

(async () => {
  const browser = await chromium.launch({headless: true, ...(process.env.CHROMIUM_BIN ? {executablePath: process.env.CHROMIUM_BIN} : {})});
  const output = path.resolve(process.env.ASAAS_UI_ARTIFACTS || 'artifacts/asaas-settings');
  fs.mkdirSync(output, {recursive: true});
  const errors = [];
  let checks = 0;
  function check(condition, label) { assert.ok(condition, label); checks++; }
  try {
    for (const width of [1280, 390]) {
      const context = await browser.newContext({viewport: {width, height: 900}});
      await context.route('**/*', route => route.abort());
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.setContent(html('prepareFixture("1","1",true);'), {waitUntil: 'load'});
      await page.locator('#gateway-tab').click();
      const initial = await page.locator('#settings-form').evaluate(form => Array.from(new FormData(form).entries()));
      const order = ['authentication', 'security', 'payments', 'webhook', 'resources', 'diagnostics'];
      check(await page.locator('#online_payments_asaas_tab ul.nav-tabs > li > a[data-toggle="tab"]').count() === 6, 'six native tabs');
      for (const id of order) {
        await page.locator('#asaas-settings-tab-' + id).click();
        check(await page.locator('#asaas-settings-panel-' + id).isVisible(), 'selected panel visible: ' + id);
        check(await page.locator('#online_payments_asaas_tab .tab-pane.active').count() === 1, 'one active subpanel');
        check(await page.locator('#asaas-settings-tab-' + id).getAttribute('aria-selected') === 'true', 'selected aria');
        check((await page.locator('#settings-form').getAttribute('action')).endsWith('&active_tab=online_payments_asaas_tab'), 'save retains Asaas');
        await page.screenshot({path: path.join(output, width + '-' + id + '.png'), fullPage: true});
      }
      assert.deepEqual(await page.locator('#settings-form').evaluate(form => Array.from(new FormData(form).entries())), initial); checks++;
      check(await page.locator('#asaas-settings-panel-payments [data-asaas-profile]').count() === 2, 'production and homologation profiles remain separate');
      check(await page.locator('#asaas-settings-panel-webhook #fixture-callback').count() === 1, 'real receiver URL is in webhook panel');
      check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'no page horizontal overflow');
      await page.locator('#asaas-settings-tab-diagnostics').press('Home');
      check(await page.locator('#asaas-settings-tab-authentication').evaluate(el => el === document.activeElement), 'Home moves focus');
      await page.keyboard.press('ArrowLeft');
      check(await page.locator('#asaas-settings-tab-diagnostics').evaluate(el => el === document.activeElement), 'left wraps');
      await page.keyboard.press('ArrowRight');
      check(await page.locator('#asaas-settings-tab-authentication').evaluate(el => el === document.activeElement), 'right wraps');
      await page.keyboard.press('End');
      check(await page.locator('#asaas-settings-tab-diagnostics').evaluate(el => el === document.activeElement), 'End moves focus');
      await page.locator('#asaas-settings-tab-security').focus();
      await page.keyboard.press('Space');
      check(await page.locator('#asaas-settings-panel-security').isVisible(), 'Space activates');
      await page.locator('#asaas-settings-tab-authentication').click();
      const key = page.locator('[name="settings[paymentmethod_asaas_api_key]"]');
      await key.fill('synthetic-key');
      await page.locator('[data-asaas-reveal="asaas-settings-api_key"]').click();
      check(await key.getAttribute('type') === 'text', 'admin action reveals only selected API key');
      check(await page.locator('[name="settings[paymentmethod_asaas_api_key_sandbox]"]').getAttribute('type') === 'password', 'other environment key stays masked');
      await page.locator('#other-tab').click(); await page.locator('#gateway-tab').click();
      check(await key.getAttribute('type') === 'password', 'switching gateways conceals the revealed key');
      await page.locator('#asaas-settings-tab-security').click(); await page.locator('#asaas-settings-tab-authentication').click();
      check(await key.getAttribute('type') === 'password', 'key is concealed when leaving tab');
      await page.locator('#asaas-operation-environment').selectOption('');
      await page.locator('[name="settings[paymentmethod_asaas_profile_production_description]"]').evaluate(input => { input.required = true; input.value = ''; });
      await page.locator('#other-tab').click();
      await page.locator('button[type=submit]').click();
      check(await page.locator('#asaas-settings-panel-authentication').isVisible(), 'invalid field reveals both tab levels');
      check(await page.locator('#asaas-operation-environment').evaluate(el => el === document.activeElement), 'invalid field receives focus');
      await page.locator('[name="settings[paymentmethod_asaas_profile_production_description]"]').evaluate(input => { input.required = false; });
      await page.locator('#asaas-operation-environment').selectOption('1');
      await page.locator('#asaas-settings-tab-webhook').click();
      await page.evaluate(() => {
        const parent = new URL(document.getElementById('settings-form').action).searchParams.get('active_tab');
        prepareFixture('1', '1', true);
        document.querySelector('a[href="#' + parent + '"]').click();
      });
      check(await page.locator('#online_payments_asaas_tab').isVisible(), 'native save-return target restores gateway');
      await page.locator('#other-tab').click(); await page.locator('#gateway-tab').click();
      check(await page.locator('#asaas-settings-panel-authentication').isVisible(), 'switching gateways preserves selected inner pane');
      await page.evaluate(() => module.exports.initialize(document));
      check(await page.locator('#online_payments_asaas_tab ul.nav-tabs > li').count() === 6, 'repeated initialization remains idempotent');
      await page.evaluate(() => prepareFixture('1', '1', true, false)); await page.locator('#gateway-tab').click();
      check(await page.locator('[data-asaas-reveal]').count() === 0, 'new reveal controls do not broaden non-admin access');
      await context.close();
    }
    assert.deepEqual(errors, []);
    console.log('Asaas native tabs Playwright: ' + checks + ' checks passed; desktop/mobile screenshots: ' + output);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
