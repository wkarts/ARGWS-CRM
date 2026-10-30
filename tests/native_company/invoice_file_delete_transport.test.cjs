const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');
const source=fs.readFileSync('assets/js/native-admin-files.js','utf8');
function fixture(native=true,revision='2'){
 const attrs={'data-native-file-revision':revision},calls=[];let removed=false;const callbacks={};
 const request={done(fn){callbacks.done=fn;return this},fail(fn){callbacks.fail=fn;return this},always(fn){callbacks.always=fn;return this}};
 const row={length:1,attr(k,v){if(v===undefined)return attrs[k];attrs[k]=v;return this},removeAttr(k){delete attrs[k];return this},remove(){removed=true;return this}};
 const body={hasClass(){return native},find(selector){if(selector.includes('data-attachment-id'))return row;return{val(){return'99'}}}};
 const context={window:{NativeCompanyTransport:{}},NativeCompanyTransport:{request(...args){calls.push(args);return request}},confirm_delete(){return true},requestGet(url){calls.push([url,'GET']);return request},$(){return body},init_invoice(id){calls.push(['refresh',id])},alert_float(...args){calls.push(['alert',...args])}};vm.runInNewContext(source,context);return{context,calls,attrs,callbacks,removed:()=>removed};
}
test('same original function issues POST with current revision, suppresses duplicate and removes only after success',()=>{const f=fixture();f.context.delete_invoice_attachment(12);f.context.delete_invoice_attachment(12);assert.equal(f.calls.length,1);assert.equal(f.calls[0][1],'POST');assert.equal(f.calls[0][2].revision,'2');assert.equal(f.removed(),false);f.callbacks.done(true);f.callbacks.always();assert.equal(f.removed(),true);assert.equal(f.attrs['data-native-file-busy'],undefined)});
test('failure preserves row, does not retry, missing revision fails before transport',()=>{const f=fixture();f.context.delete_invoice_attachment(12);f.callbacks.fail({responseText:'synthetic'});f.callbacks.always();assert.equal(f.removed(),false);assert.equal(f.calls.filter(x=>x[1]==='POST').length,1);assert.throws(()=>fixture(true,'').context.delete_invoice_attachment(12),/identity_required/)});
test('legacy original GET preserved, exactly one production definition',()=>{const f=fixture(false);f.context.delete_invoice_attachment(12);assert.equal(f.calls[0][1],'GET');assert.equal((source.match(/function delete_invoice_attachment\(/g)||[]).length,1);assert.equal(fs.readFileSync('assets/js/main.js','utf8').includes('function delete_invoice_attachment('),false)});
test('visibility receipt advances the same attachment container revision used by subsequent delete',()=>{
 const f=fixture();const nodeAttrs={'data-native-file-source':'invoice','data-native-file-revision':'2','data-native-file-visible':'0'};
 const icon={removeClass(){return this},addClass(){return this}};
 const node={attr(k,v){if(v===undefined)return nodeAttrs[k];nodeAttrs[k]=v;return this},find(){return icon},closest(){return{attr(k,v){f.attrs[k]=v;return this}}}};
 const original=f.context.$;f.context.$=(value)=>value===node?node:original(value);
 f.context.toggle_file_visibility(12,99,node);f.callbacks.done({revision:3,visible:1});f.context.delete_invoice_attachment(12);
 assert.equal(f.calls[1][2].revision,'3');assert.equal(nodeAttrs['data-native-file-visible'],'1');
});
