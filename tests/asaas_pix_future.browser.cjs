'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH});
 try{for(const stopped of [false,true])for(const width of [1280,375]){
  const html=execFileSync(process.env.ASAAS_TEST_PHP||'php',['tests/fixtures/asaas_pix_future_page.php',stopped?'stopped':'prepared'],{encoding:'utf8'});
  const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());await page.setContent(html);
  for(const form of await page.locator('form').all()){
   assert.equal(await form.getAttribute('method'),'post');
   for(const field of ['_company_context','csrf_test','company_asaas_preferences_csrf'])assert.equal(await form.locator('[name="'+field+'"]').count(),1);
   for(const check of await form.locator('[type="checkbox"]').all()){assert.equal(await check.isChecked(),false);assert.equal(await check.getAttribute('required'),'');}
  }
  const automation=page.locator('form').filter({has:page.locator('input[value="configure_pix_future_worker"]')});assert.equal(await automation.count(),1);assert.equal(await automation.locator('[name="enabled"]').inputValue(),stopped?'0':'1');assert.equal(await automation.locator('[name="worker_revision"]').inputValue(),(stopped?'d':'0').repeat(64));
  assert.equal(await page.locator('input[value="issue_pix_cycle"]').count(),stopped?0:1);
  assert.equal(await page.locator('input[value="observe_pix_cycle"]').count(),1);
  assert.equal(await page.locator('input[value="settle_pix_cycle"]').count(),1);
  if(!stopped){const form=page.locator('form').filter({has:page.locator('input[value="confirm_pix_series"]')});assert.equal(await form.evaluate(f=>f.checkValidity()),false);assert.equal(await form.locator('[name="review_hash"]').inputValue(),'a'.repeat(64));}
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await page.close();
 }
 console.log('Future Pix: actual PHP view in Chromium, prepared/stopped and desktop/mobile; confirmation, scope/CSRF and recovery controls. No HTTP/provider operations.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
