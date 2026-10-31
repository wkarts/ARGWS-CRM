'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/main.js', 'utf8');
const start = source.indexOf('function initializeTinyMceMentions(');
const end = source.indexOf('\n// Shared native UI helper:', start);
assert(start >= 0 && end > start, 'real mention initializer is present');
const initializer = source.slice(start, end);

// This exercises the actual initializer with a small DOM double. The separate
// Chromium/TinyMCE test is needed to prove browser parsing and editor behavior.
function escape(value) {
  return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function decode(value) {
  return value.replace(/&(amp|lt|gt|quot|#039|#39);/g, (_, entity) =>
    ({ amp: '&', lt: '<', gt: '>', quot: '"', '#039': "'", '#39': "'" })[entity]);
}
function documentDouble() {
  return { createElement(tag) {
    if (tag === 'textarea') return { set innerHTML(value) { this.value = decode(value); } };
    assert.equal(tag, 'span');
    return {
      className: '', textContent: '', attributes: {},
      setAttribute(name, value) { this.attributes[name] = value; },
      get outerHTML() {
        return '<span class="' + escape(this.className) + '"' +
          Object.entries(this.attributes).map(([k, v]) => ' ' + k + '="' + escape(v) + '"').join('') +
          '>' + escape(this.textContent) + '</span>';
      },
    };
  } };
}
function editorDouble() {
  return {
    getDoc: documentDouble,
    selection: { setRng() {} },
    ui: { registry: { addAutocompleter() {} } },
    insertContent(html) { this.content = html; },
  };
}
const context = vm.createContext({});
vm.runInContext(initializer, context);

test('project names remain text and are escaped exactly once when inserted', async () => {
  const editor = editorDouble();
  const name = 'João & Maria <img src=x> &lt; "test"';
  context.initializeTinyMceMentions(editor, async () => [{ id: 7, name }]);
  const users = await editor.perfexCommands.getUsersForMention();
  assert.equal(users[0].text, name);
  editor.perfexCommands.insertMentionUser('7" onmouseover="evil', users[0].text, {});
  assert(editor.content.includes('data-mention-id="7&quot; onmouseover=&quot;evil"'));
  assert(editor.content.includes('@João &amp; Maria &lt;img src=x&gt; &amp;lt; &quot;test&quot;'));
  assert(!editor.content.includes('<img'));
});

test('task wire entities are decoded once before safe DOM serialization', async () => {
  const editor = editorDouble();
  const raw = 'João & Maria <X> &lt;';
  const encoded = 'João &amp; Maria &lt;X&gt; &amp;lt;';
  context.initializeTinyMceMentions(editor, async () => [{ id: 9, name: encoded }], { namesHtmlEncoded: true });
  const users = await editor.perfexCommands.getUsersForMention();
  assert.equal(users[0].text, raw);
  editor.perfexCommands.insertMentionUser(users[0].value, users[0].text, {});
  assert(editor.content.includes('@João &amp; Maria &lt;X&gt; &amp;lt;'));
});

test('the task editor explicitly selects the existing encoded wire contract', () => {
  const tasks = fs.readFileSync('assets/js/native-tasks.js', 'utf8');
  assert.match(tasks, /tasks\/get_staff_names_for_mentions\/[\s\S]{0,150}namesHtmlEncoded:\s*true/);
});
