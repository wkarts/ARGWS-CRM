'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const {test}=require('node:test');
const source=fs.readFileSync('assets/js/native-admin-invoices.js','utf8');

function lifecycleFixture(revision='a'.repeat(64)){
 const attrs={href:'/admin/invoices/mark_as_cancelled/19','data-native-invoice-lifecycle-revision':revision};
 const node={attr(name,value){if(value===undefined)return attrs[name];attrs[name]=value;return this;}};
 const calls=[];let done,always,navigated='';
 const request={done(fn){done=fn;return this},always(fn){always=fn;return this}};
 const $=value=>{if(typeof value==='function')return;return node;};
 const window={jQuery:$,NativeCompanyTransport:{request(...args){calls.push(args);return request},navigate(url){navigated=url}},crypto:{getRandomValues(bytes){bytes.fill(1)}}};
 vm.runInNewContext(source,{window,document:{getElementById(){return null}},navigator:{userAgent:''},Uint8Array,Array,Number,String,Error});
 return{window,node,attrs,calls,complete(response){done(response);always();},navigated:()=>navigated};
}

test('official invoice lifecycle posts captured revision once and follows validated receipt',()=>{
 const fixture=lifecycleFixture();
 assert.equal(fixture.window.native_invoice_lifecycle(fixture.node),false);
 assert.equal(fixture.calls[0][0],'/admin/invoices/mark_as_cancelled/19');assert.equal(fixture.calls[0][1],'POST');
 assert.equal(fixture.calls[0][2].revision,'a'.repeat(64));assert.equal(fixture.calls[0][3].dataType,'json');
 fixture.window.native_invoice_lifecycle(fixture.node);assert.equal(fixture.calls.length,1,'busy action cannot dispatch twice');
 fixture.complete({success:true,redirect:'/admin/invoices/list_invoices/19'});
 assert.equal(fixture.navigated(),'/admin/invoices/list_invoices/19');assert.equal(fixture.attrs['aria-busy'],'false');
});

test('official invoice lifecycle rejects missing revision before transport',()=>{
 const fixture=lifecycleFixture('');assert.throws(()=>fixture.window.native_invoice_lifecycle(fixture.node),/revision_required/);assert.equal(fixture.calls.length,0);
});

test('official invoice bundle owns finite table, totals, notes and attachment transports',()=>{
 assert.match(source,/initDataTable\(table, admin_url \+ 'invoices\/table'/);
 assert.match(source,/request\('invoices\/get_invoices_total', 'POST'/);
 assert.match(source,/request\('misc\/add_sales_external_attachment', 'POST'/);
 assert.match(source,/body\.append\('request_key', file\.nativeInvoiceRequestKey\)/);
 assert.match(source,/body\.append\('_company_context', root\.NativeCompanyHeaderConfig\.context\)/);
 assert.match(source,/submit\.nativeInvoiceNotes/);
 const presentation=fs.readFileSync('application/services/NativeCompanyAdminPresentation.php','utf8');
 assert.match(presentation,/invoicePage\(\$ci\)\)\$assets\[\]='assets\/js\/native-admin-invoices\.js'/);
});
