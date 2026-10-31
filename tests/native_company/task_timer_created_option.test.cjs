'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/native-tasks.js', 'utf8');
const start = source.indexOf('function task_form_handler(');
const end = source.indexOf('\n// Context is transported', start);
assert(start >= 0 && end > start, 'actual task form handler found');

function exercise(taskResponse, saveResponseAsObject = false) {
  const form = { action: '/admin/tasks/task' };
  const timer = { id: '12"] [onclick="bad' };
  const inserted = [];
  const state = { clicks: 0, changes: 0, refreshes: 0, requested: '', errors: [] };
  const select = {
    list: { cache: { old: true } },
    append(option) { inserted.push(option); return this; },
    trigger(event) { assert.equal(event, 'change'); state.changes++; return this; },
    data(key) { assert.equal(key, 'AjaxBootstrapSelect'); return this; },
    selectpicker(action) { assert.equal(action, 'refresh'); state.refreshes++; return this; },
  };
  function $(subject) {
    if (subject === form) return { 0: form, attr(name) { return name === 'method' ? 'POST' : 'multipart/form-data'; } };
    if (subject === timer) return { attr(name) { assert.equal(name, 'data-timer-id'); return timer.id; } };
    if (subject === '[data-timer-id]') return {
      filter(fn) { return { first() { return { click() { if (fn.call(timer)) state.clicks++; } }; } }; },
    };
    if (subject === '#timer_add_task_id') return select;
    return {
      find() { return this; }, prop() { return this; }, each() { return this; },
      modal() { return this; },
    };
  }
  $.ajax = () => ({
    done(callback) { const value = { success: true, id: 'company_standalone_task:19', message: 'ok' }; callback(saveResponseAsObject ? value : JSON.stringify(value)); return this; },
    fail() { return this; },
  });
  const context = {
    $, document: { createElement(tag) { assert.equal(tag, 'option'); return { tagName: 'OPTION' }; } },
    FormData: class { constructor(received) { assert.equal(received, form); } },
    tinymce: { triggerSave() {} }, window: { _timer_id: timer.id }, admin_url: '/admin',
    requestGet(url) { state.requested = url; return { done(callback) { callback(taskResponse); } }; },
    alert_float(type, message) { if (type !== 'success') state.errors.push(message); },
  };
  vm.runInNewContext(source.slice(start, end), context);
  assert.equal(context.task_form_handler(form), false);
  assert.equal(state.requested, '/admin/tasks/get_task_by_id/company_standalone_task:19');
  assert.equal(state.clicks, 1);
  assert.equal(state.changes, 1);
  assert.equal(state.refreshes, 1);
  assert.deepEqual(state.errors, []);
  assert.equal(inserted.length, 1);
  assert.equal(inserted[0].value, 'company_standalone_task:19');
  assert.equal(inserted[0].title, '<img src=x onerror=alert(1)> & Test');
  assert.equal(inserted[0].textContent, '<img src=x onerror=alert(1)> & Test');
  assert.equal(inserted[0].selected, true);
  assert.equal('innerHTML' in inserted[0], false);
  assert.equal(context.window._timer_id, undefined);
}

test('created task safely populates its timer from an object JSON response', () => {
  exercise({ id: 'company_standalone_task:19', name: '<img src=x onerror=alert(1)> & Test' }, true);
});
test('created task also accepts the legacy text JSON response', () => {
  exercise(JSON.stringify({ id: 'company_standalone_task:19', name: '<img src=x onerror=alert(1)> & Test' }));
});
