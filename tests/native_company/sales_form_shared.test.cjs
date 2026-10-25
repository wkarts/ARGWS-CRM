'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const source = read('assets/js/native-admin-sales-form.js');
const inventory = JSON.parse(read('tests/native_company/sales_form_source.json'));
const hash = s => crypto.createHash('sha256').update(s).digest('hex');
test('original native function bodies are shared once, not parallel implementations', () => {
  assert.equal(inventory.length,22);
  for (const entry of inventory.filter(x=>x.name !== 'ready_handlers')) {
    const re = new RegExp('^function '+entry.name+'\\([^\\n]*\\)[^{]*\\{.*?^}', 'ms');
    const match = source.match(re);
    assert.ok(match,entry.name);
    assert.equal(hash(match[0]),entry.sha256,entry.name);
    assert.equal(read(entry.source).match(re),null,entry.name+' duplicated in legacy source');
  }
});
test('original transaction and sales handlers remain single source', () => {
  const body = source.split('$(function () {\n')[1].split(/\n\}\);\n$/)[0];
  assert.equal(hash(body),inventory.at(-1).sha256);
  for(const marker of ['"._transaction_form"','".transaction-submit"','// Include shipping show/hide details']) {
    assert.ok(source.includes(marker));
    assert.ok(!read('assets/js/main.js').includes(marker));
  }
  assert.equal((source.match(/var lastAddedItemKey = null;/g)||[]).length,1);
  assert.ok(!read('assets/js/main.js').includes('lastAddedItemKey = null'));
});
test('loading source declares original APIs and registers exactly one ready callback', () => {
  const ready=[];
  const context={$:fn=>{assert.equal(typeof fn,'function');ready.push(fn);},app:{options:{decimal_places:2}},accounting:{toFixed:(n,d)=>Number(n).toFixed(d)}};
  vm.createContext(context);vm.runInContext(source,context);
  assert.equal(ready.length,1);
  assert.equal(context.exclude_tax_from_amount(20,120),'100.00');
  for(const e of inventory.filter(x=>x.name!=='ready_handlers'))assert.equal(typeof context[e.name],'function',e.name);
});

test('public pure value helpers remain in common app source, not admin handlers', () => {
  const common=read('assets/js/app.js');
  for(const name of ['hidden_input','slugify']) {
    assert.ok(new RegExp('^function '+name+'\\(', 'm').test(common));
    assert.ok(!new RegExp('^function '+name+'\\(', 'm').test(source));
  }
  assert.ok(read('webpack.mix.js').includes('"assets/js/app.js"'));
});
test('native number validation transports real parent/revision without changing legacy wire',()=>{
 for(const native of [false,true]){
  let captured;
  const node=value=>({val:()=>value,data:()=>false,length:1});
  const form={find:selector=>selector.includes('_native_invoice_id')?{...node('123'),length:native?1:0}:node('a'.repeat(64))};
  const body={find:()=>({rules:(_action,options)=>{captured=options;}})};
  const $=selector=>typeof selector==='function'?undefined:selector==='#invoice-form'?form:selector==='body'?body:node('7');
  const context={$,appValidateForm:()=>{},admin_url:'/admin/',app:{lang:{invoice_number_exists:'exists'}}};
  vm.createContext(context);vm.runInContext(source,context);context.validate_invoice_form();
  assert.equal(captured.remote.url,'/admin/invoices/validate_invoice_number');
  assert.equal(captured.remote.type,'post');
  assert.deepEqual(Object.keys(captured.remote.data).sort(),(native?['date','invoice_id','isedit','number','original_number','revision']:['date','isedit','number','original_number']).sort());
  if(native){assert.equal(captured.remote.data.invoice_id(),'123');assert.equal(captured.remote.data.revision(),'a'.repeat(64));}
 }
});

test('original item lookup sends concrete invoice parent without changing legacy route or wire',()=>{
 for(const native of [false,true]){
  let request;
  const form={find:selector=>({length:native?1:0,val:()=>selector.includes('_native_invoice_id')?'42949672972':'2'})};
  const context={$:selector=>typeof selector==='function'?undefined:form,requestGetJSON:(url,params)=>{request={url,params};return{done:()=>{}};}};
  vm.createContext(context);vm.runInContext(source,context);context.add_item_to_preview('73014444033');
  assert.equal(request.url,'invoice_items/get_item_by_id/73014444033');
  if(native)assert.deepEqual(JSON.parse(JSON.stringify(request.params)),{data:{native_editor:'invoice',native_invoice_id:'42949672972',native_invoice_revision:'2'}});
  else assert.equal(request.params,undefined);
 }
 assert.ok(read('application/views/admin/invoices/invoice.php').includes("init_ajax_search('items', '#item_select.ajax-search', native_invoice_catalog_context(), admin_url + 'items/search')"));
});
