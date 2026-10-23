'use strict';
const assert = require('node:assert/strict');

// Wait for real responsive initialization, then open through one real button click.
async function ensureCompanySidebar(page) {
    await page.waitForLoadState('load');
    await page.waitForFunction(() => !document.querySelector('#header > .hide-menu') || innerWidth > 768 || document.body.classList.contains('page-small'));
    const toggle = page.locator('.company-selector [data-company-selector-toggle]');
    await toggle.waitFor({state:'attached'});
    const box = await toggle.boundingBox();
    if (!box || box.x < 0) {
        const sidebarButton = page.locator('#header .company-menu-toggle, #header > .hide-menu');
        await sidebarButton.click();

    }
    await page.waitForFunction(() => {
        const button = document.querySelector('.company-selector [data-company-selector-toggle]');
        if (!button) return false;
        const box = button.getBoundingClientRect();
        return box.width > 0 && box.left >= 0 && box.right <= innerWidth;
    });
}

// Choose an authorized visible submitter. Native form submission performs exactly
// one context POST; callers must not issue a second confirmation request.
async function selectCompanyOption(page, value) {
    const expected = String(value);
    assert.match(expected, /^[1-9]\d*$/, 'company ID is a canonical positive integer');
    const root = page.locator('.company-selector');
    await root.waitFor({ state: 'attached' });
    const choice = page.locator('.company-selector [data-company-choice="' + expected + '"]');
    assert.equal(await choice.count(), 1, 'requested company must exist in the authorized enabled catalog');
    assert.equal(await choice.isEnabled(), true, 'requested company must exist in the authorized enabled catalog');
    await ensureCompanySidebar(page);
    await page.locator('.company-selector [data-company-selector-toggle]').click();
    await choice.waitFor({ state: 'visible' });
    const [response] = await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        choice.click()
    ]);
    assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), expected,
        'server-rendered context matches the one authorized company choice');
    return response;
}
module.exports = { selectCompanyOption, ensureCompanySidebar };
