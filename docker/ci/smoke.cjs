'use strict';
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
(async () => {
  assert.equal(require('playwright/package.json').version, '1.55.1');
  const browser = await chromium.launch({headless: true, timeout: 30000});
  try {
    const context = await browser.newContext();
    await context.route('**/*', route => route.abort());
    const page = await context.newPage();
    await page.setContent('<!doctype html><html lang="pt-BR" dir="ltr"><meta charset="utf-8"><title>CI sintético</title><p id="proof">Validação isolada</p></html>');
    assert.equal(await page.locator('#proof').textContent(), 'Validação isolada');
    console.log(JSON.stringify({playwright: '1.55.1', browser: browser.version(), executable: chromium.executablePath(), check: 'synthetic-dom-pass'}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
