'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {projectReader} = require('../../assets/js/native-admin-contracts.js');
function fixture() {
  let current = true; const calls = [];
  const transport = {request(path, method, data, options) {
    let resolve, reject; const promise = new Promise((a, b) => {resolve = a; reject = b;});
    promise.abort = () => {promise.aborted = true;};
    calls.push({path, method, data, options, promise, resolve, reject}); return promise;
  }};
  return {reader: projectReader(transport, () => current), calls, switchCompany() {current = false;}};
}
test('contract draft project read uses the canonical route and exact selected customer/draft', async () => {
  const f = fixture(), result = f.reader.read('17', '21474836487');
  assert.deepEqual(f.calls[0].data, {client_id: '17', contract_id: '21474836487'});
  assert.equal(f.calls[0].path, 'contracts/client_change_data');assert.equal(f.calls[0].method, 'GET');
  assert.deepEqual(f.calls[0].options, {dataType: 'json'});assert(f.reader.busy());
  f.calls[0].resolve({client_id: 17, projects: []});assert.equal((await result).client_id, 17);assert(!f.reader.busy());
});
test('changing the contract customer discards an uncooperative late response without blocking the new selection', async () => {
  const f = fixture(), old = f.reader.read('17'), latest = f.reader.read('18');
  assert(f.calls[0].promise.aborted);f.calls[1].resolve({client_id: 18, projects: [{id: '4294967298'}]});
  assert.equal((await latest).client_id,18);assert(!f.reader.busy());
  f.calls[0].resolve({client_id: 17, projects: [{name: 'Old private project'}]});assert.equal(await old,undefined);
});
test('contract company switch prevents response hydration and future reads', async () => {
  const f = fixture(), pending = f.reader.read('17');f.switchCompany();
  f.calls[0].resolve({client_id: 17, projects: [{name: 'Previous company'}]});assert.equal(await pending,undefined);
  await assert.rejects(f.reader.read('17'));assert.equal(f.calls.length,1);assert(!f.reader.busy());
});
test('clearing the contract client releases an old read and suppresses its eventual failure', async () => {
  const f = fixture(), pending = f.reader.read('17');f.reader.cancel();assert(!f.reader.busy());
  f.calls[0].reject(new Error('aborted old selection'));assert.equal(await pending,undefined);
});
test('current contract read failures remain errors and a missing or failed transport never leaves a pending state', async () => {
  const f = fixture(), pending = f.reader.read('17');f.calls[0].reject({status:403});await assert.rejects(pending, value => value.status === 403);assert(!f.reader.busy());
  const absent = projectReader(null,()=>true);await assert.rejects(absent.read('17'));assert(!absent.busy());
  const broken = projectReader({request(){throw new Error('expired context');}},()=>true);await assert.rejects(broken.read('17'),/expired context/);assert(!broken.busy());
});
