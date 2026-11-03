const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const { readFileSync, mkdtempSync, rmSync } = require('node:fs');
const os = require('node:os');
const path = require('node:path');

(async () => {
  const dir = mkdtempSync(path.join(os.tmpdir(), 'portal-invoice-'));
  try {
    const htmlPath = path.join(dir, 'detail.html');
    execFileSync(process.env.PHP_BINARY || 'php', ['tests/companies/native_portal_invoices.php'], { env: { ...process.env, NATIVE_PORTAL_INVOICE_TEST_HTML: htmlPath }, stdio: 'pipe' });
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH, headless: true });
    try {
      for (const width of [1280, 375]) {
        const page = await browser.newPage({ viewport: { width, height: 800 } });
        await page.setContent('<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1">' + readFileSync(htmlPath, 'utf8'));
        if (await page.locator('.invoice-items-table tbody tr').count() < 1 || await page.locator('input[name="_native_portal_csrf"]').count() < 1 || await page.locator('button[name="invoicepdf"]').count() !== 1 || await page.locator('button[name="paymentpdf"]').count() < 1) throw new Error('Missing invoice items, receipt or protected PDF form');
        await page.close();
      }
      console.log('Native invoice: actual PHP detail view rendered in Chromium at desktop and mobile viewport.');
    } finally { await browser.close(); }
  } finally { rmSync(dir, { recursive: true, force: true }); }
})().catch(error => { console.error(error); process.exit(1); });
