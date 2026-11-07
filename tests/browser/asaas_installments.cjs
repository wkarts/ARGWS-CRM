'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 try{for(const width of [1280,390])for(const mode of ['write','readonly','removing','batch','batch_readonly']){
  const writable=['write','batch'].includes(mode);const readonly=['readonly','batch_readonly'].includes(mode);
  const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',route=>route.abort());
  const html=execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_installments_page.php'),mode],{encoding:'utf8'});
  await page.setContent(html);await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
  assert.equal(await page.locator('img').count(),0);const refunds=page.locator('a[href*="refund_origin"]');assert.equal(await refunds.count(),readonly?0:1);if(!readonly)assert.equal(await refunds.getAttribute('href'),'/settings/asaas?refund_origin=12#asaas-refunds');assert.equal(await page.locator('[value="observe_installment"]').count(),2);
  assert.equal(await page.locator('[value="reconcile_installment"]').count(),writable?1:0);
  assert.equal(await page.locator('form input[name="company_asaas_preferences_csrf"]').count(),readonly?3:(mode==='batch'?6:5));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  assert.equal(await page.locator('[value="remove_installment"]').count(),writable?1:0);
  assert.equal(await page.locator('[value="refresh_installment_removal"]').count(),mode==='removing'?1:0);
  if(mode==='removing')assert.equal(await page.locator('[name="confirm"]').count(),0);
  if(writable){const removal=page.locator('form').filter({has:page.locator('[value="remove_installment"]')});assert.equal(await removal.evaluate(f=>new FormData(f).get('confirm')),null);await removal.locator('[name="confirm"]').check();assert.equal(await removal.evaluate(f=>new FormData(f).get('confirm')),'1');const form=page.locator('form').filter({has:page.locator('[value="reconcile_installment"]')});
   assert.equal(await form.locator('[name="remote_id"]').inputValue(),'pay_first');
   assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),null);
   await form.locator('[name="confirm"]').check();assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),'1');
  }
  assert.equal(await page.locator('[data-installment-batch-confirm]').count(),mode==='batch'?1:0);
  assert.equal(await page.locator('svg,script').count(),0);
  if(mode==='batch'){const form=page.locator('[data-installment-batch-confirm]');assert.equal(await form.locator('[name="review_hash"]').inputValue(),'a'.repeat(64));assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),null);await form.locator('[name="confirm"]').check();assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),'1');assert.match(await form.innerText(),/Retomar parcelas revisadas/);}
  await page.close();
 }console.log('Installment native view: 10 Chromium desktop/mobile and permission scenarios passed. No provider calls.');}
 finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
