'use strict';
// Real PHP view, synthetic DB/controller fixture, local assets only. Not production QA.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const rendered = JSON.parse(execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'company_settings_native.php'), '--html-json'], { encoding: 'utf8' }));
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const rel = decodeURIComponent(url.pathname).replace(/^\//, '');
      const file = path.resolve(root, rel);
      if (url.hostname !== 'synthetic.invalid' || !rel.startsWith('assets/') || !file.startsWith(root + path.sep) || !fs.existsSync(file)) return route.abort();
      const mime = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff' }[path.extname(file)] || 'application/octet-stream';
      await route.fulfill({ body: fs.readFileSync(file), contentType: mime });
    });
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 950 });
      await page.setContent(rendered.editable, { waitUntil: 'load' });
      const nav = page.getByRole('navigation', { name: 'Seções de definições' });
      await assert.equal(await nav.locator('[aria-current="page"]').count(), 1);
      assert.equal(await nav.getByRole('link').count(), 16, 'all 15 settings groups plus assets remain discoverable');
      const form = page.locator('#company-settings-form');
      assert.equal(await form.locator('input[name="settings[smtp_host]"]').inputValue(), 'smtp.example.invalid');
      assert.equal(await form.locator('input[name="settings[smtp_password]"]').inputValue(), '', 'saved credentials never enter the HTML');
      assert.equal(await form.locator('button[type="submit"]').count(), 1);
      assert.equal(await form.locator('button[type="submit"]').evaluate(b => b.form.id), 'company-settings-form');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no horizontal overflow');
      const navBox = await nav.boundingBox();
      const formBox = await form.boundingBox();
      if (width > 991) assert.ok(navBox.x < formBox.x, 'settings categories are beside the form');
      else assert.ok(navBox.y + navBox.height <= formBox.y, 'mobile categories stack above form');
      await page.setContent(rendered.readOnly, { waitUntil: 'load' });
      assert.equal(await page.locator('#company-settings-form input[name="settings[smtp_host]"]').isDisabled(), true);
      assert.equal(await page.locator('#company-settings-form button[type="submit"]').count(), 0);
    }
    console.log('PASS: real scoped Settings view desktop/mobile, category layout, safe form bindings, readonly and no overflow. Synthetic fixture only.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
