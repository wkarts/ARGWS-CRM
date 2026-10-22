'use strict';
const assert = require('node:assert/strict');

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
    const box = await toggle.boundingBox();
    if (!box || box.x < 0) {
        await page.locator('#header .company-menu-toggle, #header > .hide-menu').click();
    }
    await toggle.click();
    const listboxId = await toggle.getAttribute('aria-owns');
    assert.match(listboxId || '', /^[A-Za-z0-9_-]+$/, 'native selectpicker owns its listbox');
    const menu = page.locator('.bs-container.company-context-select');
    await menu.locator('.bs-searchbox input').fill(option.label);
    await page.locator('[id="' + listboxId + '"]').getByRole('option', { name: option.label, exact: true }).click();
    assert.equal(await select.inputValue(), expected, 'visible dropdown selection matches the requested authorized company');
}
module.exports = { selectCompanyOption };
