'use strict';
// Drives the original controls. No DOM/style mutation, substituted responses or fake form fields.
const assert = require('node:assert/strict');

async function picker(page, selector, values) {
  values = Array.isArray(values) ? values.map(String) : [String(values)];
  const select = page.locator(selector);
  const options = await select.locator('option').evaluateAll(xs => xs.map((x, index) => ({index, value:x.value, selected:x.selected, text:x.textContent.trim()})));
  for (const value of values) assert(options.some(x=>x.value===value), 'original selector offers '+value);
  const wrapper = select.locator('xpath=ancestor::*[contains(concat(" ",normalize-space(@class)," ")," bootstrap-select ")][1]');
  if (!await wrapper.count()) { await select.selectOption(values); return; }
  const multiple = await select.getAttribute('multiple') !== null;
  for (const option of options) {
    if (multiple ? option.selected === values.includes(option.value) : !values.includes(option.value) || option.selected) continue;
    const button = wrapper.locator('button.dropdown-toggle');
    if (await button.getAttribute('aria-expanded') !== 'true') await button.click();
    const entries = wrapper.locator('.dropdown-menu.inner a[role="option"]');
    const id = await entries.evaluateAll((xs, index) => xs.find(x=>x.id.endsWith('-'+index))?.id || null, option.index);
    assert(id, 'rendered original picker retains option index '+option.index);
    await page.locator('[id="'+id+'"]').click();
  }
  const button = wrapper.locator('button.dropdown-toggle');
  if (await button.getAttribute('aria-expanded') === 'true') await button.click();
  assert.deepEqual((await select.locator('option:checked').evaluateAll(xs=>xs.map(x=>x.value))).sort(), [...values].sort());
}
async function security(page) {
  return page.locator('input[type=hidden]').evaluateAll(xs=>{
    const config=window.NativeCompanyHeaderConfig;
    if(!config)throw new Error('Original company security configuration is required');
    // The company switcher owns a separate CSRF token. It is not part of an
    // invoice command; retain only the operation and framework security fields.
    const names=new Set(['_company_context','_native_company_csrf',config.frameworkCsrf?.name].filter(Boolean));
    const fields=Object.fromEntries(xs.filter(x=>names.has(x.name)).map(x=>[x.name,x.value]));
    for(const name of names)if(!fields[name])throw new Error('Missing original security field '+name);
    return fields;
  });
}
async function postRecorded(context, request) {
  // XHR commands carry their company and native CSRF tokens in headers. Replay
  // those captured values too; never replace them with the current page tokens.
  const headers=Object.fromEntries(Object.entries(request.headers()).filter(([name])=>['content-type','x-company-context','x-native-company-csrf','x-requested-with'].includes(name.toLowerCase())));
  return context.request.post(request.url(), {data:request.postDataBuffer(),headers});
}
async function preview(page, url, id) {
  const endpoint='/admin/invoices/get_invoice_data_ajax/'+id;
  const [response] = await Promise.all([page.waitForResponse(r=>new URL(r.url()).pathname===endpoint),page.goto(url+'/admin/invoices/list_invoices/'+id)]);
  assert.equal(response.status(),200,'original invoice preview is authorized');
  await page.locator('#invoice .panel_s').first().waitFor({state:'visible'});
}
async function createForm(page,url,client) {
  const response=await page.goto(url+'/admin/invoices/invoice?customer_id='+client);
  assert.equal(response.status(),200,'original invoice create form opens');
  // The original Bootstrap form contains floated columns; its controls have
  // visible boxes even when the form element itself has zero computed height.
  await page.locator('#invoice-form .accounting-template').waitFor({state:'visible'});
  await page.waitForFunction(()=>typeof calculate_total==='function'&&typeof validate_invoice_form==='function');
  const expectedClient=String(client);
  await page.waitForFunction(expected=>{
    const select=document.querySelector('#clientid');
    return !!(select&&Array.from(select.options).some(option=>option.value===expected));
  },expectedClient);
  await picker(page,'#clientid',expectedClient);
  await page.waitForFunction(expected=>document.querySelector('#clientid')?.value===expected,expectedClient);
  const currency=await page.locator('#currency option').evaluateAll(xs=>xs.find(x=>x.textContent.trim()==='BRL')?.value);
  assert(currency,'original form offers stored BRL currency');await picker(page,'#currency',currency);
}
async function line(page,{description,qty='1.00',rate,taxes=[]}) {
  await page.locator('tr.main textarea[name=description]').fill(description);
  await page.locator('tr.main input[name=quantity]').fill(qty);
  await page.locator('tr.main input[name=rate]').fill(rate);
  if(taxes.length)await picker(page,'tr.main select.tax',taxes);
  await page.locator('tr.main button[onclick*="add_item_to_table"]').click();
  await page.locator('tr.item').last().waitFor({state:'visible'});
}
async function assertRedirect(page,response,url) {
  // Chromium exposes the redirected GET as the POST response when the existing
  // PWA worker handles that navigation. Prove the underlying 303 independently
  // by replaying the exact idempotent POST with redirects disabled.
  const request=response.request();
  const direct=await page.context().request.post(request.url(),{data:request.postDataBuffer(),headers:{'Content-Type':request.headers()['content-type']},maxRedirects:0});
  assert.equal(direct.status(),303,'original POST and durable replay use Post/Redirect/Get');
  assert.match(new URL(direct.headers().location,url).pathname,/^\/admin\/invoices\/list_invoices\/[1-9][0-9]*$/,'redirect targets the persisted original invoice');
  if(response.status()!==303){assert.equal(response.fromServiceWorker(),true,'only the existing PWA worker may expose the followed document');assert.equal(response.status(),200,'worker exposes a successful final document');}
}
async function assertInvoiceFormReady(page) {
  // The shipped form validates the invoice number remotely before its submit
  // handler is allowed to issue the original POST. Synchronize that validator
  // explicitly so the browser proof never races its pending request.
  await page.waitForFunction(() => {
    const form = window.jQuery && window.jQuery('#invoice-form');
    return !!(form && form.length && form.data('validator'));
  });
  await page.evaluate(() => {
    const form = window.jQuery('#invoice-form');
    if (!form.valid()) return;
  });
  await page.waitForFunction(() => {
    const form = window.jQuery && window.jQuery('#invoice-form');
    const validator = form && form.data('validator');
    return !!(validator && validator.pendingRequest === 0);
  });
  const result = await page.evaluate(() => {
    const form = window.jQuery('#invoice-form');
    const validator = form.data('validator');
    const valid = form.valid();
    return {
      valid,
      pending: validator.pendingRequest,
      errors: validator.errorList.map(error => ({
        field: error.element && error.element.name,
        message: error.message,
      })),
    };
  });
  assert.equal(result.pending,0,'original invoice validation settled');
  assert.equal(result.valid,true,'original invoice form is valid: '+JSON.stringify(result.errors));
}
async function save(page,url) {
  const route=new URL(await page.locator('#invoice-form').getAttribute('action'),url).pathname;
  await assertInvoiceFormReady(page);
  const matches=r=>r.method()==='POST'&&new URL(r.url()).pathname===route;
  const responseMatches=r=>matches(r.request());
  const [request,response]=await Promise.all([page.waitForRequest(matches),page.waitForResponse(responseMatches),page.locator('#invoice-form button.transaction-submit.btn-primary').click()]);
  assert.equal(request.method(),'POST','the original invoice POST is observed');
  // Only the native command carries a durable request key. The principal
  // keeps the original legacy writer, where replaying the same POST would
  // legitimately collide with its already issued invoice number.
  const nativeRequestKey=await page.locator('#invoice-form input[name="_native_invoice_request_key"]').count();
  if(nativeRequestKey)await assertRedirect(page,response,url);
  else if(response.status()!==303){assert.equal(response.fromServiceWorker(),true,'only the existing PWA worker may expose the followed legacy document');assert.equal(response.status(),200,'worker exposes a successful final legacy document');}
  await page.waitForURL(/\/admin\/invoices\/list_invoices\/[1-9][0-9]*(?:#.*)?$/);
  const id=new URL(page.url()).pathname.split('/').pop();
  await page.locator('#invoice .panel_s').first().waitFor({state:'visible'});
  return {id,request:response.request()};
}
async function edit(page,url,id) {
  const response=await page.goto(url+'/admin/invoices/invoice/'+id);assert.equal(response.status(),200,'same original invoice form opens persisted values');
  await page.locator('#invoice-form tr.item').first().waitFor({state:'visible'});
}
function formatDate(iso,format){
  assert.match(iso,/^\d{4}-\d{2}-\d{2}$/);assert(['d-m-Y','d/m/Y','Y-m-d','d.m.Y','m-d-Y','m.d.Y','m/d/Y'].includes(format),'original date format is supported');
  const [Y,m,d]=iso.split('-');return format.replace(/[Ymd]/g,key=>({Y,m,d}[key]));
}
async function date(page,selector,iso){const format=await page.evaluate(()=>window.app.options.date_format);await page.locator(selector).fill(formatDate(iso,format));}
async function payment(page,url,id,amount,reference,note='Synthetic evidence'){
  await preview(page,url,id);
  const [opened]=await Promise.all([page.waitForResponse(r=>new URL(r.url()).pathname==='/admin/invoices/record_invoice_payment_ajax/'+id),page.locator('a[onclick*="record_payment("]').click()]);
  assert.equal(opened.status(),200);const form=page.locator('#record_payment_form');await form.locator('.panel_s').waitFor({state:'visible'});
  await form.locator('#amount').fill(amount);await form.locator('#transactionid').fill(reference);await form.locator('#note').fill(note);
  if(await form.locator('#do_not_send_email_template').count())await form.locator('#do_not_send_email_template').check();
  const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoices/record_payment'),page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[type=submit]').click()]);
  await assertRedirect(page,response,url);await page.locator('#invoice .panel_s').first().waitFor({state:'visible'});return response.request();
}
module.exports={picker,security,postRecorded,preview,createForm,line,save,edit,assertRedirect,formatDate,date,payment};
