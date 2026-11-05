'use strict';
// Real PHP head and shipped browser guard, with synthetic session generations.
// Company selection/authentication are covered separately by tests/native_context.php.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const channel = 'b'.repeat(64);
const render = (mode, generation) => execFileSync(process.env.PHP_BINARY || 'php', ['-r', `
define('BASEPATH', __DIR__);
function base_url($path) { return '/' . $path; }
function get_instance() { return new stdClass(); }
class CompanyRequestScope {
    static function token() { return 'synthetic-document-context-' . $GLOBALS['argv'][2]; }
    static function nativeCsrfToken($ci) { return 'synthetic-document-csrf'; }
    static function adminUrl($ci) { return '/admin/'; }
    static function nativeFor($ci) { return $GLOBALS['argv'][1] === 'native'; }
    static function context() { return new class {
        function browserState() { return ['channel' => str_repeat('b', 64), 'generation' => (int)$GLOBALS['argv'][2]]; }
    }; }
}
(new class { function render() { include 'application/views/admin/includes/context_script.php'; } })->render();
`, mode, String(generation)], {cwd: root, encoding: 'utf8'});
const scripts = {};
for (const mode of ['native', 'legacy']) {
    scripts[mode] = {1: render(mode, 1), 2: render(mode, 2)};
}
const guard = fs.readFileSync(path.join(root, 'assets/js/native-company-context.js'));
let activeGeneration = 1;
let pageReads = 0;
let serverMode = 'native';
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    res.setHeader('Cache-Control', 'no-store');
    if (url.pathname === '/assets/js/native-company-context.js') {
        res.writeHead(200, {'Content-Type': 'application/javascript'});
        return res.end(guard);
    }
    if (!['/admin/', '/admin/page'].includes(url.pathname)) {
        res.writeHead(404);
        return res.end();
    }
    const mode = url.searchParams.get('mode') || serverMode;
    const generation = url.pathname === '/admin/' ? activeGeneration : Number(url.searchParams.get('generation'));
    if (!scripts[mode] || !scripts[mode][generation]) {
        res.writeHead(400);
        return res.end();
    }
    pageReads++;
    res.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
    res.end('<!doctype html><html><head>' + scripts[mode][generation] + '</head><body>' +
        '<main id="company-data">Empresa ' + (generation === 1 ? 'A' : 'B') + '</main>' +
        '<form id="edit" method="post" action="/admin/save"><button>Salvar</button></form>' +
        '</body></html>');
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({headless: true});
        const origin = 'http://127.0.0.1:' + server.address().port;
        for (const mode of ['native', 'legacy']) {
            activeGeneration = 1;
            serverMode = mode;
            const context = await browser.newContext();
            const errors = [];
            context.on('page', page => page.on('pageerror', error => errors.push(error.message)));
            await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
            const oldPage = await context.newPage();
            await oldPage.goto(origin + '/admin/page?mode=' + mode + '&generation=1');
            assert.equal(await oldPage.locator('#company-data').textContent(), 'Empresa A');
            assert.deepEqual(await oldPage.locator('#edit').evaluate(form => Array.from(new FormData(form))), [
                ['_company_context', 'synthetic-document-context-1'], ['_native_company_csrf', 'synthetic-document-csrf'],
            ]);
            const selectedPage = await context.newPage();
            activeGeneration = 2;
            await selectedPage.goto(origin + '/admin/page?mode=' + mode + '&generation=2');
            await oldPage.waitForURL(origin + '/admin/');
            await oldPage.waitForLoadState('domcontentloaded');
            assert.equal(await oldPage.locator('#company-data').textContent(), 'Empresa B', 'an already open tab reloads its data after a confirmed newer generation');
            assert.equal(await oldPage.locator('body').evaluate(body => getComputedStyle(body).visibility), 'visible', 'the new document is visible after the obsolete document was hidden');
            assert.deepEqual(await selectedPage.evaluate(() => Object.entries(localStorage)), [
                ['crm:company-change:/admin/:' + channel, '2'],
            ], 'cross-tab storage carries only the opaque channel and generation');
            const latePage = await context.newPage();
            await latePage.goto(origin + '/admin/page?mode=' + mode + '&generation=1');
            await latePage.waitForURL(origin + '/admin/');
            assert.equal(await latePage.locator('#company-data').textContent(), 'Empresa B', 'a late response from the previous company cannot republish an older generation');
            const readsBeforeRestore = pageReads;
            const restoreTarget = selectedPage.url();
            const restoredResponse = selectedPage.waitForResponse(response => response.url() === restoreTarget && response.request().isNavigationRequest());
            await selectedPage.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true})));
            await restoredResponse;
            await selectedPage.waitForLoadState('domcontentloaded');
            assert.ok(pageReads > readsBeforeRestore, 'a restored-page signal triggers server context revalidation');
            assert.equal(selectedPage.url(), restoreTarget, 'same-company Back keeps the original navigation target');
            assert.equal(await selectedPage.locator('#company-data').textContent(), 'Empresa B');
            assert.deepEqual(errors, [], 'no browser script exceptions in ' + mode + ' context');
            await context.close();
        }
        console.log('Company tabs PASS: actual PHP head, cross-tab generation, stale response, form tokens and persisted-page revalidation in Chromium (native/legacy; synthetic session).');
    } finally {
        if (browser) await browser.close();
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
