'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const code=fs.readFileSync('assets/js/native-admin-calendar.js','utf8');const main=fs.readFileSync('assets/js/main.js','utf8');
for(const fn of ['view_event','delete_event','validate_calendar_form','calendar_form_handler'])assert(!main.includes('function '+fn+'('));
let native=true,calls=[],callbacks=[],reload=0;
const chain={done(fn){callbacks.push(fn);return this;}};
const $=arg=>{if(typeof arg==='function')return;return {hasClass:()=>native,val:()=> '7',serialize:()=> 'title=Name&revision=7'};};
const ctx={window:{location:{reload(){reload++}},NativeCompanyTransport:{}},$,admin_url:'/admin/',confirm_delete:()=>true,requestGetJSON:path=>{calls.push(['legacyGET',path]);return chain},JSON,setTimeout:()=>{}};
ctx.NativeCompanyTransport=ctx.window.NativeCompanyTransport={request(...args){calls.push(args);return chain}};$.post=(...args)=>{calls.push(['legacyPOST',...args]);return chain};$.get=(...args)=>{calls.push(['legacyGET',...args]);return chain};vm.createContext(ctx);vm.runInContext(code,ctx);
ctx.delete_event('55834574849');assert.equal(calls[0][0],'utilities/delete_event/55834574849');assert.equal(calls[0][1],'POST');assert.equal(calls[0][2].revision,'7');callbacks.pop()({success:true});assert.equal(reload,1);
ctx.view_event('55834574849');assert.equal(calls[1][1],'GET');assert.equal(calls[1][3].dataType,'html');
ctx.calendar_form_handler({action:'/admin/utilities/calendar'});assert.equal(calls[2][1],'POST');assert.equal(calls[2][2],'title=Name&revision=7');
ctx.window.NativeCompanyTransport=null;assert.throws(()=>ctx.delete_event('1'),/transport_required/);assert.equal(calls.length,3);
native=false;ctx.delete_event('1');assert.equal(calls[3][0],'legacyPOST');assert.equal(calls[3][2].revision,'7','existing principal dashboard deletion also requires POST/revision');ctx.view_event('1');assert.equal(calls[4][0],'legacyGET','existing principal dashboard reads the shared event adapter with GET');
console.log('Calendar shared handlers: source uniqueness, native GET/read POST/CAS writes, absent transport denial, legacy transport PASS.');

// Exercise the shipped DateEnv and the actual shared event-source callback.
// DOM/transport are explicit unit doubles; no authenticated browser claim.
(async()=>{
 const bundle={};vm.runInNewContext(fs.readFileSync('assets/plugins/fullcalendar/lib/main.js','utf8'),bundle);
 const env=new bundle.FullCalendar.DateEnv({timeZone:'America/Sao_Paulo',calendarSystem:'gregory',locale:{code:'pt-br',codes:['pt-br'],week:{dow:0,doy:6},options:{weekText:'Sem'}},firstDay:0});
 const range={startStr:env.formatIso(env.createMarker('2026-10-01')),endStr:env.formatIso(env.createMarker('2026-11-01'))};
 assert.equal(range.startStr,'2026-10-01T00:00:00','actual installed named-zone output has no UTC offset');
 let settings,query,reject=false,failed=0;
 const element={},attributes={},chain={0:element,length:1,hasClass:name=>name==='native-company-admin',attr(name,value){if(value===undefined)return attributes[name];attributes[name]=value;return this;},find(){return this;},map(){return {get:()=>[]};}};
 const jq=arg=>typeof arg==='function'?undefined:chain;jq.extend=Object.assign;jq.isEmptyObject=value=>Object.keys(value).length===0;
 const transport={request(path,verb,data){assert.equal(path,'utilities/get_calendar_data');assert.equal(verb,'GET');query=data;return reject?Promise.reject(new Error('synthetic transport failure')):Promise.resolve([]);}};
 const sandbox={window:{NativeCompanyTransport:transport,nativeCalendarConfig:{options:{default_view_calendar:'dayGridMonth',timezone:'America/Sao_Paulo',google_api:''},calendar_ids:[]}},NativeCompanyTransport:transport,$:jq,jQuery:jq,isRTL:'false',app:{locale:'pt-br',lang:{filter_by:'Filtrar'}},appValidateForm(){},get_url_param:()=>'',FullCalendar:{Calendar:function(el,options){assert.equal(el,element);settings=options;this.render=()=>{};}}};
 vm.createContext(sandbox);vm.runInContext(code,sandbox);sandbox.window.NativeAdminCalendar.init();
 await settings.eventSources[0](range,rows=>assert.equal(rows.length,0),()=>{failed++;});
 assert.deepEqual({...query},{start:'2026-10-01',end:'2026-11-01'},'exclusive civil date range matches the strict server contract without timezone reinterpretation');
 reject=true;await settings.eventSources[0](range,()=>assert.fail('failed transport cannot publish events'),()=>{failed++;});assert.equal(failed,1,'FullCalendar receives the actual request failure');
 console.log('Shipped FullCalendar '+bundle.FullCalendar.version+' DateEnv + real shared callback: named-zone civil dates, exclusive end and failure propagation PASS.');
})().catch(error=>{console.error(error);process.exitCode=1;});
