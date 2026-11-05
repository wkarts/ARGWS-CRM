'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { calculate } = require('../../assets/js/native-admin-credits.js');
const line = (rate, taxes = [], qty = '1.00') => ({ rate, taxes, qty });
const data = (items, options = {}) => ({ items, discountMode: 'fixed', discountType: 'after_tax', discountValue: '0.00', adjustment: '0.00', ...options });

test('credit preview calculates money beyond Number safe integer multiplication exactly', () => {
  const result = calculate(data([line('9999999999999.99')]));
  assert.equal(result.total, '9999999999999.99');
  assert.deepEqual(result.lines, ['9999999999999.99']);
  assert.throws(() => calculate(data([line('9999999999999.99', [], '1.01')])));
  assert.throws(() => calculate(data([line('1'.repeat(1000))])));
});

test('credit per-line rounding and largest-remainder discount match canonical monetary contract', () => {
  const tax = [{ name: 'Synthetic', rate: '50.00' }];
  const before = calculate(data([line('0.03', tax), line('0.03', tax)], { discountValue: '0.01', discountType: 'before_tax' }));
  assert.equal(before.subtotal, '0.06'); assert.equal(before.discount, '0.01'); assert.equal(before.tax, '0.03'); assert.equal(before.total, '0.08');
  const after = calculate(data([line('0.03', tax), line('0.03', tax)], { discountValue: '0.01' }));
  assert.equal(after.tax, '0.04'); assert.equal(after.total, '0.09');
  const percent = calculate(data([line('0.05')], { discountMode: 'percent', discountValue: '10.00', adjustment: '0.01' }));
  assert.equal(percent.discount, '0.01'); assert.equal(percent.total, '0.05');
  assert.equal(calculate(data([line('0.01', [], '0.50')])).total, '0.01');
});

test('credit preview rejects invalid taxes, duplicates and discounts, while ignoring zero-rate tax identities', () => {
  assert.throws(() => calculate(data([line('10.00', [{ name: 'VAT', rate: '10' }, { name: ' vat ', rate: '2' }])])));
  assert.equal(calculate(data([line('10.00', [{ name: 'VAT', rate: '0' }, { name: 'vat', rate: '10' }])])).total, '11.00');
  assert.throws(() => calculate(data([line('10.00', [{ name: '', rate: '10' }])])));
  assert.throws(() => calculate(data([line('10.00', [{ name: 'VAT', rate: '100.01' }])])));
  assert.throws(() => calculate(data([line('10.00')], { discountValue: '10.01' })));
  assert.throws(() => calculate(data([line('10.00')], { adjustment: '-10.00' })));
  assert.throws(() => calculate(data([line('10,00')])));
});

function readsFixture() {
  const calls = []; let active = true;
  const transport = { request(path, method, data, options) {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    promise.abort = () => { promise.aborted = true; }; // An uncooperative late response can still arrive.
    calls.push({ path, method, data, options, promise, resolve, reject }); return promise;
  } };
  return { reader: require('../../assets/js/native-admin-credits.js').readCoordinator(transport, () => active), calls, expire() { active = false; } };
}
test('credit client reader discards a superseded response and frees old requests without blocking submission', async () => {
  const f = readsFixture();
  const old = f.reader.read('client', 'credit_notes/client_change_data/2', {});
  const next = f.reader.read('client', 'credit_notes/client_change_data/3', {});
  assert(f.calls[0].promise.aborted); assert(f.reader.busy());
  f.calls[1].resolve({ client_id: 3, billing_shipping: [{ billing_street: 'Current' }] });
  assert.equal((await next).client_id, 3); assert.equal(f.reader.busy(), false);
  f.calls[0].resolve({ client_id: 2, billing_shipping: [{ billing_street: 'Old private' }] });
  assert.equal(await old, undefined);
});
test('credit company switch drops late data and stops subsequent requests', async () => {
  const f = readsFixture(); const response = f.reader.read('client', 'credit_notes/client_change_data/2', {});
  f.expire(); f.calls[0].resolve({ client_id: 2, private: 'Old company' });
  assert.equal(await response, undefined); assert.equal(f.reader.busy(), false);
  await assert.rejects(f.reader.read('client', 'credit_notes/client_change_data/2', {})); assert.equal(f.calls.length, 1);
});
test('credit currency/client change cancels a pending catalogue selection without cancelling unrelated client data', async () => {
  const f = readsFixture(), data = { currency: '1' };
  const item = f.reader.read('catalog', 'credit_notes/item/4294967297', data);
  const client = f.reader.read('client', 'credit_notes/client_change_data/2', {});
  assert.equal(f.calls[0].data, data); assert.equal(f.calls[0].method, 'GET'); assert.deepEqual(f.calls[0].options, { dataType: 'json' });
  f.reader.cancel('catalog'); assert(f.calls[0].promise.aborted); assert(f.reader.busy());
  f.calls[0].resolve({ rate: '50.00' }); assert.equal(await item, undefined);
  f.calls[1].resolve({ client_id: 2 }); assert.equal((await client).client_id, 2); assert.equal(f.reader.busy(), false);
});
test('credit read failure is propagated once while rejected superseded requests stay silent', async () => {
  const f = readsFixture(), old = f.reader.read('search', 'credit_notes/items', {q: 'old'}), next = f.reader.read('search', 'credit_notes/items', {q: 'new'});
  f.calls[0].reject(new Error('obsolete')); assert.equal(await old, undefined);
  const failure = {status: 403, responseJSON: {message: 'Denied'}}; f.calls[1].reject(failure);
  await assert.rejects(next, value => value === failure); assert.equal(f.reader.busy(), false);
});
test('credit reader fails closed without transport and clears a synchronous request failure', async () => {
  const {readCoordinator} = require('../../assets/js/native-admin-credits.js');
  const absent = readCoordinator(null, () => true); await assert.rejects(absent.read('client','credit_notes/client_change_data/2',{})); assert.equal(absent.busy(),false);
  const broken = readCoordinator({request() {throw new Error('context obsolete');}}, () => true);
  await assert.rejects(broken.read('client','credit_notes/client_change_data/2',{}), /context obsolete/); assert.equal(broken.busy(),false);
});
