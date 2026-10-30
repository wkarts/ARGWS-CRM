'use strict';
// Real Chromium and the original customer view/handler; framework and form submission are fixtures.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const html=JSON.parse(execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'native_customer_views.php'),'--html-json'],{encoding:'utf8',cwd:root})).create;
const view=fs.readFileSync(path.join(root,'application/views/admin/clients/client_js.php'),'utf8');
const start=view.indexOf("    $('.customer-form-submiter').on('click', function() {");
const end=view.indexOf("\n    if (typeof(Dropbox)",start);
assert.ok(start>=0&&end>start,'original customer save handler exists');
const handler=view.slice(start,end);
const app=fs.readFileSync(path.join(root,'assets/js/app.js'),'utf8');
const hidden=app.match(/function hidden_input\(name, val\) \{[\s\S]*?\n\}/);
assert.ok(hidden,'original hidden input helper exists');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  const page=await browser.newPage();
  await page.setContent(html);
  await page.addScriptTag({path:path.join(root,'assets/plugins/jquery/jquery.min.js')});
  await page.evaluate(()=>{
   document.body.insertAdjacentHTML('beforeend','<form class="client-form"><div class="additional"></div></form>');
   window.submissions=0;window.jQuery.fn.valid=function(){return true;};window.jQuery.fn.submit=function(){window.submissions++;return this;};
  });
  await page.addScriptTag({content:hidden[0]+'\n'+handler});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:900});
   await page.locator('.save-and-add-contact').click();
   assert.equal(await page.locator('.client-form input[name="save_and_add_contact"]').inputValue(),'true');
   await page.locator('.only-save').click();
   assert.equal(await page.locator('.client-form input[name="save_and_add_contact"]').count(),0);
  }
  assert.equal(await page.evaluate(()=>window.submissions),4);
  console.log('Native customer create actions: original PHP view and jQuery handler passed in real Chromium at desktop/mobile; form and server are fixtures.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
