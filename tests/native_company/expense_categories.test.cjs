'use strict';
const test = require('node:test'), assert = require('node:assert/strict');
const api = require('../../assets/js/native-admin-expense-categories.js');
test('Category modal accepts only the original typed category namespace', () => {
  assert.equal(api.categoryId('227633266689'), '227633266689'); assert.equal(api.categoryId('231928233983'), '231928233983');
  for (const value of ['1', '77', '227633266688', '231928233984', '30064771073', '0227633266689', '1e11']) assert.throws(() => api.categoryId(value));
});
test('Edit attributes carry the actual category revision and state', () => {
  const data = { id: '227633266689', name: 'Category <script>text</script>', description: 'Literal description', active: 0, revision: 'a'.repeat(64) };
  assert.deepEqual(api.fields(JSON.stringify(data)), data);
  for (const bad of [{ ...data, revision: '' }, { ...data, active: true }, { ...data, name: [] }]) assert.throws(() => api.fields(bad));
});
test('Inline category values are inserted as text and select their canonical identity', () => {
  const select = { options: [{ value: '' }], insertBefore(option) { this.options.push(option); } };
  const doc = { querySelector: () => select, createElement: tag => ({ tag }) };
  const data = { id: '227633266689', name: '<img src=x onerror=alert(1)>', active: 1 };
  assert.equal(api.selectCreated(doc, data), select); assert.equal(select.value, data.id); assert.equal(select.options[1].textContent, data.name); assert.equal(select.options[1].innerHTML, undefined);
  api.selectCreated(doc, { ...data, name: 'Renamed' }); assert.equal(select.options.length, 2); assert.equal(select.options[1].textContent, 'Renamed');
  api.selectCreated(doc, { ...data, active: 0 }); assert.equal(select.options.length, 2);
});
test('Unconfirmed replies are not accepted as successful category creation', () => {
  const data = { success: true, id: '227633266689', name: 'Travel', active: 1 };
  assert.deepEqual(api.response(JSON.stringify(data)), data);
  for (const bad of [{ ...data, success: false }, { ...data, id: '77' }, { ...data, name: null }, { ...data, active: '1' }]) assert.throws(() => api.response(bad));
});
test('Each opened command receives a 32-byte request key from browser crypto', () => {
  let calls = 0; const key = api.requestKey({ crypto: { getRandomValues(bytes) { calls++; bytes.fill(17); return bytes; } } });
  assert.equal(key, '11'.repeat(32)); assert.equal(calls, 1); assert.throws(() => api.requestKey({}));
});
