const { selectCompanyOption, ensureCompanySidebar } = require('./helpers/company_selector_driver.cjs');
// Synthetic UI contract: no application login, database, or production requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const selector = fs.readFileSync(path.join(root, 'application/views/admin/includes/company_selector.php'), 'utf8');
const head = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_head.php'), 'utf8');
const tail = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_tail.php'), 'utf8');
assert.match(selector, /data-company-selector-toggle/);
assert.match(selector, /type="submit" name="company_id"/);
assert.match(selector, /data-company-choice/);
assert.doesNotMatch(selector, /<select|Confirmar empresa selecionada/);
assert.match(selector, /name="_company_switch_csrf"/);
assert.match(selector, /company_context\/select/);
assert.match(selector, /form_open/);
assert.match(selector, /CompanySettingsIdentity/);
assert.match(selector, /->displayName\(\$company\['id'\]\)/);
assert.match(selector, /e\(\$companySelectorNames\[\$companySelectorId\]\)/);
assert.match(selector, /\(principal\)/);

assert.doesNotMatch(selector, />Trocar<|onchange=/);
assert.doesNotMatch(head + tail, /init_head\(|init_tail\(|app_admin_head\(|app_compile_css\(/);
assert.ok(head.indexOf('admin/includes/company_selector.php') < head.indexOf('sidebar-user-profile'), 'isolated selector precedes user profile');
const nativeHeader = fs.readFileSync(path.join(root, 'application/views/admin/includes/header.php'), 'utf8');
const nativeAside = fs.readFileSync(path.join(root, 'application/views/admin/includes/aside.php'), 'utf8');
const nativeAccountClasses = nativeAside.match(/<div class="([^"]*company-sidebar-account[^"]*)">/)?.[1];
assert.ok(nativeAccountClasses, 'native sidebar account wrapper is preserved in the focused fixture');
assert.doesNotMatch(nativeHeader, /includes\/company_selector/);
assert.ok(nativeAside.indexOf('includes/company_selector') < nativeAside.indexOf('sidebar-user-profile'));
const assets = [...head.matchAll(/'(assets\/[^']+\.css)'/g)].map(m => m[1]);
assert.deepEqual(assets, ['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/builds/vendor-admin.css', 'assets/plugins/font-awesome/css/fontawesome.min.css', 'assets/plugins/font-awesome/css/brands.min.css', 'assets/plugins/font-awesome/css/solid.min.css', 'assets/plugins/font-awesome/css/regular.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css', 'assets/css/company-workspace.css']);
const scripts = [...tail.matchAll(/'(assets\/[^']+\.js)'/g)].map(m => m[1]);
if (process.env.STATIC_ONLY) { console.log('PASS: selector semantics, preserved POST/CSRF markup, isolated assets and no legacy hooks'); process.exit(0); }
const {execFileSync} = require('node:child_process');
execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support/CompanyShellRouteFixture.php')], {stdio:'inherit'});
const renderSelector = selected => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support/NativeHeaderFixture.php'), '--selector-only', ...(selected === '2' ? ['--selected-two'] : [])], {encoding:'utf8'});
const markup = {'1': renderSelector('1'), '2': renderSelector('2')};
let currentHtml = '', selected = '1';
const submissions = [];
const pageHtml = () => currentHtml.replace('<!--ACTUAL_SELECTOR-->', markup[selected]);
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin === 'http://fixture.test' && url.pathname === '/admin/company_selection/select') {
        assert.equal(route.request().method(), 'POST');
        const fields = new URLSearchParams(route.request().postData());
        assert.deepEqual(fields.getAll('company_id').length, 1, 'native submitter supplies one company ID');
        assert.equal(fields.get('_company_switch_csrf'), 'synthetic-fixture-only');
        assert.ok(['1', '2'].includes(fields.get('company_id')));
        submissions.push(Object.fromEntries(fields)); selected = fields.get('company_id');
        return route.fulfill({status:200, contentType:'text/html', body:pageHtml()});
      }
      const rel = decodeURIComponent(url.pathname).replace(/^\//, '');
      const file = path.resolve(root, rel);
      if (url.hostname !== 'fixture.test' || !file.startsWith(root + path.sep) || !rel.startsWith('assets/') || !fs.existsSync(file)) return route.abort();
      const ext = path.extname(file);
      await route.fulfill({ body: fs.readFileSync(file), contentType: { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff' }[ext] || 'application/octet-stream' });
    });
    for (const mode of ['isolated', 'native']) {
      const menu = `<aside id="menu" class="sidebar" aria-label="Menu do CRM" ${mode === 'native' ? 'style="display:block;position:fixed;top:57px;width:230px"' : ''}><div class="company-sidebar-account"><!--ACTUAL_SELECTOR--><div class="sidebar-user-profile company-user-profile"><span class="company-user-avatar">U</span><span class="company-user-identity"><strong>Usuário sintético</strong><small>teste@example.invalid</small></span></div></div><ul class="nav metis-menu" id="side-menu">${Array.from({length:15},(_,i)=>`<li><details open><summary>Grupo ${i}</summary><ul class="nav nav-second-level"><li><a href="#item-${i}">Recurso ${i}</a></li></ul></details></li>`).join('')}</ul></aside>`;
      const html = `<!doctype html><html><head><base href="http://fixture.test/">${assets.filter(a => mode === 'isolated' || !a.endsWith('company-shell.css')).map(a => `<link rel="stylesheet" href="${a}">`).join('')}</head><body class="admin ${mode === 'isolated' ? 'company-context-shell' : ''}"><header id="header"><button class="company-menu-toggle" aria-controls="menu" aria-expanded="true">Menu</button><span class="installation-name">CRM</span></header>${menu}<div id="wrapper"><main class="content"><section class="company-settings-card"><h1>Clientes</h1><h2>Lista de clientes</h2><form><label for="client-search">Buscar nome ou documento</label><input class="form-control" id="client-search"><button class="btn btn-default">Buscar</button></form></section></main></div>${scripts.map(s=>`<script src="${s}"></script>`).join('')}</body></html>`;
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        selected = '1'; submissions.length = 0; currentHtml = html;
        await page.setContent(pageHtml(), { waitUntil: 'load' });
        await page.locator('[data-company-selector-toggle]').waitFor({state:'attached'});
        if (mode === 'isolated' && width < 768) {
          assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'), 'false');
          await page.locator('.company-menu-toggle').click();
        }
        assert.ok(await page.evaluate(() => document.querySelector('.company-selector').getBoundingClientRect().bottom <= document.querySelector('.sidebar-user-profile:not(.company-selector)').getBoundingClientRect().top + 1), 'company selector is above user profile');
        assert.equal(await page.locator('[data-company-current-label]').innerText(), 'Empresa sintética CI');
        const toggle = page.locator('[data-company-selector-toggle]');
        await toggle.focus(); await toggle.press('ArrowDown');
        assert.equal(await page.locator('[data-company-choice="1"]').evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('End');
        assert.equal(await page.locator('[data-company-choice="2"]').evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Home'); await page.keyboard.press('ArrowUp');
        assert.equal(await page.locator('[data-company-choice="2"]').evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await toggle.evaluate(el => el === document.activeElement), true);
        if (mode === 'isolated' && width < 768) {
          await page.locator('.company-menu-toggle').click();
          assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'), 'false');
        }
        await assert.rejects(() => selectCompanyOption(page, '999'), /authorized enabled catalog/);
        assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), '1', 'absent company never changes current context');
        await selectCompanyOption(page, '2');
        assert.equal(submissions.length, 1, 'choosing a visible item posts immediately and exactly once');
        assert.equal(submissions[0].company_id, '2');
        await ensureCompanySidebar(page);
        if (mode === 'isolated') {
          assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no horizontal overflow');
          const menu = await page.locator('#menu').evaluate(el => ({ scroll: el.scrollHeight, client: el.clientHeight, overflow: getComputedStyle(el).overflowY }));
          assert.equal(menu.overflow, 'visible');
          assert.ok(menu.scroll > menu.client, 'long menu extends document instead of owning a fixed scrollport');
          await page.locator('#menu a').last().scrollIntoViewIfNeeded();
          assert.ok(await page.evaluate(() => window.scrollY > 0), 'long menu uses document scrolling');
          assert.equal(await page.locator('h1').evaluate(el => getComputedStyle(el).fontSize), '20px');
          await page.locator('.installation-name').evaluate(el => { el.textContent = 'Nome muito longo da instalação para verificar quebra de linha sem sobreposição'; el.style.fontSize = '32px'; });
          assert.ok(await page.evaluate(() => document.querySelector('.company-selector').getBoundingClientRect().top >= document.querySelector('#header').getBoundingClientRect().bottom - 1), 'selector clears the in-flow native header');
          assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'long header has no horizontal overflow');
        }
        if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: path.join(process.env.SCREENSHOT_DIR, `company-shell-${mode}-${width}.png`), fullPage: true });
      }
    }
    // Regression: use the real native toggle handler, not the isolated shell toggle.
    // Native page-small starts offscreen without hide-sidebar; one click must open it. No hidden-control forcing.
    const mainSource = fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8');
    const handlerStart = mainSource.indexOf('  $(".hide-menu").click(function (e) {');
    const handlerEnd = mainSource.indexOf('  // Hide sidebar on content click on mobile', handlerStart);
    assert.ok(handlerStart >= 0 && handlerEnd > handlerStart, 'real native sidebar handler is available');
    const nativeHandler = mainSource.slice(handlerStart, handlerEnd);
    await page.setViewportSize({width:390,height:844});
    currentHtml = `<!doctype html><html><head><base href="http://fixture.test/">${assets.filter(asset => !asset.endsWith('company-shell.css')).map(asset => `<link rel="stylesheet" href="${asset}">`).join('')}</head><body class="admin page-small"><div id="header"><button class="hide-menu">Menu</button></div><aside id="menu" class="sidebar"><div class="${nativeAccountClasses}"><!--ACTUAL_SELECTOR--></div></aside><div id="wrapper"><main class="content">Synthetic native content</main></div>${scripts.filter(script => !script.endsWith('company-shell.js')).map(script => `<script src="${script}"></script>`).join('')}<script>$(function(){const setup_menu=$('#setup-menu');$('.selectpicker').selectpicker();${nativeHandler}});</script></body></html>`;
    selected = '1'; submissions.length = 0; await page.setContent(pageHtml(), {waitUntil:'load'});
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('hide-sidebar')), false);
    assert.ok((await page.locator('.company-selector [data-company-selector-toggle]').boundingBox()).x < 0, 'actual native sidebar starts offscreen');
    let nativeClicks = 0;
    await page.exposeFunction('recordNativeSidebarClick', () => { nativeClicks++; });
    await page.locator('#header > .hide-menu').evaluate(button => button.addEventListener('click', () => window.recordNativeSidebarClick()));
    await ensureCompanySidebar(page);
    assert.ok(await page.locator('.company-selector').evaluate(element => element.getBoundingClientRect().top >= document.querySelector('#header').getBoundingClientRect().bottom), 'real native account spacing keeps selector below the header');
    assert.equal(await page.locator('#header > .hide-menu').evaluate(button => document.activeElement === button), true, 'native opening retains focus on the triggering button');
    assert.equal(await page.locator('#wrapper').evaluate(element => getComputedStyle(element, '::after').display), 'block', 'native mobile overlay remains available');
    await selectCompanyOption(page, '2');
    assert.equal(nativeClicks, 1, 'one user click opens the native mobile sidebar');
    assert.equal(submissions.length, 1);
    assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), '2');
    await ensureCompanySidebar(page);
    await page.locator('#header > .hide-menu').focus();
    await page.locator('#header > .hide-menu').press('Enter');
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('hide-sidebar')), true, 'keyboard activation closes sidebar');
    await page.locator('#header > .hide-menu').press('Enter');
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('show-sidebar')), true, 'keyboard activation reopens sidebar');
    if (process.env.SCREENSHOT_DIR) await page.screenshot({path:path.join(process.env.SCREENSHOT_DIR,'company-native-selector-mobile.png'),fullPage:true});
    for (const key of ['Enter', 'Space']) {
      await ensureCompanySidebar(page);
      await page.locator('[data-company-selector-toggle]').click();
      const choice = page.locator('[data-company-choice="1"]');
      await choice.focus();
      const count = submissions.length;
      await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}), choice.press(key)]);
      assert.equal(submissions.length, count + 1, key + ' submits exactly once');
      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), '1');
    }
    assert.deepEqual(errors, []);
    console.log('PASS: isolated/native sidebar, desktop/mobile, explicit company POST, asset order and scroll contracts');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
