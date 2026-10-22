'use strict';
const assert = require('node:assert/strict');

// Wait for real responsive initialization, then open through one real button click.
async function ensureCompanySidebar(page) {
    await page.waitForLoadState('load');
    await page.waitForFunction(() => !document.querySelector('#header > .hide-menu') || innerWidth > 768 || document.body.classList.contains('page-small'));
    const toggle = page.locator('.company-selector .bootstrap-select > .dropdown-toggle');
    await toggle.waitFor({state:'attached'});
    const box = await toggle.boundingBox();
    if (!box || box.x < 0) {
        const sidebarButton = page.locator('#header .company-menu-toggle, #header > .hide-menu');
        await sidebarButton.click();

    }
    await page.waitForFunction(() => {
        const button = document.querySelector('.company-selector .bootstrap-select > .dropdown-toggle');
        if (!button) return false;
        const box = button.getBoundingClientRect();
        return box.width > 0 && box.left >= 0 && box.right <= innerWidth;
    });
}

// Choose through the rendered sidebar dropdown. The caller still performs the
// separate confirmation POST and retains every CSRF/context/navigation assertion.
async function selectCompanyOption(page, value) {
    const expected = String(value);
    const select = page.locator('#company-context-select');
    await select.waitFor({ state: 'attached' });
    const options = await select.locator('option').evaluateAll(nodes => nodes.map(option => ({ value: option.value, label: option.textContent.trim(), disabled: option.disabled })));
    const option = options.find(entry => entry.value === expected);
    assert.ok(option && !option.disabled, 'requested company must exist in the authorized enabled catalog');
    const toggle = page.locator('.company-selector .bootstrap-select > .dropdown-toggle');
    await toggle.waitFor({ state: 'attached' });
    await ensureCompanySidebar(page);
    await toggle.click();
    const listboxId = await toggle.getAttribute('aria-owns');
    assert.match(listboxId || '', /^[A-Za-z0-9_-]+$/, 'native selectpicker owns its listbox');
    const menu = page.locator('.bs-container.company-context-select');
    await menu.locator('.bs-searchbox input').fill(option.label);
    await page.locator('[id="' + listboxId + '"]').getByRole('option', { name: option.label, exact: true }).click();
    assert.equal(await select.inputValue(), expected, 'visible dropdown selection matches the requested authorized company');
}
module.exports = { selectCompanyOption, ensureCompanySidebar };
