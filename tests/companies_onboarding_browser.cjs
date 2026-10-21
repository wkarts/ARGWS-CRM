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

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 960 } });
  await context.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
  const page = await context.newPage();
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  try {
    await login(page, 'admin-ci@example.invalid', process.env.ISSUERS_BROWSER_PASSWORD);
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_installation_identity WHERE master_staff_id IS NULL'), '1');
    await page.goto(url + '/admin/company_master');
    assert.equal((await context.request.get(url + '/argws_provisioning/authorize_company_master/17')).status(), 404, 'console authorization has no HTTP entry point');
    const consoleOutput = execFileSync('docker', ['exec', '-i', app, 'php', 'index.php', 'argws_provisioning', 'authorize_company_master', '17'],
      { input: 'Authorized synthetic legacy installation designation\n', encoding: 'utf8' });
    const code = consoleOutput.match(/^[a-f0-9]{64}$/m)?.[0];
    assert(code, 'console returns a temporary authorization (never logged by this test)');
    await page.locator('#authorization_code').fill(code);
    await page.locator('#current_password').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await navigate(page, page.locator('form').filter({ has: page.locator('#authorization_code') }).locator('button[type=submit]'));
    assert.equal(sql("SELECT CONCAT(master_staff_id,':',source) FROM tblissuers_installation_identity WHERE id=1"), '17:legacy_designation');
    assert.equal(await page.locator('#authorization_code').count(), 0, 'no code echoed after success');

    // Native Staff GUI first rejects missing company access, then creates a usable employee.
    async function fillStaff() {
      await page.locator('input[name=firstname]').fill('Synthetic');
      await page.locator('input[name=lastname]').fill('Onboarding');
      await page.locator('input[name=email]').fill('onboarding-ci@example.invalid');
      await page.locator('input[name=password]').fill('synthetic-staff-onboarding');
      const welcome = page.locator('input[name=send_welcome_email]'); if (await welcome.count()) await welcome.uncheck();
    }
    await page.goto(url + '/admin/staff/member'); await fillStaff();
    await navigate(page, page.locator('form').filter({ has: page.locator('input[name=firstname]') }).locator('button[type=submit]'));
    assert.equal(sql("SELECT COUNT(*) FROM tblstaff WHERE email='onboarding-ci@example.invalid'"), '0', 'no inaccessible employee silently created');
    await fillStaff();
    await page.locator('a[href="#staff_permissions"]').click();
    const primary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
    await page.locator('#issuer-membership-' + primary).check();
    await page.locator('#issuer_default').selectOption(primary);
    await navigate(page, page.locator('form').filter({ has: page.locator('input[name=firstname]') }).locator('button[type=submit]'));
    const employee = sql("SELECT staffid FROM tblstaff WHERE email='onboarding-ci@example.invalid'");
    assert.match(employee, /^[1-9]\d*$/);
    assert.equal(sql('SELECT issuer_id FROM tblissuers_staff_defaults WHERE staff_id=' + employee), primary);

    await page.goto(url + '/admin/company_onboarding'); await next(page);
    const draftUrl = page.url();
    await page.locator('#legal_name').fill('Synthetic company B');
    await page.locator('#tax_id').fill('52998224725');
    await page.locator('a[href="#company-address"]').click(); await page.locator('#profile_invoice_company_city').fill('Synthetic B');
    await next(page);
    const stale = await hidden(page);
    const denied = await context.request.post(draftUrl, { form: { ...stale, action: 'advance', revision: '0', step: '1', 'wizard[legal_name]': 'Stale overwrite', 'wizard[tax_id]': '52998224725' } });
    assert.equal(denied.status(), 409, 'stale draft cannot overwrite newer revision');
    await page.goto(url + '/admin/issuers');
    await page.goto(url + '/admin/company_onboarding');
    assert.equal(await page.locator('#dateformat').count(), 1, 'interrupted wizard resumes saved step');
    await next(page);
    await page.locator('[name="wizard[selected_staff][' + employee + ']"]').check();
    await page.locator('#default_' + employee).selectOption('1');
    await page.locator('[name="wizard[staff][' + employee + '][resources][settings][]"][value=view]').check();
    await next(page); await next(page);
    assert((await page.locator('section').innerText()).includes('Synthetic company B'));
    const completion = await hidden(page);
    await next(page);
    assert((await page.locator('[role=status]').innerText()).includes('Empresa criada'));
    const created = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
    assert.match(created, /^[1-9]\d*$/);
    const repeat = await context.request.post(draftUrl, { form: { ...completion, ...(await hidden(page)), action: 'complete', revision: completion.revision } });
    assert.equal(repeat.status(), 200, 'completion retry returns existing company');
    assert.equal(sql("SELECT COUNT(*) FROM tblissuers_registry WHERE tax_id='52998224725'"), '1');
    assert.equal(sql('SELECT issuer_id FROM tblissuers_staff_defaults WHERE staff_id=' + employee), created);
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id=' + employee + ' AND issuer_id IN (' + primary + ',' + created + ')'), '2', 'existing company membership retained');
    await page.screenshot({ path: output + '/company-onboarding-completed.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'wizard fits mobile');
    const oldContext = completion._company_context;
    await page.locator('#company-context-select').selectOption(created);
    await navigate(page, page.locator('.company-selector-form button[type=submit]'));
    const switched = await hidden(page);
    const staleCompany = await context.request.post(draftUrl, { form: { ...completion, ...switched, action: 'complete', _company_context: oldContext } });
    assert.equal(staleCompany.status(), 409, 'company switch refuses old-tab submission before mutation');
    assert.equal((await context.request.get(url + '/admin/staff')).status(), 403, 'unadapted secondary route remains closed');

    // Positive isolated customer directory, with actual HTTP/CSRF and durable receipts.
    async function switchCompany(id) {
      await page.goto(url + '/admin/company_context');
      await page.locator('#company-context-select').selectOption(id);
      await navigate(page, page.locator('.company-selector-form button[type=submit]'));
    }
    async function formFields(form) {
      return form.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(x => x.name).map(x => [x.name, x.value])));
    }
    await page.goto(url + '/admin/company_clients');
    await page.locator('#new_company').fill('Synthetic private B <script>');
    const createFields = await formFields(page.locator('form').filter({ has: page.locator('#new_company') }));
    await navigate(page, page.locator('form').filter({ has: page.locator('#new_company') }).locator('button[type=submit]'));
    const customer = sql("SELECT userid FROM tblclients WHERE company='Synthetic private B <script>'");
    assert.match(customer, /^[1-9]\d*$/);
    const currentFields = await formFields(page.locator('form').filter({ has: page.locator('#new_company') }));
    const retried = await context.request.post(url + '/admin/company_clients', { form: { ...createFields, ...currentFields, request_key: createFields.request_key, 'client[company]': 'Synthetic private B <script>' } });
    assert.equal(retried.status(), 200);
    assert.equal(sql("SELECT COUNT(*) FROM tblclients WHERE company='Synthetic private B <script>'"), '1');
    assert.equal(await page.locator('section script').count(), 0, 'customer text escaped');
    const beforeEdit = await formFields(page.locator('form').filter({ has: page.locator('#edit_company') }));
    await page.locator('#edit_company').fill('Synthetic private B updated');
    await navigate(page, page.locator('form').filter({ has: page.locator('#edit_company') }).locator('button[type=submit]'));
    const staleEdit = await context.request.post(url + '/admin/company_clients', { form: { ...beforeEdit, ...(await formFields(page.locator('form').filter({ has: page.locator('#edit_company') }))), revision: beforeEdit.revision, 'client[company]': 'Forbidden stale edit' } });
    assert.equal(staleEdit.status(), 409);
    const share = page.locator('form').filter({ has: page.locator('input[name=action][value=share]') });
    await share.locator('input[name="targets[]"][value="' + primary + '"]').check();
    await navigate(page, share.locator('button[type=submit]'));
    await switchCompany(primary);
    await page.goto(url + '/admin/company_clients?client=' + customer);
    assert.equal(await page.locator('#edit_company').count(), 0, 'shared identity is read only');
    assert((await page.locator('section').innerText()).includes('Synthetic private B updated'));
    const foreignWrite = await context.request.post(url + '/admin/company_clients', { form: { ...(await formFields(page.locator('form').filter({ has: page.locator('#new_company') }))), action: 'update', client_id: customer, revision: 'a'.repeat(64), 'client[company]': 'Cross company' } });
    assert.equal(foreignWrite.status(), 403);
    await switchCompany(created);
    await page.goto(url + '/admin/company_clients?client=' + customer);
    const revoke = page.locator('form').filter({ has: page.locator('input[name=action][value=share]') });
    await revoke.locator('input[name="targets[]"][value="' + primary + '"]').uncheck();
    await navigate(page, revoke.locator('button[type=submit]'));
    const oldCustomerContext = await formFields(page.locator('form').filter({ has: page.locator('#edit_company') }));
    await switchCompany(primary);
    assert.equal((await context.request.get(url + '/admin/company_clients?client=' + customer)).status(), 404, 'revoked identity no longer readable by direct ID');
    await page.goto(url + '/admin/company_clients');
    const switchedFields = await formFields(page.locator('form').filter({ has: page.locator('#new_company') }));
    const staleCustomer = await context.request.post(url + '/admin/company_clients', { form: { ...oldCustomerContext, ...switchedFields, _company_context: oldCustomerContext._company_context, action: 'update', client_id: customer, 'client[company]': 'Old tab' } });
    assert.equal(staleCustomer.status(), 409, 'old company token denied before customer write');
    await page.locator('#client-search').fill('Synthetic private B updated');
    await navigate(page, page.locator('form').filter({ has: page.locator('#client-search') }).locator('button[type=submit]'));
    assert.equal(await page.locator('tbody tr').count(), 0, 'search excludes private other-company identity');
    assert.equal((await context.request.get(url + '/admin/clients/client/' + customer)).status(), 404, 'legacy reader stays closed to private identity');
    await page.screenshot({ path: output + '/company-clients-directory.png', fullPage: true });
    const regular = await browser.newContext();
    await regular.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
    const employeePage = await regular.newPage();
    await login(employeePage, 'onboarding-ci@example.invalid', 'synthetic-staff-onboarding');
    assert.equal((await regular.request.get(draftUrl)).status(), 403, 'employee cannot access master draft by URL');
    assert.equal((await employeePage.goto(url + '/admin/settings?group=company')).status(), 200, 'positive secondary settings access');
    sql('UPDATE tblissuers_staff SET can_view=0 WHERE staff_id=' + employee + ' AND issuer_id=' + created);
    assert.equal((await regular.request.get(url + '/admin/settings?group=company')).status(), 403, 'revocation takes effect on next request');
    await regular.close();
    assert.deepEqual(errors, [], 'no browser script errors in new flows');
    console.log('Legacy master + native Staff + resumable company wizard: real HTTP/MySQL/browser checks passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
