'use strict';
const test = require('node:test'), assert = require('node:assert/strict');
const api = require('../../assets/js/native-admin-leads.js');
function fixture() {
  const calls = []; let current = true;
  function transport(options) {
    const done = [], fail = [], always = [];
    const xhr = { done(fn) { done.push(fn); return xhr; }, fail(fn) { fail.push(fn); return xhr; }, always(fn) { always.push(fn); return xhr; }, abort() { xhr.aborted = true; fail.forEach(fn => fn({}, 'abort')); }, resolve(data) { done.forEach(fn => fn(data)); always.forEach(fn => fn()); }, reject(error) { fail.forEach(fn => fn(error, 'error')); always.forEach(fn => fn()); } };
    calls.push({ options, xhr }); return xhr;
  }
  return { client: api.coordinator(transport, () => current), calls, invalidate() { current = false; } };
}
test('Original client accepts only the owned lead transport namespace', () => {
  assert.equal(api.canonical('25769803777'), '25769803777'); assert.equal(api.canonical('27917287423'), '27917287423');
  for (const id of [7, '17', '21474836497', '25769803776', '27917287424', '2e10', '025769803777', {}, null]) assert.throws(() => api.canonical(id));
});
test('Opening another lead discards superseded modal reads', () => {
  const f = fixture(), displayed = [], failures = [];
  f.client.read('modal', { path: 'leads/lead/25769803777' }, r => displayed.push(r.id), e => failures.push(e));
  f.client.read('modal', { path: 'leads/lead/25769803778' }, r => displayed.push(r.id), e => failures.push(e));
  assert(f.calls[0].xhr.aborted); f.calls[0].xhr.resolve({ success: true, id: '25769803777' }); f.calls[1].xhr.resolve({ success: true, id: '25769803778' });
  assert.deepEqual(displayed, ['25769803778']); assert.deepEqual(failures, []);
});
test('A company switch prevents late responses from repainting old leads', () => {
  const f = fixture(), displayed = []; f.client.read('board', { path: 'leads/kanban' }, r => displayed.push(r), () => {}); f.invalidate(); f.calls[0].xhr.resolve({ success: true, html: 'previous private board' }); assert.deepEqual(displayed, []);
  f.client.read('modal', { path: 'leads/lead' }, r => displayed.push(r), () => {}); assert.equal(f.calls.length, 1);
});
test('A modal submit preserves its exact body and prevents duplicate concurrent writes', () => {
  const f = fixture(), owner = {}, data = { native_leads_csrf: 'c'.repeat(64), request_key: 'a'.repeat(64), lead_id: '25769803777', revision: '3', status: '7' }, busy = [], results = [];
  const options = { path: 'leads/lead/25769803777', method: 'POST', data };
  f.client.write(owner, options, r => results.push(r.id), assert.fail, v => busy.push(v));
  assert.equal(f.client.write(owner, options, assert.fail, assert.fail, assert.fail), null); assert.equal(f.calls.length, 1); assert.equal(f.calls[0].options.data, data);
  f.calls[0].xhr.resolve(JSON.stringify({ success: true, id: data.lead_id })); assert.deepEqual(results, [data.lead_id]); assert.deepEqual(busy, [true, false]);
});
test('Rejected and malformed writes release controls without reporting success', () => {
  const f = fixture(), owner = {}, errors = [], busy = [], results = [];
  const send = () => f.client.write(owner, { path: 'leads/lead', method: 'POST', data: {} }, r => results.push(r), e => errors.push(e), v => busy.push(v));
  send(); f.calls[0].xhr.reject({ responseJSON: { message: 'Stale' } }); send(); f.calls[1].xhr.resolve('{invalid'); send(); f.calls[2].xhr.resolve({ success: false, message: 'Denied' });
  assert.equal(errors.length, 3); assert.equal(f.calls.length, 3); assert.deepEqual(results, []); assert.deepEqual(busy, [true, false, true, false, true, false]);
});
test('Kanban idempotency keys use cryptographic bytes and the real 64-character wire format', () => {
  let asked = 0; const value = api.key({ getRandomValues(bytes) { asked = bytes.length; bytes.fill(255); } }); assert.equal(asked, 32); assert.equal(value, 'f'.repeat(64));
});
