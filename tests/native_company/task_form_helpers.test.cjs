const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/native-ui-tools.js','utf8');
test('shared task modal converts configured dates without loading legacy main bootstrap',()=>{
 const context=vm.createContext({app:{options:{date_format:''}}});vm.runInContext(source,context);
 for(const [format,input] of [['d/m/Y','21/10/2026'],['d-m-Y','21-10-2026'],['d.m.Y','21.10.2026'],['m/d/Y','10/21/2026'],['m-d-Y','10-21-2026'],['m.d.Y','10.21.2026'],['Y-m-d','2026-10-21']]){context.app.options.date_format=format;assert.equal(context.unformat_date(input),'2026-10-21');}
});
test('shared Ajax picker clear retains original cache reset and change events',()=>{
 for(const multiple of [false,true]){
  const calls=[],cache={list:{cache:{previous:1}}};let timer;
  const select={data:()=>cache,parents:()=>wrapper,html:v=>(calls.push(['html',v]),select),append:v=>(calls.push(['append',v]),select),selectpicker:(k,v)=>(calls.push([k,JSON.stringify(v)]),select),attr:k=>k==='multiple'&&multiple?'multiple':undefined,trigger:(event)=>(calls.push(['event',event]),select)};
  const wrapper={removeClass:v=>(calls.push(['removeClass',v]),wrapper),find:()=>wrapper,remove:()=>calls.push(['remove'])};
  const context=vm.createContext({$:e=>typeof e==='string'?select:{attr:()=> 'rel_id'},setTimeout:fn=>{timer=fn;}});vm.runInContext(source,context);context.deselect_ajax_search({});timer();
  assert.deepEqual(Object.keys(cache.list.cache),[]);assert(calls.some(x=>x[0]==='val'&&x[1]===(multiple?'[]':'""')));assert(calls.some(x=>x[1]==='selected.cleared.ajax.bootstrap.select'));assert(calls.some(x=>x[1]==='change'));
 }
});

test('distributed shared bundle provides the same task form dependencies',()=>{
 const context=vm.createContext({app:{options:{date_format:'d/m/Y'}}});
 vm.runInContext(fs.readFileSync('assets/js/native-ui-tools.min.js','utf8'),context);
 for(const name of Object.keys(JSON.parse(fs.readFileSync('tests/fixtures/native-ui-tools-extraction.json')).functions))assert.equal(typeof context[name],'function',name);
 assert.equal(context.unformat_date('21/10/2026'),'2026-10-21');
});

test('estimate and credit-note validators are available without legacy bootstrap',()=>{
 const registered=[];const field={rules:(method,options)=>registered.push(options),hasClass:()=>false};
 const selection={find:()=>field};const context=vm.createContext({app:{lang:{credit_note_number_exists:'duplicate',estimate_number_exists:'duplicate'}},admin_url:'/admin/',$:()=>selection,appValidateForm:()=>{}});
 vm.runInContext(source,context);context.validate_credit_note_form();context.validate_estimate_form();
 assert.equal(registered[0].remote.url,'/admin/credit_notes/validate_number');assert.equal(registered[1].remote.url,'/admin/estimates/validate_estimate_number');
 assert.equal(registered[0].remote.type,'post');assert.equal(registered[1].remote.type,'post');
});
