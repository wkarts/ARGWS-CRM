'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync('assets/js/native-ui-tools.js','utf8');
function run(native,transport=true,success=true){
 let click,done,removed=false;const requests=[];
 const node={appendTo(){return this;},html(){return this;},removeClass(){return this;},addClass(){return this;},find(){return this;},on(event,fn){assert.equal(event,'click');click=fn;return this;},off(){return this;},fadeOut(ms,fn){fn();return this;},remove(){removed=true;}};
 const $=()=>node;$.extend=Object.assign;$.ajax=options=>{requests.push(options);return{done(fn){done=fn;}};};
 const context={$,admin_url:'/admin/',document:{body:{classList:{contains:()=>native}}},setTimeout:fn=>fn()};
 if(transport)context.NativeCompanyTransport={request(path,method,data){requests.push({path,method,data});return{done(fn){done=fn;}};}};
 vm.runInNewContext(source,context);context.system_popup({message:'notice'});assert.equal(requests.length,0);
 let error;try{click();}catch(e){error=e;}if(done&&success)done({});
 return{requests,removed,error};
}
test('native explicit POST uses captured transport and removes popup only after success',()=>{const r=run(true);assert.equal(r.requests.length,1);assert.equal(r.requests[0].path,'misc/clear_system_popup');assert.equal(r.requests[0].method,'POST');assert.equal(r.removed,true);assert.equal(r.error,undefined);assert.equal(run(true,true,false).removed,false);});
test('missing native transport fails before request and preserves visible popup',()=>{const r=run(true,false);assert.equal(r.error.message,'native_transport_unavailable');assert.equal(r.requests.length,0);assert.equal(r.removed,false);});
test('legacy branch preserves existing GET and same completion',()=>{const r=run(false,false);assert.equal(r.requests.length,1);assert.equal(r.requests[0].type,'GET');assert.equal(r.requests[0].url,'/admin/misc/clear_system_popup');assert.equal(r.removed,true);});
