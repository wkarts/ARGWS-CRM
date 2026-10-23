'use strict';
// Browser fixture only: all network requests blocked; no provider, credentials or real accounts.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.ASAAS_PLAYWRIGHT_MODULE || 'playwright');
const {html} = require('./support/AsaasSettingsFixture.cjs');
(async () => {
 const browser = await chromium.launch({headless:true,...(process.env.CHROMIUM_BIN ? {executablePath:process.env.CHROMIUM_BIN}:{})});
 const output=path.resolve(process.env.ASAAS_UI_ARTIFACTS || 'artifacts/asaas-settings');fs.mkdirSync(output,{recursive:true});
 let checks=0; const check=(ok,label)=>{assert.ok(ok,label);checks++;};const errors=[];
 try {
  for(const width of [1280,390]) {
   const context=await browser.newContext({viewport:{width,height:900}});
   // about:blank/setContent has a null origin. Exercise the same-origin guard
   // with a synthetic document at the configured CRM origin, never live network.
   const fixtureUrl='https://crm.example.test/admin/settings?group=payment_gateways';
   await context.route('**/*',route=>route.request().isNavigationRequest() && route.request().url()===fixtureUrl
    ? route.fulfill({contentType:'text/html',body:html('prepareFixture("1","1",true);')})
    : route.abort());
   const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
   await page.goto(fixtureUrl);await page.locator('#gateway-tab').click();
   assert.deepEqual(await page.locator('.nav-tabs-horizontal > li > a').allTextContents(),['Geral','Autenticação','Webhook e operações']);checks++;
   const initial=await page.locator('#settings-form').evaluate(f=>Array.from(new FormData(f).entries()));
   async function assertNoOverflow(stage) {
    const geometry=await page.evaluate(()=>({viewport:innerWidth,width:document.documentElement.scrollWidth,
     offenders:Array.from(document.querySelectorAll('body *')).filter(el=>{
      const box=el.getBoundingClientRect();return box.width>0&&(box.right>innerWidth+1||box.left < -1);
     }).slice(0,12).map(el=>({tag:el.tagName,id:el.id,className:el.className,right:el.getBoundingClientRect().right}))}));
    check(geometry.width<=geometry.viewport+1,'no horizontal overflow '+stage+': '+JSON.stringify(geometry));
   }

   for(const id of ['general','authentication','operations']) {
    await page.locator('#asaas-settings-tab-'+id).click();check(await page.locator('#asaas-settings-panel-'+id).isVisible(),id+' visible');
    if(id==='operations') {
     check(await page.getByRole('button',{name:'Webhooks e eventos',exact:true}).isVisible(),'same-origin operation navigation initialized');
     await page.waitForFunction(()=>!document.getElementById('asaas-settings-panel-operations').hasAttribute('aria-busy'));
     assert.match(await page.locator('#asaas-settings-panel-operations > [role=status]').textContent(),/Não foi possível carregar/);checks++;
    }
    check((await page.locator('#settings-form').getAttribute('action')).endsWith('&active_tab=online_payments_asaas_tab'),'native save target preserved');
    await page.screenshot({path:path.join(output,width+'-'+id+'.png'),fullPage:true});
    await assertNoOverflow(id);
   }
   assert.deepEqual(await page.locator('#settings-form').evaluate(f=>Array.from(new FormData(f).entries())),initial);checks++;
   await page.locator('#asaas-settings-tab-authentication').click();
   const selector=page.locator('#asaas-operation-environment');check(await selector.inputValue()==='1','single persisted selection');
   for(const env of ['production','sandbox']) {
    await page.locator('#asaas-profile-authentication-'+env+'-tab').click();
    check(await page.locator('#asaas-profile-authentication-'+env).isVisible(),'profile visible '+env);
    check(await selector.inputValue()==='1','browsing never changes operational environment');
   }
   check(await selector.evaluate(el=>el.getBoundingClientRect().bottom<=document.querySelector('[data-asaas-environment-tabs]').getBoundingClientRect().top),'combo above subtabs');
   await page.locator('#asaas-profile-authentication-production-tab').click();
   const key=page.locator('[name="settings[paymentmethod_asaas_api_key]"]');await key.fill('synthetic-key');
   await page.locator('[data-asaas-reveal="asaas-settings-api_key"]').click();check(await key.getAttribute('type')==='text','explicit reveal');
   await page.locator('#asaas-profile-authentication-sandbox-tab').click();check(await key.getAttribute('type')==='password','leaving profile masks key');
   await page.locator('#asaas-profile-authentication-sandbox-tab').press('ArrowLeft');check(await page.locator('#asaas-profile-authentication-production-tab').evaluate(el=>el===document.activeElement),'profile keyboard focus');
   const hidden=page.locator('[name="settings[paymentmethod_asaas_profile_sandbox_label]"]');await hidden.evaluate(el=>{el.required=true;el.value='';});
   await page.locator('button[type=submit]').click();check(await hidden.isVisible(),'native invalid reveals nested profile');check(await hidden.evaluate(el=>el===document.activeElement),'invalid gets focus');
   await hidden.fill('synthetic-label');await selector.selectOption('');await page.locator('#other-tab').click();await page.locator('button[type=submit]').click();
   check(await selector.isVisible(),'invalid selector restores gateway and auth');check(await selector.evaluate(el=>el===document.activeElement),'invalid selector focused');
   await selector.selectOption('1');await page.evaluate(()=>module.exports.initialize(document));check(await page.locator('.nav-tabs-horizontal > li').count()===3,'idempotent init');
   await assertNoOverflow('after validation and profile navigation');
   await context.close();
  }
  assert.deepEqual(errors,[]);console.log('Asaas settings browser: '+checks+' checks passed; '+output);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
