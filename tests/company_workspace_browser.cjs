// Synthetic DOM with repository assets. No app login, production or database access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:process.env.CHROMIUM_PATH || undefined});
  try {
    for (const width of [1440,390]) {
      const page = await browser.newPage({ viewport:{width,height:900} });
      await page.setContent(`<section data-company-workspace class="company-settings-card"><h1>Faturas</h1><h2>Lista de faturas</h2><table><tbody><tr><td>FAT-001</td></tr></tbody></table><h2 data-workspace-create data-workspace-label="Nova fatura">Emitir fatura</h2><form action="/admin/company_invoices" method="post"><input type="hidden" name="company_invoices_csrf" value="synthetic"><input type="hidden" name="_company_context" value="context-a"><input name="invoice[name]" id="invoice-name" required><button type="submit">Emitir</button></form><h2>Recebimentos</h2><form action="/admin/company_invoices" method="post"><input type="hidden" name="action" value="payment"><input name="amount" value="10"><button>Receber</button></form></section>`);
      const before = await page.locator('form').evaluateAll(forms => forms.map(f => ({action:f.getAttribute('action'), method:f.method, data:[...new FormData(f)]})));
      await page.addStyleTag({path:path.resolve(__dirname,'../assets/css/company-workspace.css')});
      await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/company-workspace.js')});
      assert.equal(await page.locator('[role=tabpanel]:visible').count(),1);
      assert.match(await page.locator('[role=tabpanel]:visible').innerText(),/Lista de faturas/);
      await page.getByRole('button',{name:'Nova fatura',exact:true}).click();
      assert.equal(await page.locator('#invoice-name').isVisible(),true);
      await page.locator('#invoice-name').fill('Documento sintético');
      await page.getByRole('tab',{name:'Recebimentos',exact:true}).click();
      assert.equal(await page.locator('#invoice-name').isVisible(),false);
      await page.getByRole('button',{name:'Nova fatura',exact:true}).click();
      assert.equal(await page.locator('#invoice-name').inputValue(),'Documento sintético');
      await page.locator('#invoice-name').fill('');
      assert.deepEqual(await page.locator('form').evaluateAll(forms => forms.map(f => ({action:f.getAttribute('action'), method:f.method, data:[...new FormData(f)]}))),before,'tokens, fields, method and destinations preserved');
      await page.getByRole('tab',{name:'Lista de faturas',exact:true}).click();
      await page.getByRole('tab',{name:'Lista de faturas',exact:true}).focus();
      await page.keyboard.press('ArrowRight');
      assert.equal(await page.getByRole('tab',{name:'Recebimentos',exact:true}).getAttribute('aria-selected'),'true');
      await page.evaluate(() => document.querySelector('#invoice-name').dispatchEvent(new Event('invalid',{bubbles:false,cancelable:true})));
      assert.equal(await page.locator('#invoice-name').isVisible(),true,'invalid input reveals its panel');
      const count = await page.locator('[role=tabpanel]').count();
      await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/company-workspace.js')});
      assert.equal(await page.locator('[role=tabpanel]').count(),count,'initialization is idempotent');
      await page.close();
    }
    const page = await browser.newPage();
    await page.setContent('<section data-company-workspace><h1>Faturas</h1><p role="alert">Confira os dados</p><h2>Lista</h2><p>Lista</p><h2>Novo registro</h2><form><input name="value"></form></section>');
    await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/company-workspace.js')});
    assert.equal(await page.locator('[role=tabpanel]:visible').count(),2,'errors do not conceal the failed form');
    await page.setContent('<section data-company-workspace><h1>Leads</h1><section data-workspace-section data-workspace-label="Lista" id="board"><h2>Lista</h2><p>Uma empresa</p></section><section data-workspace-section data-workspace-label="Importação" data-workspace-default><h2>Prévia</h2><input name="preview"></section><section data-workspace-section data-workspace-create data-workspace-label="Novo lead"><h2>Novo</h2><input name="lead"></section></section>');
    await page.addScriptTag({path:path.resolve(__dirname,'../assets/js/company-workspace.js')});
    assert.equal(await page.locator('[name="preview"]').isVisible(),true,'server-selected import preview opens without hidden continuation');
    await page.evaluate(() => { location.hash = 'board'; });
    await page.waitForFunction(() => !document.querySelector('#board').closest('[role="tabpanel"]').hidden);
    assert.equal(await page.getByRole('tab',{name:'Lista',exact:true}).getAttribute('aria-selected'),'true','deep links reveal their panel');
    await page.getByRole('button',{name:'Novo lead',exact:true}).click();
    assert.equal(await page.locator('[name="lead"]').isVisible(),true);
    assert.equal(await page.getByRole('tab',{name:'Lista',exact:true}).getAttribute('tabindex'),'0','tabs stay keyboard-reachable while creation is open');
    console.log('PASS company workspace: desktop/mobile actions, focus, keyboard, payload preservation, validation, replay and error recovery');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
