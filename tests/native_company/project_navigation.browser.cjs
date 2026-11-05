'use strict';
const assert = require('node:assert/strict');
const http = require('node:http');
const { chromium } = require('playwright');
const { openNativeProjectTasks } = require('../helpers/native_browser_forms.cjs');
let imageResponse;
const server = http.createServer((req, res) => {
    if (req.url === '/slow-image') { imageResponse = res; return; }
    res.writeHead(req.url === '/failure' ? 503 : 200, { 'Content-Type': 'text/html' });
    res.end('<!doctype html><script>window.new_task_from_relation=()=>document.querySelector("#result").textContent="ready";</script><a href="#" onclick="new_task_from_relation();return false">Nova tarefa</a><div id="result"></div><img src="/slow-image">');
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    let browser;
    try {
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();
        const origin = 'http://127.0.0.1:' + server.address().port;
        await openNativeProjectTasks(page, origin + '/project');
        assert.equal(await page.evaluate(() => document.readyState), 'interactive', 'delayed decorative image still prevents full load');
        await page.locator('a').click();
        assert.equal(await page.locator('#result').textContent(), 'ready', 'task action works before decorative resource completes');
        imageResponse?.end();
        await assert.rejects(openNativeProjectTasks(page, origin + '/failure'), /HTTP 200/, 'real document failure remains a failure');
        imageResponse?.end();
        await page.close();
        console.log('Project navigation: DOM readiness, usable task action and HTTP failure verified in Chromium.');
    } finally { if (browser) await browser.close(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
