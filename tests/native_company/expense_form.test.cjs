'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

// Render the actual PHP template and execute its emitted JS. UI libraries and
// network responses below are spies, so this is not browser/screenshot evidence.
const rendered = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, '../companies/native_expense_original_http.php'), '--emit-html'], { encoding: 'utf8' });
const { html } = JSON.parse(rendered.trim().split('\n').at(-1));
const script = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m => m[1]).find(s => s.includes('function expenseSubmitHandler'));
assert(script, 'Original expense renderer emits its own form handler');

function fixture() {
    const nodes = new Map(), ready = [], posts = [], gets = [], navigations = [];
    const make = () => ({ props: {}, attrs: {}, classes: new Set(), value: '', children: [], text: '', data: { base: '1' } });
    const node = key => { if (!nodes.has(key)) nodes.set(key, make()); return nodes.get(key); };
    class Collection {
        constructor(value) { this.node = value; this.length = 1; this[0] = value; }
        prop(key, value) { if (arguments.length === 1) return this.node.props[key] || false; this.node.props[key] = value; return this; }
        attr(key, value) { if (arguments.length === 1) return this.node.attrs[key]; this.node.attrs[key] = value; return this; }
        data(key) { return this.node.data[key]; }
        val(value) { if (!arguments.length) return this.node.value; this.node.value = String(value); return this; }
        text(value) { if (!arguments.length) return this.node.text; this.node.text = String(value); return this; }
        empty() { this.node.children = []; this.node.text = ''; this.node.value = ''; return this; }
        html(value) { if (!arguments.length) return ''; return this.empty(); }
        clone() { return new Collection(make()); }
        remove() { return this; }
        append(value) { this.node.children.push(value instanceof Collection ? value.node : value); return this; }
        addClass(value) { this.node.classes.add(value); return this; }
        removeClass(value) { this.node.classes.delete(value); return this; }
        toggleClass(value, state) { return state ? this.addClass(value) : this.removeClass(value); }
        on() { return this; }
        trigger() { return this; }
        focus() { return this; }
        find(selector) { return $(selector); }
        selectpicker(command, value) { if (command === 'val') return arguments.length === 1 ? this.val() : this.val(value); return this; }
        serialize() { return 'native_expense_form=original_expense_v1&native_expenses_csrf=synthetic&_native_company_csrf=scope'; }
    }
    function $(selector, attrs) {
        if (typeof selector === 'function') { ready.push(selector); return; }
        if (typeof selector === 'string' && selector.startsWith('<')) { const value = make(); value.attrs = attrs || {}; return new Collection(value); }
        if (selector instanceof Collection) return selector;
        return new Collection(node(selector));
    }
    function request(list, url, data, callback) {
        const value = { url, data, doneHandler: callback, failHandler: null, done(fn) { this.doneHandler = fn; return this; }, fail(fn) { this.failHandler = fn; return this; }, resolve(body) { this.doneHandler(body); }, reject(body) { this.failHandler(body); } };
        list.push(value); return value;
    }
    $.post = (url, data) => request(posts, url, data);
    $.get = (url, callback) => request(gets, url, undefined, callback);
    class Dropzone {
        static options = {}; static ERROR = 'error';
        constructor(selector, options) { this.options = options; this.queued = []; this.rejected = []; this.failed = []; this.sent = []; }
        getQueuedFiles() { return this.queued; }
        getRejectedFiles() { return this.rejected; }
        getFilesWithStatus() { return this.failed; }
        getUploadingFiles() { return []; }
        processQueue() { const body = new FormData(); body.set('native_expenses_csrf', 'synthetic'); this.options.sending(this.queued[0], {}, body); this.sent.push(body); }
    }
    const context = { $, Dropzone, FormData, window: { location: { assign(url) { navigations.push(url); } } }, admin_url: '/admin/',
        init_ajax_project_search_by_customer_id() {}, appCreateDropzoneOptions(value) { return value; }, appValidateForm() {},
        alert_float() {}, setTimeout() {}, delay() {}, app: { options: { decimal_places: 2 } } };
    vm.createContext(context); vm.runInContext(script, context); ready.forEach(fn => fn());
    return { context, $, node, posts, gets, navigations, form: { action: '/admin/expenses/expense?source=company' }, zone: context.expenseDropzone };
}
const reply = { success: true, expenseid: '30064771073', upload_key: 'a'.repeat(64), url: '/admin/expenses/list_expenses/30064771073?source=company' };

test('Original save sends once while pending and accepts actual JSON response', () => {
    const f = fixture();
    assert.equal(f.context.expenseSubmitHandler(f.form), false); f.context.expenseSubmitHandler(f.form);
    assert.equal(f.posts.length, 1); assert(f.posts[0].data.includes('native_expenses_csrf'));
    assert.equal(f.node('#expense-form button[type="submit"]').props.disabled, true);
    f.posts[0].resolve(reply); assert.deepEqual(f.navigations, [reply.url]);
});
test('Original transport handles string response and restores a failed submit without navigation', () => {
    const f = fixture(); f.context.expenseSubmitHandler(f.form); f.posts[0].reject({ responseJSON: { message: '<script>forbidden</script>' } });
    assert.equal(f.node('#native-expense-form-error').text, '<script>forbidden</script>');
    assert.equal(f.node('#expense-form button[type="submit"]').props.disabled, false); assert.deepEqual(f.navigations, []);
    f.context.expenseSubmitHandler(f.form); f.posts[1].resolve(JSON.stringify(reply)); assert.deepEqual(f.navigations, [reply.url]);
});
test('Receipt upload follows the confirmed typed parent and retains CSRF', () => {
    const f = fixture(); f.zone.queued = [{ name: 'synthetic.png' }]; f.context.expenseSubmitHandler(f.form); f.posts[0].resolve(reply);
    assert.equal(f.zone.options.url, '/admin/expenses/add_expense_attachment/' + reply.expenseid);
    assert.equal(f.zone.sent[0].get('expense_id'), reply.expenseid); assert.equal(f.zone.sent[0].get('request_key'), reply.upload_key);
    assert.equal(f.zone.sent[0].get('native_expenses_csrf'), 'synthetic'); assert.deepEqual(f.navigations, []);
    f.zone.options.error({}, '', { responseJSON: { message: 'Upload refused' } });
    assert.equal(f.node('#native-expense-form-error').children.at(-1).attrs.href, reply.url);
    assert.equal(f.node('#expense-form button[type="submit"]').props.disabled, false); assert.deepEqual(f.navigations, []);
});
test('Failed upload retry cannot silently redirect as though the receipt succeeded', () => {
    const f = fixture(); f.zone.failed = [{ name: 'synthetic.png' }]; f.context.expenseSubmitHandler(f.form); f.posts[0].resolve(reply);
    assert.deepEqual(f.navigations, []); assert(f.node('#native-expense-form-error').text.includes('comprovante recusado'));
});
test('Malformed save response keeps the form reviewable', () => {
    const f = fixture(); f.context.expenseSubmitHandler(f.form); f.posts[0].resolve('{invalid');
    assert.deepEqual(f.navigations, []); assert.equal(f.node('#expense-form button[type="submit"]').props.disabled, false);
});
test('Customer changes clear old recipient data and safely insert current authorized choices', () => {
    const f = fixture(); f.$('select[name="clientid"]').val('7'); f.node('#native_contact_id').children = [{ text: 'Previous private contact' }];
    f.context.customer_init(); assert.equal(f.node('#native_contact_id').children.length, 1);
    f.gets[0].resolve({ client_currency: 1, customer_has_projects: false, recurring_contacts: [{ id: 9, firstname: '<script>name', lastname: 'Current' }] });
    assert.equal(f.node('#native_contact_id').children.at(-1).text, '<script>name Current');
    f.$('select[name="clientid"]').val('8'); f.context.customer_init(); f.gets[1].reject({ responseJSON: { message: 'Customer denied' } });
    assert.equal(f.node('#native_contact_id').children.length, 1); assert.deepEqual(f.navigations, []);
});
