'use strict';
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
// Real controller, multipart upload, CRM context middleware, MySQL and browser.
// Run after companies_onboarding_browser in the disposable native-upgrade fixture.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
const output = process.env.ISSUERS_BROWSER_ARTIFACTS || '/tmp/issuer-browser-evidence';
fs.mkdirSync(output, { recursive: true });
function sql(query) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', query],
    { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
function fixture(tax) {
  return JSON.parse(execFileSync('docker', ['exec', '-e', 'ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci', app, 'php', 'tests/certificates/browser_fixture.php', /^[A-Z0-9]{12}[0-9]{2}$/.test(tax) ? tax : ''], { encoding: 'utf8' }));
}
async function navigate(page, button) { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]); }
async function upload(page, file, password = file.password) {
  await page.locator('#certificate-file').setInputFiles({ name: 'synthetic-only.pfx', mimeType: 'application/octet-stream', buffer: Buffer.from(file.pfx, 'base64') });
  await page.locator('#certificate-password').fill(password);
  await navigate(page, page.locator('#issuer-certificate-upload button[type=submit]'));
}
async function select(page, issuer) {
  await page.goto(url + '/admin/company_context');
  await selectCompanyOption(page, issuer);

  await page.goto(url + '/admin/company_certificates/index/' + issuer);
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 960 } });
  await context.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
  const page = await context.newPage();
  try {
    await page.goto(url + '/admin/authentication');
    await page.locator('input[name=email]').fill('admin-ci@example.invalid');
    await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await navigate(page, page.locator('button[type=submit]'));
    const companies = sql('SELECT id,COALESCE(tax_id,\'\') FROM tblissuers_registry WHERE active=1 ORDER BY id LIMIT 2').split('\n').map(line => line.split('\t'));
    assert.equal(companies.length, 2, 'fixture needs two active synthetic companies');
    const [a, b] = companies; assert.match(a[0], /^\d+$/); assert.match(b[0], /^\d+$/);
    const fa = fixture(a[1]); const fb = fixture('');
    await select(page, a[0]);
    assert.equal((await context.request.get(page.url())).headers()['cache-control'], 'no-store');
    const tokens = await page.locator('#issuer-certificate-upload input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.map(i => [i.name, i.value])));
    await upload(page, fa);
    assert.match(await page.locator('#certificate-status').innerText(), /armazenado/);
    assert.equal(await page.locator('#certificate-password').inputValue(), '');
    await page.screenshot({ path: output + '/certificate-a-stored.png', fullPage: true });
    const before = sql('SELECT revision FROM tblissuer_certificate_credentials WHERE issuer_id=' + a[0]);
    await upload(page, fa, 'wrong-synthetic-password');
    assert.match(await page.locator('[role=alert]').innerText(), /Não foi possível abrir/);
    assert.equal(sql('SELECT revision FROM tblissuer_certificate_credentials WHERE issuer_id=' + a[0]), before);
    assert.equal(await page.locator('#certificate-password').inputValue(), '');
    const stale = await context.request.post(page.url(), { form: { ...tokens, action: 'remove' } });
    assert.equal(stale.status(), 409, 'old revision denied');
    await page.goto(url + '/admin/company_certificates/index/' + a[0]);
    await upload(page, fa);
    await select(page, b[0]); await upload(page, fb);
    assert.match(await page.locator('#certificate-document-status').innerText(), /Não verificado/);
    await page.screenshot({ path: output + '/certificate-b-unverified.png', fullPage: true });
    const staleContext = await context.request.post(url + '/admin/company_certificates/index/' + a[0], { form: { ...tokens, action: 'remove' } });
    assert.equal(staleContext.status(), 409, 'stale company context denied');
    const bCipher = sql('SELECT SHA2(pfx_cipher,256) FROM tblissuer_certificate_credentials WHERE issuer_id=' + b[0]);
    await select(page, a[0]);
    await navigate(page, page.locator('#issuer-certificate-remove button[type=submit]'));
    assert.match(await page.locator('#certificate-status').innerText(), /Material removido/);
    assert.equal(sql('SELECT LENGTH(pfx_cipher)+LENGTH(password_cipher) FROM tblissuer_certificate_credentials WHERE issuer_id=' + a[0]), '0');
    assert.equal(sql('SELECT SHA2(pfx_cipher,256) FROM tblissuer_certificate_credentials WHERE issuer_id=' + b[0]), bCipher);
    await page.setViewportSize({ width: 390, height: 844 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'A1 form fits mobile');
    await page.screenshot({ path: output + '/certificate-a-removed-mobile.png', fullPage: true });
    console.log('Certificate browser: multipart, status, replacement, invalid password, stale revision/context, separate company, removal and screenshots passed with synthetic fixtures.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exit(1); });
