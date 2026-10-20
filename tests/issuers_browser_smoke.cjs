'use strict';
// Real browser + real isolated CRM. This script refuses a public/production URL.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
const dbContainer = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(dbContainer || '', /^argws-crm-provision-db-\d+$/);
const dbName = process.env.ISSUERS_BROWSER_DB_NAME || 'argws_crm_ci';
assert.match(dbName, /^argws_(crm|legacy|upgrade)_ci$/);
const output = process.env.ISSUERS_BROWSER_ARTIFACTS || '/tmp/issuer-browser-evidence';
fs.mkdirSync(output, { recursive: true });
function sql(query) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', dbContainer, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, dbName, '--batch', '--skip-column-names', '-e', query],
    { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
async function login(page) {
  const response = await page.goto(url + '/admin/authentication');
  assert.equal(response.status(), 200, 'login remains available before core upgrade');
  await page.locator('input[name=email]').fill('admin-ci@example.invalid');
  await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('button[type=submit]').click()]);
  assert(!page.url().includes('/authentication'), 'authentication established');
}
async function shell(page) {
  try {
    await page.locator('#header').waitFor();
  } catch (error) {
    const currentUrl = new URL(page.url());
    currentUrl.search = '';
    currentUrl.hash = '';
    const body = await page.locator('body').innerText().catch(() => '');
    const safeSql = query => { try { return sql(query); } catch { return 'unavailable'; } };
    const diagnostic = {
      url: currentUrl.toString(),
      title: await page.title().catch(() => ''),
      body: body.replace(/\s+/g, ' ').slice(0, 500),
      migrationVersion: safeSql('SELECT version FROM tblmigrations LIMIT 1'),
      companyTableCount: safeSql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'tblissuers_%'"),
      permissionColumns: safeSql("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblissuers_resource_permissions'"),
      adminState: safeSql("SELECT CONCAT(admin, ':', active) FROM tblstaff WHERE email='admin-ci@example.invalid' LIMIT 1"),
    };
    console.error('Native shell diagnostic: ' + JSON.stringify(diagnostic));
    throw error;
  }
  await page.locator('#menu').waitFor({ state: 'attached' });
  await page.locator('#setup-menu').waitFor({ state: 'attached' });
  await page.locator('#wrapper .content').waitFor();
  assert.equal(await page.locator('#side-menu .menu-item-issuers').count(), 0);
  assert.equal((await page.locator('#setup-menu .menu-item-issuers .menu-text').textContent()).trim(), 'Empresas');
  const order = await page.locator('#setup-menu > li').evaluateAll(items => items.map(item => item.className));
  assert.equal(order.findIndex(x => x.includes('menu-item-issuers')) + 1, order.findIndex(x => x.includes('menu-item-staff')));
  assert(await page.locator('link[rel=stylesheet]').count() > 3, 'native stylesheet bundle is loaded');
  assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('#header')).position), 'relative', 'native header stays in document flow');
}

async function waitForUpgradeForm(page) {
  try {
    await page.locator('#upgrade_db_form').waitFor({ state: 'visible' });
  } catch (error) {
    const currentUrl = new URL(page.url());
    currentUrl.search = '';
    currentUrl.hash = '';
    const body = await page.locator('body').innerText().catch(() => '');
    const diagnostic = {
      url: currentUrl.toString(),
      title: await page.title().catch(() => ''),
      body: body.replace(/\s+/g, ' ').slice(0, 400),
      migrationVersion: sql('SELECT version FROM tblmigrations LIMIT 1'),
      adminState: sql("SELECT CONCAT(admin, ':', active) FROM tblstaff WHERE email='admin-ci@example.invalid' LIMIT 1"),
    };
    console.error('Upgrade browser diagnostic: ' + JSON.stringify(diagnostic));
    throw error;
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  // Keep test traffic local; synthetic installation has no integration configured.
  await context.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
  try {
    await login(page);
    if (process.env.ISSUERS_BROWSER_MODE === 'upgrade') {
      await waitForUpgradeForm(page);
      assert(await page.locator('input[name=admin_database_upgrade_csrf]').getAttribute('value'));
      const before = sql('SELECT version FROM tblmigrations LIMIT 1');
      const hasExistingIdentity = sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblissuers_installation_identity'") === '1';
      const existingMaster = hasExistingIdentity && sql('SELECT COUNT(*) FROM tblissuers_installation_identity WHERE id=1') === '1' ? sql("SELECT COALESCE(CAST(master_staff_id AS CHAR),'NULL') FROM tblissuers_installation_identity WHERE id=1") : null;

      const denied = await context.request.post(url + '/admin', { form: { upgrade_database: 'true' } });
      assert.equal(denied.status(), 403, 'native upgrade still needs CSRF');
      assert.equal(sql('SELECT version FROM tblmigrations LIMIT 1'), before);
      await page.reload();
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]);
      assert(!page.url().includes('/essential_modules'), 'same native POST finishes company core');
      assert.notEqual(sql('SELECT version FROM tblmigrations LIMIT 1'), before, 'real old schema migrated');
      assert.equal(new URL(page.url()).pathname, '/admin/company_context', 'native upgrade finishes in the existing isolated company selector');
      await page.getByText('O CRM foi atualizado com sucesso.', { exact: true }).waitFor({ state: 'visible' });
      await page.goto(url + '/admin');
      await shell(page);
      if (existingMaster === null) {
        assert.equal(sql('SELECT COUNT(*) FROM tblissuers_installation_identity WHERE master_staff_id IS NOT NULL'), '0', 'legacy admin is not silently made master');
      } else {
        assert.equal(sql("SELECT COALESCE(CAST(master_staff_id AS CHAR),'NULL') FROM tblissuers_installation_identity WHERE id=1"), existingMaster, 'native upgrade preserves the previously identified master without new CLI authorization');
      }
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_installation_identity WHERE primary_issuer_id IS NOT NULL'), '1', 'legacy primary company is preserved or created without a wizard');
      await page.screenshot({ path: output + '/legacy-native-upgrade.png', fullPage: true });
    } else {
      assert(!page.url().includes('/essential_modules'), 'first login has no company wizard');
      await shell(page);
      await page.locator('#setup-menu-item .open-customizer').click();
      await page.locator('#setup-menu-wrapper').waitFor({ state: 'visible' });
      await page.screenshot({ path: output + '/companies-native-settings.png', fullPage: true });
      await page.goto(url + '/admin/modules');
      const row = page.locator('tr').filter({ has: page.locator('td strong').filter({ hasText: /^Emitentes$|^Empresas(?: emitentes)?$/ }) });
      assert.equal(await row.count(), 0, 'company core is absent from the module catalog');
      assert.equal((await context.request.get(url + '/admin/essential_modules')).status(), 404, 'old wizard route removed');
      // Synthetic isolated schema incompatibility. The original table and all
      // its rows remain intact under a temporary name, restored in finally.
      sql('RENAME TABLE tblissuers_resource_permissions TO tblissuers_resource_permissions_smoke_backup');
      sql('CREATE TABLE tblissuers_resource_permissions (invalid_schema INT) ENGINE=InnoDB');
      let restored = false;
      try {
        let response = await page.goto(url + '/admin');
        assert.equal(response.status(), 200, 'native recovery screen is available for an authenticated administrator');
        await waitForUpgradeForm(page);
        const companyResponse = await context.request.get(url + '/admin/company_context');
        assert.equal(companyResponse.status(), 503, 'company selection remains blocked while the native core is invalid');
        const bancoBrasilResponse = await context.request.get(url + '/admin/banco_brasil');
        assert(bancoBrasilResponse.status() >= 400, 'Banco do Brasil module stays unavailable during native recovery');
        assert.equal(sql("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblissuers_resource_permissions' AND COLUMN_NAME='invalid_schema'"), '1', 'GET never repairs schema');
        await page.screenshot({ path: output + '/native-upgrade-desktop.png', fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'native upgrade fits mobile');
        await page.screenshot({ path: output + '/native-upgrade-mobile.png', fullPage: true });
        response = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]).then(results => results[0]);
        assert.equal(response.status(), 503, 'incompatible schema returns a real failed native update');
        assert(await page.locator('[role=alert]').isVisible());
        assert(await page.locator('#submit_btn').isEnabled(), 'failure preserves an enabled retry');
        assert(!(await page.content()).includes('invalid_schema'), 'SQL details are not exposed');
        sql('DROP TABLE tblissuers_resource_permissions; RENAME TABLE tblissuers_resource_permissions_smoke_backup TO tblissuers_resource_permissions; DELETE FROM tblissuers_lock WHERE id=1');
        restored = true;
        // The missing singleton still requires authorized repair, after the
        // genuine incompatibility has been corrected by the administrator.
        // A normal authenticated notification poll must not consume upgrade feedback
        // before the authorized destination document renders it.
        await page.route(url + '/admin', async route => {
          // Fetch the real authenticated POST without following its redirect.
          // Delay delivery to Chromium until a genuine background poll completes.
          const post = await route.fetch({ maxRedirects: 0 });
          if (post.status() === 303) {
            const poll = await context.request.get(url + '/admin/misc/notifications_check', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            assert.equal(poll.status(), 200, 'real intermediate notification request remains available');
          }
          await route.fulfill({ response: post });
        }, { times: 1 });
        const successPost = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]);
        const successResponse = await successPost;
        assert.equal(successResponse.status(), 303, 'native successful POST redirects with See Other');
        assert.equal(successResponse.headers().refresh, undefined, 'native success does not return an empty Refresh document');
        assert.equal(new URL(page.url()).pathname, '/admin/company_context', 'native success returns to safe isolated company selection');
        assert((await page.content()).includes('atualizado com sucesso'), 'native success alert is delivered');
        await page.getByText('O CRM foi atualizado com sucesso.', { exact: true }).waitFor({ state: 'visible' });
        assert.equal(sql('SELECT COUNT(*) FROM tblissuers_lock WHERE id=1'), '1');
        await page.reload();
        assert.equal(await page.locator('#upgrade_db_form').count(), 0, 'completed update never reopens a wizard');
        assert(!(await page.content()).includes('O CRM foi atualizado com sucesso.'), 'success feedback is delivered only once');
        await page.goto(url + '/admin');
        await shell(page);
      } finally {
        if (!restored) sql('DROP TABLE IF EXISTS tblissuers_resource_permissions; RENAME TABLE tblissuers_resource_permissions_smoke_backup TO tblissuers_resource_permissions');
        sql('INSERT IGNORE INTO tblissuers_lock (id) VALUES (1)');
      }

      // An already identified master may be working in a secondary company.
      // Keep that context during native recovery, without another CLI designation.
      const identityBeforeSecondary = sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1");
      assert(identityBeforeSecondary.startsWith('17:'), 'fresh installer master is already identified before secondary recovery');
      const primaryCompany = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
      if (sql("SELECT COUNT(*) FROM tblissuers_registry WHERE tax_id='52998224725'") === '0') {
        await page.goto(url + '/admin/company_onboarding');
        async function nextSecondaryStep() { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('section form button[type=submit]').click()]); }
        await nextSecondaryStep();
        await page.locator('#legal_name').fill('Synthetic secondary native recovery');
        await page.locator('#tax_id').fill('52998224725');
        await page.locator('#profile_invoice_company_city').fill('Synthetic secondary');
        for (let step = 0; step < 5; step++) await nextSecondaryStep();
        assert((await page.locator('[role=status]').innerText()).includes('Empresa criada'));
      }
      const secondaryCompany = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
      await page.goto(url + '/admin/company_context');
      await page.locator('#company-context-select').selectOption(secondaryCompany);
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.company-selector-form button[type=submit]').click()]);
      assert.equal(await page.locator('#company-context-select').inputValue(), secondaryCompany);
      sql('DELETE FROM tblissuers_lock WHERE id=1');
      await page.goto(url + '/admin');
      await waitForUpgradeForm(page);
      await page.route(url + '/admin', async route => {
        const post = await route.fetch({ maxRedirects: 0 });
        if (post.status() === 303) {
          const poll = await context.request.get(url + '/admin/company_context', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          assert.equal(poll.status(), 200, 'secondary isolated background poll remains available');
          assert(!(await poll.text()).includes('O CRM foi atualizado com sucesso.'), 'XHR does not consume or display native success');
          assert.equal((await context.request.head(url + '/admin/company_context')).status(), 200, 'HEAD preserves native success for the document');
        }
        await route.fulfill({ response: post });
      }, { times: 1 });
      const secondaryPost = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin');
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]);
      const secondaryResponse = await secondaryPost;
      assert.equal(secondaryResponse.status(), 303);
      assert.equal(secondaryResponse.headers().refresh, undefined);
      assert.equal(new URL(page.url()).pathname, '/admin/company_context');
      assert((await page.content()).includes('atualizado com sucesso'), 'native success alert is delivered');
      await page.locator('[role=status].alert-success').waitFor({ state: 'visible' });
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_lock WHERE id=1'), '1');
      assert.equal(sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"), identityBeforeSecondary, 'native upgrade preserves the already identified master without new CLI authorization');
      assert.equal(await page.locator('#company-context-select').inputValue(), secondaryCompany, 'secondary company selection is preserved');
      assert.equal((await context.request.get(url + '/admin/settings?group=update')).status(), 403, 'native feedback never opens global Settings to a secondary company');
      await page.reload();
      assert(!(await page.content()).includes('O CRM foi atualizado com sucesso.'), 'secondary native success is one-shot');
      await page.locator('#company-context-select').selectOption(primaryCompany);
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('.company-selector-form button[type=submit]').click()]);
      await shell(page);
    }
    assert.deepEqual(errors, [], 'no uncaught JavaScript errors in native shell');
    console.log('Issuer browser smoke passed: native shell/menu, installation/upgrade, errors/retry, desktop/mobile.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
