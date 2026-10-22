'use strict';
const { workspacePage } = require('../helpers/company_workspace_driver.cjs');
// New recovery regression: actual shared form, cloned fields, optional flags, PDF and conversion.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),{chromium}=require('playwright');
const base=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER,output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';
assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');fs.mkdirSync(output,{recursive:true});
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function nav(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.ISSUERS_BROWSER_EXECUTABLE_PATH?{executablePath:process.env.ISSUERS_BROWSER_EXECUTABLE_PATH}:{})});const context=await browser.newContext();
 await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
 const page=await workspacePage(context),errors=[],results=[],stamp=Date.now();page.on('pageerror',error=>errors.push(error.message));
 try{
  await page.goto(base+'/admin/authentication');await page.locator('[name=email]').fill('admin-ci@example.invalid');await page.locator('[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await nav(page,page.locator('button[type=submit]'));
  const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");assert.match(a,/^[1-9]\d*$/);assert.match(b,/^[1-9]\d*$/);
  const cashBefore=sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords');
  async function select(issuer){await page.goto(base+'/admin/company_context');await page.locator('#company-context-select').selectOption(issuer);await nav(page,page.locator('.company-selector-form button[type=submit]'));}
  for(const [issuer,letter] of [[a,'a'],[b,'b']])for(const kind of ['estimates','proposals']){
   await select(issuer);const client=sql("SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id="+issuer+" AND c.company='Synthetic portal client "+letter+"' ORDER BY c.userid LIMIT 1");assert.match(client,/^[1-9]\d*$/);
   await page.goto(base+'/admin/company_sales?kind='+kind);await page.locator('#invoice-client').selectOption(client);await page.locator('#invoice-date').fill('2026-10-19');await page.locator('#invoice-due').fill('2026-11-19');
   await page.locator('#invoice-item-0-description').fill('Required metadata '+letter+' '+stamp);await page.locator('#invoice-item-0-qty').fill('2.00');await page.locator('#invoice-item-0-rate').fill('50.00');
   await page.locator('#invoice-item-0-details').fill('Complemento literal <b>não HTML</b>\nOutra linha');await page.locator('#invoice-item-0-unit').fill('hora <b>');await page.locator('#invoice-item-0-tax-0-name').fill('Tax');await page.locator('#invoice-item-0-tax-0-rate').fill('10.00');
   // Cloning a chosen optional row must reset only the new row and correctly renumber all field types.
   await page.locator('#invoice-item-0-choice').selectOption('chosen');
   assert.equal(await page.locator('[name="invoice[items][0][is_optional]"]').inputValue(),'1');
   await page.locator('[data-invoice-add="sales-items"]').click();
   assert.equal(await page.locator('#invoice-item-1-details').count(),1);assert.equal(await page.locator('#invoice-item-1-details').getAttribute('name'),'invoice[items][1][long_description]');
   assert.equal(await page.locator('#invoice-item-1-details').inputValue(),'');assert.equal(await page.locator('#invoice-item-1-unit').inputValue(),'');
   assert.equal(await page.locator('#invoice-item-1-choice').inputValue(),'required');assert.equal(await page.locator('[name="invoice[items][1][is_optional]"]').inputValue(),'0');assert.equal(await page.locator('[name="invoice[items][1][is_selected]"]').inputValue(),'1');
   await page.locator('#invoice-item-0-choice').selectOption('required');
   await page.locator('#invoice-item-1-description').fill('Chosen metadata '+letter);await page.locator('#invoice-item-1-qty').fill('1.00');await page.locator('#invoice-item-1-rate').fill('20.00');await page.locator('#invoice-item-1-details').fill('Detalhe escolhido');await page.locator('#invoice-item-1-unit').fill('UN');await page.locator('#invoice-item-1-choice').selectOption('chosen');await page.locator('#invoice-item-1-tax-0-name').fill('Tax');await page.locator('#invoice-item-1-tax-0-rate').fill('5.00');
   await page.locator('[data-invoice-add="sales-items"]').click();
   await page.locator('#invoice-item-2-description').fill('NEVER-RENDER-OPTIONAL');await page.locator('#invoice-item-2-qty').fill('1.00');await page.locator('#invoice-item-2-rate').fill('999.00');await page.locator('#invoice-item-2-details').fill('NEVER-RENDER-OPTIONAL-DETAIL');await page.locator('#invoice-item-2-choice').selectOption('unselected');assert.equal(await page.locator('[name="invoice[items][2][is_optional]"]').inputValue(),'1');assert.equal(await page.locator('[name="invoice[items][2][is_selected]"]').inputValue(),'0');
   await page.locator('#invoice-note').fill('Recovery metadata '+letter+' '+kind+' '+stamp);
   await nav(page,page.getByRole('button',{name:'Emitir documento privado',exact:true}));
   const document=new URL(page.url()).searchParams.get('document');assert.match(document||'',/^[1-9]\d*$/);
   assert.equal(sql('SELECT total FROM tblcompany_sales_documents WHERE id='+document),'131.00');
   const body=await page.locator('section').first().innerText();assert(body.includes('Complemento literal <b>não HTML</b>'));assert(body.includes('hora <b>'));assert(body.includes('Opcional não escolhido — fora do total e do PDF'));
   const pdf=await context.request.get(await page.getByRole('link',{name:'Baixar PDF privado',exact:true}).getAttribute('href'));assert.equal(pdf.status(),200);assert.match(pdf.headers()['content-type'],/application\/pdf/);const bytes=await pdf.body();assert.equal(bytes.subarray(0,5).toString(),'%PDF-');
   const text=execFileSync('pdftotext',['-layout','-','-'],{input:bytes,encoding:'utf8'}).replace(/\s+/g,' ');assert(text.includes('Complemento literal <b>não HTML</b>'));assert(text.includes('hora <b>'));assert(!text.includes('NEVER-RENDER-OPTIONAL'));assert(text.includes('131.00'));
   await nav(page,page.getByRole('button',{name:'Registrar aceite',exact:true}));await nav(page,page.getByRole('button',{name:'Converter em fatura',exact:true}));
   const invoice=sql('SELECT invoice_id FROM tblcompany_sales_documents WHERE id='+document);assert.match(invoice,/^[1-9]\d*$/);assert.equal(sql('SELECT CONCAT(subtotal,\':\',total_tax,\':\',total) FROM tblinvoices WHERE id='+invoice),'120.00:11.00:131.00');
   assert.equal(sql("SELECT COUNT(*) FROM tblitemable WHERE rel_type='invoice' AND rel_id="+invoice),'3');assert.equal(sql("SELECT CONCAT(is_optional,':',is_selected) FROM tblitemable WHERE rel_type='invoice' AND rel_id="+invoice+" ORDER BY item_order DESC LIMIT 1"),'1:0');
   const nativePdf=await context.request.get(base+'/admin/company_invoices?invoice='+invoice+'&pdf=1');assert.equal(nativePdf.status(),200);const nativeText=execFileSync('pdftotext',['-layout','-','-'],{input:await nativePdf.body(),encoding:'utf8'}).replace(/\s+/g,' ');assert(nativeText.includes('Complemento literal <b>não HTML</b>'));assert(!nativeText.includes('NEVER-RENDER-OPTIONAL'));
   results.push({issuer,kind,document,invoice,total:'131.00',native_lines:3,charged_lines:2,cloned_metadata_and_flags:true,literal_pdf:true});
  }
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords'),cashBefore);assert.deepEqual(errors,[]);const proof={source:'rebuilt recovery439 regression',results,external_transports:false};fs.writeFileSync(output+'/operations-resume439-sales-lines.json',JSON.stringify(proof,null,2));console.log(JSON.stringify(proof));
 }finally{await context.close();await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
