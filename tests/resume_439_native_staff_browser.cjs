'use strict';
// Actual CRM/HTTP/MySQL/browser, exclusively on the disposable standalone-upgrade fixture.
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
async function login(page, email, password) {
  await page.goto(url + '/admin/authentication');
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(password);
  await navigate(page, page.locator('button[type=submit]'));
}
async function navigate(page, button) {
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}
async function hidden(page) {
  return page.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(x => x.name && !x.name.startsWith('wizard[')).map(x => [x.name, x.value])));
}
async function next(page) { await navigate(page, page.locator('section form button[type=submit]')); }

(async()=>{
 const browser=await chromium.launch({headless:true}),context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await context.newPage();try{
 await login(page,'admin-ci@example.invalid',process.env.ISSUERS_BROWSER_PASSWORD);
 const identity=sql("SELECT CONCAT(master_staff_id,':',source) FROM tblissuers_installation_identity WHERE id=1");assert.match(identity,/^17:(installer|legacy_designation)$/);
    // Native Staff GUI first rejects missing company access, then creates a usable employee.
    async function fillStaff() {
      await page.locator('input[name=firstname]').fill('Synthetic');
      await page.locator('input[name=lastname]').fill('Onboarding');
      await page.locator('input[name=email]').fill('native-staff-439@example.invalid');
      await page.locator('input[name=password]').fill('synthetic-native-staff-439');
      const welcome = page.locator('input[name=send_welcome_email]'); if (await welcome.count()) await welcome.uncheck();
    }
    await page.goto(url + '/admin/staff/member'); await fillStaff();
    await navigate(page, page.locator('form').filter({ has: page.locator('input[name=firstname]') }).locator('button[type=submit]'));
    assert.equal(sql("SELECT COUNT(*) FROM tblstaff WHERE email='native-staff-439@example.invalid'"), '0', 'no inaccessible employee silently created');
    await fillStaff();
    await page.locator('a[href="#staff_permissions"]').click();
    const primary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
    await page.locator('#issuer-membership-' + primary).check();
    await page.locator('#issuer_default').selectOption(primary);
    await navigate(page, page.locator('form').filter({ has: page.locator('input[name=firstname]') }).locator('button[type=submit]'));
    const employee = sql("SELECT staffid FROM tblstaff WHERE email='native-staff-439@example.invalid'");
    assert.match(employee, /^[1-9]\d*$/);
    assert.equal(sql('SELECT issuer_id FROM tblissuers_staff_defaults WHERE staff_id=' + employee), primary);


 assert.equal(sql("SELECT CONCAT(master_staff_id,':',source) FROM tblissuers_installation_identity WHERE id=1"),identity);
 console.log('Fresh439 native Staff GUI: missing membership rejected, canonical native employee creation with primary membership/default; installation master17 and designation source unchanged. No welcome email.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
