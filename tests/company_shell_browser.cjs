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
const assets = [...head.matchAll(/'(assets\/[^']+\.css)'/g)].map(m => m[1]);
assert.deepEqual(assets, ['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/plugins/bootstrap/css/bootstrap.min.css', 'assets/plugins/bootstrap-select/css/bootstrap-select.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css']);
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
      const html = `<!doctype html><html><head><base href="http://fixture.test/">${assets.filter(a => mode === 'isolated' || !a.endsWith('company-shell.css')).map(a => `<link rel="stylesheet" href="${a}">`).join('')}</head><body class="admin ${mode === 'isolated' ? 'company-context-shell' : ''}"><header id="header" style="display:flex;align-items:center;justify-content:space-between"><span class="installation-name">CRM</span>${selectMarkup}</header>${mode === 'isolated' ? `<nav id="menu" aria-label="Menu do CRM">${Array.from({length:40},(_,i)=>`<a href="#item-${i}">Recurso de teste ${i}</a>`).join('')}</nav><div id="wrapper"><main class="content"><section class="company-settings-card"><h1>Clientes da empresa</h1><h2>Lista de clientes</h2><form><label for="client-search">Buscar nome ou documento</label><input class="form-control" id="client-search"><button class="btn btn-default">Buscar</button></form><h2>Emitir fatura</h2><form><label for="invoice-client">Cliente autorizado</label><select class="form-control" id="invoice-client"><option>Cliente sintético</option></select><label for="invoice-date">Emissão</label><input class="form-control" id="invoice-date" type="date"><button class="btn btn-primary">Emitir fatura da empresa</button></form></section></main></div>` : ''}${scripts.map(s=>`<script src="${s}"></script>`).join('')}</body></html>`;
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.setContent(html, { waitUntil: 'load' });
        await page.waitForFunction(() => document.querySelector('.company-selector .bootstrap-select'));
        assert.equal(await page.locator('.company-selector label').evaluate(el => getComputedStyle(el).position), 'absolute');
        assert.equal(await page.locator('.company-selector button[type="submit"]').innerText(), '');
        assert.equal(await page.locator('.company-selector .filter-option').innerText(), 'Empresa de teste');
        await page.evaluate(() => { window.submissions = []; document.querySelector('.company-selector form').addEventListener('submit', e => { e.preventDefault(); window.submissions.push(Object.fromEntries(new FormData(e.target))); }); });
        await page.locator('.company-selector .dropdown-toggle').click();
        await page.locator('.bs-container .dropdown-menu a').filter({ hasText: 'Outra empresa de teste' }).click();
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
    assert.deepEqual(errors, []);
    console.log('PASS: isolated/native header, desktop/mobile, explicit company POST, asset order and scroll contracts');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
