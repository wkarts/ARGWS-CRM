'use strict';
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
try{for(const width of [1280,390])for(const mode of ['allowed','denied','operations','operations-denied']){
 const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());
 await page.setContent(execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_automatic_pix_page.php'),mode],{encoding:'utf8'}));
 await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
 assert.equal(await page.locator('img,script').count(),0);
 await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/native-asaas-settings.js'),'utf8')});await page.click('a[href="#automatic-pix"]');assert(await page.locator('#automatic-pix').isVisible());assert(!(await page.locator('#accounts').isVisible()));
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 const forms=page.locator('#automatic-pix form');assert.equal(await forms.count(),mode==='allowed'?6:mode==='operations'?12:0);
 if(mode==='allowed'||mode==='operations')for(const form of await forms.all()){const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));if(['automatic_pix_monitor','pix_worker','reserve_pix','pix_receipt_worker'].includes(data.action))assert.equal(data.account_row,'10');if(!['observe_pix_initial','settle_pix_initial','review_pix_series','open_pix_series'].includes(data.action))assert.equal(data.account_revision,'a'.repeat(64));assert.equal(data.company_asaas_preferences_csrf,'synthetic-csrf');assert(['automatic_pix_monitor','pix_worker','pix_observe','reserve_pix','observe_pix_initial','settle_pix_initial','pix_receipt_worker','cancel_pix','review_pix_series','open_pix_series'].includes(data.action));if(data.action==='pix_worker'){assert.equal(data.worker_revision,'0'.repeat(64));assert.equal(data.enabled,'1');}if(data.action==='pix_observe')assert.equal(data.event_id,'1');assert.equal(await form.getAttribute('method'),'post');}
 if(mode==='operations'){
  const reservation=page.locator('form').filter({has:page.locator('[name="action"][value="reserve_pix"]')});
  assert.equal(await reservation.locator('[name="frequency"]').inputValue(),'');
  assert.equal(await reservation.locator('[name="confirm"]').isChecked(),false);
  assert.equal(await reservation.locator('[name="invoice_revision"]').inputValue(),'b'.repeat(64));
  const cancellation=page.locator('form').filter({has:page.locator('[name="action"][value="cancel_pix"]')});
  assert.equal(await cancellation.locator('[name="confirm"]').isChecked(),false);
  const financial=page.locator('form').filter({has:page.locator('[name="action"][value="pix_receipt_worker"]')});
  assert.equal(await financial.locator('[name="confirm"]').isChecked(),false);
  assert.equal(await financial.locator('[name="worker_revision"]').inputValue(),'0'.repeat(64));
 }
 await page.close();
}console.log('Automatic Pix: 8 Chromium native tab/permission/responsive scenarios passed.');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
