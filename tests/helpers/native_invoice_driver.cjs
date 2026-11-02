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
async function legacyInvoiceTarget(page,response,url,navigationResponse=null,navigationRequest=null,clientId=null) {
  const listPath=/\/admin\/invoices\/list_invoices\/[1-9][0-9]*$/;
  const invoiceTargetFromRows=(rows,expectedClient=null)=>{
    const candidates=[];
    const add=(value)=>{
      const html=String(value??'');
      const owned=expectedClient===null||html.includes('/clients/client/'+String(expectedClient));
      for(const match of html.matchAll(/\/admin\/invoices\/list_invoices\/([1-9][0-9]*)/g))candidates.push({href:new URL(match[0],url).href,id:Number(match[1]),owned});
    };
    for(const row of rows){
      if(Array.isArray(row))row.forEach(add);else if(row&&typeof row==='object')Object.values(row).forEach(add);else add(row);
    }
    const owned=candidates.filter(candidate=>candidate.owned);
    return [...(owned.length?owned:candidates)].sort((left,right)=>left.id-right.id).pop()?.href||null;
  };
  const invoiceTargetFromTable=async(tableResponse,expectedClient=null)=>{
    try {
      const payload=await tableResponse.json();
      const rows=Array.isArray(payload.aaData)?payload.aaData:(Array.isArray(payload.data)?payload.data:[]);
      return invoiceTargetFromRows(rows,expectedClient);
    } catch (_) { return null; }
  };
  const invoiceTargetFromDom=async(expectedClient=null)=>{
    try {
      const rows=await page.locator('table.table-invoices-single-client tbody tr, table.table-invoices tbody tr').evaluateAll(xs=>xs.map(row=>row.innerHTML));
      return invoiceTargetFromRows(rows,expectedClient);
    } catch (_) { return null; }
  };
  const invoiceTargetFromDirectTable=async(tableUrl,expectedClient=null)=>{
    try {
      const hidden=await page.locator('input[type="hidden"][name]').evaluateAll(xs=>Object.fromEntries(xs.map(x=>[x.name,x.value])));
      const response=await page.context().request.post(tableUrl,{form:{...hidden,draw:'1',start:'0',length:'100','search[value]':'','search[regex]':'false','order[0][column]':'3','order[0][dir]':'desc','order[1][column]':'0','order[1][dir]':'desc'},headers:{'X-Requested-With':'XMLHttpRequest','Referer':tableUrl}});
      if(!response.ok())return null;
      return invoiceTargetFromTable(response,expectedClient);
    } catch (_) { return null; }
  };
  const responses=navigationResponse?[response,navigationResponse]:[response];
  const candidates=[];
  const addRequest=request=>{
    if(!request)return;
    candidates.push(request.url());
    try {
      for(let current=request.redirectedFrom();current&&candidates.length<16;current=current.redirectedFrom())candidates.push(current.url());
    } catch (_) {}
  };
  for(const candidateResponse of responses){
    candidates.push(candidateResponse.url(),candidateResponse.headers()['location']);
    try {
      for(let request=candidateResponse.request();request&&candidates.length<16;request=request.redirectedFrom())candidates.push(request.url());
    } catch (_) {}
    try {
      const body=await candidateResponse.text();
      const match=body.match(/(?:https?:[^"'\s]+)?(\/admin\/invoices\/list_invoices\/[1-9][0-9]*)/);
      if(match)candidates.push(match[1]);
    } catch (_) {}
  }
  addRequest(navigationRequest);
  try {
    const entries=await page.evaluate(()=>[
      ...performance.getEntriesByType('navigation'),
      ...performance.getEntriesByType('resource'),
    ].map(entry=>entry.name));
    candidates.push(...entries);
  } catch (_) {}
  for(const candidate of candidates){
    if(!candidate)continue;
    try {
      const target=new URL(candidate,url);
      if(listPath.test(target.pathname))return target.href;
    } catch (_) {}
  }
  // A service-worker navigation can follow the legacy 302 internally while
  // Chromium exposes only the original form URL and response. The invoice is
  // already committed, so recover its canonical id from the original table
  // through the same authenticated browser session; never replay the POST.
  if(clientId){
    // The original PWA worker may serve the list page without allowing its
    // DataTables bootstrap to reach the server. Read the same legacy table
    // endpoint through the context request API, which shares the browser's
    // authenticated cookies but bypasses service-worker navigation caching.
    const profileTarget=await invoiceTargetFromDirectTable(new URL('/admin/invoices/table/'+encodeURIComponent(String(clientId)),url).href,clientId);
    if(profileTarget)return profileTarget;
    const globalTarget=await invoiceTargetFromDirectTable(new URL('/admin/invoices/table',url).href,clientId);
    if(globalTarget)return globalTarget;
    try {
      const listUrl=new URL('/admin/invoices/list_invoices',url).href;
      const tableResponsePromise=page.waitForResponse(candidateResponse=>{
        try{return candidateResponse.request().method()==='POST'&&/^\/admin\/invoices\/table(?:\/[1-9][0-9]*)?$/.test(new URL(candidateResponse.url()).pathname);}catch(_){return false;}
      },{timeout:90000}).catch(()=>null);
      await page.goto(listUrl,{waitUntil:'domcontentloaded'});
      const tableResponse=await tableResponsePromise;
      if(tableResponse){
        const target=await invoiceTargetFromTable(tableResponse,clientId);
        if(target)return target;
      }
      const table=page.locator('table.table-invoices').first();
      await table.waitFor({state:'visible',timeout:90000});
      await page.waitForFunction(expected=>Array.from(document.querySelectorAll('table.table-invoices tbody tr a[href]')).some(anchor=>{
        try{return new URL(anchor.href,location.href).pathname.endsWith('/clients/client/'+expected);}catch(_){return false;}
      }),String(clientId),{timeout:90000}).catch(()=>{});
      const target=await invoiceTargetFromDom(clientId);
      if(target)return target;
    } catch (_) {}
    try {
      // The principal table can be slow or omit the client column while the
      // legacy list is rebuilding. The official customer profile owns a
      // client-scoped DataTable endpoint; use its authenticated GET/POST
      // sequence as a second read-only recovery path.
      const profileUrl=new URL('/admin/clients/client/'+encodeURIComponent(String(clientId))+'?group=statement',url).href;
      const profileTableResponse=page.waitForResponse(candidateResponse=>{
        try{return candidateResponse.request().method()==='POST'&&new URL(candidateResponse.url()).pathname==='/admin/invoices/table/'+String(clientId);}catch(_){return false;}
      },{timeout:90000}).catch(()=>null);
      await page.goto(profileUrl,{waitUntil:'domcontentloaded'});
      const tableResponse=await profileTableResponse;
      if(tableResponse){
        const target=await invoiceTargetFromTable(tableResponse,clientId);
        if(target)return target;
      }
      await page.waitForFunction(expected=>Array.from(document.querySelectorAll('table.table-invoices-single-client tbody tr a[href], table.table-invoices tbody tr a[href]')).some(anchor=>{
        try{return new URL(anchor.href,location.href).pathname.includes('/admin/invoices/list_invoices/');}catch(_){return false;}
      }),String(clientId),{timeout:90000}).catch(()=>{});
      const target=await invoiceTargetFromDom(clientId);
      if(target)return target;
    } catch (_) {}
  }
  return null;
}
async function save(page,url) {
  const route=new URL(await page.locator('#invoice-form').getAttribute('action'),url).pathname;
  await assertInvoiceFormReady(page);
  const matches=r=>r.method()==='POST'&&new URL(r.url()).pathname===route;
  const responseMatches=r=>matches(r.request());
  const listPath=/\/admin\/invoices\/list_invoices\/[1-9][0-9]*(?:#.*)?$/;
  const clientId=await page.locator('#clientid').inputValue().catch(()=>null);
  const observedNavigationRequests=[];
  const observeNavigationRequest=request=>{
    try {
      if(request.method()==='GET'&&listPath.test(new URL(request.url()).pathname))observedNavigationRequests.push(request);
    } catch (_) {}
  };
  page.context().on('request',observeNavigationRequest);
  const navigationResponse=page.waitForResponse(r=>{
    try{return r.request().method()==='GET'&&listPath.test(new URL(r.url()).pathname+'#'+new URL(r.url()).hash.replace(/^#/,''));}catch(_){return false;}
  },{timeout:10000}).catch(()=>null);
  const navigationRequest=page.waitForRequest(r=>{
    try{return r.method()==='GET'&&listPath.test(new URL(r.url()).pathname);}catch(_){return false;}
  },{timeout:10000}).catch(()=>null);
  let observed;
  try {
    observed=await Promise.all([page.waitForRequest(matches),page.waitForResponse(responseMatches),navigationRequest,navigationResponse,page.locator('#invoice-form button.transaction-submit.btn-primary').click()]);
  } finally {
    page.context().off('request',observeNavigationRequest);
  }
  const [request,response,redirectedRequest,redirectedResponse]=observed;
  assert.equal(request.method(),'POST','the original invoice POST is observed');
  // Only the native command carries a durable request key. The principal
  // keeps the original legacy writer, where replaying the same POST would
  // legitimately collide with its already issued invoice number.
  const nativeRequestKey=await page.locator('#invoice-form input[name="_native_invoice_request_key"]').count();
  if(nativeRequestKey)await assertRedirect(page,response,url);
  else if(response.status()!==303){assert.equal(response.fromServiceWorker(),true,'only the existing PWA worker may expose the followed legacy document');assert.equal(response.status(),200,'worker exposes a successful final legacy document');}
  if(!listPath.test(page.url())){
    // The legacy POST can be fulfilled by Chromium's navigation loader while
    // the existing PWA worker leaves the address bar on the form. Recover the
    // durable PRG target from the redirect GET/POST response pair and perform
    // only its GET; never replay the legacy POST (the invoice number is already
    // consumed).
    const target=await legacyInvoiceTarget(page,response,url,redirectedResponse,redirectedRequest||observedNavigationRequests[0]||null,clientId);
    assert(target,'legacy invoice POST exposes its persisted list destination');
    await page.goto(target);
  }
  await page.waitForURL(listPath);
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
