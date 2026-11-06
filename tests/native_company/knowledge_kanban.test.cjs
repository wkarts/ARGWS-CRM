'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(__dirname + '/../../application/views/admin/knowledge_base/articles.php', 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];
const rev = letter => letter.repeat(64);

// Exercise the actual inline handlers and revision hand-off, without a browser
// server. This verifies JS behavior, not screenshots or a live HTTP endpoint.
function fixture(native = true) {
    const ready = [], requests = [], handlers = new Map();
    let reloads = 0;
    function node(attributes = {}, classes = []) { return { attributes, classes, values: {}, children: {}, parent: null, sortable: {} }; }
    const body = node(), container = node(), heading = node({ 'data-group-id': '184683593729' });
    const group = node({ 'data-col-group-id': '184683593729', 'data-native-revision': rev('a') }, ['kan-ban-col']);
    const otherGroup = node({ 'data-col-group-id': '184683593730', 'data-native-revision': rev('b') }, ['kan-ban-col']);
    const edit = node({ 'data-native-revision': rev('a'), 'data-order': '1', 'data-color': '#123456' });
    const otherEdit = node({ 'data-native-revision': rev('b'), 'data-order': '2' });
    const count = node(), otherCount = node(), list = node({ 'data-group-id': '184683593729' }, ['article-group', 'groups']);
    const otherList = node({ 'data-group-id': '184683593730' }, ['article-group', 'groups']);
    const article = node({ 'data-article-id': '180388626433', 'data-native-revision': rev('c') });
    const color = node({ 'data-color': '#abcdef' });
    article.parent = list; list.parent = group; otherList.parent = otherGroup;
    color.parent = heading; heading.parent = group;
    list.children.li = [article]; otherList.children.li = [];
    group.children['.panel-heading [data-native-revision]'] = [edit];
    otherGroup.children['.panel-heading [data-native-revision]'] = [otherEdit];
    group.children['[data-kb-group-count]'] = [count]; otherGroup.children['[data-kb-group-count]'] = [otherCount];
    group.children['.article-group > li'] = [article]; otherGroup.children['.article-group > li'] = [];
    const selectors = { body: [body], '.groups': [list, otherList], '.container-fluid': [container], '.kan-ban-col': [group, otherGroup] };
    class Collection {
        constructor(nodes = []) { this.nodes = nodes; this.length = nodes.length; nodes.forEach((n, i) => { this[i] = n; }); }
        each(fn) { this.nodes.forEach((n, i) => fn.call(n, i, n)); return this; }
        attr(key, value) { if (arguments.length === 1) return this.nodes[0]?.attributes[key]; return this.each(function () { this.attributes[key] = String(value); }); }
        data(key, value) { if (arguments.length === 1) return this.nodes[0]?.values[key] ?? this.attr('data-' + key); return this.each(function () { this.values[key] = value; }); }
        hasClass(value) { return this.nodes[0]?.classes.includes(value); }
        find(selector) { return new Collection(this.nodes.flatMap(n => n.children[selector] || [])); }
        filter(fn) { return new Collection(this.nodes.filter((n, i) => fn.call(n, i, n))); }
        parent() { return new Collection(this.nodes.map(n => n.parent).filter(Boolean)); }
        parents(selector) {
            const found = [];
            this.nodes.forEach(n => { for (let p = n.parent; p; p = p.parent) { if (selector === '.panel-heading' ? p === heading : p.classes.includes(selector.slice(1))) found.push(p); } });
            return new Collection(found);
        }
        text(value) { return this.each(function () { this.text = String(value); }); }
        sortable(options) { return this.each(function () { Object.assign(this.sortable, options); }); }
        on(event, selector, handler) { if (handler) handlers.set(selector, handler); return this; }
        disableSelection() { return this; }
        css() { return this; }
        removeAttr() { return this; }
        removeClass() { return this; }
        toggleClass() { return this; }
    }
    const $ = value => {
        if (typeof value === 'function') { ready.push(value); return; }
        if (value instanceof Collection) return value;
        return new Collection(typeof value === 'string' ? selectors[value] || [] : [value]);
    };
    $.each = (values, fn) => values.each(fn);
    $.post = (url, data) => { const request = { url, data }; requests.push(request); return { done(fn) { request.done = fn; return this; } }; };
    const context = {
        $, window: { NativeCompanyTransport: native ? {} : undefined, location: { href: '/admin/knowledge_base', reload() { reloads++; } } },
        admin_url: '/admin/', setTimeout(fn) { fn(); }, fix_kanban_height() {}, initDataTable() {}, mainWrapperHeightFix() {},
    };
    vm.createContext(context); vm.runInContext(script, context); ready.forEach(fn => fn());
    return { context, $, requests, handlers, body, container, group, otherGroup, list, article, color, edit, count, otherCount, reloads: () => reloads };
}

test('article drag keeps native kanban open and carries confirmed revisions to the next drag', () => {
    const f = fixture();
    const drag = () => f.list.sortable.update.call(f.list, {}, { item: f.$(f.article) });
    drag();
    const first = f.requests[0];
    assert.equal(first.url, '/admin/knowledge_base/update_kan_ban');
    assert.equal(first.data.revisions[0], rev('c'));
    assert.equal(first.data.order[0][0], '180388626433');
    first.done({ success: true, revisions: [rev('d')] });
    assert.equal(f.article.attributes['data-native-revision'], rev('d'));
    assert.equal(f.count.text, '1'); assert.equal(f.otherCount.text, '0');
    drag(); assert.equal(f.requests[1].data.revisions[0], rev('d'));
    f.requests[1].done(JSON.stringify({ success: true, revisions: [rev('e')] }));
    assert.equal(f.article.attributes['data-native-revision'], rev('e'));
    assert.equal(f.reloads(), 0);
});

test('group order and color update both the kanban and its existing edit modal data', () => {
    const f = fixture();
    f.container.sortable.update();
    f.requests[0].done({ success: true, revisions: [rev('d'), rev('e')] });
    assert.equal(f.group.attributes['data-native-revision'], rev('d'));
    assert.equal(f.edit.attributes['data-native-revision'], rev('d'));
    assert.equal(f.edit.values.order, 0);
    f.handlers.get('.kb-kan-ban .cpicker').call(f.color);
    assert.equal(f.requests[1].data.revision, rev('d'));
    f.requests[1].done(JSON.stringify({ success: true, revision: rev('f') }));
    assert.equal(f.group.attributes['data-native-revision'], rev('f'));
    assert.equal(f.edit.attributes['data-native-revision'], rev('f'));
    assert.equal(f.edit.values.color, '#abcdef');
    f.handlers.get('.kb-kan-ban .cpicker').call(f.color);
    assert.equal(f.requests[2].data.revision, rev('f'));
    assert.equal(f.reloads(), 0);
});

test('unconfirmed, malformed and out-of-order responses cannot replace a revision', () => {
    const f = fixture(), elements = f.$(f.group), update = f.context.updateKnowledgeRevisions;
    for (const response of [null, 'invalid-json', { success: false, revisions: [rev('d')] }, { success: true, revisions: [] }, { success: true, revisions: ['invalid'] }]) {
        assert.equal(update(response, elements, [rev('a')]), false);
        assert.equal(f.group.attributes['data-native-revision'], rev('a'));
    }
    assert.equal(update({ success: true, revisions: [rev('d')] }, elements, [rev('a')]), true);
    assert.equal(update({ success: true, revisions: [rev('e')] }, elements, [rev('a')]), false);
    assert.equal(f.group.attributes['data-native-revision'], rev('d'));
    assert.equal(f.edit.attributes['data-native-revision'], rev('d'));
});

test('legacy kanban payload remains unchanged and does not acquire company-only tokens', () => {
    const f = fixture(false);
    f.list.sortable.update.call(f.list, {}, { item: f.$(f.article) });
    assert.equal(Object.hasOwn(f.requests[0].data, 'revisions'), false);
    f.requests[0].done('');
    f.container.sortable.update();
    assert.equal(Object.hasOwn(f.requests[1].data, 'revisions'), false);
    f.requests[1].done('');
    f.handlers.get('.kb-kan-ban .cpicker').call(f.color);
    assert.equal(Object.hasOwn(f.requests[2].data, 'revision'), false);
    f.requests[2].done('');
    assert.equal(f.reloads(), 0);
});
