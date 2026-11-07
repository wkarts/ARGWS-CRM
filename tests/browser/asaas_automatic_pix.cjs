'use strict';
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');const path=require('node:path');const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
try{for(const width of [1280,390])for(const mode of ['allowed','denied']){
 const page=await browser.newPage({viewport:{width,height:900}});await page.route('**/*',r=>r.abort());
 await page.setContent(execFileSync(process.env.ASAAS_TEST_PHP||'php',[path.join(root,'tests/fixtures/asaas_automatic_pix_page.php'),mode],{encoding:'utf8'}));
 await page.addStyleTag({content:fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css'),'utf8')});
 assert.equal(await page.locator('img,script').count(),0);
 await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/native-asaas-settings.js'),'utf8')});await page.click('a[href="#automatic-pix"]');assert(await page.locator('#automatic-pix').isVisible());assert(!(await page.locator('#accounts').isVisible()));
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 const forms=page.locator('#automatic-pix form');assert.equal(await forms.count(),mode==='allowed'?3:0);
 if(mode==='allowed')for(const form of await forms.all()){const data=await form.evaluate(f=>Object.fromEntries(new FormData(f)));assert.equal(data.account_row,'10');assert.equal(data.account_revision,'a'.repeat(64));assert.equal(data.company_asaas_preferences_csrf,'synthetic-csrf');assert.equal(data.action,'automatic_pix_monitor');assert.equal(await form.getAttribute('method'),'post');}
 await page.close();
}console.log('Automatic Pix: 4 Chromium native tab/permission/responsive scenarios passed.');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
