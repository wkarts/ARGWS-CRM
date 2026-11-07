'use strict';
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});try{
 for(const width of [1280,390])for(const mode of ['edit','review','pending','denied']){
  const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());await page.setContent(execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_installment_recreation_page.php'),mode],{encoding:'utf8'}));await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
  assert.equal(await page.locator('script,img').count(),0);assert.equal(await page.locator('form').count(),mode==='denied'?0:1);
  if(mode!=='denied'){const form=page.locator('form');assert.equal(await form.locator('[name="company_asaas_preferences_csrf"]').inputValue(),'synthetic-recreation-csrf');assert.equal(await form.locator('[name="group_origin"]').inputValue(),'10');assert.equal(await form.locator('[name="action"]').inputValue(),{edit:'review_installment_recreation',review:'execute_installment_recreation',pending:'recover_installment_recreation'}[mode]);assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),null);if(mode!=='edit'){assert.equal(await form.locator('[name="review_hash"]').inputValue(),(mode==='review'?'a':'b').repeat(64));await form.locator('[name="confirm"]').check();assert.equal(await form.evaluate(f=>new FormData(f).get('confirm')),'1');}if(mode==='review')assert.equal(await form.locator('input[type=date]').getAttribute('readonly'),'');}
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.close();
 }console.log('Installment recreation view: 8 Chromium desktop/mobile/permission states passed, real PHP view.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
