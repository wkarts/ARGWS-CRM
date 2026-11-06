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

test('Inline submit reaches the actual protected transport with one CSRF and active field', () => {
  const vm = require('node:vm'), fs = require('node:fs'), path = require('node:path');
  const calls = [], errors = []; let submit, prefilter;
  const form = { elements: { active: { type: 'checkbox', checked: true } } };
  const doc = { body: { classList: { contains: () => true } }, querySelector: () => null };
  const chain = {
    on(event, selector, handler) { if (event === 'submit.nativeExpenseCategories') submit = handler; return this; },
    serializeArray: () => [{ name: 'name', value: 'Category + & literal' }, { name: 'description', value: '<img src=x>' }, { name: 'active', value: '0' }, { name: 'csrf_name', value: 'framework-token' }],
    find() { return this; }, prop() { return this; }, removeClass() { return this; },
    text(value) { errors.push(value); return this; }
  };
  function $(value) { if (typeof value === 'function') { value(); return; } return chain; }
  $.param = fields => { const params = new URLSearchParams(); for (const field of fields) params.append(field.name, field.value); return params.toString(); };
  $.ajaxPrefilter = handler => { prefilter = handler; };
  const xhr = { done() { return this; }, fail() { return this; }, always() { return this; } };
  $.ajax = options => { prefilter(options); calls.push(options); return xhr; };
  const root = { document: doc, jQuery: $, URL, URLSearchParams, FormData,
    location: { href: 'https://crm.test/admin/expenses/expense', origin: 'https://crm.test' },
    NativeCompanyHeaderConfig: { root: 'https://crm.test/admin/', context: 'issuer2:r1', csrf: 'native-token', frameworkCsrf: { name: 'csrf_name', value: 'framework-token' } } };
  root.window = root;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/js/native-company-transport.js'), 'utf8'), root);
  api.mount(root, $); let prevented = false;
  submit.call(form, { preventDefault() { prevented = true; } });
  assert.equal(prevented, true); assert.deepEqual(errors, []); assert.equal(calls.length, 1);
  const request = calls[0], fields = new URLSearchParams(request.data);
  assert.equal(request.url, 'https://crm.test/admin/expenses/category?source=company');
  assert.equal(request.headers['X-Company-Context'], 'issuer2:r1');
  assert.equal(request.headers['X-Native-Company-CSRF'], 'native-token');
  assert.deepEqual(fields.getAll('csrf_name'), ['framework-token']); assert.deepEqual(fields.getAll('active'), ['1']);
  assert.equal(fields.get('name'), 'Category + & literal'); assert.equal(fields.get('description'), '<img src=x>');
});
