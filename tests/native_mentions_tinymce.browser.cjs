'use strict';

// Real TinyMCE and Chromium against the distributed local editor assets.
// Run with the project's pinned Playwright environment; no application or
// provider credentials are required.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const plugin = path.join(root, 'assets/plugins/tinymce');
const source = fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8');
const begin = source.indexOf('function initializeTinyMceMentions(');
const end = source.indexOf('\n// Shared native UI helper:', begin);
assert(begin >= 0 && end > begin);
const initializer = source.slice(begin, end);
const types = { '.js': 'application/javascript', '.css': 'text/css', '.svg': 'image/svg+xml' };

const server = http.createServer((req, res) => {
  const pathname = new URL(req.url, 'http://127.0.0.1').pathname;
  if (pathname === '/') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end('<!doctype html><html><body><textarea id="project"></textarea><textarea id="task"></textarea></body></html>');
    return;
  }
  const prefix = '/assets/plugins/tinymce/';
  if (!pathname.startsWith(prefix)) { res.writeHead(404).end(); return; }
  const file = path.resolve(plugin, pathname.slice(prefix.length));
  if (!file.startsWith(plugin + path.sep)) { res.writeHead(404).end(); return; }
  fs.readFile(file, (error, contents) => {
    if (error) { res.writeHead(404).end(); return; }
    res.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
    res.end(contents);
  });
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    const page = await browser.newPage();
    const origin = 'http://127.0.0.1:' + server.address().port;
    await page.goto(origin + '/');
    await page.addScriptTag({ url: origin + '/assets/plugins/tinymce/tinymce.min.js' });
    await page.addScriptTag({ content: initializer });
    const observed = await page.evaluate(async () => {
      await tinymce.init({ selector: '#project, #task', base_url: '/assets/plugins/tinymce', suffix: '.min', skin: false, content_css: false, menubar: false, toolbar: false });
      const raw = 'João & Maria <img src=x onerror=alert(1)> &lt; "test"';
      const encoded = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
      const dangerousId = '7" onmouseover="alert(1)';
      const outcomes = [];
      for (const [id, name, options] of [['project', raw, {}], ['task', encoded, { namesHtmlEncoded: true }]]) {
        const editor = tinymce.get(id);
        window.initializeTinyMceMentions(editor, async () => [{ id: dangerousId, name }], options);
        const users = await editor.perfexCommands.getUsersForMention();
        editor.focus();
        editor.perfexCommands.insertMentionUser(users[0].value, users[0].text, editor.selection.getRng());
        const html = editor.getContent();
        editor.setContent(html);
        const mention = editor.getBody().querySelector('span.mention');
        outcomes.push({ display: users[0].text, text: mention?.textContent, id: mention?.getAttribute('data-mention-id'), injected: editor.getBody().querySelectorAll('img, script, [onmouseover], [onerror]').length });
      }
      return { raw, dangerousId, outcomes };
    });
    for (const outcome of observed.outcomes) {
      assert.equal(outcome.display, observed.raw);
      assert.equal(outcome.text, '@' + observed.raw);
      assert.equal(outcome.id, observed.dangerousId);
      assert.equal(outcome.injected, 0);
    }
    console.log('Real TinyMCE project and task mentions preserve text, identity and safe markup.');
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
