'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/native-company-context.js'), 'utf8');
const channel = 'a'.repeat(64);

function browser(generation, options = {}) {
    const listeners = {}, documentListeners = {}, storage = options.storage || new Map(), writes = [], replaced = [], styles = [], broadcasts = [];
    const document = {visibilityState: 'visible', documentElement: {appendChild: style => styles.push(style)}, createElement: tag => ({tag}), addEventListener: (type, listener) => {documentListeners[type] = listener;}};
    const window = {
        location: {href: 'https://crm.example.test/sub/admin/payments', origin: 'https://crm.example.test', replace: url => replaced.push(url)},
        addEventListener: (type, listener) => {listeners[type] = listener;},
        localStorage: {getItem: key => {if (options.blockStorage) throw Error('denied'); return storage.get(key) ?? null;}, setItem: (key, value) => {if (options.blockStorage) throw Error('denied'); storage.set(key, value); writes.push([key, value]);}},
        BroadcastChannel: class {constructor(key) {this.key = key; broadcasts.push(this);} postMessage(value) {this.sent = value;}}
    };
    if (options.noBroadcast) delete window.BroadcastChannel;
    vm.runInNewContext(source, {window, document, URL});
    window.NativeCompanyContextGuard.mount({root: options.root || '/sub/admin/', channel: options.channel || channel, generation});
    return {window, document, listeners, documentListeners, storage, writes, replaced, styles, broadcasts};
}

test('confirmed company switch invalidates older tabs without sharing identities or tokens', () => {
    const a = browser(0), b = browser(1, {storage: a.storage});
    assert.equal(a.window.NativeCompanyContextGuard.isCurrent(), true);
    assert.deepEqual(b.writes.map(entry => entry[1]), ['1']);
    a.listeners.storage({key: b.writes[0][0], newValue: b.writes[0][1]});
    assert.equal(a.window.NativeCompanyContextGuard.isCurrent(), false);
    assert.deepEqual(a.replaced, ['https://crm.example.test/sub/admin/']);
    assert.equal(a.styles[0].textContent, 'body{visibility:hidden!important}');
    assert.equal(b.window.NativeCompanyContextGuard.isCurrent(), true);
    assert.equal(b.broadcasts[0].sent, 1);
});

test('late old response cannot overwrite a newer generation or repaint the old company', () => {
    const current = browser(3), late = browser(2, {storage: current.storage});
    assert.equal(late.window.NativeCompanyContextGuard.isCurrent(), false);
    assert.deepEqual(late.writes, []);
    assert.equal(late.storage.get(current.writes[0][0]), '3');
    late.listeners.pageshow({persisted: true});
    assert.equal(late.replaced.length, 1, 'invalidation is idempotent');
});

test('same/older generations and other installation/session channels leave the current page intact', () => {
    const page = browser(4), key = page.writes[0][0];
    for (const value of ['0','4','-1','4x','NaN','Infinity','9007199254740992']) page.listeners.storage({key, newValue: value});
    page.listeners.storage({key: key + '-other', newValue: '5'});
    for (const value of [4, 3, '5', {generation: 5}, NaN]) page.broadcasts[0].onmessage({data: value});
    assert.deepEqual(page.replaced, []);
    const other = browser(0, {storage: page.storage, channel: 'b'.repeat(64)});
    assert.deepEqual(other.replaced, []);
});

test('back-forward cache is revalidated and returning visible tabs check missed storage events', () => {
    const restored = browser(0);
    restored.listeners.pageshow({persisted: true});
    assert.equal(restored.window.NativeCompanyContextGuard.isCurrent(), false);
    assert.deepEqual(restored.replaced, ['https://crm.example.test/sub/admin/payments'], 'same-company Back revalidates the original navigation target');
    const background = browser(0);
    background.storage.set(background.writes[0][0], '1');
    background.documentListeners.visibilitychange();
    assert.equal(background.window.NativeCompanyContextGuard.isCurrent(), false);
});

test('BroadcastChannel works when storage is unavailable and absence of both leaves server authority intact', () => {
    const page = browser(0, {blockStorage: true});
    page.broadcasts[0].onmessage({data: 1});
    assert.equal(page.window.NativeCompanyContextGuard.isCurrent(), false);
    const unavailable = browser(0, {blockStorage: true, noBroadcast: true});
    assert.equal(unavailable.window.NativeCompanyContextGuard.isCurrent(), true);
    unavailable.listeners.pageshow({persisted: true});
    assert.equal(unavailable.window.NativeCompanyContextGuard.isCurrent(), false);
});

test('configuration cannot redirect to another origin or include secrets/query parameters', () => {
    for (const root of ['https://other.example/admin/', '/sub/admin/?token=x', '/sub/admin/#x', 'https://user@crm.example.test/sub/admin/']) {
        assert.throws(() => browser(0, {root}), /native_company_context_config_invalid/);
    }
});

test('native selector retires reads before its POST without redirecting or publishing an unconfirmed generation', () => {
    const page=browser(4), guard=page.window.NativeCompanyContextGuard, calls=[];
    guard.onObsolete(()=>calls.push('abort'));
    guard.beginSelection();guard.beginSelection();
    assert.equal(guard.isCurrent(),false);
    assert.deepEqual(calls,['abort']);
    assert.deepEqual(page.replaced,[],'the native form owns navigation and its existing submitter');
    assert.deepEqual(page.writes.map(entry=>entry[1]),['4'],'selection intent never advances a shared generation');
    guard.onObsolete(()=>calls.push('late-abort'));
    assert.deepEqual(calls,['abort','late-abort']);
});
