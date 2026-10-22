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
assert.match(selector, /class="sr-only">Selecionar empresa/);
// Responsive classes may be added without changing the native widget contract.
const companySelect = (selector.match(/<select(?=\s|>)[^>]*>/g) || [])
  .find(tag => /\sname="company_id"/.test(tag));
assert.ok(companySelect, 'company_id must remain a select element');
const companySelectClasses = companySelect.match(/\sclass="([^"]*)"/)?.[1].split(/\s+/) || [];
assert.ok(companySelectClasses.includes('selectpicker'), 'company selector must retain the native selectpicker class');
assert.match(selector, /name="_company_switch_csrf"/);
assert.match(selector, /company_context\/select/);
assert.match(selector, /form_open/);
assert.match(selector, /CompanySettingsIdentity/);
assert.match(selector, /->displayName\(\$company\['id'\]\)/);
assert.match(selector, /e\(\$companyDisplayName\)/);
assert.match(selector, /\(principal\)/);
assert.match(selector, /title="Confirmar empresa selecionada" aria-label="Confirmar empresa selecionada"/);
assert.doesNotMatch(selector, />Trocar<|onchange=/);
assert.doesNotMatch(head + tail, /init_head\(|init_tail\(|app_admin_head\(|app_compile_css\(/);
assert.ok(head.indexOf('admin/includes/company_selector.php') < head.indexOf('sidebar-user-profile'), 'isolated selector precedes user profile');
const nativeHeader = fs.readFileSync(path.join(root, 'application/views/admin/includes/header.php'), 'utf8');
const nativeAside = fs.readFileSync(path.join(root, 'application/views/admin/includes/aside.php'), 'utf8');
assert.doesNotMatch(nativeHeader, /includes\/company_selector/);
assert.ok(nativeAside.indexOf('includes/company_selector') < nativeAside.indexOf('sidebar-user-profile'));
const assets = [...head.matchAll(/'(assets\/[^']+\.css)'/g)].map(m => m[1]);
assert.deepEqual(assets, ['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/plugins/bootstrap/css/bootstrap.min.css', 'assets/plugins/bootstrap-select/css/bootstrap-select.min.css', 'assets/plugins/font-awesome/css/all.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css', 'assets/css/company-workspace.css']);
const scripts = [...tail.matchAll(/'(assets\/[^']+\.js)'/g)].map(m => m[1]);
let selectMarkup = selector.slice(selector.indexOf('<div class="company-selector"'), selector.lastIndexOf('<?php } ?>'));
selectMarkup = selectMarkup.replace(/<\?= form_open\([\s\S]*?\?>/, '<form method="post" action="/admin/company_context/select" class="company-selector-form" style="display:flex;align-items:center;gap:.5rem;margin:0">')
  .replace(/<\?= form_close\(\); \?>/, '</form>')
  .replace(/<\?php if \(\$selectedCompanyId[\s\S]*?<\/select>/, '<option value="1" selected>Empresa de teste</option><option value="2">Outra empresa de teste</option></select>')
  .replace(/<\?[\s\S]*?\?>/g, '');
if (process.env.STATIC_ONLY) { console.log('PASS: selector semantics, preserved POST/CSRF markup, isolated assets and no legacy hooks'); process.exit(0); }
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const rel = decodeURIComponent(url.pathname).replace(/^\//, '');
      const file = path.resolve(root, rel);
      if (url.hostname !== 'fixture.test' || !file.startsWith(root + path.sep) || !rel.startsWith('assets/') || !fs.existsSync(file)) return route.abort();
      const ext = path.extname(file);
      await route.fulfill({ body: fs.readFileSync(file), contentType: { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff' }[ext] || 'application/octet-stream' });
    });
    for (const mode of ['isolated', 'native']) {
      const menu = `<aside id="menu" class="sidebar" aria-label="Menu do CRM"><div class="company-sidebar-account">${selectMarkup}<div class="sidebar-user-profile company-user-profile"><span class="company-user-avatar">U</span><span class="company-user-identity"><strong>Usuário sintético</strong><small>teste@example.invalid</small></span></div></div><ul class="nav metis-menu" id="side-menu">${Array.from({length:15},(_,i)=>`<li><details open><summary>Grupo ${i}</summary><ul class="nav nav-second-level"><li><a href="#item-${i}">Recurso ${i}</a></li></ul></details></li>`).join('')}</ul></aside>`;
      const html = `<!doctype html><html><head><base href="http://fixture.test/">${assets.filter(a => mode === 'isolated' || !a.endsWith('company-shell.css')).map(a => `<link rel="stylesheet" href="${a}">`).join('')}</head><body class="admin ${mode === 'isolated' ? 'company-context-shell' : ''}"><header id="header"><button class="company-menu-toggle" aria-controls="menu" aria-expanded="true">Menu</button><span class="installation-name">CRM</span></header>${menu}<div id="wrapper"><main class="content"><section class="company-settings-card"><h1>Clientes</h1><h2>Lista de clientes</h2><form><label for="client-search">Buscar nome ou documento</label><input class="form-control" id="client-search"><button class="btn btn-default">Buscar</button></form></section></main></div>${scripts.map(s=>`<script src="${s}"></script>`).join('')}</body></html>`;
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.setContent(html, { waitUntil: 'load' });
        await page.waitForFunction(() => document.querySelector('.company-selector .bootstrap-select'));
        if (mode === 'isolated' && width < 768) {
          assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'), 'false');
          await page.locator('.company-menu-toggle').click();
        }
        if (mode === 'native') { await page.locator('#menu').evaluate(el => { el.style.display = 'block'; el.style.position = 'fixed'; el.style.top = '57px'; el.style.width = '230px'; }); }
        assert.ok(await page.evaluate(() => document.querySelector('.company-selector').getBoundingClientRect().bottom <= document.querySelector('.sidebar-user-profile').getBoundingClientRect().top + 1), 'company selector is above user profile');
        assert.equal(await page.locator('.company-selector label').evaluate(el => getComputedStyle(el).position), 'absolute');
        assert.equal(await page.locator('.company-selector button[type="submit"]').innerText(), '');
        assert.equal(await page.locator('.company-selector .filter-option').innerText(), 'Empresa de teste');
        await page.evaluate(() => { window.submissions = []; document.querySelector('.company-selector form').addEventListener('submit', e => { e.preventDefault(); window.submissions.push(Object.fromEntries(new FormData(e.target))); }); });
        if (mode === 'isolated' && width < 768) {
          await page.locator('.company-menu-toggle').click();
          assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'), 'false');
        }
        await assert.rejects(() => selectCompanyOption(page, '999'), /authorized enabled catalog/);
        assert.equal(await page.locator('#company-context-select').inputValue(), '1', 'absent company never changes current selection');
        await selectCompanyOption(page, '2');
        if (mode === 'isolated' && width < 768) assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'), 'true', 'driver opens collapsed sidebar through its real button');
        assert.deepEqual(await page.evaluate(() => window.submissions), [], 'changing selected company must not submit');
        await page.getByRole('button', { name: 'Confirmar empresa selecionada' }).click();
        const submitted = await page.evaluate(() => window.submissions);
        assert.equal(submitted.length, 1);
        assert.equal(submitted[0].company_id, '2');
        assert.ok(Object.hasOwn(submitted[0], '_company_switch_csrf'));
        if (mode === 'isolated') {
          assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no horizontal overflow');
          const menu = await page.locator('#menu').evaluate(el => ({ scroll: el.scrollHeight, client: el.clientHeight, overflow: getComputedStyle(el).overflowY }));
          assert.equal(menu.overflow, 'auto');
          assert.ok(menu.scroll > menu.client, 'long menu scrolls separately');
          await page.locator('#menu a').last().scrollIntoViewIfNeeded();
          assert.equal(await page.locator('h1').evaluate(el => getComputedStyle(el).fontSize), '20px');
          await page.locator('.installation-name').evaluate(el => { el.textContent = 'Nome muito longo da instalação para verificar quebra de linha sem sobreposição'; el.style.fontSize = '32px'; });
          assert.ok(await page.evaluate(() => document.querySelector('#menu').getBoundingClientRect().top >= document.querySelector('#header').getBoundingClientRect().bottom - 1), 'menu follows dynamically sized header without overlap');
          assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'long header has no horizontal overflow');
        }
        if (process.env.SCREENSHOT_DIR) await page.screenshot({ path: path.join(process.env.SCREENSHOT_DIR, `company-shell-${mode}-${width}.png`), fullPage: true });
      }
    }
    // Regression: use the real native toggle handler, not the isolated shell toggle.
    // Native page-small starts offscreen without hide-sidebar; one click must open it. The helper must never force the hidden select.
    const mainSource = fs.readFileSync(path.join(root, 'assets/js/main.js'), 'utf8');
    const handlerStart = mainSource.indexOf('  $(".hide-menu").click(function (e) {');
    const handlerEnd = mainSource.indexOf('  // Hide sidebar on content click on mobile', handlerStart);
    assert.ok(handlerStart >= 0 && handlerEnd > handlerStart, 'real native sidebar handler is available');
    const nativeHandler = mainSource.slice(handlerStart, handlerEnd);
    await page.setViewportSize({width:390,height:844});
    await page.setContent(`<!doctype html><html><head><base href="http://fixture.test/">${assets.filter(asset => !asset.endsWith('company-shell.css')).map(asset => `<link rel="stylesheet" href="${asset}">`).join('')}</head><body class="admin page-small"><div id="header"><button class="hide-menu">Menu</button></div><aside id="menu" class="sidebar">${selectMarkup}</aside><div id="wrapper"><main class="content">Synthetic native content</main></div>${scripts.filter(script => !script.endsWith('company-shell.js')).map(script => `<script src="${script}"></script>`).join('')}<script>$(function(){const setup_menu=$('#setup-menu');$('.selectpicker').selectpicker();${nativeHandler}});</script></body></html>`, {waitUntil:'load'});
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('hide-sidebar')), false);
    assert.ok((await page.locator('.company-selector .dropdown-toggle').boundingBox()).x < 0, 'actual native sidebar starts offscreen');
    let nativeClicks = 0;
    await page.exposeFunction('recordNativeSidebarClick', () => { nativeClicks++; });
    await page.locator('#header > .hide-menu').evaluate(button => button.addEventListener('click', () => window.recordNativeSidebarClick()));
    await ensureCompanySidebar(page);
    assert.equal(await page.locator('#header > .hide-menu').evaluate(button => document.activeElement === button), true, 'native opening retains focus on the triggering button');
    assert.equal(await page.locator('#wrapper').evaluate(element => getComputedStyle(element, '::after').display), 'block', 'native mobile overlay remains available');
    await selectCompanyOption(page, '2');
    assert.equal(nativeClicks, 1, 'one user click opens the native mobile sidebar');
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('show-sidebar')), true);
    assert.equal(await page.locator('#company-context-select').inputValue(), '2');
    await page.locator('#header > .hide-menu').focus();
    await page.locator('#header > .hide-menu').press('Enter');
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('hide-sidebar')), true, 'keyboard activation closes sidebar');
    await page.locator('#header > .hide-menu').press('Enter');
    assert.equal(await page.locator('body').evaluate(body => body.classList.contains('show-sidebar')), true, 'keyboard activation reopens sidebar');
    if (process.env.SCREENSHOT_DIR) await page.screenshot({path:path.join(process.env.SCREENSHOT_DIR,'company-native-selector-mobile.png'),fullPage:true});
    assert.deepEqual(errors, []);
    console.log('PASS: isolated/native sidebar, desktop/mobile, explicit company POST, asset order and scroll contracts');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
