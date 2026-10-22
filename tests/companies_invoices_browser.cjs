'use strict';
const { workspacePage } = require('./helpers/company_workspace_driver.cjs');
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
 const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await workspacePage(context),errors=[];page.on('pageerror',e=>errors.push(e.message));
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
   await page.locator('#invoice-item-0-description').fill('Synthetic invoice '+letter);await page.locator('#invoice-item-0-qty').fill('2.00');await page.locator('#invoice-item-0-rate').fill('50.25');await page.locator('#invoice-item-0-tax-0-name').fill('Synthetic tax');await page.locator('#invoice-item-0-tax-0-rate').fill('5.00');
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
  // Private portal permission is native, explicit and independent for A/B contacts.
  const publicContexts=[];
  try{
   await select(a);await page.goto(url+'/admin/company_clients?client='+clients[a]);
   const publicA=await browser.newContext();publicContexts.push(publicA);await publicA.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const portalA=await workspacePage(publicA);portalA.on('pageerror',e=>errors.push(e.message));
   async function portalLogin(p,issuer,email,password){await p.goto(url+'/company_portal/index/'+issuer);await p.locator('#login-email').fill(email);await p.locator('#login-password').fill(password);await navigate(p,p.locator('#portal-login button[type=submit]'));}
   async function portalPdf(ctx,p,issuer,invoice){await p.reload();const csrf=await fields(p.locator('form').filter({has:p.locator('input[name=action][value=logout]')}));return ctx.request.post(url+'/company_portal/index/'+issuer,{form:{...csrf,action:'invoice_pdf',invoice_id:invoice}});}
   await portalLogin(portalA,a,'changed-portal@example.invalid','synthetic-password-a');assert.equal(await portalA.locator('#portal-identity').count(),1);assert.equal(await portalA.locator('#portal-invoices').count(),0,'identity login does not implicitly grant financial access');
   assert.equal((await portalPdf(publicA,portalA,a,ids[a])).status(),404,'direct portal PDF denied before grant');
   await navigate(page,page.getByRole('button',{name:'Permitir acesso a faturas no portal',exact:true}));
   await portalA.reload();assert.equal(await portalA.locator('#portal-invoices article').count(),1,'native contact permission opens only own invoice list');
   const portalDownload=await portalPdf(publicA,portalA,a,ids[a]);assert.equal(portalDownload.status(),200);assert.equal((await portalDownload.body()).subarray(0,5).toString(),'%PDF-','authorized portal PDF uses actual TCPDF');
   assert.equal((await portalPdf(publicA,portalA,a,ids[b])).status(),404,'A cannot download direct B invoice ID');
   // Earlier recovery test deactivated B; reactivate only this disposable synthetic contact.
   sql('UPDATE tblcontacts SET active=1 WHERE userid='+clients[b]);const publicB=await browser.newContext();publicContexts.push(publicB);await publicB.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const portalB=await workspacePage(publicB);portalB.on('pageerror',e=>errors.push(e.message));
   await portalLogin(portalB,b,'same-portal@example.invalid','synthetic-password-b');assert.equal(await portalB.locator('#portal-invoices').count(),0,'A grant is not inherited by same-email B contact');
   await select(b);await page.goto(url+'/admin/company_clients?client='+clients[b]);await navigate(page,page.getByRole('button',{name:'Permitir acesso a faturas no portal',exact:true}));await portalB.reload();assert.equal(await portalB.locator('#portal-invoices article').count(),1);assert.equal((await portalPdf(publicB,portalB,b,ids[a])).status(),404,'B grant cannot authorize A PDF');
   await select(a);await page.goto(url+'/admin/company_clients?client='+clients[a]);await navigate(page,page.getByRole('button',{name:'Revogar acesso a faturas no portal',exact:true}));await portalA.reload();assert.equal(await portalA.locator('#portal-invoices').count(),0,'native revocation takes effect in existing session');assert.equal((await portalPdf(publicA,portalA,a,ids[a])).status(),404,'revoked session cannot download PDF');
   assert.equal(sql("SELECT COUNT(*) FROM tblissuers_audit WHERE event='company_portal_invoice_pdf_downloaded' AND issuer_id="+a+" AND subject_id="+ids[a]),'1','only authorized PDF delivery is audited as delivered');
  }finally{for(const ctx of publicContexts)await ctx.close();}
  // Expanded monetary editor: two native taxes, discount timing and signed adjustment in both owners.
  const raster=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAQAABQAAWX1h1kAAAAASUVORK5CYII=','base64');
  for(const [issuer,letter]of[[a,'a'],[b,'b']]){
   await select(issuer);await page.goto(url+'/admin/company_assets');
   for(const kind of ['logo','signature']){
    const form=page.locator('form').filter({has:page.locator('#asset-upload-'+kind)});
    await page.locator('#asset-upload-'+kind).setInputFiles({name:'synthetic-'+kind+'.png',mimeType:'image/png',buffer:raster});
    const oldFields=await fields(form);await navigate(page,form.locator('button[value=upload]'));
    assert.equal(await page.locator('#company-asset-'+kind).count(),1,'own image preview after real multipart upload');
    const downloaded=await context.request.get(url+'/admin/company_assets?download='+kind);assert.equal(downloaded.status(),200);assert.equal((await downloaded.body()).subarray(0,4).toString('hex'),'89504e47');
    const stale=await context.request.post(url+'/admin/company_assets',{form:{...oldFields,action:'remove'}});assert.equal(stale.status(),409,'old image editor cannot remove a newer revision');
   }
   const assetFields=await fields(page.locator('form').filter({has:page.locator('#asset-upload-logo')}));await select(issuer===a?b:a);
   assert.equal((await context.request.post(url+'/admin/company_assets',{form:{...assetFields,action:'remove'}})).status(),409,'image mutation from obsolete company tab denied before effects');
   await select(issuer);await page.goto(url+'/admin/settings?group=documents');
   await page.locator('#company-setting-invoice_prefix').fill('SYN-'+letter+'-');await page.locator('#company-setting-predefined_terms_invoice').fill('Synthetic terms '+letter);
   await navigate(page,page.locator('section form button[type=submit]'));
   await page.goto(url+'/admin/company_invoices');
   await page.locator('#invoice-client').selectOption(clients[issuer]);await page.locator('#invoice-currency').selectOption({label:'BRL'});
   await page.locator('#invoice-item-0-description').fill('Expanded '+letter);await page.locator('#invoice-item-0-rate').fill('100.00');
   await page.locator('#invoice-item-0-tax-0-name').fill('ISS');await page.locator('#invoice-item-0-tax-0-rate').fill('5.00');
   await page.locator('#invoice-items [data-add-tax]').click();await page.locator('#invoice-item-0-tax-1-name').fill('Levy');await page.locator('#invoice-item-0-tax-1-rate').fill('2.50');
   await page.locator('#discount-mode').selectOption('percent');await page.locator('#discount-value').fill('10.00');await page.locator('#discount-type').selectOption('before_tax');await page.locator('#adjustment').fill('-0.01');
   await navigate(page,page.locator('form').filter({has:page.locator('#invoice-client')}).locator('button[type=submit]'));
   const expandedId=new URL(page.url()).searchParams.get('invoice');assert.match(expandedId||'',/^[1-9]\d*$/);
   assert.equal(sql('SELECT CONCAT(total,"/",total_tax,"/",discount_total,"/",adjustment) FROM tblinvoices WHERE id='+expandedId),'96.74/6.75/10.00/-0.01');
   assert.equal(await page.locator('#edit-item-0-tax-1-rate').inputValue(),'2.50');assert.equal(await page.locator('#edit-discount-mode').inputValue(),'percent');
   const expandedPdf=await context.request.get(url+'/admin/company_invoices?invoice='+expandedId+'&pdf=1');assert.equal(expandedPdf.status(),200);assert.equal((await expandedPdf.body()).subarray(0,5).toString(),'%PDF-');
   await page.locator('#edit-discount-mode').selectOption('fixed');await page.locator('#edit-discount-type').selectOption('after_tax');await page.locator('#edit-adjustment').fill('0.01');
   await navigate(page,page.locator('form').filter({has:page.locator('#edit-invoice-date')}).locator('button[type=submit]'));
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+expandedId),'97.51');
   // Native multipart attachment upload: private by default, explicit portal publication per blob.
   const uploadForm=page.locator('form').filter({has:page.locator('#invoice-attachment')});
   await page.locator('#invoice-attachment').setInputFiles({name:'private-'+letter+'.png',mimeType:'image/png',buffer:raster});
   await navigate(page,uploadForm.locator('button[type=submit]'));
   const privateFile=sql('SELECT file_key FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId+' AND portal_visible=0');assert.match(privateFile,/^[a-f0-9]{64}$/);
   const binaryImage=await context.request.get(url+'/admin/company_invoices?invoice='+expandedId+'&attachment='+privateFile);assert.equal(binaryImage.status(),200);assert.equal((await binaryImage.body()).subarray(0,4).toString('hex'),'89504e47');
   const preview=await context.request.get(url+'/admin/company_invoices?invoice='+expandedId+'&attachment='+privateFile+'&preview=1');assert.equal(preview.status(),200);assert.equal(preview.headers()['x-content-type-options'],'nosniff');assert.match(preview.headers()['content-disposition'],/^inline;/);
   await page.locator('#invoice-attachment').setInputFiles({name:'visible-'+letter+'.pdf',mimeType:'application/pdf',buffer:await expandedPdf.body()});await page.locator('input[name=portal_visible]').check();
   const fileUploadFields=await fields(page.locator('form').filter({has:page.locator('#invoice-attachment')}));
   await navigate(page,page.locator('form').filter({has:page.locator('#invoice-attachment')}).locator('button[type=submit]'));
   const publicFile=sql('SELECT file_key FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId+' AND portal_visible=1');assert.match(publicFile,/^[a-f0-9]{64}$/);
   const fileRetry=await context.request.post(url+'/admin/company_invoices',{multipart:{...fileUploadFields,portal_visible:'1',invoice_attachment:{name:'visible-'+letter+'.pdf',mimeType:'application/pdf',buffer:await expandedPdf.body()}}});assert.equal(fileRetry.status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId),'2','multipart retry stores one blob and receipt');
   // Explicit private recipient, native queue and durable retry in both companies. No SMTP worker runs here.
   const expandedUrl=page.url();
   const contact=sql('SELECT id FROM tblcontacts WHERE userid='+clients[issuer]+' AND active=1 ORDER BY id LIMIT 1');
   assert.match(contact,/^[1-9]\d*$/);
   await page.goto(url+'/admin/company_clients?client='+clients[issuer]);
   const grant=page.locator('form').filter({has:page.locator('input[name=action][value=contact_invoice_access]')}).filter({has:page.locator('input[name=contact_id][value="'+contact+'"]')});
   assert.equal(await grant.count(),1,'exact native contact permission form is available');
   if(await grant.locator('input[name=invoice_access]').inputValue()==='1')await navigate(page,grant.locator('button[type=submit]'));
   assert.equal(sql('SELECT COUNT(*) FROM tblcontact_permissions WHERE userid='+contact+' AND permission_id=1'),'1','native permission persisted before notification');
   await page.goto(expandedUrl);
   await page.locator('#invoice-notify-contact').selectOption(contact);
   const notificationForm=page.locator('form').filter({has:page.locator('#invoice-notify-contact')});const notificationFields=await fields(notificationForm);
   await navigate(page,notificationForm.locator('button[type=submit]'));
   assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_deliveries WHERE issuer_id='+issuer+' AND invoice_id='+expandedId),'1');
   const queued=sql('SELECT q.status FROM tblmail_queue q JOIN tblcompany_invoice_deliveries d ON d.queue_id=q.id WHERE d.invoice_id='+expandedId);assert.equal(queued,'pending','GUI confirms queue persistence, never SMTP success');
   assert.match(await page.locator('[aria-label="Estado das notificações"]').innerText(),/Na fila/);
   const retryNotification=await context.request.post(url+'/admin/company_invoices',{form:{...notificationFields,contact_id:contact}});assert.equal(retryNotification.status(),200);
   assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_deliveries WHERE invoice_id='+expandedId),'1','same notification request does not queue twice');
   const otherContact=sql('SELECT id FROM tblcontacts WHERE userid='+clients[issuer===a?b:a]+' AND active=1 ORDER BY id LIMIT 1');
   const crossedNotification=await context.request.post(url+'/admin/company_invoices',{form:{...notificationFields,request_key:'e'.repeat(64),contact_id:otherContact}});assert.equal(crossedNotification.status(),403,'foreign contact ID denied despite equal or familiar email');
   // Real private portal lists only the published blob, and hiding revokes its existing download form.
   const filePortalContext=await browser.newContext();await filePortalContext.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
   try{
    const filePortal=await workspacePage(filePortalContext);filePortal.on('pageerror',e=>errors.push(e.message));
    await filePortal.goto(url+'/company_portal/index/'+issuer);await filePortal.locator('#login-email').fill(letter==='a'?'changed-portal@example.invalid':'same-portal@example.invalid');await filePortal.locator('#login-password').fill('synthetic-password-'+letter);await navigate(filePortal,filePortal.locator('#portal-login button[type=submit]'));
    const publishedForm=filePortal.locator('form').filter({has:filePortal.locator('input[name=file_key][value="'+publicFile+'"]')});assert.equal(await publishedForm.count(),1);assert.equal(await filePortal.locator('input[name=file_key][value="'+privateFile+'"]').count(),0,'default-private attachment metadata never reaches contact HTML');
    const portalFileFields=await fields(publishedForm);const publicDownload=await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:portalFileFields});assert.equal(publicDownload.status(),200);assert.equal((await publicDownload.body()).subarray(0,5).toString(),'%PDF-');assert.match(publicDownload.headers()['content-disposition'],/^attachment;/);
    assert.equal((await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:{...portalFileFields,file_key:privateFile}})).status(),404,'portal direct private key denied before bytes');
    const hideForm=page.locator('form').filter({has:page.locator('input[name=action][value=attachment_hide]')}).filter({has:page.locator('input[name=file_key][value="'+publicFile+'"]')});const hideFields=await fields(hideForm);await navigate(page,hideForm.locator('button[type=submit]'));
    assert.equal((await context.request.post(url+'/admin/company_invoices',{form:hideFields})).status(),200,'hide retry is durable without duplicate mutation');
    assert.equal((await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:portalFileFields})).status(),404,'hidden attachment denies saved authorized portal form');await filePortal.reload();assert.equal(await filePortal.locator('input[name=file_key][value="'+publicFile+'"]').count(),0);
   }finally{await filePortalContext.close();}
   // Two tabs: a saved monetary form from A cannot mutate after switching to B.
   const prior=await fields(page.locator('form').filter({has:page.locator('#edit-invoice-date')}));
   await select(issuer===a?b:a);
   assert.equal((await context.request.get(url+'/admin/company_invoices?invoice='+expandedId+'&attachment='+privateFile)).status(),403,'foreign company cannot download attachment by exact known parent and key');
   const obsoleteUpload=await context.request.post(url+'/admin/company_invoices',{multipart:{...fileUploadFields,portal_visible:'1',invoice_attachment:{name:'visible-'+letter+'.pdf',mimeType:'application/pdf',buffer:await expandedPdf.body()}}});assert.equal(obsoleteUpload.status(),409,'obsolete company multipart denied before write');
   const obsoleteNotification=await context.request.post(url+'/admin/company_invoices',{form:{...notificationFields,contact_id:contact}});assert.equal(obsoleteNotification.status(),409,'obsolete company notification page denied before queue effects');
   const obsolete=await context.request.post(url+'/admin/company_invoices',{form:{...prior,action:'cancel'}});assert.equal(obsolete.status(),409);
   assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+expandedId),'1');
  }
  await page.screenshot({path:output+'/company-invoices.png',fullPage:true});assert.deepEqual(errors,[]);
  console.log('Named-company invoice CRUD/numbering/PDF/manual partial+final receipts/private portal grant+PDF+revocation: real HTTP/MySQL/Chromium passed, including owner-bound notifications and private attachment upload/preview/download/portal revocation; no SMTP or bank/provider operations.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
