'use strict';
// Unit/contract tests only. No claim of authenticated Chromium/MySQL execution.
const assert=require('node:assert/strict');
const {test}=require('node:test');
const {EventEmitter}=require('node:events');
const fs=require('node:fs');
const {safeResponse,diagnostics,go,openCompany}=require('./companies_asaas_browser.cjs');
const SECRET='synthetic-sensitive-value';
function response({url='http://127.0.0.1:42123/admin/company_asaas?token='+SECRET,status=200,method='POST',frame=null}={}) {
 return {url:()=>url,status:()=>status,request:()=>({method:()=>method,isNavigationRequest:()=>true,frame:()=>frame})};
}
function setup({count=1,invalid=[],disabled=false,noValidate=false,status=200,clickError=null,navigationError=null}={}) {
 const records=[],calls=[];const frame={};const page=new EventEmitter();page.mainFrame=()=>frame;
 const result=response({status,frame});
 page.waitForNavigation=async options=>{calls.push(['navigation',options]);if(navigationError)throw navigationError;return result;};
 const button={count:async()=>count,evaluate:async callback=>callback({disabled,formNoValidate:false,form:{noValidate,elements:invalid.map(name=>({name,value:SECRET,willValidate:true,validity:{valid:false}}))}}),click:async()=>{calls.push(['click']);if(clickError)throw clickError;page.emit('response',result);}};
 return {page,button,records,calls,trace:diagnostics(page,record=>records.push(record))};
}
function sanitized(records) {assert.ok(!JSON.stringify(records).includes(SECRET),'no URL, exception or field-value material reaches diagnostics');}

test('status evidence exposes only fixed pathname, method and numeric status',()=>{
 assert.deepEqual(safeResponse(response()),{pathname:'/admin/company_asaas',method:'POST',status:200});
 assert.deepEqual(safeResponse(response({url:'http://127.0.0.1:42123/company_asaas_callback/integration/'+SECRET+'?csrf='+SECRET,method:'UNRECOGNIZED'})),{pathname:'other',method:'other',status:200});
 assert.deepEqual(safeResponse(response({url:'not a URL '+SECRET})),{pathname:'other',method:'POST',status:200});
});

test('successful submit observes navigation and native validity without changing timeout',async()=>{
 const x=setup();await go(x.page,x.button,x.trace,'a:save-account');
 assert.deepEqual(x.calls,[['navigation',{waitUntil:'domcontentloaded'}],['click']]);
 assert.equal(x.records[1].phase,'a:save-account');assert.equal(x.records[1].state,'passed');
 assert.equal(x.records[1].lastResponse.status,200);assert.equal(x.records[1].form.invalidCount,0);sanitized(x.records);
});

test('missing or ambiguous button fails before starting a navigation wait',async()=>{
 for(const count of [0,2]){const x=setup({count});await assert.rejects(go(x.page,x.button,x.trace,'a:reveal-token'),/fixed-label diagnostics/);assert.deepEqual(x.calls,[]);assert.equal(x.records.at(-1).state,'failed');sanitized(x.records);}
});

test('required controls are counted without exposing their values or arbitrary names',async()=>{
 const x=setup({invalid:['confirm',SECRET,'api_key']});
 await assert.rejects(go(x.page,x.button,x.trace,'a:rotate-token'),/fixed-label diagnostics/);
 assert.deepEqual(x.calls,[]);assert.deepEqual(x.records.at(-1).form.invalidNames,['confirm','other','api_key']);assert.equal(x.records.at(-1).form.invalidCount,3);sanitized(x.records);
});

test('disabled submit fails without forcing clicks or bypassing native validation',async()=>{
 const x=setup({disabled:true});await assert.rejects(go(x.page,x.button,x.trace,'b:automation'));assert.deepEqual(x.calls,[]);assert.equal(x.records.at(-1).form.disabled,true);
});

test('existing novalidate remains observable and is not introduced by the runner',async()=>{
 const x=setup({invalid:['email'],noValidate:true});await go(x.page,x.button,x.trace,'authenticate');assert.equal(x.records.at(-1).form.noValidate,true);assert.equal(x.records.at(-1).form.invalidCount,1);assert.equal(x.calls.at(-1)[0],'click');
});

test('HTTP rejection fails at the submitted phase with exact status',async()=>{
 const x=setup({status:403});await assert.rejects(go(x.page,x.button,x.trace,'b:reveal-token'));assert.equal(x.records.at(-1).lastResponse.status,403);assert.equal(x.records.at(-1).phase,'b:reveal-token');assert.equal(x.records.at(-1).state,'failed');sanitized(x.records);
});

test('click/navigation errors and page errors never emit raw messages',async()=>{
 for(const key of ['clickError','navigationError']){const x=setup({[key]:new Error('URL or field '+SECRET)});x.page.emit('pageerror',new Error(SECRET));await assert.rejects(go(x.page,x.button,x.trace,'a:settle'),error=>!error.message.includes(SECRET));assert.equal(x.records.at(-1).pageErrors,1);sanitized(x.records);}
});

test('only fixed phase labels can be recorded',()=>{
 const x=setup();assert.throws(()=>x.trace.stage('a:'+SECRET),/fixed diagnostic phase/);assert.deepEqual(x.records,[]);
});

test('refresh explicitly GETs a fresh company page instead of replaying the previous POST',async()=>{
 const x=setup();x.page.goto=async(target,options)=>{x.calls.push(['goto',target,options]);return response({method:'GET'});};
 x.page.reload=async()=>assert.fail('POST replay must not be invoked');
 await openCompany(x.page,'http://127.0.0.1:42123',x.trace,'a:refresh-token');
 assert.deepEqual(x.calls,[['goto','http://127.0.0.1:42123/admin/company_asaas',{waitUntil:'domcontentloaded'}]]);assert.equal(x.records.at(-1).state,'passed');
});

test('fresh GET rejects an error response or an unexpected mutation transport',async()=>{
 for(const options of [{method:'GET',status:403},{method:'POST',status:200}]){const x=setup();x.page.goto=async()=>response(options);await assert.rejects(openCompany(x.page,'http://127.0.0.1:42123',x.trace,'b:refresh-reveal'));assert.equal(x.records.at(-1).state,'failed');}
});

test('actual runner refreshes every sensitive POST through GET and masks its only screenshot',()=>{
 const source=fs.readFileSync(__dirname+'/companies_asaas_browser.cjs','utf8');
 assert.doesNotMatch(source,/page\.reload\s*\(/);
 for(const phase of ['refresh-account','refresh-token','refresh-reveal','refresh-worker'])assert.ok(source.includes("openCompany(page,url,trace,letter+':"+phase+"')"));
 assert.ok(source.includes("mask:[page.locator('input[type=password], [data-asaas-secret], input[id^=company-webhook-url-]')]"));
 assert.doesNotMatch(source,/console\.(?:log|error)\([^\n]*(?:\.message|\.stderr|\.url\(|\.content\()/);
 const controller=fs.readFileSync(__dirname+'/../application/controllers/admin/Company_asaas.php','utf8');
 assert.ok(controller.includes('if($oneTime!==null||$revealedApiKey!==null){$webhookCsrf=bin2hex(random_bytes(32))'));
 assert.doesNotMatch(controller,/\bredirect\s*\(/,'direct POST rendering means reload is not a fresh GET contract');
});
