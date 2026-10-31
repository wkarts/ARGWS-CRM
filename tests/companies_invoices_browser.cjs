'use strict';
const { selectCompanyOption, ensureCompanySidebar } = require('./helpers/native_selector_driver.cjs');
const { workspacePage } = require('./helpers/native_workspace_driver.cjs');
const native = require('./helpers/native_invoice_driver.cjs');
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
  await button.waitFor({state:'attached'});
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}
async function hidden(page) {
  return page.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(x => x.name && !x.name.startsWith('wizard[')).map(x => [x.name, x.value])));
}
async function next(page) { await navigate(page, page.locator('section form button[type=submit]')); }


// Read-only evidence from the authenticated disposable CRM, not a synthetic DOM.
// Each route is reloaded at its target viewport so native responsive initialization runs.
async function captureRealScreens(page, routes) {
  const originalUrl = page.url(), originalViewport = page.viewportSize();
  try {
    for (const [name, route] of routes) for (const [size, viewport] of [['desktop', {width:1440,height:960}], ['mobile', {width:390,height:844}]]) {
      await page.setViewportSize(viewport);
      const response = await page.goto(route, {waitUntil:'load'});
      assert.equal(response.status(), 200, name + ' evidence uses an authorized real document');
      await page.locator('#wrapper').waitFor({state:'visible'});
      if (size === 'mobile') await page.waitForFunction(() => document.body.classList.contains('company-context-shell') || document.body.classList.contains('page-small'));
      await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
      const geometry = await page.evaluate(() => ({viewport:{width:innerWidth,height:innerHeight}, pageWidth:document.documentElement.scrollWidth,
        fitsViewport:document.documentElement.scrollWidth <= innerWidth + 1, bodyClasses:document.body.className,
        elements:Array.from(document.querySelectorAll('#header,#menu,#wrapper,.company-registry,.company-native-settings,[data-company-workspace]')).map(element => {
          const box=element.getBoundingClientRect();return {id:element.id, classes:element.className, left:box.left,right:box.right,width:box.width,scrollWidth:element.scrollWidth};
        })}));
      const prefix = output + '/real-' + name + '-' + size;
      fs.writeFileSync(prefix + '.json', JSON.stringify(geometry,null,2));
      await page.screenshot({path:prefix + '.png',fullPage:true});
      if (name === 'invoice-detail' && size === 'desktop') await page.screenshot({path:output + '/company-invoices.png',fullPage:true});
      if (size === 'mobile' && name === 'companies-grid') {
        await ensureCompanySidebar(page);
        await page.locator('.company-selector .dropdown-toggle').waitFor({state:'visible'});
        await page.screenshot({path:output + '/real-companies-sidebar-mobile.png',fullPage:true});
      }
    }
  } finally {
    await page.setViewportSize(originalViewport);
    await page.goto(originalUrl, {waitUntil:'load'});
  }
}

(async()=>{
 const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await workspacePage(context),errors=[];page.on('pageerror',e=>errors.push(e.stack||e.message));
 try{
  await login(page,'admin-ci@example.invalid',process.env.ISSUERS_BROWSER_PASSWORD);
  const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
  const clients={};clients[a]=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client a'");clients[b]=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client b'");
  async function select(id){await page.goto(url+'/admin/');await selectCompanyOption(page, id);}
  async function fields(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
  const ids={};
  // Synthetic catalogue prerequisites only. Invoice, edit, payment and file writes below use original HTTP/UI.
  for(const issuer of [a,b]){
   for(const [name,rate] of [['Synthetic tax','5.0000'],['ISS','5.0000'],['Levy','2.5000']])if(sql("SELECT COUNT(*) FROM tblcompany_taxes WHERE issuer_id="+issuer+" AND name='"+name+"'")==='0')sql("INSERT INTO tblcompany_taxes(issuer_id,name,taxrate,revision) VALUES("+issuer+",'"+name+"','"+rate+"',1)");
   if(sql("SELECT COUNT(*) FROM tblcompany_payment_modes WHERE issuer_id="+issuer+" AND name='Synthetic manual receipt'")==='0')sql("INSERT INTO tblcompany_payment_modes(issuer_id,name,description,active,show_on_pdf,selected_by_default,invoices_only,expenses_only,revision,created_by) VALUES("+issuer+",'Synthetic manual receipt','Synthetic offline mode',1,1,1,1,0,1,17)");
  }
  async function payment(id,amount,reference){
   const [paymentFormResponse]=await Promise.all([page.waitForResponse(r=>new URL(r.url()).pathname==='/admin/invoices/record_invoice_payment_ajax/'+id),page.locator('a[onclick*="record_payment("]').click()]);
   assert.equal(paymentFormResponse.status(),200,'original payment form remains available after a partial receipt');
   const form=page.locator('#record_payment_form');await form.locator('.panel_s').waitFor({state:'visible'});
   await form.locator('#amount').fill(amount);await form.locator('#transactionid').fill(reference);await form.locator('#note').fill('Synthetic evidence');
   if(await form.locator('#do_not_send_email_template').count())await form.locator('#do_not_send_email_template').check();
   const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoices/record_payment'),navigate(page,form.locator('button[type=submit]'))]);
   await native.assertRedirect(page,response,url);
   await page.locator('#invoice .panel_s').first().waitFor({state:'visible'});return response.request();
  }
  async function originalAction(selector){
   const action=page.locator(selector);if(!await action.isVisible())await action.locator('xpath=ancestor::div[contains(@class,"dropdown") or contains(@class,"btn-group")][1]').locator('.dropdown-toggle').first().click();
   return action;
  }
  for(const [issuer,letter]of[[a,'a'],[b,'b']]){
   await select(issuer);await page.goto(url+'/admin/settings?group=company');
   for(const [field,value]of Object.entries({invoice_company_address:'Synthetic '+letter+' street',invoice_company_city:'Synthetic city',company_state:'BA',invoice_company_country_code:'BR',invoice_company_postal_code:'00000-000'}))await page.locator('#company-setting-'+field).fill(value);
   await navigate(page,page.locator('#settings-form button[type=submit]'));
   assert.equal(await page.locator('#settings-form input[name="settings[invoice_company_address]"]').inputValue(),'Synthetic '+letter+' street','official company settings persist the active company address');
   await native.createForm(page,url,clients[issuer]);
   await native.line(page,{description:'Synthetic invoice '+letter,qty:'2.00',rate:'50.25',taxes:['Synthetic tax|5.0000']});
   const creation=await native.save(page,url);ids[issuer]=creation.id;assert.match(ids[issuer],/^[1-9]\d*$/);
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+ids[issuer]),'105.53');
   const repeat=await native.postRecorded(context,creation.request);assert.equal(repeat.status(),200,'invoice create retry remains idempotent');assert.equal(sql('SELECT COUNT(*) FROM tblinvoices WHERE issuer_id='+issuer),'1');
   await native.edit(page,url,ids[issuer]);const stale=await fields(page.locator('#invoice-form'));
   const malformed=await context.request.post(url+'/admin/invoices/invoice/'+ids[issuer],{form:{...await native.security(page),_native_invoice_id:ids[issuer]}});assert.equal(malformed.status(),422,'malformed update cannot become a cancellation');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'1');
   const pdf=await context.request.get(url+'/admin/invoices/pdf/'+ids[issuer]);assert.equal(pdf.status(),200);assert.equal((await pdf.body()).subarray(0,5).toString(),'%PDF-','actual own-company PDF downloaded');
   if(letter==='a'){
    await page.locator('tr.item input[name$="[rate]"]').fill('40.00');await native.save(page,url);
    assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+ids[issuer]),'84.00');
    const old=await context.request.post(url+'/admin/invoices/mark_as_cancelled/'+ids[issuer],{form:{...await native.security(page),revision:stale._native_invoice_revision}});assert.equal(old.status(),409,'stale cancel cannot bypass newer revision');
    const first=await payment(ids[issuer],'20.00','synthetic-browser-receipt-1');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'3');
    assert.equal((await native.postRecorded(context,first)).status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+ids[issuer]),'1');
    await payment(ids[issuer],'64.00','synthetic-browser-receipt-2');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'2');assert.equal(await page.locator('a[onclick*="record_payment("]').count(),0);
   }else{
    await native.preview(page,url,ids[issuer]);const action=await originalAction('a[href*="/invoices/mark_as_cancelled/"]');await navigate(page,action);assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+ids[issuer]),'5');
   }
  }
  assert.equal((await context.request.get(url+'/admin/invoices/get_invoice_data_ajax/'+ids[a])).status(),403,'B cannot read direct A invoice ID');
  assert.equal((await context.request.get(url+'/admin/invoices/pdf/'+ids[a])).status(),403,'B cannot download A PDF');
  // Private portal permission is native, explicit and independent for A/B contacts.
  const publicContexts=[];
  try{
   // The original contact editor applies configured default permissions. Establish
   // the no-financial-grant precondition through its real revocation action before
   // proving that identity authentication alone cannot open invoice access.
   for(const issuer of [a,b]){
    await select(issuer);await page.goto(url+'/admin/clients/client/'+clients[issuer]+'?group=contacts');
    const revoke=page.getByRole('button',{name:'Revogar acesso a faturas',exact:true});
    if(await revoke.count())await navigate(page,revoke);
    assert.equal(sql('SELECT COUNT(*) FROM tblcontact_permissions p JOIN tblcontacts c ON c.id=p.userid WHERE c.userid='+clients[issuer]+' AND p.permission_id=1'),'0','original revocation establishes the no-financial-access fixture');
   }
   await select(a);await page.goto(url+'/admin/clients/client/'+clients[a]+'?group=contacts');
   const publicA=await browser.newContext();publicContexts.push(publicA);await publicA.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const portalA=await workspacePage(publicA);portalA.on('pageerror',e=>errors.push(e.message));
   async function portalLogin(p,issuer,email,password){await p.goto(url+'/company_portal/index/'+issuer);await p.locator('#login-email').fill(email);await p.locator('#login-password').fill(password);await navigate(p,p.locator('#portal-login button[type=submit]'));}
   async function portalPdf(ctx,p,issuer,invoice){await p.reload();const csrf=await fields(p.locator('form').filter({has:p.locator('input[name=action][value=logout]')}));return ctx.request.post(url+'/company_portal/index/'+issuer,{form:{...csrf,action:'invoice_pdf',invoice_id:invoice}});}
   await portalLogin(portalA,a,'changed-portal@example.invalid','synthetic-password-a');assert.equal(await portalA.locator('#portal-identity').count(),1);assert.equal(await portalA.locator('#portal-invoices').count(),0,'identity login does not implicitly grant financial access');
   assert.equal((await portalPdf(publicA,portalA,a,ids[a])).status(),404,'direct portal PDF denied before grant');
   await navigate(page,page.getByRole('button',{name:'Permitir acesso a faturas',exact:true}));
   await portalA.reload();assert.equal(await portalA.locator('#portal-invoices article').count(),1,'native contact permission opens only own invoice list');
   const portalDownload=await portalPdf(publicA,portalA,a,ids[a]);assert.equal(portalDownload.status(),200);assert.equal((await portalDownload.body()).subarray(0,5).toString(),'%PDF-','authorized portal PDF uses actual TCPDF');
   assert.equal((await portalPdf(publicA,portalA,a,ids[b])).status(),404,'A cannot download direct B invoice ID');
   // Earlier recovery test deactivated B; reactivate only this disposable synthetic contact.
   sql('UPDATE tblcontacts SET active=1 WHERE userid='+clients[b]);const publicB=await browser.newContext();publicContexts.push(publicB);await publicB.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const portalB=await workspacePage(publicB);portalB.on('pageerror',e=>errors.push(e.message));
   await portalLogin(portalB,b,'same-portal@example.invalid','synthetic-password-b');assert.equal(await portalB.locator('#portal-invoices').count(),0,'A grant is not inherited by same-email B contact');
   await select(b);await page.goto(url+'/admin/clients/client/'+clients[b]+'?group=contacts');await navigate(page,page.getByRole('button',{name:'Permitir acesso a faturas',exact:true}));await portalB.reload();assert.equal(await portalB.locator('#portal-invoices article').count(),1);assert.equal((await portalPdf(publicB,portalB,b,ids[a])).status(),404,'B grant cannot authorize A PDF');
   await select(a);await page.goto(url+'/admin/clients/client/'+clients[a]+'?group=contacts');await navigate(page,page.getByRole('button',{name:'Revogar acesso a faturas',exact:true}));await portalA.reload();assert.equal(await portalA.locator('#portal-invoices').count(),0,'native revocation takes effect in existing session');assert.equal((await portalPdf(publicA,portalA,a,ids[a])).status(),404,'revoked session cannot download PDF');
   assert.equal(sql("SELECT COUNT(*) FROM tblissuers_audit WHERE event='company_portal_invoice_pdf_downloaded' AND issuer_id="+a+" AND subject_id="+ids[a]),'1','only authorized PDF delivery is audited as delivered');
  }finally{for(const ctx of publicContexts)await ctx.close();}
  // Expanded monetary editor: two native taxes, discount timing and signed adjustment in both owners.
  const raster=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAQAABQAAWX1h1kAAAAASUVORK5CYII=','base64');
  async function settingsAssetMutation(form){
   const all=await fields(form),revision=await page.locator('[data-native-assets-revision]').getAttribute('data-native-assets-revision'),body={revision};
   for(const [name,value]of Object.entries(all))if(name==='_company_context'||name==='_native_company_csrf'||/csrf/i.test(name))body[name]=value;
   assert.match(body.revision||'',/^[a-f0-9]{64}$/);return body;
  }
  for(const [issuer,letter]of[[a,'a'],[b,'b']]){
   await select(issuer);await page.goto(url+'/admin/settings?group=general');
   for(const asset of [{kind:'logo',input:'input[name=company_logo]',remove:'remove_company_logo'},{kind:'signature',input:'input[name=signature_image]',remove:'remove_signature_image'},{kind:'favicon',input:'input[name=favicon]',remove:'remove_fv'}]){
    const form=page.locator('#settings-form'),oldFields=await settingsAssetMutation(form);
    await page.locator(asset.input).setInputFiles({name:'synthetic-'+asset.kind+'.png',mimeType:'image/png',buffer:raster});
    await navigate(page,form.locator('button[type=submit]'));
    const downloadLink=page.locator('a[href*="settings/company_asset/'+asset.kind+'?revision="]');
    assert.equal(await downloadLink.count(),1,'official Settings renders own '+asset.kind+' image and private download');
    const downloadHref=await downloadLink.getAttribute('href'),downloaded=await context.request.get(downloadHref);assert.equal(downloaded.status(),200);assert.equal((await downloaded.body()).subarray(0,4).toString('hex'),'89504e47');
    const stale=await context.request.post(url+'/admin/settings/'+asset.remove,{form:oldFields});assert.equal(stale.status(),409,'old official Settings editor cannot remove a newer '+asset.kind+' revision');
    const removeLink=page.locator('a[href*="settings/'+asset.remove+'"][data-native-revision]');assert.equal(await removeLink.count(),1,'official Settings exposes POST-backed removal for '+asset.kind);page.once('dialog',dialog=>dialog.accept());const removed=page.waitForResponse(response=>response.request().method()==='POST'&&new URL(response.url()).pathname.includes('/admin/settings/'+asset.remove));await Promise.all([removed,page.waitForNavigation({waitUntil:'domcontentloaded'}),removeLink.click()]);assert.equal((await removed).status(),200);assert.equal(await page.locator(asset.input).count(),1,'successful official removal restores '+asset.kind+' upload');assert.equal((await context.request.get(downloadHref)).status(),409,'removed '+asset.kind+' invalidates the previous private revision URL');
   }
   const assetFields=await settingsAssetMutation(page.locator('#settings-form'));await select(issuer===a?b:a);
   assert.equal((await context.request.post(url+'/admin/settings/remove_company_logo',{form:assetFields})).status(),409,'official image mutation from obsolete company tab denied before effects');
   await select(issuer);assert.equal((await page.goto(url+'/admin/settings?group=invoices')).status(),200);
   const invoiceSettings=page.locator('#settings-form');
   await invoiceSettings.locator('input[name="settings[invoice_prefix]"]').fill('SYN-'+letter+'-');
   await invoiceSettings.locator('textarea[name="settings[predefined_terms_invoice]"]').fill('Synthetic terms '+letter);
   await navigate(page,invoiceSettings.locator('button[type=submit]'));
   assert.equal(await invoiceSettings.locator('input[name="settings[invoice_prefix]"]').inputValue(),'SYN-'+letter+'-','official invoice prefix is persisted for the active company');
   assert.equal(await invoiceSettings.locator('textarea[name="settings[predefined_terms_invoice]"]').inputValue(),'Synthetic terms '+letter,'official invoice terms are persisted for the active company');
   await native.createForm(page,url,clients[issuer]);
   await native.line(page,{description:'Expanded '+letter,rate:'100.00',taxes:['ISS|5.0000','Levy|2.5000']});
   await page.locator('input[name=discount_percent]').fill('10.00');await native.picker(page,'select[name=discount_type]','before_tax');await page.locator('input[name=adjustment]').fill('-0.01');
   const expanded=await native.save(page,url),expandedId=expanded.id;assert.match(expandedId,/^[1-9]\d*$/);
   assert.equal(sql('SELECT CONCAT(total,"/",total_tax,"/",discount_total,"/",adjustment) FROM tblinvoices WHERE id='+expandedId),'96.74/6.75/10.00/-0.01');
   await native.edit(page,url,expandedId);
   assert.deepEqual(await page.locator('tr.item select.tax option:checked').evaluateAll(xs=>xs.map(x=>x.value).sort()),['ISS|5.0000','Levy|2.5000']);assert.equal(await page.locator('input[name=discount_percent]').inputValue(),'10.00');
   const expandedPdf=await context.request.get(url+'/admin/invoices/pdf/'+expandedId);assert.equal(expandedPdf.status(),200);assert.equal((await expandedPdf.body()).subarray(0,5).toString(),'%PDF-');
   await page.locator('#dropdown_menu_tax_total_type').click();await page.locator('.discount-type-fixed').click();await page.locator('input[name=discount_total]').fill('10.00');await native.picker(page,'select[name=discount_type]','after_tax');await page.locator('input[name=adjustment]').fill('0.01');await native.save(page,url);
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+expandedId),'97.51');
   // Original attachment modal and private file projection; publication is a separate explicit action.
   async function upload(file){
    await (await originalAction('a[data-target="#sales_attach_file"]')).click();await page.locator('#sales_attach_file').waitFor({state:'visible'});
    const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/misc/upload_sales_file'),page.locator('input.dz-hidden-input').setInputFiles(file)]);
    assert.equal(response.status(),200,'original multipart endpoint persists private attachment');const result=await response.json();assert.equal(result.success,true);await page.locator('#sales_attach_file').waitFor({state:'hidden'});await page.locator('[data-attachment-id="'+result.id+'"]').waitFor({state:'visible'});return{...result,request:response.request()};
   }
   async function visibility(file){
    const toggle=page.locator('[data-attachment-id="'+file.id+'"] a[data-native-file-source="invoice"]');
    const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/misc/toggle_file_visibility/'+file.id),toggle.click()]);assert.equal(response.status(),200);return response.request();
   }
   const privateUpload=await upload({name:'private-'+letter+'.png',mimeType:'image/png',buffer:raster});
   const privateFile=sql('SELECT file_key FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId+' AND portal_visible=0');assert.match(privateFile,/^[a-f0-9]{64}$/);
   const privateDownload=url+'/download/file/sales_attachment/'+privateUpload.key;
   const binaryImage=await context.request.get(privateDownload);assert.equal(binaryImage.status(),200);assert.equal((await binaryImage.body()).subarray(0,4).toString('hex'),'89504e47');
   const inline=await context.request.get(privateDownload+'?inline=1');assert.equal(inline.status(),200);assert.equal(inline.headers()['x-content-type-options'],'nosniff');assert.match(inline.headers()['content-disposition'],/^inline;/);
   const publicUpload=await upload({name:'visible-'+letter+'.pdf',mimeType:'application/pdf',buffer:await expandedPdf.body()});await visibility(publicUpload);
   const publicFile=sql('SELECT file_key FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId+' AND portal_visible=1');assert.match(publicFile,/^[a-f0-9]{64}$/);
   const fileRetry=await native.postRecorded(context,publicUpload.request);assert.equal(fileRetry.status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_attachments WHERE invoice_id='+expandedId),'2','multipart retry stores one blob and receipt');
   // Original recipient modal queues only an explicit, currently permitted private contact.
   const contact=sql('SELECT id FROM tblcontacts WHERE userid='+clients[issuer]+' AND active=1 ORDER BY id LIMIT 1');assert.match(contact,/^[1-9]\d*$/);
   await page.goto(url+'/admin/clients/client/'+clients[issuer]+'?group=contacts');
   const grant=page.locator('form[action$="/clients/contact_invoice_access/'+clients[issuer]+'/'+contact+'"]');
   assert.equal(await grant.count(),1,'exact native contact permission form is available');
   if(await grant.locator('input[name=allowed]').inputValue()==='1')await navigate(page,grant.locator('button[type=submit]'));
   assert.equal(sql('SELECT COUNT(*) FROM tblcontact_permissions WHERE userid='+contact+' AND permission_id=1'),'1','native permission persisted before notification');
   await native.preview(page,url,expandedId);await page.locator('.invoice-send-to-client').click();
   const notificationForm=page.locator('#invoice_send_to_client_modal form');await notificationForm.waitFor({state:'visible'});await native.picker(page,'#invoice_send_to_client_modal select[name="sent_to[]"]',contact);
   const [notificationResponse]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoices/send_to_email/'+expandedId),navigate(page,notificationForm.locator('button[type=submit]'))]);await native.assertRedirect(page,notificationResponse,url);const notificationRequest=notificationResponse.request();
   assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_deliveries WHERE issuer_id='+issuer+' AND invoice_id='+expandedId),'1');
   const queued=sql('SELECT q.status FROM tblmail_queue q JOIN tblcompany_invoice_deliveries d ON d.queue_id=q.id WHERE d.invoice_id='+expandedId);assert.equal(queued,'pending','GUI confirms queue persistence, never SMTP success');
   await page.locator('[aria-label="Estado das notificações"]').waitFor({state:'attached'});assert.match(await page.locator('[aria-label="Estado das notificações"]').innerText(),/Na fila/);
   assert.equal((await native.postRecorded(context,notificationRequest)).status(),200);assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_deliveries WHERE invoice_id='+expandedId),'1','same notification request does not queue twice');
   const otherContact=sql('SELECT id FROM tblcontacts WHERE userid='+clients[issuer===a?b:a]+' AND active=1 ORDER BY id LIMIT 1');
   const crossedBody=new URLSearchParams(notificationRequest.postData());crossedBody.set('request_key','e'.repeat(64));crossedBody.set('sent_to[]',otherContact);
   const crossedNotification=await context.request.post(notificationRequest.url(),{data:crossedBody.toString(),headers:{'Content-Type':'application/x-www-form-urlencoded'}});assert.equal(crossedNotification.status(),403,'foreign contact ID denied despite equal or familiar email');
   // Real private portal lists only the published blob, and hiding revokes its existing download form.
   const filePortalContext=await browser.newContext();await filePortalContext.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
   try{
    const filePortal=await workspacePage(filePortalContext);filePortal.on('pageerror',e=>errors.push(e.message));
    await filePortal.goto(url+'/company_portal/index/'+issuer);await filePortal.locator('#login-email').fill(letter==='a'?'changed-portal@example.invalid':'same-portal@example.invalid');await filePortal.locator('#login-password').fill('synthetic-password-'+letter);await navigate(filePortal,filePortal.locator('#portal-login button[type=submit]'));
    const publishedForm=filePortal.locator('form').filter({has:filePortal.locator('input[name=file_key][value="'+publicFile+'"]')});assert.equal(await publishedForm.count(),1);assert.equal(await filePortal.locator('input[name=file_key][value="'+privateFile+'"]').count(),0,'default-private attachment metadata never reaches contact HTML');
    const portalFileFields=await fields(publishedForm);const publicDownload=await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:portalFileFields});assert.equal(publicDownload.status(),200);assert.equal((await publicDownload.body()).subarray(0,5).toString(),'%PDF-');assert.match(publicDownload.headers()['content-disposition'],/^attachment;/);
    assert.equal((await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:{...portalFileFields,file_key:privateFile}})).status(),404,'portal direct private key denied before bytes');
    const hideRequest=await visibility(publicUpload);
    assert.equal((await native.postRecorded(context,hideRequest)).status(),200,'hide retry is durable without duplicate mutation');
    assert.equal((await filePortalContext.request.post(url+'/company_portal/index/'+issuer,{form:portalFileFields})).status(),404,'hidden attachment denies saved authorized portal form');await filePortal.reload();assert.equal(await filePortal.locator('input[name=file_key][value="'+publicFile+'"]').count(),0);
   }finally{await filePortalContext.close();}
   // Saved original mutations retain their captured company context across tab switches.
   const prior=await native.security(page),revision=await page.locator('a[data-native-invoice-lifecycle-revision]').first().getAttribute('data-native-invoice-lifecycle-revision');
   await select(issuer===a?b:a);
   // Canonical private-download route deliberately hides existence with 404.
   assert.equal((await context.request.get(privateDownload)).status(),404,'foreign company cannot download attachment by exact known private key');
   assert.equal((await native.postRecorded(context,publicUpload.request)).status(),409,'obsolete company multipart denied before write');
   assert.equal((await native.postRecorded(context,notificationRequest)).status(),409,'obsolete company notification page denied before queue effects');
   assert.equal((await context.request.post(url+'/admin/invoices/mark_as_cancelled/'+expandedId,{form:{...prior,revision}})).status(),409);
   assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+expandedId),'1');
  }
  const visualIssuer = await page.locator('.company-selector').getAttribute('data-company-current');
  assert.ok(ids[visualIssuer], 'invoice evidence belongs to the currently selected company');
  await captureRealScreens(page, [['company-settings-isolated',url + '/admin/settings?group=company'], ['invoices-grid',url + '/admin/invoices'], ['invoice-detail',url + '/admin/invoices/list_invoices/' + ids[visualIssuer]]]);
  assert.deepEqual(errors,[]);
  console.log('Named-company invoice CRUD/numbering/PDF/manual partial+final receipts/private portal grant+PDF+revocation: real HTTP/MySQL/Chromium passed, including owner-bound notifications and private attachment upload/preview/download/portal revocation; no SMTP or bank/provider operations.');
 }catch(e){console.error("Browser page errors:",JSON.stringify(errors));await page.screenshot({path:output+"/invoice-smoke-failure.png",fullPage:true});throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
