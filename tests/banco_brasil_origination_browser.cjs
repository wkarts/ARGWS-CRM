'use strict';
// Actual CRM/HTTP/MySQL/browser, exclusively on the disposable standalone-upgrade fixture.
const assert = require('node:assert/strict');
const { execFileSync, spawnSync } = require('node:child_process');
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
  execFileSync('docker',['cp','tests/support/BbHttpNativeRelease.php',app+':/app/tests/support/BbHttpNativeRelease.php']);
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
  // A bank credit cannot predate invoice issuance: choose the bank's business date explicitly, independent of the PHP default timezone.
  await page.locator('#invoice-date').fill(new Intl.DateTimeFormat('en-CA',{timeZone:'America/Sao_Paulo'}).format(new Date()));
  await page.locator('#invoice-item-0-description').fill('SYNTHETIC BB SERVICE');await page.locator('#invoice-item-0-qty').fill('1');await page.locator('#invoice-item-0-rate').fill('100.00');
  await navigate(page,page.locator('form').filter({has:page.locator('#invoice-client')}).locator('button[type=submit]'));
  const invoice=new URL(page.url()).searchParams.get('invoice');assert.match(invoice||'',/^[1-9]\d*$/);
  let response=await page.goto(url+'/admin/banco_brasil?issuer_id='+a);assert.equal(response.status(),200,(await page.locator('body').innerText()).replace(/\s+/g,' ').slice(0,350));
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
  const nativeContract=page.locator('form').filter({has:page.locator('#bb-contract-beneficiary_name')});
  assert.equal(await nativeContract.count(),1);
  await navigate(page,nativeContract.getByRole('button',{name:'Registrar declaração do operador',exact:true}));
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
  // Independent synthetic T/U fixture, delivered through the actual multipart route.
  const due=sql('SELECT duedate FROM tblinvoices WHERE id='+invoice).split('-').reverse().join('');
  const credit=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Sao_Paulo'}).format(new Date()).split('-').reverse().join('');
  const records=bytes.toString().split('\r\n');function record(fields){let line=' '.repeat(240);for(const [start,value]of fields){line=line.slice(0,start-1)+value+line.slice(start-1+value.length);}return line;}
  const account='000000123873';const remote='12345670000000100';
  const t=record([[1,'0010001300001T 06'],[18,'00452'],[23,'3'],[24,account],[36,'X'],[38,remote.padEnd(20)],[58,'7'],[59,('CRM'+invoice).padEnd(15)],[74,due],[82,'10000'.padStart(15,'0')],[106,('CRM'+invoice).padEnd(25)],[131,'09'],[199,'0'.repeat(15)],[214,' '.repeat(10)]]);
  const u=record([[1,'0010001300002U 06'],[18,'0'.repeat(60)],[78,'10000'.padStart(15,'0')],[93,'10000'.padStart(15,'0')],[108,'0'.repeat(30)],[138,credit],[146,credit]]);
  const returned=Buffer.from([records[0].slice(0,142)+'2'+records[0].slice(143),records[1].slice(0,8)+'T'+records[1].slice(9),t,u,records[4],records[5]].join('\r\n')+'\r\n');
  await page.locator('#bb-tab-return').click();const importForm=page.locator('form[action*="import_return"]');
  const hiddenReturn=await importForm.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.map(x=>[x.name,x.value])));
  const multipart={...hiddenReturn,connection_id:connection,layout_binding_id:binding,return_file:{name:'synthetic.ret',mimeType:'application/octet-stream',buffer:returned}};
  const imported=await context.request.post(url+'/admin/banco_brasil/import_return',{multipart});assert.equal(imported.status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+invoice),'0','upload cannot become an automatic payment');
  await page.reload();await page.locator('#bb-tab-return').click();const event=sql('SELECT id FROM tblbb_return_events WHERE issuer_id='+a);assert.match(event,/^[1-9]\d*$/);
  let detail=page.locator('details').filter({has:page.locator('input[name=event_id][value="'+event+'"]')});await detail.locator('summary').click();await navigate(page,detail.locator('form[action*="link_event"] button'));
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_event_links WHERE event_id='+event),'1');detail=page.locator('details').filter({has:page.locator('input[name=event_id][value="'+event+'"]')});await detail.locator('summary').click();
  const settle=detail.locator('form[action*="settle_event"]');await settle.locator('input[type=checkbox]').check();await settle.locator('input[name="settlement[reason]"]').fill('Synthetic credit independently reviewed');await settle.locator('input[name="settlement[reference]"]').fill('SYNTHETIC CI CREDIT ONLY');
  const settlementData=await settle.locator('input').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));await navigate(page,settle.locator('button'));
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+invoice),'1');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+invoice),'2');assert.equal(sql('SELECT financial_state FROM tblbb_return_events WHERE id='+event),'applied_assisted');
  const retry=await context.request.post(url+'/admin/banco_brasil/settle_event',{form:settlementData});assert.equal(retry.status(),200);await context.request.post(url+'/admin/banco_brasil/import_return',{multipart});assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+invoice),'1','retry and reimport cannot duplicate reviewed payment');
  // Configure native API vault through the real UI. This browser never invokes an API banking operation.
  await page.goto(url+'/admin/banco_brasil?issuer_id='+a+'#bb-auth');await page.locator('#bb-api-connection').selectOption(connection);await page.locator('#bb-api-stage').selectOption('homologation');
  for(const [key,value]of Object.entries({first_number:'100',last_number:'999',first_file:'1',reference:'SYNTHETIC API CONTRACT ONLY'}))await page.locator('#bb-api-'+key).fill(value);
  await page.locator('#bb-api-interest-policy').selectOption('exempt');await page.locator('input[name="api_profile[interest_policy_confirmed]"]').check();await page.locator('input[name="api_profile[exclusive_range]"]').check();await navigate(page,page.locator('form[action*="declare_api_profile"] button'));
  const apiProfile=sql('SELECT id FROM tblbb_api_profiles WHERE issuer_id='+a);assert.match(apiProfile,/^[1-9]\d*$/);
  await page.locator('#bb-api-credential-profile').selectOption(apiProfile);
  for(const [key,value]of Object.entries({client_id:'SYNTHETIC-UI-CLIENT',client_secret:'SYNTHETIC-UI-SECRET',application_key:'SYNTHETIC-UI-KEY'}))await page.locator('#bb-api-credential-'+key).fill(value);
  await navigate(page,page.locator('form[action*="save_api_credentials"] button'));
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_api_credentials WHERE profile_id='+apiProfile),'1','native encryption/vault UI saves the explicitly selected profile');
  const ciphertext=sql('SELECT ciphertext FROM tblbb_api_credentials WHERE profile_id='+apiProfile);assert(!ciphertext.includes('SYNTHETIC-UI-SECRET'));assert(!(await page.content()).includes('SYNTHETIC-UI-SECRET'));assert.equal(await page.locator('#bb-api-credential-client_secret').inputValue(),'');
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_api_operations'),'0','saving credentials does not enqueue a bank operation');
  // The HTTP fixture invokes production API services with an injected simulated transport,
  // then the real browser endpoint and writer apply the credit without any bank HTTP.
  execFileSync('docker',['cp','tests/banco_brasil_api_credit_http_fixture.php',app+':/app/tests/banco_brasil_api_credit_http_fixture.php']);
  const creditFixture=JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/banco_brasil_api_credit_http_fixture.php'],{encoding:'utf8'}));
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+creditFixture.invoice),'0','validated API query does not apply payment automatically');
  await page.goto(url+'/admin/banco_brasil?issuer_id='+a+'#bb-billing');
  // A fragment-only navigation reuses the old DOM; the CLI fixture changed the server state.
  await page.reload({waitUntil:'domcontentloaded'});await page.locator('#bb-tab-billing').click();
  const apiCredit=page.locator('form[action*=settle_api]').filter({has:page.locator('input[name=operation_id][value="'+creditFixture.operation+'"]')});
  assert.equal(await apiCredit.count(),1,JSON.stringify({page:(await page.locator('body').innerText()).slice(0,18000),proof:sql('SELECT operation,status,result_json FROM tblbb_api_operations WHERE id='+creditFixture.operation),receipt:sql('SELECT COUNT(*) FROM tblbb_api_receipts WHERE origin_id='+creditFixture.origin)}));
  await apiCredit.locator('input[type=checkbox]').check();const apiCreditFields=await apiCredit.locator('input').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));
  await navigate(page,apiCredit.locator('button'));assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+creditFixture.invoice),'2');
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_api_receipts WHERE origin_id='+creditFixture.origin),'1','API credit and canonical provenance commit together');
  await context.request.post(url+'/admin/banco_brasil/settle_api',{form:apiCreditFields});assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+creditFixture.invoice),'1','browser retry does not duplicate API payment');
  await select(b);response=await context.request.post(url+'/admin/banco_brasil/download_remittance',{form:data});assert.equal(response.status(),409,'stale context rejected before old batch download');
  const cross=await page.goto(url+'/admin/banco_brasil?issuer_id='+a);assert.equal(cross.status(),403,'selected B cannot request A banking history');
  // Complete positive UI credit path for B, with a distinct account/agreement/profile.
  await page.goto(url+'/admin/banco_brasil?issuer_id='+b);await page.locator('#bb-tab-account').click();
  await page.locator('#bb_account_id').fill('001:00452-3:000000223873-X');await page.locator('#bb_env').selectOption('sandbox');await page.locator('#bb_agreement').fill('7654321');
  await page.locator('input[name=variation]').fill('019');await navigate(page,page.locator('input[name=variation]').locator('xpath=ancestor::form').locator('button'));
  const connectionB=sql('SELECT id FROM tblbb_connections WHERE issuer_id='+b);await page.locator('#bb-api-connection').selectOption(connectionB);await page.locator('#bb-api-stage').selectOption('homologation');
  for(const [key,value]of Object.entries({first_number:'100',last_number:'999',first_file:'1',reference:'SYNTHETIC B API CONTRACT'}))await page.locator('#bb-api-'+key).fill(value);
  await page.locator('#bb-api-interest-policy').selectOption('exempt');await page.locator('input[name="api_profile[interest_policy_confirmed]"]').check();await page.locator('input[name="api_profile[exclusive_range]"]').check();await navigate(page,page.locator('form[action*="declare_api_profile"] button'));
  const profileB=sql('SELECT id FROM tblbb_api_profiles WHERE issuer_id='+b);await page.locator('#bb-api-credential-profile').selectOption(profileB);
  for(const [key,value]of Object.entries({client_id:'SYNTHETIC-B-CLIENT',client_secret:'SYNTHETIC-B-SECRET',application_key:'SYNTHETIC-B-KEY'}))await page.locator('#bb-api-credential-'+key).fill(value);
  await navigate(page,page.locator('form[action*="save_api_credentials"] button'));
  const fixtureB=JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/banco_brasil_api_credit_http_fixture.php','b'],{encoding:'utf8'}));
  await page.reload({waitUntil:'domcontentloaded'});await page.locator('#bb-tab-billing').click();
  const creditB=page.locator('form[action*=settle_api]').filter({has:page.locator('input[name=operation_id][value="'+fixtureB.operation+'"]')});await creditB.locator('input[type=checkbox]').check();await navigate(page,creditB.locator('button'));
  assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+fixtureB.invoice+' AND issuer_id='+b),'2','B positive credit settles its independently owned invoice');
  assert.equal(sql('SELECT COUNT(*) FROM tblbb_api_receipts WHERE origin_id='+fixtureB.origin+' AND issuer_id='+b),'1');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+creditFixture.invoice),'1','B positive flow preserves A payment');
  // Explicit positive daily-interest profile and authenticated movement observation in A and B.
  for(const [issuer,conn,letter] of [[a,connection,'a'],[b,connectionB,'b']]){
   await select(issuer);await page.goto(url+'/admin/banco_brasil?issuer_id='+issuer+'#bb-auth');await page.locator('#bb-api-connection').selectOption(conn);await page.locator('#bb-api-stage').selectOption('homologation');
   for(const [key,value]of Object.entries({first_number:'100',last_number:'999',first_file:'1',reference:'SYNTHETIC DAILY INTEREST '+letter}))await page.locator('#bb-api-'+key).fill(value);
   const beforeNumber=sql('SELECT next_number FROM tblbb_number_reservations WHERE issuer_id='+issuer);
   await page.locator('#bb-api-interest-policy').selectOption('daily_fixed');await page.locator('input[name="api_profile[interest_policy_confirmed]"]').check();await page.locator('input[name="api_profile[exclusive_range]"]').check();await navigate(page,page.locator('form[action*="declare_api_profile"] button'));
   assert.equal(sql('SELECT next_number FROM tblbb_number_reservations WHERE issuer_id='+issuer),beforeNumber,'interest profile does not restart the shared bank number range');
   const interestProfile=sql('SELECT MAX(id) FROM tblbb_api_profiles WHERE issuer_id='+issuer);assert.equal(JSON.parse(sql('SELECT definition_json FROM tblbb_api_profiles WHERE id='+interestProfile)).interest_policy,'daily_fixed');
   await page.locator('#bb-api-credential-profile').selectOption(interestProfile);for(const [key,value]of Object.entries({client_id:'SYNTHETIC-DAILY-'+letter,client_secret:'SYNTHETIC-DAILY-SECRET-'+letter,application_key:'SYNTHETIC-DAILY-KEY-'+letter}))await page.locator('#bb-api-credential-'+key).fill(value);await navigate(page,page.locator('form[action*="save_api_credentials"] button'));
   const interestFixture=JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/banco_brasil_api_credit_http_fixture.php',letter,'daily'],{encoding:'utf8'}));
   await page.reload({waitUntil:'domcontentloaded'});await page.locator('#bb-tab-billing').click();assert.equal(await page.locator('#bb-api-daily-interest').count(),1);assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+interestFixture.invoice),'0','interest registration/query/movement observation creates no financial receipt');
   const movementProof=JSON.parse(sql('SELECT result_json FROM tblbb_api_operations WHERE id='+interestFixture.movementOperation));assert.equal(movementProof.movements[0].fee,'0.50');assert.equal(movementProof.movements[0].received,'20.00');
   const movementDetails=page.locator('details').filter({has:page.locator('summary').filter({hasText:new RegExp('^Operação #'+interestFixture.movementOperation+' /')})});await movementDetails.evaluate(element=>{element.open=true;});assert.equal(await movementDetails.getAttribute('open'),'');
   assert.ok((await page.locator('body').innerText()).includes('movement_page_observed'));assert.ok((await page.locator('body').innerText()).includes('Sem autoridade financeira automática.'));
  }
  assert.deepEqual(errors,[]);console.log('BB invoice-bound CNAB240 declaration/generation/download/manual report/return upload/assisted receipt/retry/API native encrypted configuration and A/B scope: actual HTTP/MySQL/Chromium, no bank calls.');
 }catch(error){const logs=spawnSync('docker',['logs','--tail','80',app],{encoding:'utf8'});console.error('Disposable BB HTTP server diagnostic:',(logs.stdout||'')+(logs.stderr||''));throw error;}finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
