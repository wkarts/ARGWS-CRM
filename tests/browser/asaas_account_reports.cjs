'use strict';
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
try{for(const width of [1280,390])for(const mode of ['export','view','denied']){
 const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());
 await page.setContent(execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_account_reports_page.php'),mode],{encoding:'utf8'}));
 await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
 assert.equal(await page.locator('img,script').count(),0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 assert.equal(await page.locator('details').count(),mode==='denied'?0:20);
 const queries=page.locator('form').filter({has:page.locator('[name="action"][value="account_report"]')});assert.equal(await queries.count(),mode==='denied'?0:20);
 const exports=page.locator('form').filter({has:page.locator('[name="action"][value="export_account_report"]')});assert.equal(await exports.count(),mode==='export'?1:0);
 for(const form of await queries.all()){const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));assert.equal(data.account_row,'10');assert.equal(data.account_revision,'a'.repeat(64));assert.equal(data.company_asaas_preferences_csrf,'synthetic-csrf');assert.equal(data['report_filters[offset]'],'0');assert.equal(await form.getAttribute('method'),'post');}
 const ids=await page.locator('[id]').evaluateAll(nodes=>nodes.map(n=>n.id));assert.equal(ids.length,new Set(ids).size);
 await page.close();
}console.log('Account reports: 6 Chromium permission/responsive/catalog scenarios passed.');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
