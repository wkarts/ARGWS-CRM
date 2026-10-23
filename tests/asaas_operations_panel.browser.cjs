'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');
const {chromium}=require(process.env.ASAAS_PLAYWRIGHT_MODULE||'playwright');
const {html}=require('./support/AsaasSettingsFixture.cjs');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_BIN?{executablePath:process.env.CHROMIUM_BIN}:{})});
 const output=path.resolve(process.env.ASAAS_UI_ARTIFACTS||'artifacts/asaas-settings');fs.mkdirSync(output,{recursive:true});
 try{
  const context=await browser.newContext({viewport:{width:1280,height:900}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',route=>route.request().isNavigationRequest()?route.fulfill({contentType:'text/html',body:html('prepareFixture("1","0",true);')}):route.abort());
  await page.goto('https://crm.example.test/admin/settings?group=payment_gateways');
  await page.evaluate(()=>{
   window.panelRequests=[];
   window.fetch=async(url,options)=>{
    window.panelRequests.push({url,method:options.method,values:options.body?Array.from(options.body.entries()):[]});
    return new Response(`<div data-asaas-operation-content><h3>Webhook sintético</h3><p>Nenhum evento real.</p><form action="https://crm.example.test/admin/asaas/reveal_integration_token" method="post"><input type="hidden" name="csrf_test" value="synthetic-csrf"><input type="hidden" name="webhook_admin_csrf" value="synthetic-admin-csrf"><input type="hidden" name="integration_id" value="7"><button type="submit" id="synthetic-recover">Recuperar token</button></form><div class="form-group"><input id="generated-webhook-token" type="password" readonly value="synthetic-not-real"><button type="button" class="js-reveal-webhook-token" aria-controls="generated-webhook-token">Revelar</button><span class="js-copy-status"></span></div><a id="synthetic-origins" href="https://crm.example.test/admin/asaas/payment_origins">Conciliação</a></div>`,{status:200,headers:{'Content-Type':'text/html'}});
   };
  });
  await page.locator('#gateway-tab').click();assert.equal(await page.evaluate(()=>panelRequests.length),0,'initial settings never fetch operations');
  const settingsBefore=await page.locator('#settings-form').evaluate(f=>Array.from(new FormData(f).entries()));
  await page.locator('#asaas-settings-tab-operations').click();await page.locator('#synthetic-recover').waitFor();
  assert.deepEqual(await page.evaluate(()=>panelRequests.map(r=>r.method)),['GET']);
  assert.equal(await page.locator('#settings-form form').count(),0,'no nested forms');
  assert.equal(await page.locator('#synthetic-recover').evaluate(el=>el.form.id.startsWith('asaas-operation-form-')&&!el.form.closest('#settings-form')),true,'explicit separate form owner');
  assert.deepEqual(await page.locator('#settings-form').evaluate(f=>Array.from(new FormData(f).entries())),settingsBefore,'operation data never leaks into settings POST');
  await page.locator('#synthetic-recover').click();await page.waitForFunction(()=>panelRequests.length===2);
  const request=await page.evaluate(()=>panelRequests[1]);assert.equal(request.method,'POST');assert.deepEqual(request.values,[['csrf_test','synthetic-csrf'],['webhook_admin_csrf','synthetic-admin-csrf'],['integration_id','7']]);
  await page.locator('.js-reveal-webhook-token').click();assert.equal(await page.locator('#generated-webhook-token').getAttribute('type'),'text');
  await page.locator('#asaas-settings-tab-general').click();assert.equal(await page.locator('#generated-webhook-token').getAttribute('type'),'password','leaving operations masks token');
  await page.locator('#asaas-settings-tab-operations').click();assert.equal(await page.evaluate(()=>panelRequests.length),2,'reopening does not repeat POST or GET');
  await page.locator('#synthetic-origins').click();await page.waitForFunction(()=>panelRequests.length===3);assert.equal(await page.evaluate(()=>panelRequests[2].method),'GET');
  assert.equal(await page.locator('body > form[id^=asaas-operation-form-]').count(),1,'old form owners removed');
  // A blocked/missing session must never inject a login page into the panel.
  await page.evaluate(()=>{window.fetch=async()=>new Response('<h1 id="injected-login">Login</h1>',{status:403});});
  await page.getByRole('button',{name:'Webhooks e eventos',exact:true}).click();
  await page.waitForFunction(()=>!document.getElementById('asaas-settings-panel-operations').hasAttribute('aria-busy'));
  assert.equal(await page.locator('#injected-login').count(),0);
  await page.evaluate(()=>{window.fetch=async()=>new Response('<h1 id="injected-login">Login</h1>',{status:200});});
  await page.getByRole('button',{name:'Webhooks e eventos',exact:true}).click();
  await page.waitForFunction(()=>!document.getElementById('asaas-settings-panel-operations').hasAttribute('aria-busy'));
  assert.equal(await page.locator('#injected-login').count(),0,'redirected session HTML rejected');
  // Hold a synthetic POST in flight: repeated submit cannot duplicate it; failure never retries.
  await page.evaluate(()=>{window.pendingPosts=0;window.fetch=()=>{window.pendingPosts++;return new Promise((resolve,reject)=>{window.rejectPending=reject;});};});
  await page.locator('#synthetic-recover').click();await page.locator('#synthetic-recover').click();
  assert.equal(await page.evaluate(()=>pendingPosts),1,'busy guard blocks duplicate submit');
  await page.evaluate(()=>rejectPending(new Error('synthetic-network-failure')));
  await page.waitForFunction(()=>!document.getElementById('asaas-settings-panel-operations').hasAttribute('aria-busy'));
  assert.equal(await page.evaluate(()=>pendingPosts),1,'failed POST not retried');
  assert.match(await page.locator('#asaas-settings-panel-operations > [role=status]').textContent(),/Não foi possível confirmar/);
  const active=await page.locator('#asaas-operation-environment').inputValue();
  await page.evaluate(()=>{const link=document.createElement('a');link.id='synthetic-return';link.href='https://crm.example.test/admin/settings?group=payment_gateways&active_tab=online_payments_asaas_tab';link.textContent='Voltar';document.querySelector('[data-asaas-operation-content]').appendChild(link);});
  await page.locator('#synthetic-return').click();assert.equal(await page.locator('#asaas-settings-panel-general').isVisible(),true);
  assert.equal(await page.locator('#asaas-operation-environment').inputValue(),active,'return preserves active environment');
  await page.locator('#asaas-settings-tab-operations').click();
  await page.screenshot({path:path.join(output,'operations-synthetic-fixture.png'),fullPage:true});assert.deepEqual(errors,[]);
  console.log('Asaas operations panel browser: routing, CSRF/form ownership, no automatic mutations and masking passed');await context.close();
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
