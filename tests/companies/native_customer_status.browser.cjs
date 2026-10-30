'use strict';
// Real PHP view and Chromium; AJAX/server are controlled fixtures.
const assert=require('node:assert/strict');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const rendered=JSON.parse(execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'native_customer_status_view.php'),'--html-json'],{cwd:root,encoding:'utf8'}));
const start=rendered.script.indexOf("    $('body').on('submit','.native-customer-activate'");
const end=rendered.script.indexOf("    $('#send_file').on('show.bs.modal'",start);
assert.ok(start>=0&&end>start,'original activation handler is present');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  const page=await browser.newPage();await page.setContent(rendered.view);
  await page.addScriptTag({path:path.join(root,'assets/plugins/jquery/jquery.min.js')});
  await page.evaluate(()=>{window.requests=[];window.alerts=[];window.pending=window.jQuery.Deferred();window.alert_float=(...args)=>window.alerts.push(args);window.jQuery.post=(url,data)=>{window.requests.push({url,data});return window.pending.promise();};});
  await page.addScriptTag({content:rendered.script.slice(start,end)});
  await page.locator('.native-customer-activate button').click();
  await page.evaluate(()=>window.jQuery('.native-customer-activate').trigger('submit'));
  assert.equal(await page.evaluate(()=>window.requests.length),1,'pending activation is not submitted twice');
  const request=await page.evaluate(()=>window.requests[0]);
  assert.match(request.url,/\/clients\/change_client_status\/[1-9][0-9]*\/1$/);
  const body=new URLSearchParams(request.data);assert.equal(body.get('_native_company_csrf'),'synthetic-native-csrf');assert.match(body.get('revision')||'',/^[a-f0-9]{64}$/);
  await page.evaluate(()=>window.pending.resolve({success:false}));
  assert.equal(await page.locator('.native-customer-activate button').isDisabled(),false);
  assert.equal(await page.evaluate(()=>window.alerts.length),1,'server refusal remains visible');
  console.log('Native customer activation: original view/handler and real Chromium passed; AJAX and server are fixtures.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
