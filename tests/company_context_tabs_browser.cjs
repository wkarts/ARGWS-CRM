const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const keys = ['auth', 'environment', 'agreement', 'account', 'billing', 'remittance', 'return', 'webhook'];
const shellTail = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_tail.php'), 'utf8');
const bbView = fs.readFileSync(path.join(root, 'modules/banco_brasil/views/index.php'), 'utf8');
const tabsScript = path.join(root, 'assets/js/native-admin-tabs.js');
const bootstrapCss = path.join(root, 'assets/plugins/bootstrap/css/bootstrap.min.css');

assert.match(shellTail, /assets\/js\/native-admin-tabs\.js/);
assert.equal((bbView.match(/name="_company_context"/g) || []).length, 0, 'BB view delegates company context injection to the native form helper');
assert.ok(bbView.includes('id="bb-tab-<?php echo $key; ?>"'), 'BB tabs have stable IDs');
for (const key of keys) {
    assert.ok(bbView.includes(`aria-labelledby="bb-tab-${key}"`), `BB panel ${key} is associated with its tab`);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for(const bodyClass of ['company-context-shell','app admin native-company-admin'])for(const width of [1280,768,390]){
        const page = await browser.newPage({viewport:{width,height:850}});
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        const links = keys.map((key, index) => `<li role="presentation" class="${index === 0 ? 'active' : ''}"><a id="bb-tab-${key}" href="#bb-${key}" aria-controls="bb-${key}" aria-selected="${index === 0}" tabindex="${index === 0 ? '0' : '-1'}" role="tab" data-toggle="tab">${key}</a></li>`).join('');
        const panels = keys.map((key, index) => `<div role="tabpanel" class="tab-pane${index === 0 ? ' active' : ''}" id="bb-${key}" aria-labelledby="bb-tab-${key}" aria-hidden="${index !== 0}" tabindex="0">${key} panel</div>`).join('');
        await page.setContent(`<body class="${bodyClass}"><div class="panel-body"><ul id="bb-tabs" class="nav nav-tabs" role="tablist" aria-label="Configurações Banco do Brasil">${links}</ul><div class="tab-content">${panels}</div></div></body>`);
        await page.addStyleTag({ path: bootstrapCss });
        await page.addScriptTag({ path: tabsScript });

        await page.locator('#bb-tab-webhook').click();
        assert.equal(await page.locator('#bb-tab-webhook').getAttribute('aria-selected'), 'true');
        assert.equal(await page.locator('#bb-webhook').getAttribute('aria-hidden'), 'false');
        assert.equal(await page.locator('#bb-webhook').evaluate(element => getComputedStyle(element).display), 'block');
        assert.equal(await page.locator('#bb-tabs li.active').count(), 1);

        await page.locator('#bb-tab-webhook').focus();
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'bb-tab-auth', 'right arrow wraps and moves focus');
        assert.equal(await page.locator('#bb-auth').getAttribute('aria-hidden'), 'false');
        await page.keyboard.press('End');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'bb-tab-webhook', 'End selects the final tab');
        await page.keyboard.press('Home');
        assert.equal(await page.evaluate(() => document.activeElement.id), 'bb-tab-auth', 'Home selects the first tab');
        await page.evaluate(() => { window.location.hash = '#bb-remittance'; });
        await page.waitForFunction(() => document.getElementById('bb-tab-remittance').getAttribute('aria-selected') === 'true');
        assert.equal(await page.locator('#bb-remittance').getAttribute('aria-hidden'), 'false', 'POST redirect fragment opens the remittance tab');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true,'tabs remain within '+width+'px viewport');
        assert.deepEqual(pageErrors, []);
        await page.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
