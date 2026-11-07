'use strict';
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');
const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
try{for(const width of [1280,390])for(const mode of ['write','readonly']){
 const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());
 await page.setContent(execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_payment_report_page.php'),mode],{encoding:'utf8'}));
 await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
 assert.equal(await page.locator('img,script').count(),0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 const form=page.locator('form').filter({has:page.locator('[value="export_payment_report"]')});assert.equal(await form.count(),mode==='write'?1:0);
 if(mode==='write'){const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));assert.equal(data.account_row,'10');assert.equal(data.account_revision,'a'.repeat(64));assert.equal(data.company_asaas_preferences_csrf,'synthetic-csrf');assert.equal(data['remote_filters[offset]'],'0');assert.equal(data['remote_filters[status]'],'RECEIVED');assert.equal(data['remote_filters[date_from]'],'2026-01-01');assert.equal(await form.getAttribute('method'),'post');}
 await page.close();
}console.log('Payment report: 4 Chromium native view scenarios passed; no remote requests.');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
