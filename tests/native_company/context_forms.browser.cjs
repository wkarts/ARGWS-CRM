'use strict';
// Real PHP context script + streamed HTML parsing in Chromium, with synthetic tokens.
const assert = require('node:assert/strict');
const http = require('node:http');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const render = mode => execFileSync(process.env.PHP_BINARY || 'php', ['-r', `
define('BASEPATH', __DIR__);
function get_instance() { return new stdClass(); }
class CompanyRequestScope {
    static function token() { return 'document-context'; }
    static function nativeCsrfToken($ci) { return 'document-csrf'; }
    static function adminUrl($ci) { return '/admin/'; }
    static function nativeFor($ci) { return $GLOBALS['argv'][1] === 'native'; }
}
(new class { function render() { include 'application/views/admin/includes/context_script.php'; } })->render();
`, mode], {cwd: root, encoding: 'utf8'});
const scripts = {native: render('native'), legacy: render('legacy')};
let pending;
const server = http.createServer((req, res) => {
    res.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
    if (req.url === '/admin/loaded') return res.end('<!doctype html><form id="late" method="post" action="/admin/save"></form>');
    const requestUrl = new URL(req.url, 'http://127.0.0.1');
    if (requestUrl.pathname !== '/admin/streamed') return res.end();
    const mode = requestUrl.searchParams.get('mode');
    if (!Object.hasOwn(scripts, mode)) return res.end();
    res.write('<!doctype html><html><head>' + scripts[mode] + '</head><body><form id="streamed" method="post" action="/admin/save">');
    pending = res; // Deliberately hold the server-rendered fields until Chromium parses the form start.
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({headless: true});
        const origin = 'http://127.0.0.1:' + server.address().port;
        for (const mode of ['native', 'legacy']) {
            const page = await browser.newPage();
            await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
            await page.goto(origin + '/admin/streamed?mode=' + mode, {waitUntil: 'commit'});
            await page.locator('#streamed').waitFor({state: 'attached'});
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            assert.equal(await page.evaluate(() => document.readyState), 'loading');
            assert.equal(await page.locator('#streamed input').count(), 0, 'never inject ahead of the server fields while the HTML parser is still active');
            pending.end('<input type="hidden" name="_company_context" value="server-context"><input type="hidden" name="_native_company_csrf" value="server-csrf"></form></body></html>');
            pending = null;
            await page.waitForLoadState('domcontentloaded');
            const entries = await page.locator('#streamed').evaluate(form => Array.from(new FormData(form)));
            assert.deepEqual(entries, [['_company_context', 'server-context'], ['_native_company_csrf', 'server-csrf']], 'one server value per token, preserved without overwriting');
            await page.evaluate(() => {
                document.body.insertAdjacentHTML('beforeend', '<form id="dynamic" method="post" action="/admin/save"></form><form id="query" method="get" action="/admin/search"></form><form id="external" method="post" action="https://external.invalid/save"></form>');
            });
            await page.waitForFunction(() => document.querySelectorAll('#dynamic input').length === 2);
            const dynamic = await page.locator('#dynamic').evaluate(form => Array.from(new FormData(form)));
            assert.deepEqual(dynamic, [['_company_context', 'document-context'], ['_native_company_csrf', 'document-csrf']]);
            assert.equal(await page.locator('#query input, #external input').count(), 0, 'GET and external forms never receive tokens');
            await page.evaluate(() => { const form = document.querySelector('#dynamic'); form.addEventListener('submit', e => e.preventDefault()); form.requestSubmit(); form.append(document.createElement('span')); });
            assert.deepEqual(await page.locator('#dynamic').evaluate(form => Array.from(new FormData(form))), dynamic, 'submit and subsequent mutations remain idempotent');
            await page.goto(origin + '/admin/loaded');
            await page.addScriptTag({content: scripts[mode].match(/<script>([\s\S]*?)<\/script>/)[1]});
            assert.deepEqual(await page.locator('#late').evaluate(form => Array.from(new FormData(form))), dynamic, 'a script loaded after DOMContentLoaded stamps existing forms immediately');
            await page.close();
        }
        console.log('Context forms PASS: streamed parser, exact server tokens, dynamic forms, submit idempotency, GET/external exclusion and late initialization (native/legacy).');
    } finally {
        if (pending) pending.end();
        if (browser) await browser.close();
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
