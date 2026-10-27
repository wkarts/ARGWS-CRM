'use strict';
const { selectCompanyOption, ensureCompanySidebar } = require('./helpers/company_selector_driver.cjs');
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
function permissionSchemaSnapshot() {
  return sql("SELECT COALESCE(GROUP_CONCAT(CONCAT(COLUMN_NAME,':',LOWER(COLUMN_TYPE),':',IS_NULLABLE,':',COALESCE(COLLATION_NAME,'none')) ORDER BY ORDINAL_POSITION SEPARATOR '|'),'table_missing') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblissuers_resource_permissions'");
}
function assertNativePermissionSchema(stage) {
  const expected='issuer_id:int unsigned:NO:none|staff_id:int:NO:none|feature:varchar(100):NO:ascii_bin|capability:varchar(100):NO:ascii_bin';
  const actual=permissionSchemaSnapshot();
  assert.equal(actual,expected,stage+': native ACL schema snapshot');
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

// Read-only evidence from the authenticated disposable CRM, not a synthetic DOM.
// Each route is reloaded at its target viewport so native responsive initialization runs.
async function captureRealScreens(page, routes) {
  const originalUrl = page.url(), originalViewport = page.viewportSize();
  try {
    for (const [name, route] of routes) for (const [size, viewport] of [['desktop', {width:1440,height:960}], ['tablet', {width:834,height:1112}], ['mobile', {width:390,height:844}]]) {
      await page.setViewportSize(viewport);
      const response = await page.goto(route, {waitUntil:'load'});
      assert.equal(response.status(), 200, name + ' evidence uses an authorized real document');
      await page.locator('#wrapper').waitFor({state:'visible'});
      if (size === 'mobile') await page.waitForFunction(() => document.body.classList.contains('company-context-shell') || document.body.classList.contains('page-small'));
      await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
      const geometry = await page.evaluate(() => ({viewport:{width:innerWidth,height:innerHeight}, pageWidth:document.documentElement.scrollWidth,
        fitsViewport:document.documentElement.scrollWidth <= innerWidth + 1, bodyClasses:document.body.className,
        elements:Array.from(document.querySelectorAll('#header,#menu,#wrapper,.company-registry,.company-native-settings,[data-company-workspace]')).map(element => {
          const box=element.getBoundingClientRect();return {id:element.id, classes:element.className, left:box.left,right:box.right,width:box.width,scrollWidth:element.scrollWidth};
        })}));
      const prefix = output + '/real-' + name + '-' + size;
      fs.writeFileSync(prefix + '.json', JSON.stringify(geometry,null,2));
      await page.screenshot({path:prefix + '.png',fullPage:true});
      if (size === 'mobile' && name === 'companies-grid') {
        await ensureCompanySidebar(page);
        await page.locator('.company-selector .dropdown-toggle').waitFor({state:'visible'});
        await page.screenshot({path:output + '/real-companies-sidebar-mobile.png',fullPage:true});
      }
    }
  } finally {
    await page.setViewportSize(originalViewport);
    await page.goto(originalUrl, {waitUntil:'load'});
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
  const page = await context.newPage();
  const errors = [];
  const errorDiagnostics = [];
  let nativeCalendarStage = 'not_started';
  const calendarStage = stage => { nativeCalendarStage = stage; console.log('Native calendar browser stage: ' + stage); };
  page.on('pageerror', error => {
    errors.push(error.message);
    // Only code identifiers/static asset locations; never messages, URLs or values.
    const missing = /^([A-Za-z_$][A-Za-z0-9_$]{0,79}) is not defined$/.exec(error.message);
    const property = /\(reading '([A-Za-z_$][A-Za-z0-9_$]{0,79})'\)/.exec(error.message);
    const callable = /^([A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*){0,5}) is not a function$/.exec(error.message);
    const assets = [...String(error.stack || '').matchAll(/\/(assets\/[A-Za-z0-9_./-]+\.js):(\d+):(\d+)/g)]
      .slice(0,5).map(match => ({path:match[1],line:Number(match[2]),column:Number(match[3])}));
    const boundaryCode = /^(?:native|company|issuer)_[a-z0-9_]{1,100}$/.test(error.message)?error.message:null;
    const dataTable = /^DataTables warning: table id=([A-Za-z0-9_-]{1,80}) - /.exec(error.message);
    const tableCategory = dataTable?['Cannot reinitialise DataTable','Requested unknown parameter','Incorrect column count','Ajax error','Invalid JSON response'].find(label=>error.message.includes(label))||'other_table_error':null;
    errorDiagnostics.push({stage:nativeCalendarStage,boundary_code:boundaryCode,
      table:dataTable?dataTable[1]:null,table_category:tableCategory,
      kind:['Error','TypeError','ReferenceError','SyntaxError','RangeError'].includes(error.name)?error.name:'OtherError',
      missing:missing?.[1]||null,property:property?.[1]||null,callable:callable?.[1]||null,assets});
  });
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
      // Legacy access migration preserves the actor's principal membership/default.
      // That authorizes its dashboard, NOT installation-master/company-creation rights.
      assert.equal(sql("SELECT COUNT(*) FROM tblstaff s JOIN tblissuers_staff_defaults d ON d.staff_id=s.staffid JOIN tblissuers_installation_identity i ON i.id=1 AND i.primary_issuer_id=d.issuer_id JOIN tblissuers_registry r ON r.id=d.issuer_id AND r.active=1 JOIN tblissuers_staff m ON m.issuer_id=d.issuer_id AND m.staff_id=s.staffid AND m.can_view=1 WHERE s.email='admin-ci@example.invalid' AND s.active=1"), '1', 'legacy upgrade preserves a valid principal default and membership for this actor');
      const upgradedPrimary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
      assert.equal(new URL(page.url()).pathname.replace(/\/$/, ''), '/admin', 'persisted authorized principal context returns directly to CRM even without master designation');
      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), upgradedPrimary, 'dashboard actually retains the authorized principal context');
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
      const bbRow = page.locator('tr').filter({has: page.locator('td strong').filter({hasText: /^Banco do Brasil - Cobrança e CNAB 240\/400$/})});
      assert.equal(await bbRow.count(), 1, 'Banco do Brasil is visible in the actual native catalog');
      assert.equal(await bbRow.locator('form[action*="modules/activate/banco_brasil"], form[action*="modules/deactivate/banco_brasil"]').count(), 1, 'BB exposes one native availability control without clicking it');
      const idealRow = page.locator('tr').filter({has: page.locator('td strong').filter({hasText: /^iDEAL$/})});
      assert.equal(await idealRow.count(), 1, 'iDEAL remains visible in the actual native catalog');
      const moduleState = () => sql('SELECT CONCAT(module_name,CHAR(58),installed_version,CHAR(58),active) FROM tblmodules ORDER BY module_name');
      const beforeCatalogEvidence = moduleState();
      await captureRealScreens(page, [['module-catalog',url + '/admin/modules'], ['module-maintenance',url + '/admin/modules/maintenance']]);
      assert.equal(moduleState(), beforeCatalogEvidence, 'read-only catalog evidence never activates modules or updates version tracking');
      assert.equal((await context.request.get(url + '/admin/essential_modules')).status(), 404, 'old wizard route removed');
      const recoveryIdentity = sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1");
      const recoveryPrimary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
      assert.equal(recoveryIdentity.split(':')[0], sql("SELECT staffid FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1"), 'fresh recovery actor is the persisted installation master');
      assert.match(recoveryPrimary, /^[1-9]\d*$/);
      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), recoveryPrimary, 'fresh recovery starts in the authorized principal context');
      // Synthetic isolated schema incompatibility. The original table and all
      // its rows remain intact under a temporary name, restored in finally.
      sql('RENAME TABLE tblissuers_resource_permissions TO tblissuers_resource_permissions_smoke_backup');
      sql('CREATE TABLE tblissuers_resource_permissions (invalid_schema INT) ENGINE=InnoDB');
      let restored = false;
      try {
        let response = await page.goto(url + '/admin');
        assert.equal(response.status(), 200, 'native recovery screen is available for an authenticated administrator');
        await waitForUpgradeForm(page);
        const companyResponse = await context.request.get(url + '/admin/company_selection/select');
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
        assertNativePermissionSchema('restored after synthetic invalid-schema rejection');
        restored = true;
        // The missing singleton still requires authorized repair, after the
        // genuine incompatibility has been corrected by the administrator.
        // A normal authenticated notification poll must not consume upgrade feedback
        // before the authorized destination document renders it.
        let successTransportStatus = null;
        let successTransportHeaders = null;
        await page.route(url + '/admin', async route => {
          // Fetch the real authenticated POST without following its redirect.
          // Delay delivery to Chromium until a genuine background poll completes.
          const post = await route.fetch({ maxRedirects: 0 });
          successTransportStatus = post.status();
          successTransportHeaders = post.headers();
          if (post.status() === 303) {
            const poll = await context.request.get(url + '/admin/misc/notifications_check', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            assert.equal(poll.status(), 200, 'real intermediate notification request remains available');
          }
          await route.fulfill({ response: post });
        }, { times: 1 });
        const successPost = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]);
        const successResponse = await successPost;
        assert.equal(successResponse.request().method(), 'POST', 'native repair was submitted through the real form');
        assert.equal(successTransportStatus, 303, 'native successful POST redirects with See Other');
        assert.equal(successTransportHeaders.refresh, undefined, 'native success does not return an empty Refresh document');
        assert.equal(new URL(page.url()).pathname.replace(/\/$/, ''), '/admin', 'valid principal context returns to the native CRM dashboard');
        assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), recoveryPrimary, 'successful repair preserves the principal context');
        assert.equal(sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"), recoveryIdentity, 'repair never changes persisted master or principal identity');
        assert((await page.content()).includes('atualizado com sucesso'), 'native success alert is delivered');
        await page.getByText('O CRM foi atualizado com sucesso.', { exact: true }).waitFor({ state: 'visible' });
        assert.equal(sql('SELECT COUNT(*) FROM tblissuers_lock WHERE id=1'), '1');
        assertNativePermissionSchema('after authorized native recovery');
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
      assert.equal(sql("SELECT COUNT(*) FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1"), '1', 'the authenticated synthetic installer administrator is unique');
      const installerAdminId = sql("SELECT staffid FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1");
      assert.match(installerAdminId, /^[1-9]\d*$/);
      assert.equal(identityBeforeSecondary.split(':')[0], installerAdminId, 'fresh installer master is already identified as the authenticated administrator before secondary recovery');
      const primaryCompany = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
      // The original principal dashboard is also a caller of these shared
      // endpoints. Exercise its real form, not only a request assembled by a test.
      calendarStage('principal_widget_create');
      const eventTitle='Synthetic principal calendar '+Date.now();
      const eventStart=await page.evaluate(()=>new DateFormatter().formatDate((()=>{const day=new Date();day.setHours(10,0,0,0);return day;})(),app.options.date_format+(app.options.time_format==24?' H:i':' g:i A')));
      await page.goto(url+'/admin?new_event=1&date='+encodeURIComponent(eventStart));
      const widgetModal=page.locator('#newEventModal');await widgetModal.waitFor({state:'visible'});
      await widgetModal.locator('[name=title]').fill(eventTitle);
      await widgetModal.locator('[name=description]').fill('Synthetic local calendar event; no notification transport');
      if(await widgetModal.locator('[name=reminder_before]').count())await widgetModal.locator('[name=reminder_before]').fill('0');
      assert.equal(await widgetModal.locator('[name=_native_company_csrf]').count(),1,'principal dashboard form carries the shared endpoint CSRF');
      const widgetSave=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/utilities/calendar');
      await widgetModal.locator('button[type=submit]').click();const widgetSaved=await widgetSave;
      assert.equal(widgetSaved.status(),200,'principal widget saves through the same native calendar controller');
      assert.equal((await widgetSaved.json()).success,true);
      assert.equal(sql("SELECT COUNT(*) FROM tblcompany_events WHERE issuer_id="+Number(primaryCompany)+" AND title='"+eventTitle+"'"),'1','widget writes only the principal company event lineage');
      await page.waitForURL(u=>u.pathname.replace(/\/$/,'')==='/admin'&&u.search==='');
      calendarStage('principal_feed');
      const primaryCalendarFeed=page.waitForResponse(r=>new URL(r.url()).pathname==='/admin/utilities/get_calendar_data');
      assert.equal((await page.goto(url+'/admin/utilities/calendar')).status(),200,'principal uses the original native calendar route');
      const primaryFeedResponse=await primaryCalendarFeed;
      if(primaryFeedResponse.status()!==200){
        const query=new URL(primaryFeedResponse.url()).searchParams,known=new Set(['start','end','calendar_filters','events','tasks','projects','invoices','estimates','proposals','contracts','lead_reminders','customer_reminders','estimate_reminders','invoice_reminders','proposal_reminders','expense_reminders','task_reminders','credit_note_reminders','ticket_reminders']);
        const frameworkName=await page.evaluate(()=>window.NativeCompanyHeaderConfig?.frameworkCsrf?.name||null),fields={},unknown=[];
        for(const[key,value]of query){
          if(known.has(key))fields[key]=(key==='start'||key==='end')&&/^\d{4}-\d{2}-\d{2}(?:T[0-9:+Z.-]+)?$/.test(value)?value:{length:value.length,boolean:['true','1'].includes(value)};
          else unknown.push(key==='_'?'jquery_cache_buster':key==='_company_context'?'context_in_url':key==='_native_company_csrf'?'native_csrf_in_url':key===frameworkName?'framework_csrf_in_url':'other');
        }
        let responseKind='unreadable';try{const type=primaryFeedResponse.headers()['content-type']||'';if(type.includes('application/json')){const body=await primaryFeedResponse.json();responseKind=body&&body.success===false?'native_json_rejection':'other_json';}else{const body=await primaryFeedResponse.text();responseKind=body.includes('A empresa desta aba mudou.')?'company_context_changed':body.includes('Esta operação não está disponível no contexto de empresa atual.')?'company_context_denied':'other_document';}}catch{}
        const requestHeaders=await primaryFeedResponse.request().allHeaders();const evidence={status:primaryFeedResponse.status(),method:primaryFeedResponse.request().method(),fields,unknown,response_kind:responseKind,company_context_header_present:typeof requestHeaders['x-company-context']==='string'};
        fs.writeFileSync(output+'/native-calendar-feed-failure.json',JSON.stringify(evidence,null,2));console.error('Native calendar feed request shape; no token/body values: '+JSON.stringify(evidence));
      }
      assert.equal(primaryFeedResponse.status(),200,'principal historical feed uses the shared authenticated endpoint');
      const primaryCalendarRows=await primaryFeedResponse.json();assert(Array.isArray(primaryCalendarRows)&&primaryCalendarRows.some(row=>row.title===eventTitle&&row.eventid),'the shared feed contains the real event just created by this principal widget');
      const nativeCalendarHeaders=await primaryFeedResponse.request().allHeaders(),nativeCalendarDocumentContext=await page.evaluate(()=>window.NativeCompanyHeaderConfig?.context||null);
      assert(typeof nativeCalendarDocumentContext==='string'&&nativeCalendarHeaders['x-company-context']===nativeCalendarDocumentContext,'native feed sends exactly the captured document context once; tokens omitted');
      assert.equal(await page.locator('body.native-company-admin').count(),1,'principal calendar has the same native presentation as secondary companies');
      assert.equal(await page.locator('#newEventModal').count(),1,'one original event modal and no competing calendar shell');
      assert.equal(await page.locator('#event').count(),1,'native footer provides the same single mount as the legacy CRM for its original event modal');
      assert.equal(await page.locator('#newEventModal [name=_native_company_csrf]').count(),1,'native new-event form also carries one CSRF field rather than duplicate serialization');
      await page.screenshot({path:output+'/native-principal-calendar-before-event.png',fullPage:true,mask:[page.locator('input,textarea')]});
      calendarStage('original_modal_read');
      // The time/dot remain tappable when FullCalendar shrinks its title to
      // zero width in the mobile month grid. Click the actual owned event.
      const eventTarget=page.locator('#calendar .fc-event').filter({has:page.locator('.fc-event-title').filter({hasText:eventTitle})});
      assert.equal(await eventTarget.count(),1,'one actual owned event is the interaction target');
      assert(await eventTarget.isVisible(),'the original event card is visible at the retained mobile viewport');
      const interaction=await eventTarget.evaluate(node=>{
        const event=node.closest('.fc-event'),jq=window.jQuery,listeners=event&&jq&&jq._data?jq._data(event,'events'):null;
        return {event_found:!!event,click_handlers:listeners&&listeners.click?listeners.click.length:0,
          has_href:!!event?.getAttribute('href'),native_body:document.body.classList.contains('native-company-admin'),
          small_body:document.body.classList.contains('page-small'),sidebar_shown:document.body.classList.contains('show-sidebar'),
          view_handler:typeof window.view_event,transport:typeof window.NativeCompanyTransport?.request,
          ancestor_link:!!node.closest('a[href]'),viewport_width:innerWidth,
          card_width:event?.getBoundingClientRect().width||0,title_width:event?.querySelector('.fc-event-title')?.getBoundingClientRect().width||0};
      });
      const calendarRequests=[];
      const recordCalendarRequest=request=>{
        const target=new URL(request.url());
        const kind=target.pathname.startsWith('/admin/utilities/view_event/')?'event_read':target.pathname==='/admin/utilities/calendar'?'calendar_page':target.pathname==='/admin/utilities/get_calendar_data'?'feed':null;
        if(kind&&calendarRequests.length<16)calendarRequests.push({kind,method:request.method(),same_origin:target.origin===url});
      };
      page.on('request',recordCalendarRequest);
      console.log('Native calendar interaction metadata: '+JSON.stringify(interaction));
      fs.writeFileSync(output+'/native-calendar-interaction.json',JSON.stringify({interaction,requests:calendarRequests},null,2));
      let eventRead;
      try {
        const interactionResults=await Promise.allSettled([page.waitForResponse(r=>r.request().method()==='GET'&&new URL(r.url()).pathname.startsWith('/admin/utilities/view_event/')),eventTarget.click()]);
        // If both waits expire, preserve Playwright's actual click/actionability
        // error instead of losing it behind the earlier response timer.
        if(interactionResults[1].status==='rejected')throw interactionResults[1].reason;
        if(interactionResults[0].status==='rejected')throw interactionResults[0].reason;
        eventRead=interactionResults[0].value;
      } finally {
        page.off('request',recordCalendarRequest);
        console.log('Native calendar interaction requests: '+JSON.stringify(calendarRequests));
        fs.writeFileSync(output+'/native-calendar-interaction.json',JSON.stringify({interaction,requests:calendarRequests},null,2));
      }
      assert.equal(eventRead.status(),200,'native page opens the event created by the original principal dashboard');
      const eventModal=page.locator('#viewEvent');await eventModal.waitFor({state:'visible'});assert.equal(await eventModal.locator('[name=revision]').inputValue(),'1');
      assert.equal(await eventModal.locator('[name=_native_company_csrf]').count(),1,'native edit serializes one CSRF value');
      assert.equal(await page.locator('#calendar-event-form').count(),1);assert.equal(await page.locator('#calendar-event-edit-form').count(),1,'new and edit forms have independent DOM IDs');
      await eventModal.locator('[name=description]').fill('Edited through original native calendar modal');
      if(await eventModal.locator('[name=reminder_before]').count())await eventModal.locator('[name=reminder_before]').fill('0');
      calendarStage('original_modal_edit');
      const nativeEventSave=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/utilities/calendar');
      await Promise.all([page.waitForNavigation({waitUntil:'load'}),eventModal.locator('button[type=submit]').click()]);
      assert.equal((await nativeEventSave).status(),200);assert.equal(sql("SELECT revision FROM tblcompany_events WHERE issuer_id="+Number(primaryCompany)+" AND title='"+eventTitle+"'"),'2','same original modal commits the native edit with its matching revision');
      calendarStage('original_filters');
      const beforeCalendarFilter=sql('SELECT SHA2(settings_json,256) FROM tblissuers_registry WHERE id='+Number(primaryCompany)),eventsBeforeFilter=sql('SELECT COUNT(*) FROM tblcompany_events');
      await page.locator('.fc-calendarFilter-button').click();await page.locator('#calendar_filters [name=events]').check();
      const filteredCalendarFeed=page.waitForResponse(r=>new URL(r.url()).pathname==='/admin/utilities/get_calendar_data'&&new URL(r.url()).searchParams.get('calendar_filters')==='true');
      const filterNavigation=await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('#calendar_filters button[type=submit]').click()]);
      assert.equal(filterNavigation[0].status(),200,'original filter form POST renders the same native calendar rather than invoking event creation');
      const filterFeed=await filteredCalendarFeed;assert.equal(filterFeed.status(),200);const filteredEvents=await filterFeed.json();assert(filteredEvents.some(row=>row.title===eventTitle)&&filteredEvents.every(row=>Object.hasOwn(row,'eventid')),'original events checkbox reaches the real feed');
      assert.equal(sql('SELECT SHA2(settings_json,256) FROM tblissuers_registry WHERE id='+Number(primaryCompany)),beforeCalendarFilter,'display filtering does not rewrite company settings');assert.equal(sql('SELECT COUNT(*) FROM tblcompany_events'),eventsBeforeFilter,'display filtering creates no event');
      const oldCalendarFilterForm=await page.locator('#calendar_filters form').evaluate(node=>Object.fromEntries(new FormData(node).entries()));
      calendarStage('original_modal_delete');
      await eventTarget.click();await eventModal.waitFor({state:'visible'});assert.equal(await eventModal.locator('[name=revision]').inputValue(),'2');
      page.once('dialog',dialog=>dialog.accept());
      const eventDeletion=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname.startsWith('/admin/utilities/delete_event/'));
      await Promise.all([page.waitForNavigation({waitUntil:'load'}),eventModal.locator('button.btn-danger').click()]);
      assert.equal((await eventDeletion).status(),200);assert.equal(sql("SELECT COUNT(*) FROM tblcompany_events WHERE issuer_id="+Number(primaryCompany)+" AND title='"+eventTitle+"'"),'0','revision-checked native modal deletion removes only that event');
      const oldCalendarForm=await page.locator('#calendar-event-form').evaluate(node=>Object.fromEntries(new FormData(node).entries()));
      calendarStage('responsive_evidence');
      await captureRealScreens(page,[['native-principal-calendar',url+'/admin/utilities/calendar']]);
      if (sql("SELECT COUNT(*) FROM tblissuers_registry WHERE tax_id='52998224725'") === '0') {
        await page.goto(url + '/admin/company_onboarding');
        await page.locator('#company-create-modal').waitFor({state:'visible'});
        const wizard = page.frame({name:'company-create-frame'});
        await wizard.locator('section form button[type=submit]').waitFor();
        async function nextSecondaryStep() { await Promise.all([wizard.waitForNavigation({ waitUntil: 'domcontentloaded' }), wizard.locator('section form button[type=submit]').click()]); }
        await nextSecondaryStep();
        await wizard.locator('#legal_name').fill('Synthetic secondary native recovery');
        await wizard.locator('#tax_id').fill('52998224725');
        await wizard.locator('a[href="#company-address"]').click(); await wizard.locator('#profile_invoice_company_city').fill('Synthetic secondary');
        for (let step = 0; step < 5; step++) await nextSecondaryStep();
        assert((await wizard.locator('[role=status]').innerText()).includes('Empresa criada'));
      }
      const secondaryCompany = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
      assert.match(secondaryCompany, /^[1-9]\d*$/, 'the secondary company has a stable native identity before selection');
      assertNativePermissionSchema('after secondary company creation');
      sql('UPDATE tblissuers_registry SET tax_id=NULL WHERE id=' + Number(secondaryCompany));
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_registry WHERE id=' + Number(secondaryCompany) + ' AND tax_id IS NULL'), '1', 'company selection does not depend on a CNPJ');
      await page.goto(url + '/admin');
      assertNativePermissionSchema('immediately before secondary company selection');
      const selectionTrace=[];
      const recordSelectionResponse=response=>{
        const request=response.request();
        const pathname=new URL(response.url()).pathname.replace(/\/$/,'')||'/';
        if(pathname!=='/admin/company_selection/select'&&pathname!=='/admin')return;
        const location=response.headers().location;
        let locationPath='';
        if(location){try{locationPath=new URL(location,url).pathname.replace(/\/$/,'')||'/';}catch(_){locationPath='invalid';}}
        selectionTrace.push({method:request.method(),path:pathname,status:response.status(),locationPath});
      };
      page.on('response',recordSelectionResponse);
      try {
        await selectCompanyOption(page, secondaryCompany);
      } catch (error) {
        console.error('Native company selection HTTP trace: ' + JSON.stringify(selectionTrace));
        console.error('Native company ACL schema after selection response: ' + permissionSchemaSnapshot());
        throw error;
      } finally {
        page.off('response',recordSelectionResponse);
      }
      assert.equal(new URL(page.url()).pathname.replace(/\/$/, ''), '/admin', 'company switch stays on the original CRM dashboard URL');
      await shell(page);
      assert.equal(await page.locator('#side-menu .menu-item-dashboard').count(), 1, 'original dashboard menu remains present for the selected company');
      assert.equal(await page.locator('#wrapper .content').count(), 1, 'one native application content root, not a parallel workspace');
      await page.setViewportSize({width:1440,height:960});await page.reload();
      await page.screenshot({path: output + '/native-secondary-dashboard-desktop.png', fullPage:true});
      await page.setViewportSize({width:390,height:844});await page.reload();
      await page.waitForFunction(()=>document.body.classList.contains('page-small'));
      await page.evaluate(async()=>{await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});
      const mobileGeometry=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth,overflow:Array.from(document.querySelectorAll('#header *,#wrapper *')).map(el=>({tag:el.tagName,classes:typeof el.className==='string'?el.className:'',right:el.getBoundingClientRect().right,width:el.getBoundingClientRect().width})).filter(r=>r.right>innerWidth+1&&r.width>0).slice(0,40)}));
      fs.writeFileSync(output+'/native-secondary-dashboard-mobile-geometry.json',JSON.stringify(mobileGeometry,null,2));
      await page.screenshot({path:output+'/native-secondary-dashboard-mobile.png',fullPage:true});
      assert(mobileGeometry.scrollWidth<=mobileGeometry.width+1,'selected-company native dashboard fits mobile: '+JSON.stringify(mobileGeometry));
      await page.screenshot({path: output + '/native-secondary-dashboard-mobile.png', fullPage:true});
      await page.setViewportSize({width:1280,height:900});

      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), secondaryCompany);
      const calendarEventsBeforeStale=sql('SELECT COUNT(*) FROM tblcompany_events');
      assert.equal((await context.request.post(url+'/admin/utilities/calendar',{form:oldCalendarForm,maxRedirects:0})).status(),409,'principal calendar form cannot write after selecting a secondary company');
      assert.equal((await context.request.post(url+'/admin/utilities/calendar',{form:oldCalendarFilterForm,maxRedirects:0})).status(),409,'old filter form cannot emit a primary company page after selecting a secondary company');
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_events'),calendarEventsBeforeStale,'stale calendar submission creates no event');
      const nativeRoutes = ['todo','announcements','taxes','departments','tickets','invoice_items','settings?group=company','utilities/calendar','staff','staff/member'];
      for (const route of nativeRoutes) {
        calendarStage('native_route_'+route.split('?')[0].replaceAll('/','_'));
        let initialTicketTable=null,initialItemTable=null;
        const isItemTable=response=>response.request().method()==='POST'&&new URL(response.url()).pathname==='/admin/invoice_items/table';
        const rememberItemTable=response=>{if(isItemTable(response)&&!initialItemTable)initialItemTable=response;};
        const isTicketTable=response=>response.request().method()==='POST'&&new URL(response.url()).pathname==='/admin/tickets';
        const rememberTicketTable=response=>{if(isTicketTable(response)&&!initialTicketTable)initialTicketTable=response;};
        if(route==='tickets')page.on('response',rememberTicketTable);
        if(route==='invoice_items')page.on('response',rememberItemTable);
        const response = await page.goto(url + '/admin/' + route, {waitUntil:'load'});
        if(response.status()!==200){
          const responseHtml=await response.text();
          const missing=/undefined function ([a-zA-Z0-9_]+)/i.exec(responseHtml);
          fs.writeFileSync(output+'/native-route-failure.json',JSON.stringify({route,status:response.status(),category:missing?'undefined_presentation_helper':'native_route_failed',helper:missing?missing[1]:null},null,2));
        }
        if(response.status()!==200){
          const document=await response.text(),missing=/Call to undefined function ([A-Za-z_][A-Za-z0-9_]{0,79})\(/.exec(document);
          const diagnostic={route:route.split('?')[0],status:response.status(),missing_function:missing?missing[1]:null};
          console.error('Native route document failure: '+JSON.stringify(diagnostic));
          fs.writeFileSync(output+'/native-route-failure.json',JSON.stringify(diagnostic,null,2));
        }
        assert.equal(response.status(), 200, 'selected company uses the original CRM route /admin/' + route);
        await shell(page);
        if(route==='tickets'){
          const tableResponse=initialTicketTable||await page.waitForResponse(isTicketTable);
          page.off('response',rememberTicketTable);
          assert.equal(tableResponse.status(),200,'native ticket table responds through the real guarded POST');
          assert(Array.isArray((await tableResponse.json()).aaData),'native Tickets returns the original DataTable rows');
          await page.waitForFunction(()=>!!document.querySelector('#vueApp')?.__vue_app__&&typeof window.vNewApp==='function');
          assert.equal(await page.locator('#vueApp app-filters').count(),0,'the original Vue bundle hydrates the existing filter component');
          const labels=await page.evaluate(()=>({newFilter:app.lang.filter_new,apply:app.lang.filter_apply}));
          assert(labels.newFilter&&labels.newFilter!=='filter_new'&&labels.apply&&labels.apply!=='filter_apply','native filter labels come from the trusted PT-BR catalog');
          const filterToggle=page.locator('#vueApp button.dropdown-toggle').filter({has:page.locator('.fa-filter')});
          await filterToggle.click();
          const clearFilterResponse=page.waitForResponse(isTicketTable);
          await page.locator('#vueApp .dropdown-menu').getByRole('link',{name:labels.newFilter,exact:true}).click();
          const clearFilter=await clearFilterResponse;
          assert.equal(clearFilter.status(),200,'clearing the original Vue filter accepts the actual form-encoded empty rules');
          assert(Array.isArray((await clearFilter.json()).aaData));
          const filterModal=page.locator('.filters-modal#tickets');await filterModal.waitFor({state:'visible'});
          const rules=filterModal.locator('#ticketsRules'),rulePicker=filterModal.locator('.bootstrap-select').filter({has:page.locator('#ticketsRules')});
          const subjectLabel=(await rules.locator('option[value=subject]').textContent()).trim();
          await rulePicker.locator('button.dropdown-toggle').click();
          await rulePicker.locator('.dropdown-menu a[role=option]').getByText(subjectLabel,{exact:true}).click();
          const subject=filterModal.locator('input[type=text]:visible');
          assert.equal(await subject.count(),1,'new subject rule uses the original text input');
          await subject.fill('Synthetic native filter');
          const filteredTable=page.waitForResponse(isTicketTable);
          await filterModal.locator('.modal-footer button.btn-primary').click();
          const filteredResponse=await filteredTable;assert.equal(filteredResponse.status(),200,'original Vue filter reaches the company-scoped ticket query');
          assert(Array.isArray((await filteredResponse.json()).aaData));await filterModal.waitFor({state:'hidden'});
        }
        assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), secondaryCompany, 'native route keeps the selected company: ' + route);
        assert.equal(await page.locator('#wrapper .content').count(), 1, 'native route keeps one original CRM content root: ' + route);
        await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
        assert.equal(await page.locator('.company-selector-form').evaluate(form=>!!window.jQuery(form).data('validator')),false,'business form validators never claim the company selector: '+route);
        if(route==='invoice_items'){
          const tableResponse=initialItemTable||await page.waitForResponse(isItemTable);page.off('response',rememberItemTable);
          assert.equal(tableResponse.status(),200,'original item DataTable uses the guarded company endpoint');
          assert(Array.isArray((await tableResponse.json()).aaData));
          const modal=page.locator('#sales_item_modal'),form=modal.locator('#invoice_item_form');
          await page.locator('#wrapper ._buttons [data-target="#sales_item_modal"]').click();
          await modal.waitFor({state:'visible'});
          await form.locator('[name=description]').fill('Synthetic native catalog');
          await form.locator('[name=rate]').fill('17.50');
          await form.locator('[name=unit]').fill('un');
          const isItemSave=response=>response.request().method()==='POST'&&new URL(response.url()).pathname==='/admin/invoice_items/manage';
          const created=await Promise.all([page.waitForResponse(isItemSave),form.locator('button[type=submit]').click()]);
          assert.equal(created[0].status(),200,'item creation posts the original form');
          const receipt=await created[0].json();assert.equal(receipt.success,true);
          const itemId=String(receipt.item.id);assert.match(itemId,/^[1-9][0-9]{0,15}$/);
          await modal.waitFor({state:'hidden'});
          const itemRead=page.waitForResponse(response=>response.request().method()==='GET'&&new URL(response.url()).pathname==='/admin/invoice_items/get_item_by_id/'+itemId);
          await page.locator('.table-invoice-items a[data-id="'+itemId+'"]').click();
          assert.equal((await itemRead).status(),200,'typed item identity opens the original editor');
          await modal.waitFor({state:'visible'});
          assert.equal(await form.locator('[name=description]').inputValue(),'Synthetic native catalog');
          const oldItemForm=await form.evaluate(node=>Object.fromEntries(new FormData(node).entries()));
          await form.locator('[name=description]').fill('Synthetic native catalog edited');
          const edited=await Promise.all([page.waitForResponse(isItemSave),form.locator('button[type=submit]').click()]);
          assert.equal(edited[0].status(),200,'item editing posts the same original form');
          const editedReceipt=await edited[0].json();assert.equal(editedReceipt.success,true);
          assert.notEqual(String(editedReceipt.item.revision),String(receipt.item.revision),'native editor advances the actual item revision');
          await modal.waitFor({state:'hidden'});
          assert.equal(sql("SELECT COUNT(*) FROM tblcompany_items WHERE issuer_id="+secondaryCompany+" AND description='Synthetic native catalog edited'"),'1');
          assert.equal(sql("SELECT COUNT(*) FROM tblcompany_items WHERE issuer_id="+primaryCompany+" AND description='Synthetic native catalog edited'"),'0','secondary item never appears in the primary catalogue');
          await selectCompanyOption(page,primaryCompany);
          assert.equal((await context.request.post(url+'/admin/invoice_items/manage',{form:oldItemForm,maxRedirects:0})).status(),409,'old original item form cannot mutate after a company switch');
          await selectCompanyOption(page,secondaryCompany);
        }
        if(route==='departments'){
          await selectCompanyOption(page,primaryCompany);
          assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'),primaryCompany,'company selection from Departments performs the real native POST');
          await selectCompanyOption(page,secondaryCompany);
          assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'),secondaryCompany,'principal to secondary return remains native after Departments');
        }
      }
      // Same production controller/views and DataTable transport, under the selected company.
      const staffRows = page.waitForResponse(r => r.request().method()==='POST' && new URL(r.url()).pathname==='/admin/staff');
      assert.equal((await page.goto(url+'/admin/staff')).status(),200);
      const staffTableResponse=await staffRows;assert.equal(staffTableResponse.status(),200);
      const staffTable=await staffTableResponse.json();assert(Array.isArray(staffTable.aaData));
      await page.goto(url+'/admin/staff/member');
      const nativeStaffForm=page.locator('.staff-form');
      assert.equal(await nativeStaffForm.locator('input[name=native_staff_form_revision]').count(),1);
      await nativeStaffForm.locator('input[name=firstname]').fill('Synthetic native');
      await nativeStaffForm.locator('input[name=lastname]').fill('Staff');
      const nativeStaffEmail='native-stack-'+secondaryCompany+'@example.invalid';
      await nativeStaffForm.locator('input[name=email]').fill(nativeStaffEmail);
      await nativeStaffForm.locator('input[name=password]').fill('synthetic-native-stack-password');
      if(await nativeStaffForm.locator('input[name=send_welcome_email]').count()) await nativeStaffForm.locator('input[name=send_welcome_email]').uncheck();
      const oldStaffForm=await nativeStaffForm.evaluate(node=>Object.fromEntries(new FormData(node).entries()));
      const createdStaffResponse=await Promise.all([page.waitForNavigation({waitUntil:'load'}),nativeStaffForm.locator('button[type=submit]').click()]);
      assert.equal(createdStaffResponse[0].status(),200,'native create returns to the same original member form');
      const nativeStaffId=sql("SELECT staffid FROM tblstaff WHERE email='"+nativeStaffEmail+"'");
      assert.match(nativeStaffId,/^[1-9]\d*$/);assert.equal(new URL(page.url()).pathname,'/admin/staff/member/'+nativeStaffId);
      assert.equal(sql('SELECT issuer_id FROM tblissuers_staff WHERE staff_id='+nativeStaffId),secondaryCompany,'creation belongs only to the selected company');
      assert.equal(sql('SELECT admin FROM tblstaff WHERE staffid='+nativeStaffId),'0','company creation grants no global administrator');
      await selectCompanyOption(page,primaryCompany);
      assert.equal((await context.request.post(url+'/admin/staff/member',{form:oldStaffForm,maxRedirects:0})).status(),409,'old native Staff form cannot write after a company switch');
      await selectCompanyOption(page,secondaryCompany);
      assert.equal((await page.goto(url+'/admin/staff/member/'+nativeStaffId)).status(),200);
      await captureRealScreens(page,[['native-staff-table',url+'/admin/staff'],['native-staff-member',url+'/admin/staff/member/'+nativeStaffId]]);
      await page.goto(url + '/admin');
      await shell(page);
      sql('DELETE FROM tblissuers_lock WHERE id=1');
      await page.goto(url + '/admin');
      await waitForUpgradeForm(page);
      let secondaryTransportStatus = null;
      let secondaryTransportHeaders = null;
      await page.route(url + '/admin', async route => {
        const post = await route.fetch({ maxRedirects: 0 });
        secondaryTransportStatus = post.status();
        secondaryTransportHeaders = post.headers();
        if (post.status() === 303) {
          const poll = await context.request.get(url + '/admin', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          assert.equal(poll.status(), 200, 'secondary isolated background poll remains available');
          assert(!(await poll.text()).includes('O CRM foi atualizado com sucesso.'), 'XHR does not consume or display native success');
          assert.equal((await context.request.head(url + '/admin')).status(), 200, 'HEAD preserves native success for the document');
        }
        await route.fulfill({ response: post });
      }, { times: 1 });
      const secondaryPost = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin');
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('#submit_btn').click()]);
      const secondaryResponse = await secondaryPost;
      assert.equal(secondaryResponse.request().method(), 'POST');
      assert.equal(secondaryTransportStatus, 303);
      assert.equal(secondaryTransportHeaders.refresh, undefined);
      assert.equal(new URL(page.url()).pathname.replace(/\/$/, ''), '/admin');
      assert((await page.content()).includes('atualizado com sucesso'), 'native success alert is delivered');
      await page.locator('[role=status].alert-success').waitFor({ state: 'visible' });
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_lock WHERE id=1'), '1');
      assert.equal(sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"), identityBeforeSecondary, 'native upgrade preserves the already identified master without new CLI authorization');
      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), secondaryCompany, 'secondary company selection is preserved');
      assert.equal((await context.request.get(url + '/admin/settings?group=update')).status(), 403, 'native feedback never opens global Settings to a secondary company');
      await page.reload();
      assert(!(await page.content()).includes('O CRM foi atualizado com sucesso.'), 'secondary native success is one-shot');
      await selectCompanyOption(page, primaryCompany);

      await shell(page);
      await page.goto(url + '/admin/issuers');
      const selectedCompanyLabel = (await page.locator('.company-selector [data-company-current-label]').textContent()).trim();
      assert.ok(selectedCompanyLabel.replace(/\(principal\)/g, '').trim(), 'empty optional display identity retains the authorized canonical company name');
      await captureRealScreens(page, [['companies-grid',url + '/admin/issuers'], ['company-settings',url + '/admin/settings?group=company']]);
    }
    calendarStage('completed');
    assert.deepEqual(errors, [], 'no uncaught JavaScript errors in native shell');
    console.log('Issuer browser smoke passed: native shell/menu, installation/upgrade, errors/retry, desktop/mobile.');
  } catch (error) {
    // Closed stage names locate an actual browser failure without logging form,
    // response, URL query, company context, CSRF or credential values.
    const diagnostic = {calendar_stage:nativeCalendarStage, page_error_count:errors.length,
      missing_identifiers:errors.map(message => /^([A-Za-z_$][A-Za-z0-9_$]{0,79}) is not defined$/.exec(message)?.[1]).filter(Boolean), error_diagnostics:errorDiagnostics};
    console.error('Native browser failure stage: ' + JSON.stringify(diagnostic));
    fs.writeFileSync(output + '/native-browser-failure-stage.json', JSON.stringify(diagnostic,null,2));
    throw error;
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
