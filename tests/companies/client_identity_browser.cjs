const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const render = mode => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'client_identity_fixture.php'), mode], { encoding: 'utf8' });
for (const mode of ['owned', 'legacy']) {
  const html = render(mode); assert(html.includes('RG (opcional)')); assert(html.includes('data-client-lookup="cnpj"')); assert(html.includes('data-client-lookup="cep"'));
  assert(html.includes(mode === 'legacy' ? 'brazil_profile[rg]' : 'client[profile][rg]'));
  assert(!html.includes('jsonp') && html.includes('serviços terceiros'));
}
assert(!fs.readFileSync(path.join(root, 'assets/plugins/masks-cnpj-cep/masks-cnpj-cep.min.js'), 'utf8').includes('$.ajax'));
assert(!fs.readFileSync(path.join(root, 'application/helpers/assets_helper.php'), 'utf8').includes("'assets/plugins/masks-cnpj-cep/masks-cnpj-cep.min.js'"));
if (process.env.STATIC_ONLY) { console.log('PASS actual owned/legacy form fields and retired automatic lookup'); process.exit(0); }
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage(), errors = []; page.on('pageerror', e => errors.push(e.message)); let fail = false, calls = 0;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url()); if (url.hostname !== 'fixture.test') return route.abort();
      if (url.pathname === '/') return route.fulfill({ body: render(url.searchParams.get('mode')), contentType: 'text/html; charset=utf-8' });
      if (url.pathname === '/admin/company_clients/lookup') {
        const data = new URLSearchParams(route.request().postData()); assert.equal(data.get('_company_context'), 'synthetic-context'); assert.equal(data.get('client_id'), '42'); assert.equal(data.get('lookup_csrf'), 'a'.repeat(64)); assert(!Array.from(data.keys()).some(key => key.includes('rg') || key.includes('profile'))); calls++;
        return route.fulfill({ status: fail ? 503 : 200, contentType: 'application/json', body: JSON.stringify(fail ? { error: 'Consulta indisponível.' } : { provider: data.get('kind') === 'cep' ? 'ViaCEP' : 'ReceitaWS', fields: { company: 'Nome consultado', trade_name: '<script>texto</script>', address: 'Rua sintética', address_number: '10', city: 'Cidade teste' }, csrf: { name: 'native_csrf', hash: 'renewed' } }) });
      }
      if (url.pathname.startsWith('/assets/')) return route.fulfill({ body: fs.readFileSync(path.join(root, url.pathname)), contentType: url.pathname.endsWith('.js') ? 'application/javascript' : 'text/css' });
      return route.abort();
    });
    for (const mode of ['owned', 'legacy']) for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 }); await page.goto('https://fixture.test/?mode=' + mode, { waitUntil: 'load' }); await page.waitForFunction(() => document.querySelector('[data-client-identity]').dataset.ready);
      assert((await page.locator('body').textContent()).includes('Inscrição estadual'));
      const prefix = mode === 'legacy' ? 'brazil' : 'edit', vat = mode === 'legacy' ? '#vat' : '#edit_vat', company = mode === 'legacy' ? '#company' : '#edit_company';
      assert.equal(await page.locator('[data-person-field=rg]').isVisible(), false);
      await page.locator('[data-client-lookup=cnpj]').click(); await page.locator('[data-client-preview]').waitFor({ state: 'visible' });
      assert.equal(await page.locator('[data-client-preview-fields] input[type=checkbox]').first().isChecked(), false);
      await page.locator('[data-client-apply]').click(); assert.equal(await page.locator(company).inputValue(), 'Nome existente'); assert.equal(await page.locator('#' + prefix + '_trade_name').inputValue(), '<script>texto</script>');
      assert.equal(await page.locator('input[name=native_csrf]').inputValue(), 'renewed');
      await page.locator('[data-client-lookup=cnpj]').click(); await page.locator('[data-client-preview]').waitFor({ state: 'visible' }); await page.locator('[data-client-preview-fields] input[type=checkbox]').first().check(); await page.locator(company).fill('Edição manual'); await page.locator('[data-client-apply]').click();
      assert.equal(await page.locator(company).inputValue(), 'Edição manual'); assert.match(await page.locator('[data-client-status]').textContent(), /cadastro mudou/); await page.locator('[data-client-cancel]').click();
      await page.locator(vat).fill('39053344705'); await page.locator('#' + prefix + '_person_type').selectOption('pf'); assert.equal(await page.locator('[data-person-field=rg]').isVisible(), true); assert.equal(await page.locator('[data-client-lookup=cnpj]').isDisabled(), true); assert.equal(await page.locator('[data-person-field=state_registration]').isVisible(), false);
      const zip = mode === 'legacy' ? '#zip' : '#edit_zip'; await page.locator(zip).fill('01001-000'); fail = true; await page.locator('[data-client-lookup=cep]').click(); await page.waitForFunction(() => document.querySelector('[data-client-status]').textContent.includes('indisponível')); fail = false; assert.equal(await page.locator(company).inputValue(), 'Edição manual');
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      const out = process.env.SCREENSHOT_DIR || '/tmp/client-profile-screenshots'; fs.mkdirSync(out, { recursive: true }); await page.screenshot({ path: path.join(out, `client-profile-${mode}-${width}.png`), fullPage: true });
    }
    assert.equal(calls, 12); assert.deepEqual(errors, []); console.log('PASS owned/legacy PF/PJ, preview, preserve, manual edit, cancel, failures, responsive and no automatic requests');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
