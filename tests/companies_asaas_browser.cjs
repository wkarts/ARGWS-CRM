'use strict';
// Actual CRM/HTTP/MySQL/Chromium; protocol responses are injected only by the CLI service fixture.
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');const {chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER,app=process.env.ISSUERS_BROWSER_APP_CONTAINER;assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');const output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';fs.mkdirSync(output,{recursive:true});
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
function fixture(letter,mode='prepare',origin=''){return JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_http_fixture.php',letter,mode,...(origin?[String(origin)]:[])],{encoding:'utf8'}));}
async function go(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
async function hidden(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
(async()=>{const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
try{
 execFileSync('docker',['exec',app,'mkdir','-p','/app/tests/support','/app/tests/companies']);
 for(const file of ['tests/support/AsaasHttpNativeTarget.php','tests/support/BbPdoDatabase.php','tests/companies/asaas_install_http_fixture.php','tests/companies/asaas_http_fixture.php','tests/companies/asaas_preferences_fixture.php'])execFileSync('docker',['cp',file,app+':/app/'+file]);
 execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_install_http_fixture.php'],{stdio:'pipe'});
 await require('./companies/asaas_preferences_real_browser.cjs').run({...process.env,ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'unselected'});
 await page.goto(url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await go(page,page.locator('button[type=submit]'));
 const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");const prepared={};let oldForm;
 async function select(id){await page.goto(url+'/admin/company_context');await page.locator('#company-context-select').selectOption(id);await go(page,page.locator('.company-selector-form button[type=submit]'));}
 for(const [issuer,letter]of[[a,'a'],[b,'b']]){
  await select(issuer);assert.equal((await page.goto(url+'/admin/company_asaas')).status(),200);
  await page.locator('#asaas-new-environment').selectOption('sandbox');await page.locator('#asaas-new-account').fill('wallet-browser-'+letter);await page.locator('#asaas-new-key').fill('synthetic-browser-key-'+letter);
  await go(page,page.locator('form').filter({has:page.locator('#asaas-new-key')}).locator('button[type=submit]'));
  assert.equal(await page.locator('#asaas-new-key').inputValue(),'');assert.ok(!await page.content().then(v=>v.includes('synthetic-browser-key-'+letter)),'stored API secret is never rendered');
  const x=fixture(letter);prepared[issuer]=x;await page.reload({waitUntil:'domcontentloaded'});
  await page.locator('#webhooks details summary').click();const rotate=page.locator('form').filter({has:page.locator('input[name=action][value=rotate]')});await rotate.locator('input[name=confirm]').check();await go(page,rotate.locator('button[type=submit]'));
  const token=(await page.locator('#asaas-one-time-token').inputValue()).trim();assert.match(token,/^[a-f0-9]{64}$/);
  fixture(letter,'refresh',x.origin);await page.reload({waitUntil:'domcontentloaded'});assert.equal(await page.locator('#asaas-one-time-token').count(),0,'GET never hydrates plaintext token');
  const reveal=page.locator('form').filter({has:page.locator('input[name=action][value=reveal_webhook]')});await go(page,reveal.locator('button[type=submit]'));assert.equal(await page.locator('#asaas-one-time-token').inputValue(),token,'explicit reveal recovers the original token');assert.equal(await page.locator('#asaas-one-time-token').getAttribute('type'),'password','recovered token starts masked');await page.reload({waitUntil:'domcontentloaded'});
  const settle=page.locator('#asaas-origin-'+x.origin+' form').filter({has:page.locator('input[name=action][value=settle]')});const retry=await hidden(settle);await settle.locator('input[name=confirm]').check();await go(page,settle.locator('button[type=submit]'));
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+x.invoice),'2');
  assert.equal((await context.request.post(url+'/admin/company_asaas',{form:{...retry,confirm:'1'}})).status(),200,'same proof retry is idempotent');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1');
  const fee=page.locator('#asaas-origin-'+x.origin+' form').filter({has:page.locator('input[name=action][value=fee]')});await fee.locator('select[name=category_id]').selectOption(String(x.category));await fee.locator('input[name=confirm]').check();await go(page,fee.locator('button[type=submit]'));
  assert.equal(sql('SELECT COUNT(*) FROM tblasaas_company_fee_expenses WHERE origin_id='+x.origin),'1');assert.equal(sql('SELECT total_cents FROM tblcompany_expenses WHERE id=(SELECT expense_id FROM tblasaas_company_fee_expenses WHERE origin_id='+x.origin+')'),'100');
  const publicId=sql('SELECT webhook_public_id FROM tblasaas_company_accounts WHERE issuer_id='+issuer+" AND environment='sandbox'");const event={id:'evt_browser_'+letter,event:'PAYMENT_RECEIVED',payment:{id:x.remote}};
  const cb=url+'/company_asaas_callback/integration/'+publicId;assert.equal((await context.request.get(cb)).status(),405);const headers={'Content-Type':'application/json','asaas-access-token':token};
  assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(event)})).status(),200);assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(event)})).status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblasaas_company_events WHERE account_row=(SELECT id FROM tblasaas_company_accounts WHERE issuer_id='+issuer+" AND environment='sandbox')"),'1');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'1','callback does not produce another payment');
  const automation=page.locator('form').filter({has:page.locator('input[name=action][value=automation]')});await go(page,automation.locator('button[type=submit]'));
  const asyncTitle=fixture(letter);const asyncEvent={id:'evt_browser_async_'+letter,event:'PAYMENT_RECEIVED',payment:{id:asyncTitle.remote}};assert.equal((await context.request.post(cb,{headers,data:JSON.stringify(asyncEvent)})).status(),200);
  fixture(letter,'worker');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+asyncTitle.invoice),'1','opt-in worker creates exactly one canonical nominal receipt');fixture(letter,'worker');assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+asyncTitle.invoice),'1','worker retry preserves canonical receipt');await page.reload({waitUntil:'domcontentloaded'});
  if(letter==='a')oldForm={...await hidden(page.locator('form').filter({has:page.locator('input[name=action][value=rotate]')}))};
 }
 assert.equal((await context.request.post(url+'/admin/company_asaas',{form:oldForm})).status(),409,'obsolete A company editor denied before effects');
 assert.equal((await context.request.get(url+'/admin/company_asaas?invoice='+prepared[a].invoice)).status(),403,'B cannot hydrate A invoice in issuer UI');
 assert.ok(!(await page.locator('#charges').innerText()).includes(prepared[a].remote),'B charge list never renders A remote identity');
 await page.screenshot({path:output+'/company-asaas.png',fullPage:true});assert.deepEqual(errors,[]);console.log('Company Asaas A/B actual UI credentials/protected-token-recovery/receipt + HTTP inbox/dedup/stale/company isolation passed. Tariff native expense and opt-in worker passed; creation/query transport simulated by CLI fixture; no real Asaas homologation.');
 await require('./companies/asaas_preferences_real_browser.cjs').run({...process.env,ISSUERS_BROWSER_ASAAS_PREFERENCES_PHASE:'selected'});
}finally{await browser.close();}})().catch(e=>{console.error(e.stderr?.toString()||e.message);process.exitCode=1;});
