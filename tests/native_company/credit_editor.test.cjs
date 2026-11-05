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
