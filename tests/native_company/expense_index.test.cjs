'use strict';
const test = require('node:test'), assert = require('node:assert/strict');
const api = require('../../assets/js/native-admin-expense-index.js');
const { execFileSync } = require('node:child_process'), path = require('node:path');
test('Browser identifiers accept the canonical IDs produced by the actual PHP encoder', () => {
  const source = 'require "application/services/NativeCompanyResourceId.php"; echo json_encode([NativeCompanyResourceId::encode("expense",77),NativeCompanyResourceId::encode("company_expense",1),NativeCompanyResourceId::encode("company_expense",4294967295),NativeCompanyResourceId::encode("company_expense_category",1),NativeCompanyResourceId::encode("company_expense_category",4294967295)]);';
  const values = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', ['-r', source], { cwd: path.join(__dirname, '../..'), encoding: 'utf8' }));
  for (const id of values.slice(0, 3)) assert.equal(api.canonical(id), id);
  const categories = require('../../assets/js/native-admin-expense-categories.js'); for (const id of values.slice(3)) assert.equal(categories.categoryId(id), id);
  assert.throws(() => api.canonical(values[3])); assert.throws(() => categories.categoryId(values[1]));
});
function fixture() {
  const calls = []; let current = true;
  function transport(options) {
    const done = [], fail = [];
    const xhr = { done(fn) { done.push(fn); return xhr; }, fail(fn) { fail.push(fn); return xhr; }, abort() { xhr.aborted = true; fail.forEach(fn => fn({}, 'abort')); }, resolve(data) { done.forEach(fn => fn(data)); }, reject(error) { fail.forEach(fn => fn(error, 'error')); } };
    calls.push({ options, xhr }); return xhr;
  }
  return { read: api.coordinator(transport, () => current), calls, invalidate() { current = false; } };
}
test('Original expense links retain separate historical and current expense namespaces', () => {
  for (const id of ['1', '4294967295', '30064771073', '34359738367']) assert.equal(api.canonical(id), id);
  for (const id of ['0', '4294967296', '30064771072', '34359738368', '25769803777', '030064771073', '1e10', {}, null]) assert.throws(() => api.canonical(id));
});
test('Selecting another expense aborts and discards late older previews', () => {
  const f = fixture(), shown = [], errors = [];
  f.read('preview', { path: 'expenses/get_expense_data_ajax/30064771073', method: 'GET' }, r => shown.push(r.html), e => errors.push(e));
  f.read('preview', { path: 'expenses/get_expense_data_ajax/30064771074', method: 'GET' }, r => shown.push(r.html), e => errors.push(e));
  assert(f.calls[0].xhr.aborted); f.calls[0].xhr.resolve({ success: true, html: 'old expense' }); f.calls[1].xhr.resolve({ success: true, html: 'selected expense' });
  assert.deepEqual(shown, ['selected expense']); assert.deepEqual(errors, []);
});
test('Company invalidation prevents late preview or total data from repainting', () => {
  const f = fixture(), shown = [], errors = [];
  f.read('preview', { method: 'GET' }, r => shown.push(r), e => errors.push(e));
  f.read('totals', { method: 'POST' }, r => shown.push(r), e => errors.push(e));
  f.invalidate(); f.calls.forEach(call => call.xhr.resolve({ success: true, html: 'previous company' }));
  f.read('preview', { method: 'GET' }, r => shown.push(r), e => errors.push(e));
  assert.equal(f.calls.length, 2); assert.deepEqual(shown, []); assert.equal(errors.length, 1);
});
test('Currency and year summary reads preserve the exact bounded request and remain independent of preview reads', () => {
  const f = fixture(), shown = [], body = { native_expenses_csrf: 'a'.repeat(64), currency: '2', years: ['2025', '2026'], state: 'recorded' };
  f.read('totals', { path: 'expenses/get_expenses_total', method: 'POST', data: body }, r => shown.push(r.html), assert.fail);
  f.read('preview', { path: 'expenses/get_expense_data_ajax/77', method: 'GET' }, r => shown.push(r.html), assert.fail);
  assert.equal(f.calls[0].options.data, body); assert.equal(f.calls[0].xhr.aborted, undefined);
  f.calls[0].xhr.resolve(JSON.stringify({ success: true, html: 'US$3.01' })); f.calls[1].xhr.resolve({ success: true, html: 'Historical expense' });
  assert.deepEqual(shown, ['US$3.01', 'Historical expense']);
});
test('Rejected or malformed reads never report a successful preview', () => {
  const f = fixture(), errors = [], shown = [];
  for (const response of ['{invalid', { success: false, message: 'Denied' }, { success: true }]) {
    f.read('preview', {}, value => shown.push(value), error => errors.push(error)); f.calls.at(-1).xhr.resolve(response);
  }
  f.read('preview', {}, value => shown.push(value), error => errors.push(error)); f.calls.at(-1).xhr.reject({ responseJSON: { message: 'Revoked' } });
  assert.equal(errors.length, 4); assert.deepEqual(shown, []);
});
test('Print headings are text nodes and attachment/mutation controls are removed', () => {
  const children = [], writes = [], removed = []; let focused = false, printed = false;
  const panel = {}, links = [{ href: '/assets/css/style.css' }], heading = { textContent: 'Expense <img src=x onerror=alert(1)>' };
  const doc = { head: { appendChild(node) { children.push(node); } }, body: { appendChild(node) { children.push(node); } }, open() {}, close() {}, write(value) { writes.push(value); }, createElement(tag) { return { tag, textContent: '' }; }, importNode(node) { if (node === panel) return { querySelectorAll(selector) { assert.equal(selector, '#expenseReceipt,form,button,[data-toggle="modal"]'); return [{ remove() { removed.push(true); } }]; } }; return node; } };
  const root = { document: { querySelector(selector) { return selector === '#tab_expense' ? panel : heading; }, querySelectorAll() { return links; } }, open() { return { document: doc, focus() { focused = true; }, print() { printed = true; } }; } };
  api.printPreview(root);
  assert(!writes.join('').includes('<img')); assert.equal(children.find(node => node.tag === 'h1').textContent, heading.textContent);
  assert.equal(removed.length, 1); assert(focused && printed);
});
test('Original tasks initializer receives the canonical expense and refuses a stale company', () => {
  const calls = []; let current = true;
  const root = { NativeCompanyContextGuard: { isCurrent: () => current }, init_rel_tasks_table: (...args) => calls.push(args) };
  api.relatedTasks(root, '30064771073'); api.relatedTasks(root, '77');
  assert.deepEqual(calls, [['30064771073', 'expense'], ['77', 'expense']]);
  assert.throws(() => api.relatedTasks(root, '25769803777')); current = false;
  assert.throws(() => api.relatedTasks(root, '30064771073')); assert.equal(calls.length, 2);
});
test('Bulk fields retain decimal strings, canonical identities and captured revisions', () => {
  const selection = [{ id: '30064771073', revision: 'a'.repeat(64) }, { id: '30064771074', revision: 'b'.repeat(64) }];
  const fields = { cancel: false, amount: '0.05', date: '06/11/2026', category: '227633266689', paymentmode: '' };
  const result = api.bulkPayload(selection, fields, 'c'.repeat(64), 'd'.repeat(64));
  assert.equal(result.amount, '0.05'); assert.equal(result.date, '06/11/2026'); assert.deepEqual(result.ids, selection.map(row => row.id)); assert.equal(result.revisions['30064771073'], selection[0].revision);
  for (const rows of [[], [selection[0], selection[0]], [{ ...selection[0], id: '77' }], [{ ...selection[0], revision: '' }]]) assert.throws(() => api.bulkPayload(rows, fields, 'c'.repeat(64), 'd'.repeat(64)));
  assert.throws(() => api.bulkPayload(selection, { ...fields, amount: 0.05 }, 'c'.repeat(64), 'd'.repeat(64)));
});
test('Cancellation transmits its explicit operation without applying hidden bulk-edit values', () => {
  const result = api.bulkPayload([{ id: '30064771073', revision: 'a'.repeat(64) }], { cancel: true, amount: '999.00', date: '06/11/2026', category: '227633266689', paymentmode: '' }, 'c'.repeat(64), 'd'.repeat(64));
  assert.equal(result.mass_delete, '1'); for (const field of ['amount', 'date', 'category', 'paymentmode']) assert.equal(result[field], '');
});
