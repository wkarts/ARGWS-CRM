'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
function fixture(collapsed) {
    const events = []; let value = '1';
    const select = {waitFor: async () => {}, inputValue: async () => value,
        locator: () => ({evaluateAll: async () => [{value: '1', label: 'Primary', disabled: false}, {value: '2', label: 'Authorized company', disabled: false}]})};
    const toggle = {waitFor: async () => {}, boundingBox: async () => collapsed ? null : {x: 10},
        click: async () => events.push('open dropdown'), getAttribute: async () => 'bs-select-1'};
    const page = {locator: selector => {
        if (selector === '#company-context-select') return select;
        if (selector.includes('.bootstrap-select >')) return toggle;
        if (selector.includes('#header')) return {click: async () => events.push('open sidebar')};
        if (selector.includes('.bs-container')) return {locator: () => ({fill: async text => events.push('search ' + text)})};
        if (selector === '[id="bs-select-1"]') return {getByRole: (role, options) => {
            assert.equal(role, 'option'); assert.deepEqual(options, {name: 'Authorized company', exact: true});
            return {click: async () => {events.push('click option'); value = '2';}};
        }};
        throw Error('unexpected selector ' + selector);
    }};
    return {page, events, current: () => value};
}
test('desktop/mobile choose through real controls without submitting the context form', async () => {
    for (const collapsed of [false, true]) {
        const f = fixture(collapsed); await selectCompanyOption(f.page, 2);
        assert.deepEqual(f.events, [...(collapsed ? ['open sidebar'] : []), 'open dropdown', 'search Authorized company', 'click option']);
        assert.equal(f.current(), '2');
    }
});
test('unauthorized company fails before any UI action', async () => {
    const f = fixture(true); await assert.rejects(() => selectCompanyOption(f.page, 999), /authorized enabled catalog/);
    assert.deepEqual(f.events, []); assert.equal(f.current(), '1');
});
test('driver never writes DOM values, forces hidden selects or sends a request', () => {
    const source = fs.readFileSync(__dirname + '/helpers/company_selector_driver.cjs', 'utf8');
    assert.doesNotMatch(source, /\.value\s*=(?!=)|\.hidden\s*=(?!=)|\.style\s*[.=]|force:\s*true|selectOption\(|dispatchEvent|\.request\.|fetch\(|\.submit\(/);
});
