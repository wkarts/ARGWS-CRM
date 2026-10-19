'use strict';
// Actual CRM/HTTP/MySQL/browser, exclusively on the disposable standalone-upgrade fixture.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
const output = process.env.ISSUERS_BROWSER_ARTIFACTS || '/tmp/issuer-browser-evidence';
fs.mkdirSync(output, { recursive: true });
function sql(query) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', query],
    { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
async function login(page, email, password) {
  await page.goto(url + '/admin/authentication');
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(password);
  await navigate(page, page.locator('button[type=submit]'));
}
async function navigate(page, button) {
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}
async function hidden(page) {
  return page.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(x => x.name && !x.name.startsWith('wizard[')).map(x => [x.name, x.value])));
}
async function next(page) { await navigate(page, page.locator('section form button[type=submit]')); }


(async()=>{
 const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await login(page,'admin-ci@example.invalid',process.env.ISSUERS_BROWSER_PASSWORD);
  const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
  const clients={};clients[a]=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client a'");clients[b]=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client b'");
  async function select(id){await page.goto(url+'/admin/company_context');await page.locator('#company-context-select').selectOption(id);await navigate(page,page.locator('.company-selector-form button[type=submit]'));}
  async function fields(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
  const ids={};
  for(const [issuer,letter]of[[a,'a'],[b,'b']]){
   await select(issuer);await page.goto(url+'/admin/settings?group=company');
   for(const [field,value]of Object.entries({invoice_company_address:'Synthetic '+letter+' street',invoice_company_city:'Synthetic city',company_state:'BA',invoice_company_country_code:'BR',invoice_company_postal_code:'00000-000'}))await page.locator('#company-setting-'+field).fill(value);
   await navigate(page,page.locator('section form button[type=submit]'));
   await page.goto(url+'/admin/company_invoices');await page.locator('#invoice-client').selectOption(clients[issuer]);await page.locator('#invoice-currency').selectOption({label:'BRL'});
   await page.locator('#invoice-item-0-description').fill('Synthetic invoice '+letter);await page.locator('#invoice-item-0-qty').fill('2.00');await page.locator('#invoice-item-0-rate').fill('50.25');await page.locator('#invoice-item-0-taxname').fill('Synthetic tax');await page.locator('#invoice-item-0-taxrate').fill('5.00');
   const form=page.locator('form').filter({has:page.locator('#invoice-client')});const creation=await fields(form);
   await navigate(page,form.locator('button[type=submit]'));
   ids[issuer]=new URL(page.url()).searchParams.get('invoice');assert.match(ids[issuer]||'',/^[1-9]\d*$/);
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+ids[issuer]),'105.53');
   const current=await fields(page.locator('form').filter({has:page.locator('#invoice-client')}));
   const repeat=await context.request.post(url+'/admin/company_invoices',{form:{...creation,...current,request_key:creation.request_key,'invoice[client_id]':clients[issuer],'invoice[currency]':'1','invoice[date]':await page.locator('#invoice-date').inputValue(),'invoice[due_date]':await page.locator('#invoice-due').inputValue(),'invoice[items][0][description]':'Synthetic invoice '+letter,'invoice[items][0][qty]':'2.00','invoice[items][0][rate]':'50.25','invoice[items][0][taxname]':'Synthetic tax','invoice[items][0][taxrate]':'5.00','invoice[note]':''}});
   assert.equal(repeat.status(),200,'invoice create retry remains idempotent');assert.equal(sql('SELECT COUNT(*) FROM tblinvoices WHERE issuer_id='+issuer),'1');
   const malformed=await context.request.post(url+'/admin/company_invoices',{form:{...Object.fromEntries(Object.entries(await fields(page.locator('form').filter({has:page.locator('#edit-invoice-date')}))).filter(([key])=>!key.startsWith('invoice['))),action:'update'}});assert.equal(malformed.status(),422,'malformed update cannot become a cancellation');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'1');
   const pdf=await context.request.get(url+'/admin/company_invoices?invoice='+ids[issuer]+'&pdf=1');assert.equal(pdf.status(),200);assert((await pdf.body()).subarray(0,5).toString()==='%PDF-','actual own-company PDF downloaded');
   if(letter==='a'){
    const edit=page.locator('form').filter({has:page.locator('#edit-invoice-date')});const stale=await fields(edit);
    await page.locator('#edit-item-0-rate').fill('40.00');await navigate(page,edit.locator('button[type=submit]'));
    assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+ids[issuer]),'84.00');
    const old=await context.request.post(url+'/admin/company_invoices',{form:{...stale,...(await fields(page.locator('form').filter({has:page.locator('#edit-invoice-date')}))),action:'cancel',revision:stale.revision}});assert.equal(old.status(),409,'stale cancel cannot bypass newer revision');
    await page.locator('#payment-amount').fill('20.00');await page.locator('#payment-reference').fill('synthetic-browser-receipt-1');await page.locator('#payment-note').fill('Synthetic evidence');
    const payment=page.locator('form').filter({has:page.locator('#payment-amount')});const paymentFields=await fields(payment);const paymentDate=await page.locator('#payment-date').inputValue();await navigate(page,payment.locator('button[type=submit]'));
    assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'3');
    const retry=await context.request.post(url+'/admin/company_invoices',{form:{...paymentFields,...(await fields(page.locator('form').filter({has:page.locator('#payment-amount')}))),request_key:paymentFields.request_key,'payment[amount]':'20.00','payment[date]':paymentDate,'payment[reference]':'synthetic-browser-receipt-1','payment[note]':'Synthetic evidence'}});assert.equal(retry.status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+ids[issuer]),'1');
    await page.locator('#payment-amount').fill('64.00');await page.locator('#payment-reference').fill('synthetic-browser-receipt-2');await navigate(page,page.locator('form').filter({has:page.locator('#payment-amount')}).locator('button[type=submit]'));
    assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'2');assert.equal(await page.locator('#payment-amount').count(),0);
   }else{
    const cancel=page.locator('form').filter({has:page.locator('input[name=action][value=cancel]')});await navigate(page,cancel.locator('button[type=submit]'));assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'5');
   }
  }
  assert.equal((await context.request.get(url+'/admin/company_invoices?invoice='+ids[a])).status(),403,'B cannot read direct A invoice ID');
  assert.equal((await context.request.get(url+'/admin/company_invoices?invoice='+ids[a]+'&pdf=1')).status(),403,'B cannot download A PDF');
  await page.screenshot({path:output+'/company-invoices.png',fullPage:true});assert.deepEqual(errors,[]);
  console.log('Named-company invoice CRUD/numbering/PDF/manual partial+final receipts: real HTTP/MySQL/Chromium passed; no bank/provider operations.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
