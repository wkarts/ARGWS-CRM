'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(__dirname+'/../../assets/js/tickets.js','utf8');const fn=source.slice(source.indexOf('function ticket_status_request('));
test('same native status controls use POST with captured CAS and only advance after exact receipt',()=>{
 let post,callback,gets=0;const fields={revision:'a'.repeat(64),request_nonce:'b'.repeat(64)};
 const context={document:{body:{classList:{contains(){return true;}}}},window:{crypto:{getRandomValues(a){a.fill(5);}},NativeCompanyTransport:{request(path,method,data,options){post={path,method,data,options};return{done(fn){callback=fn;return this;}};}}},requestGetJSON(){gets++;},$:()=>({find(selector){const key=selector.includes('revision')?'revision':'request_nonce';return{val(value){if(arguments.length)fields[key]=value;return fields[key];}};}})};
 vm.runInNewContext(fn,context);context.ticket_status_request('94489280513','103079215105');assert.equal(post.method,'POST');assert.equal(post.data.revision,'a'.repeat(64));assert.equal(post.data.request_nonce,'b'.repeat(64));assert.equal(gets,0);
 assert.throws(()=>callback({success:true,ticketid:'wrong',revision:'c'.repeat(64)}),/receipt_invalid/);assert.equal(fields.revision,'a'.repeat(64));assert.equal(fields.request_nonce,'b'.repeat(64));
 callback({success:true,ticketid:'94489280513',revision:'c'.repeat(64)});assert.equal(fields.revision,'c'.repeat(64));assert.equal(fields.request_nonce,'05'.repeat(32));
 fields.revision='';assert.throws(()=>context.ticket_status_request('94489280513','103079215105'),/revision_required/);assert.equal(gets,0);
});
test('principal status handler retains original GET semantics without a named transport',()=>{
 let path;const context={document:{body:{classList:{contains(){return false;}}}},requestGetJSON(value){path=value;return 'xhr';}};vm.runInNewContext(fn,context);assert.equal(context.ticket_status_request(1,2),'xhr');assert.equal(path,'tickets/change_status_ajax/1/2');
});
