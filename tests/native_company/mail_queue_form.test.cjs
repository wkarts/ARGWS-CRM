'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../application/views/admin/settings/includes/email.php'), 'utf8');
const nativeScript = source.slice(source.indexOf('<?php if($nativeMail){ ?>\n<script>'));
const start = nativeScript.indexOf(' // DataTables creates a named pagination select');
const end = nativeScript.indexOf(" document.querySelectorAll('.native-mail-command')", start);
assert.ok(start >= 0 && end > start, 'native form handler is present in the native-only script');
const handler = nativeScript.slice(start, end);

test('native SMTP submission omits only queue pagination controls', () => {
  const listeners = {};
  const pagination = { name: 'DataTables_Table_0_length', value: '25', removeAttribute(name) {
    assert.equal(name, 'name'); delete this.name;
  } };
  const unrelated = { name: 'settings[smtp_host]', value: 'smtp.example.invalid' };
  const foreignPagination = { name: 'other_table_length', value: '10' };
  const form = {
    addEventListener(event, callback) { listeners[event] = callback; },
    querySelectorAll(selector) {
      assert.equal(selector, '#email_queue .dataTables_length select[name]');
      return [pagination];
    },
  };
  vm.runInNewContext(handler, { document: { getElementById(id) { assert.equal(id, 'settings-form'); return form; } } });
  assert.equal(typeof listeners.submit, 'function');
  listeners.submit();
  assert.equal(pagination.name, undefined);
  assert.equal(pagination.value, '25', 'display choice remains intact');
  assert.equal(unrelated.name, 'settings[smtp_host]');
  assert.equal(foreignPagination.name, 'other_table_length');
});

test('missing settings form does not crash the native email script', () => {
  assert.doesNotThrow(() => vm.runInNewContext(handler, { document: { getElementById() { return null; } } }));
});
