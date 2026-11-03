const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto').webcrypto;
const source = fs.readFileSync('assets/js/companies/native-portal-files.js', 'utf8');
function boot() {
 const handlers = {}, requests = [], navigation = [], data = {issuer:41,csrf:'a'.repeat(64),maximum:2097152,allowed:'.pdf,.png',returnUrl:'https://fixture.invalid/clients/files?issuer_id=41',google:false,dropbox:false};
 const error={hidden:true}, form={action:'https://fixture.invalid/clients/upload_files?issuer_id=41'};
 function Dropzone(el, options) { assert.equal(el,form); this.getAcceptedFiles=()=>[{}]; this.on=(k,fn)=>handlers[k]=fn; requests.push(options); }
 const context={crypto,Uint8Array,Array,Number,JSON,Error,FormData,Dropzone,document:{getElementById:(id)=>id==='native-portal-files-config'?{textContent:JSON.stringify(data)}:id==='files-upload'?form:error,querySelectorAll:()=>[]},jQuery:()=>({DataTable:()=>{}}),window:{location:{assign:url=>navigation.push(url)}}};
 vm.runInNewContext(source,context);return{handlers,error,navigation,requests};
}
let s=boot();const file={}, first=new FormData();s.handlers.sending(file,null,first);assert.match(first.get('native_file_request'),/^[a-f0-9]{64}$/);assert.equal(first.get('issuer_id'),'41');assert.equal(first.get('_native_portal_csrf'),'a'.repeat(64));const retry=new FormData();s.handlers.sending(file,null,retry);assert.equal(retry.get('native_file_request'),first.get('native_file_request'));
s.handlers.success(file,{success:true,file_id:1,notification:'recorded',mail:'pending'});s.handlers.queuecomplete();assert.deepEqual(s.navigation,['https://fixture.invalid/clients/files?issuer_id=41']);
s=boot();s.handlers.success({},'<!doctype html>');s.handlers.queuecomplete();assert.equal(s.error.hidden,false);assert.equal(s.navigation.length,0);
s=boot();s.handlers.success({},{success:true,file_id:1,notification:'sent',mail:'sent'});s.handlers.queuecomplete();assert.equal(s.error.hidden,false);assert.equal(s.navigation.length,0);
s=boot();s.handlers.error();s.handlers.queuecomplete();assert.equal(s.navigation.length,0);
console.log('Native portal files JS: request identity/retry/CSRF, durable pending receipt, malformed response and errors passed; no browser claim.');
