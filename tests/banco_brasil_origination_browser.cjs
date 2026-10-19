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
  execFileSync('docker',['exec',app,'mkdir','-p','/app/tests/support']);
  execFileSync('docker',['cp','tests/banco_brasil_http_fixture.php',app+':/app/tests/banco_brasil_http_fixture.php']);
  execFileSync('docker',['cp','tests/support/BbPdoDatabase.php',app+':/app/tests/support/BbPdoDatabase.php']);
  execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/banco_brasil_http_fixture.php'],{stdio:'pipe'});
  async function select(id){await page.goto(url+'/admin/company_context');await page.locator('#company-context-select').selectOption(id);await navigate(page,page.locator('.company-selector-form button[type=submit]'));}
  await select(a);
  // CPF is a source field of the invoice snapshot, never a remittance form override.
  const client=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client a'");
  await page.goto(url+'/admin/company_clients?client='+client);
  const vat=page.locator('#edit_vat');await vat.fill('11144477735');
  await navigate(page,vat.locator('xpath=ancestor::form').locator('button[type=submit]'));
  await page.goto(url+'/admin/company_invoices');await page.locator('#invoice-client').selectOption(client);await page.locator('#invoice-currency').selectOption({label:'BRL'});
  await page.locator('#invoice-item-0-description').fill('SYNTHETIC BB SERVICE');await page.locator('#invoice-item-0-qty').fill('1');await page.locator('#invoice-item-0-rate').fill('100.00');
  await navigate(page,page.locator('form').filter({has:page.locator('#invoice-client')}).locator('button[type=submit]'));
  const invoice=new URL(page.url()).searchParams.get('invoice');assert.match(invoice||'',/^[1-9]\d*$/);
  let response=await page.goto(url+'/admin/banco_brasil?issuer_id='+a);assert.equal(response.status(),200);
  await page.locator('#bb-tab-account').click();await page.locator('#bb_account_id').fill('001:00452-3:000000123873-X');await page.locator('#bb_env').selectOption('sandbox');await page.locator('#bb_agreement').fill('1234567');
  const variation=page.locator('input[name=variation]');await variation.fill('019');
  await navigate(page,variation.locator('xpath=ancestor::form').locator('button'));
  const connection=sql('SELECT id FROM tblbb_connections WHERE issuer_id='+a);assert.match(connection,/^[1-9]\d*$/);
  await page.locator('#bb-tab-account').click();const layoutForm=page.locator('form[action*="register_layout"]');
  await layoutForm.locator('select[name=connection_id]').selectOption(connection);await layoutForm.locator('select[name=layout_id]').selectOption('bb-cobranca-240-2026-06-084-043');await navigate(page,layoutForm.locator('button'));
  const binding=sql('SELECT id FROM tblbb_layout_bindings WHERE connection_id='+connection);
  await page.locator('#bb-tab-remittance').click();
  for(const [key,value]of Object.entries({beneficiary_name:'SYNTHETIC PORTAL A',first_number:'100',last_number:'999',first_file:'1',reference:'SYNTHETIC CONTRACT ONLY'}))await page.locator('#bb-contract-'+key).fill(value);
  await page.locator('input[name="contract[interest_exempt]"]').check();await page.locator('input[name="contract[exclusive_range]"]').check();await page.locator('#bb-contract-test').selectOption('1');
  await navigate(page,page.locator('form[action*="declare_contract"]').locator('button'));
  await page.waitForFunction(()=>document.querySelector('#bb-remittance').getAttribute('aria-hidden')==='false');
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_contract_declarations WHERE issuer_id='+a),'1');
  for(const [key,value]of Object.entries({invoice_id:invoice,payer_name:'SYNTHETIC PORTAL CLIENT A',payer_address:'SYNTHETIC STREET',payer_district:'CENTRO',payer_zip:'40000000',payer_city:'SALVADOR',payer_state:'BA'}))await page.locator('#bb-title-'+key).fill(value);
  await navigate(page,page.locator('form[action*="generate_remittance"]').locator('button'));
  const batch=sql('SELECT id FROM tblbb_batches WHERE issuer_id='+a);assert.match(batch,/^[1-9]\d*$/);assert.equal(sql('SELECT generation_state FROM tblbb_batches WHERE id='+batch),'generated');
  assert.equal(sql('SELECT delivery_state FROM tblbb_batches WHERE id='+batch),'not_submitted');
  const downloadForm=page.locator('form[action*="download_remittance"]');const data=await downloadForm.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.map(x=>[x.name,x.value])));
  const download=await context.request.post(url+'/admin/banco_brasil/download_remittance',{form:data});assert.equal(download.status(),200);
  const bytes=await download.body();assert.equal(bytes.length,6*242);assert.equal(bytes.toString().split('\r\n')[2].slice(37,54),'12345670000000100');
  assert.equal(sql('SELECT delivery_state FROM tblbb_batches WHERE id='+batch),'downloaded');
  await page.reload();await page.locator('#bb-tab-remittance').click();await page.locator('input[name=submission_reference]').fill('SYNTHETIC OPERATOR STATEMENT');await navigate(page,page.locator('form[action*="report_transmission"]').locator('button'));
  assert.equal(sql('SELECT delivery_state FROM tblbb_batches WHERE id='+batch),'reported_submitted');assert.equal(sql('SELECT financial_state FROM tblbb_batches WHERE id='+batch),'not_applied');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+invoice),'0');
  await select(b);response=await context.request.post(url+'/admin/banco_brasil/download_remittance',{form:data});assert.equal(response.status(),409,'stale context rejected before old batch download');
  const cross=await page.goto(url+'/admin/banco_brasil?issuer_id='+a);assert.equal(cross.status(),403,'selected B cannot request A banking history');
  assert.deepEqual(errors,[]);console.log('BB invoice-bound CNAB240 declaration/generation/download/manual report and A/B scope: actual HTTP/MySQL/Chromium, no bank calls.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
