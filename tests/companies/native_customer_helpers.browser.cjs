'use strict';
// Original customer JavaScript in Chromium, with network/framework dependencies stubbed.
const assert=require('node:assert/strict');
const fs=require('node:fs');const path=require('node:path');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const rendered=JSON.parse(execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'native_customer_status_view.php'),'--html-json'],{cwd:root,encoding:'utf8'}));
const app=fs.readFileSync(path.join(root,'assets/js/app.js'),'utf8');const hidden=app.match(/function hidden_input\(name, val\) \{[\s\S]*?\n\}/)[0];
const main=fs.readFileSync(path.join(root,'assets/js/main.js'),'utf8');const legacyConsent=main.match(/function view_contact_consent\(id\) \{[\s\S]*?\n\}/)[0];
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  for(const mode of ['native','optional','legacy']){
   const page=await browser.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
   await page.setContent(rendered.view);await page.addScriptTag({path:path.join(root,'assets/plugins/jquery/jquery.min.js')});
   await page.evaluate(()=>{
    document.body.insertAdjacentHTML('beforeend','<form class="client-form"><div class="additional"></div></form><div id="contact"><input name="email" value="user@example.invalid"><input name="native_customer_id" value="41"><input name="_native_company_csrf" value="synthetic-native-csrf"><input name="contactid" value="7"></div><div id="consent_data"></div>');
    window.Dropzone=function(){};window.Dropzone.options={};window.admin_url='https://fixture.test/admin/';window.get_url_param=()=>undefined;
    window.app={options:{company_is_required:0},lang:{}};window.tableCalls=[];window.initDataTable=(...args)=>{window.tableCalls.push(args);return null;};window.appValidateForm=(selector,rules)=>{if(selector==='#contact-form')window.contactRules=rules;else window.clientRules=rules;};
    window.jQuery.fn.valid=function(){return true;};window.submissions=0;window.jQuery.fn.submit=function(){window.submissions++;return this;};
    window.jQuery.fn.modal=function(value){window.modalOpened=value;return this;};window.initDataTableInline=()=>{window.consentTable=true;};
    window.requestGet=route=>{window.consentRoute=route;return window.jQuery.Deferred().resolve('<div id="consentHistoryTable"></div><div id="consentModal"></div>').promise();};
   });
   if(mode==='legacy')await page.addScriptTag({content:legacyConsent});
   await page.addScriptTag({content:hidden});
   const script=mode==='native'?rendered.script:(mode==='optional'?rendered.optional_script:rendered.legacy_script);
   for(const part of script.matchAll(/<script>([\s\S]*?)<\/script>/g))await page.addScriptTag({content:part[1]});
   await page.waitForTimeout(100);
   assert.deepEqual(errors,[],mode+' shell must initialize without unprovided globals');
   await page.evaluate(()=>window.validate_contact_form());
   assert.equal(await page.evaluate(()=>window.clientRules.company??null),mode==='native'?'required':null,'validation reflects current company policy');
   assert.equal(await page.evaluate(()=>window.contactRules.email.remote.url),'https://fixture.test/admin/'+(mode!=='legacy'?'clients/contact_email_exists':'misc/contact_email_exists'));
   if(mode!=='legacy'){
    assert.equal(await page.evaluate(()=>window.contactRules.email.remote.data.client_id()),'41');
    assert.equal(await page.evaluate(()=>window.contactRules.email.remote.data._native_company_csrf()),'synthetic-native-csrf');
    const ticket=await page.evaluate(()=>window.tableCalls.find(args=>args[0]==='.table-tickets-single'));
    assert.equal(ticket[1],'https://fixture.test/admin/tickets/index');
    assert.equal(ticket[4].userid,'input[name="userid"]');
    const projects=await page.evaluate(()=>window.tableCalls.find(args=>args[0]==='.table-projects-single-client'));
    const invoices=await page.evaluate(()=>window.tableCalls.find(args=>args[0]==='.table-invoices-single-client'));
    const customer=await page.evaluate(()=>$('input[name="userid"]').val());
    assert.equal(projects[1],'https://fixture.test/admin/projects/table/'+customer);
    assert.equal(invoices[1],'https://fixture.test/admin/invoices/table/'+customer);
   }
   await page.evaluate(()=>window.view_contact_consent(7));
   assert.equal(await page.evaluate(()=>window.consentRoute),'clients/consents/7');
   assert.equal(await page.evaluate(()=>window.consentTable&&window.modalOpened==='show'),true);
   await page.close();
  }
  console.log('Native and legacy customer helpers passed in real Chromium; HTTP and framework are fixtures.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
