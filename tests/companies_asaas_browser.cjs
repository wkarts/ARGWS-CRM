'use strict';
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
// Actual CRM/HTTP/MySQL/Chromium; protocol responses are injected only by the CLI service fixture.
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');

// Diagnostic fields are allowlisted. Never serialize exception messages, URLs, bodies,
// field values, page content, CSRF material, or browser console arguments.
const PHASES = /^(?:authenticate|install-native|preferences-unselected|preferences-selected|isolation|capture-evidence|[ab]:(?:select-company|open-company|save-account|prepare-origin|refresh-account|rotate-token|refresh-proof|refresh-token|reveal-token|refresh-reveal|settle|fee|callback|automation|worker|refresh-worker))$/;
function safeResponse(response) {
 const request=response.request();let pathname='other';
 try {const path=new URL(response.url()).pathname;if(['/admin','/admin/','/admin/authentication','/admin/authentication/admin','/admin/issuers/select','/admin/settings/asaas'].includes(path))pathname=path;}catch{}
 const method=request.method();return {pathname,method:['GET','POST','HEAD'].includes(method)?method:'other',status:response.status()};
}
function diagnostics(page,write=record=>console.log(JSON.stringify(record))) {
 let phase='install-native',lastResponse=null,pageErrors=0;
 page.on('response',response=>{if(response.request().isNavigationRequest()&&response.request().frame()===page.mainFrame())lastResponse=safeResponse(response);});
 page.on('pageerror',()=>{pageErrors++;});
 return {
  stage(value){assert.ok(PHASES.test(value),'fixed diagnostic phase required');phase=value;},
  report(state,form=null){write({test:'companies-asaas-browser',phase,state,lastResponse,pageErrors,...(form?{form}: {})});},
  pageErrors:()=>pageErrors,
 };
}
async function go(page,button,trace,phase) {
 trace.stage(phase);trace.report('start');let form=null;
 try {
  assert.equal(await button.count(),1,'one native submit button required');
  form=await button.evaluate(node=>{
   const form=node.form;const invalid=form?Array.from(form.elements).filter(input=>input.willValidate&&!input.validity.valid):[];
   const names=['email','password','company_id','environment','account_id','api_key','confirm','category_id'];
   return {present:!!form,disabled:!!node.disabled,noValidate:!!form?.noValidate,formNoValidate:!!node.formNoValidate,
    invalidCount:invalid.length,invalidNames:[...new Set(invalid.map(input=>names.includes(input.name)?input.name:'other'))]};
  });
  assert.ok(form.present&&!form.disabled,'enabled native form submit required');
  assert.ok(form.noValidate||form.formNoValidate||form.invalidCount===0,'native form validity required');
  const [response]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);
  assert.ok(response&&response.status()===200,'native submit must finish with HTTP 200');
  trace.report('passed',form);return response;
 }catch{trace.report('failed',form);throw new Error('Asaas native submission failed; inspect fixed-label diagnostics.');}
}
async function openCompany(page,url,trace,phase) {
 trace.stage(phase);trace.report('start');
 try {
  // These controllers render their POST response directly. Reload would replay the
  // previous mutation, including an already-consumed secret-recovery CSRF token.
  const response=await page.goto(url+'/admin/settings/asaas',{waitUntil:'domcontentloaded'});
  assert.ok(response&&response.status()===200,'fresh native company GET must return HTTP 200');
  assert.equal(response.request().method(),'GET','refresh must not replay a mutation');
  trace.report('passed');return response;
 }catch{trace.report('failed');throw new Error('Asaas fresh company GET failed; inspect fixed-label diagnostics.');}
}
async function run() {
 const {chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER,app=process.env.ISSUERS_BROWSER_APP_CONTAINER;assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');const output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';fs.mkdirSync(output,{recursive:true});
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
function fixture(letter,mode='prepare',origin=''){return JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_http_fixture.php',letter,mode,...(origin?[String(origin)]:[])],{encoding:'utf8'}));}
async function hidden(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await context.newPage();const trace=diagnostics(page);
try{
 execFileSync('docker',['exec',app,'mkdir','-p','/app/tests/support','/app/tests/companies']);
 for(const file of ['tests/support/AsaasHttpNativeTarget.php','tests/support/BbPdoDatabase.php','tests/companies/asaas_install_http_fixture.php','tests/companies/asaas_http_fixture.php','tests/companies/asaas_preferences_fixture.php'])execFileSync('docker',['cp',file,app+':/app/'+file]);
 execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_install_http_fixture.php'],{stdio:'pipe'});
 trace.stage('preferences-unselected');await require('./companies/asaas_preferences_real_browser.cjs').run({...process.env,ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'unselected'});
 trace.stage('authenticate');
 await page.goto(url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await go(page,page.locator('form').filter({has:page.locator('input[name=password]')}).locator('button[type=submit]'),trace,'authenticate');
 const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");const prepared={};let oldForm;
 async function select(id,letter){trace.stage(letter+':select-company');await page.goto(url+'/admin/');await selectCompanyOption(page, id);}
 for(const [issuer,letter]of[[a,'a'],[b,'b']]){
  await select(issuer,letter);await openCompany(page,url,trace,letter+':open-company');
  await page.locator('#asaas-new-environment').selectOption('sandbox');await page.locator('#asaas-new-account').fill('wallet-browser-'+letter);await page.locator('#asaas-new-key').fill('synthetic-browser-key-'+letter);
  await go(page,page.locator('form').filter({has:page.locator('#asaas-new-key')}).locator('button[type=submit]'),trace,letter+':save-account');
  assert.equal(await page.locator('#asaas-new-key').inputValue(),'');assert.ok(!await page.content().then(v=>v.includes('synthetic-browser-key-'+letter)),'stored API secret is never rendered');
  trace.stage(letter+':prepare-origin');const x=fixture(letter);prepared[issuer]=x;await openCompany(page,url,trace,letter+':refresh-account');
  await page.locator('#webhooks details summary').click();const rotate=page.locator('form').filter({has:page.locator('input[name=action][value=rotate]')});await rotate.locator('input[name=confirm]').check();await go(page,rotate.locator('button[type=submit]'),trace,letter+':rotate-token');
  const token=(await page.locator('#asaas-one-time-token').inputValue()).trim();assert.match(token,/^[a-f0-9]{64}$/);
  trace.stage(letter+':refresh-proof');fixture(letter,'refresh',x.origin);await openCompany(page,url,trace,letter+':refresh-token');assert.equal(await page.locator('#asaas-one-time-token').count(),0,'GET never hydrates plaintext token');
  const reveal=page.locator('form').filter({has:page.locator('input[name=action][value=reveal_webhook]')});await go(page,reveal.locator('button[type=submit]'),trace,letter+':reveal-token');assert.equal(await page.locator('#asaas-one-time-token').inputValue(),token,'explicit reveal recovers the original token');assert.equal(await page.locator('#asaas-one-time-token').getAttribute('type'),'password','recovered token starts masked');await openCompany(page,url,trace,letter+':refresh-reveal');
  const settle=page.locator('#asaas-origin-'+x.origin+' form').filter({has:page.locator('input[name=action][value=settle]')});const retry=await hidden(settle);await settle.locator('input[name=confirm]').check();await go(page,settle.locator('button[type=submit]'),trace,letter+':settle');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+x.invoice),'2');
  assert.equal((await context.request.post(url+'/admin/settings/asaas',{form:{...retry,confirm:'1'}})).status(),200,'same proof retry is idempotent');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1');
  const fee=page.locator('#asaas-origin-'+x.origin+' form').filter({has:page.locator('input[name=action][value=fee]')});await fee.locator('select[name=category_id]').selectOption(String(x.category));await fee.locator('input[name=confirm]').check();await go(page,fee.locator('button[type=submit]'),trace,letter+':fee');
  assert.equal(sql('SELECT COUNT(*) FROM tblasaas_company_fee_expenses WHERE origin_id='+x.origin),'1');assert.equal(sql('SELECT total_cents FROM tblcompany_expenses WHERE id=(SELECT expense_id FROM tblasaas_company_fee_expenses WHERE origin_id='+x.origin+')'),'100');
  trace.stage(letter+':callback');const publicId=sql('SELECT webhook_public_id FROM tblasaas_company_accounts WHERE issuer_id='+issuer+" AND environment='sandbox'");const event={id:'evt_browser_'+letter,event:'PAYMENT_RECEIVED',payment:{id:x.remote}};
  const cb=url+'/company_asaas_callback/integration/'+publicId;assert.equal((await context.request.get(cb)).status(),405);const headers={'Content-Type':'application/json','asaas-access-token':token};
  assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(event)})).status(),200);assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(event)})).status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblasaas_company_events WHERE account_row=(SELECT id FROM tblasaas_company_accounts WHERE issuer_id='+issuer+" AND environment='sandbox')"),'1');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1','callback does not produce another payment');
  const automation=page.locator('form').filter({has:page.locator('input[name=action][value=automation]')});await go(page,automation.locator('button[type=submit]'),trace,letter+':automation');
  trace.stage(letter+':worker');const asyncTitle=fixture(letter);const asyncEvent={id:'evt_browser_async_'+letter,event:'PAYMENT_RECEIVED',payment:{id:asyncTitle.remote}};assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(asyncEvent)})).status(),200);
  fixture(letter,'worker');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+asyncTitle.invoice),'1','opt-in worker creates exactly one canonical nominal receipt');fixture(letter,'worker');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+asyncTitle.invoice),'1','worker retry preserves canonical receipt');await openCompany(page,url,trace,letter+':refresh-worker');
  if(letter==='a')oldForm={...await hidden(page.locator('form').filter({has:page.locator('input[name=action][value=rotate]')}))};
 }
 trace.stage('isolation');assert.equal((await context.request.post(url+'/admin/settings/asaas',{form:oldForm})).status(),409,'obsolete A company editor denied before effects');
 assert.equal((await context.request.get(url+'/admin/settings/asaas?invoice='+prepared[a].invoice)).status(),403,'B cannot hydrate A invoice in issuer UI');
 assert.ok(!(await page.locator('#charges').innerText()).includes(prepared[a].remote),'B charge list never renders A remote identity');
 trace.stage('capture-evidence');await page.screenshot({path:output+'/company-asaas.png',fullPage:true,mask:[page.locator('input[type=password], [data-asaas-secret], input[id^=company-webhook-url-]')]});assert.equal(trace.pageErrors(),0,'no native page errors');console.log('Company Asaas A/B actual UI credentials/protected-token-recovery/receipt + HTTP inbox/dedup/stale/company isolation passed. Tariff native expense and opt-in worker passed; creation/query transport simulated by CLI fixture; no real Asaas homologation.');
 trace.stage('preferences-selected');await require('./companies/asaas_preferences_real_browser.cjs').run({...process.env,ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'selected'});
}catch{trace.report('failed');throw new Error('Asaas browser integration failed; inspect fixed-label diagnostics.');}
finally{await browser.close();}
}
module.exports={safeResponse,diagnostics,go,openCompany,run};
if(require.main===module)run().catch(()=>{console.error('Asaas browser integration failed; inspect fixed-label diagnostics.');process.exitCode=1;});
