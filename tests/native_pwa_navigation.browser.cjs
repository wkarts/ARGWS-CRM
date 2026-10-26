'use strict';
// Real Node HTTP + Chromium exercising shipped JS/SW only. This is not a PHP,
// authenticated CRM, OS installation or bank integration acceptance test.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {once} = require('node:events');
const {chromium} = require('playwright');
const repo = path.resolve(__dirname, '..');
const install = fs.readFileSync(path.join(repo, 'assets/pwa/install.js'));
const worker = fs.readFileSync(path.join(repo, 'assets/pwa/worker.js'), 'utf8');

(async () => {
  let generation = 1, mutations = 0, browser;
  const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://127.0.0.1');
    const prefix = url.pathname.startsWith('/crm/') ? '/crm/' : '/';
    const route = url.pathname.slice(prefix.length);
    response.setHeader('Cache-Control', 'no-store');
    if (route === 'assets/pwa/install.js') {
      response.setHeader('Content-Type', 'application/javascript'); return response.end(install);
    }
    if (route === 'pwa.php' && url.searchParams.get('resource') === 'worker') {
      response.setHeader('Content-Type', 'application/javascript');
      response.setHeader('Service-Worker-Allowed', prefix);
      return response.end(worker + '\n// disposable-generation-' + generation);
    }
    if (route === 'api/private') {
      response.setHeader('Content-Type', 'application/json'); return response.end('{"synthetic":"PRIVATE_FIXTURE"}');
    }
    if (route === 'api/mutate') {
      if (request.method === 'POST') mutations++;
      return response.end(String(mutations));
    }
    if (!route.startsWith('admin')) { response.statusCode = 404; return response.end(); }
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    // Intentionally repeated asset: a layout regression must not create another
    // registration/listener or application invitation in the same document.
    const script = '<script defer src="' + prefix + 'assets/pwa/install.js" data-pwa-root="' + prefix + '"></script>';
    response.end('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + script + script +
      '<title>PWA synthetic navigation fixture</title><main><h1>Disposable PWA fixture</h1><form><label for="unsaved">Synthetic unsaved field</label><input id="unsaved"></form></main></html>');
  });
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  const origin = 'http://127.0.0.1:' + server.address().port;
  try {
    browser = await chromium.launch({headless: true, ...(process.env.PWA_CHROMIUM_EXECUTABLE ? {executablePath: process.env.PWA_CHROMIUM_EXECUTABLE} : {})});
    for (const prefix of ['/', '/crm/']) for (const width of [1280, 768, 390]) {
      const context = await browser.newContext({serviceWorkers: 'allow', viewport: {width, height: 900}});
      try {
        const page = await context.newPage(), errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const route of ['admin', 'admin/invoices', 'admin/settings', 'admin/companies', 'admin/authentication/logout', 'admin/authentication']) {
          await page.goto(origin + prefix + route);
          await page.waitForFunction(() => !!navigator.serviceWorker.controller);
          const state = await page.evaluate(async () => {
            let calls = 0;
            const event = new Event('beforeinstallprompt', {cancelable: true});
            Object.defineProperties(event, {prompt: {value: () => { calls++; }}, userChoice: {value: Promise.resolve({outcome: 'dismissed'})}});
            const allowed = window.dispatchEvent(event);
            window.dispatchEvent(new Event('appinstalled'));
            const registrations = await navigator.serviceWorker.getRegistrations();
            return {allowed, calls, registrations: registrations.length, scope: registrations[0].scope,
              panels: document.querySelectorAll('aside[aria-label="Aplicativo"], dialog, [role="dialog"]').length,
              cache: await caches.keys(), databases: await indexedDB.databases(), storage: localStorage.length + sessionStorage.length};
          });
          assert.deepEqual(state, {allowed: true, calls: 0, registrations: 1, scope: origin + prefix, panels: 0, cache: [], databases: [], storage: 0});
        }
        await page.locator('#unsaved').fill('synthetic-unsaved');
        await page.evaluate(() => { window.fixtureChanges = 0; navigator.serviceWorker.addEventListener('controllerchange', () => window.fixtureChanges++); });
        generation++;
        await page.evaluate(async () => { const registration = await navigator.serviceWorker.ready; await registration.update(); });
        await page.waitForFunction(async () => !!(await navigator.serviceWorker.getRegistration()).waiting);
        assert.equal(await page.locator('#unsaved').inputValue(), 'synthetic-unsaved');
        assert.equal(await page.evaluate(() => window.fixtureChanges), 0);
        assert.equal(await page.locator('aside[aria-label="Aplicativo"]').count(), 0);
        await context.setOffline(true);
        assert.equal(await page.evaluate(async prefix => {
          try { await fetch(prefix + 'api/mutate', {method: 'POST', body: 'synthetic'}); return false; } catch (_) { return true; }
        }, prefix), true);
        const offline = await page.goto(origin + prefix + 'admin/invoices');
        assert.equal(offline.status(), 503);
        assert.match(await page.locator('body').innerText(), /Sem conexão/);
        assert.doesNotMatch(await page.content(), /PRIVATE_FIXTURE|synthetic-unsaved|<form|<script/);
        assert.equal(offline.headers()['cache-control'], 'no-store');
        await context.setOffline(false); await page.goto(origin + prefix + 'admin');
        assert.equal(await page.evaluate(async prefix => (await fetch(prefix + 'api/mutate')).text(), prefix), '0');
        assert.deepEqual(await page.evaluate(async () => ({cache: await caches.keys(), databases: await indexedDB.databases()})), {cache: [], databases: []});
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({fixture: 'shipped-pwa-js-and-worker', prefix, width, routes: 6, invitation: 'none', registration: 'single', update: 'waits', offline: 'generic-503', replay: false, result: 'PASS'}));
      } finally { await context.close(); }
    }
  } finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
