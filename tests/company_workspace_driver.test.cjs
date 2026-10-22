'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { workspacePage, revealWorkspaceControl } = require('./helpers/company_workspace_driver.cjs');

test('workspace driver navigates by a real accessible button click before filling without mutating visibility', async () => {
    const events = [];
    let active = false;
    const control = {count: async () => 1, evaluate: async () => active ? [] : ['company-workspace-0-1'], fill: async value => events.push(['fill', value]), first() { return this; }};
    const button = {count: async () => 1, evaluate: async () => [], click: async () => {events.push(['click']); active = true;}};
    const page = {locator: selector => selector.includes('aria-controls') ? button : control};
    const result = await workspacePage({newPage: async () => page});
    await result.locator('input[name=name]').first().fill('Synthetic company');
    assert.deepEqual(events, [['click'], ['fill', 'Synthetic company']]);
    await result.locator('input[name=name]').fill('Changed');
    assert.deepEqual(events.at(-1), ['fill', 'Changed']);
    assert.equal(events.filter(event => event[0] === 'click').length, 1);
});
test('missing/ambiguous controls are not replaced with another permitted control', async () => {
    for (const count of [0, 2]) await revealWorkspaceControl({locator: () => {throw new Error('must not navigate');}}, {count: async () => count});
});
test('navigation adapter cannot inject hidden state, submit requests, or bypass actionability', () => {
    const source = fs.readFileSync(__dirname + '/helpers/company_workspace_driver.cjs', 'utf8');
    assert.doesNotMatch(source, /\.hidden\s*=|\.style\s*[.=]|force:\s*true|dispatchEvent|\.request\.|fetch\(|\.submit\(/);
    assert.match(source, /await button\.click\(\)/);
});
