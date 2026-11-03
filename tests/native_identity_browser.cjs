// Actual PHP partial + actual JS; synthetic provider responses, no live service or customer data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const render = mode => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'native_identity_fixture.php'), mode], { encoding: 'utf8' });
for (const mode of ['registry', 'onboarding']) {
  const html = render(mode);
  for (const key of ['company_trade_name', 'company_state_registration', 'company_address_number', 'company_address_complement', 'company_address_district']) assert(html.includes(`data-company-field="${key}"`));
  assert(html.includes(mode === 'onboarding' ? 'wizard[profile][company_trade_name]' : 'company_profile[company_trade_name]'));
  assert(html.includes('serviços terceiros') && html.includes('A consulta não salva'));
}
if (process.env.STATIC_ONLY) { console.log('PASS actual shared partial: canonical names, both modes, third-party notice'); process.exit(0); }
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage(); const errors = []; page.on('pageerror', e => errors.push(e.message));
    let requests = 0, failure = false;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname !== 'fixture.test') return route.abort();
      if (url.pathname === '/') return route.fulfill({ body: render('registry'), contentType: 'text/html; charset=utf-8' });
      if (url.pathname === '/admin/issuers/lookup') {
        requests++; const data = new URLSearchParams(route.request().postData());
        assert.equal(data.get('_company_context'), 'synthetic-context'); assert.equal(data.get('lookup_csrf'), 'a'.repeat(64));
        return route.fulfill({ status: failure ? 503 : 200, contentType: 'application/json', body: JSON.stringify(failure ? { error: 'Consulta indisponível. Nenhum dado alterado.' } : { provider: 'ReceitaWS', fields: { legal_name: 'Nome consultado', company_trade_name: '<script>teste</script>', invoice_company_address: 'Rua sintética', company_address_number: '10' }, csrf: { name: 'native_csrf', hash: 'renewed-token' } }) });
      }
      if (url.pathname.startsWith('/assets/')) return route.fulfill({ body: fs.readFileSync(path.join(root, url.pathname)), contentType: url.pathname.endsWith('.js') ? 'application/javascript' : 'text/css' });
      return route.abort();
    });
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 }); await page.goto('https://fixture.test/', { waitUntil: 'load' });
      await page.waitForFunction(() => document.querySelector('[data-company-identity]').dataset.ready);
      assert((await page.locator('body').textContent()).includes('Identificação'));
      assert.equal(await page.locator('#company-address').isVisible(), false);
      const formBeforeTabs=await page.locator('form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries())));
      const requestsBeforeTabs=requests;
      assert.equal(await page.getByRole('tab').count(),3,'all native sub-tabs remain reachable');
      await page.getByRole('tab',{name:'1. Identificação',exact:true}).press('ArrowRight');
      assert.equal(await page.getByRole('tab',{name:'2. Endereço',exact:true}).getAttribute('aria-selected'),'true');
      assert.equal(new URL(page.url()).hash,'#company-address','deep link tracks selected tab');
      await page.getByRole('tab',{name:'2. Endereço',exact:true}).press('End');
      assert.equal(await page.locator('#company-contact').isVisible(),true);
      await page.goBack();assert.equal(await page.locator('#company-address').isVisible(),true,'Back restores previous sub-tab');
      await page.goBack();assert.equal(await page.locator('#company-identification').isVisible(),true);
      assert.equal(await page.locator('form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries()))),formBeforeTabs,'tab navigation does not mutate inputs');
      assert.equal(requests,requestsBeforeTabs,'navigation never calls provider');

      await page.locator('[data-company-lookup=cnpj]').click(); await page.locator('[data-lookup-preview]').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#legal_name').inputValue(), 'Nome existente');
      assert.equal(await page.locator('[data-lookup-fields] input[type=checkbox]').first().isChecked(), false);
      await page.locator('[data-lookup-apply]').click();
      assert.equal(await page.locator('#legal_name').inputValue(), 'Nome existente');
      assert.equal(await page.locator('#profile_company_trade_name').inputValue(), '<script>teste</script>');
      assert.equal(await page.locator('input[name=native_csrf]').inputValue(), 'renewed-token');
      await page.locator('a[href="#company-address"]').click();
      assert.equal(await page.locator('#profile_company_address_number').inputValue(), '10');
      assert.equal(await page.locator('#company-address').isVisible(), true);
      await page.locator('a[href="#company-identification"]').click();
      await page.locator('[data-company-lookup=cnpj]').click(); await page.locator('[data-lookup-preview]').waitFor({ state: 'visible' });
      await page.locator('[data-lookup-fields] input[type=checkbox]').first().check();
      await page.locator('#legal_name').fill('Edição posterior'); await page.locator('[data-lookup-apply]').click();
      assert.equal(await page.locator('#legal_name').inputValue(), 'Edição posterior');
      assert.match(await page.locator('[data-lookup-status]').textContent(), /alterou campos/);
      await page.locator('[data-lookup-cancel]').click(); assert.equal(await page.locator('[data-lookup-preview]').isVisible(), false);
      failure = true; await page.locator('[data-company-lookup=cnpj]').click(); await page.waitForFunction(() => document.querySelector('[data-lookup-status]').textContent.includes('indisponível')); failure = false;
      assert.equal(await page.locator('#legal_name').inputValue(), 'Edição posterior');
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'no horizontal overflow');
      const out = process.env.SCREENSHOT_DIR || '/tmp/company-identity-screenshots'; fs.mkdirSync(out, { recursive: true });
      await page.screenshot({ path: path.join(out, `company-identity-${width}.png`), fullPage: true });
    }
    assert.deepEqual(errors, []); assert.equal(requests, 9);
    console.log('PASS lookup preview, selective apply, changed-fields protection, cancel, failure, native CSRF refresh, responsive form');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
