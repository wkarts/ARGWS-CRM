'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
test('same native service modal accepts typed JSON and inserts provider-independent labels as DOM text',()=>{
 const source=read('application/views/admin/tickets/services/service.php');const script=source.slice(source.indexOf('    function manage_ticket_services('),source.indexOf('    function new_service('));
 for(const response of [{success:true,id:'107374182401',name:'<img src=x onerror=bad()> '},JSON.stringify({success:true,id:'107374182401',name:'<img src=x onerror=bad()> '})]){
  let sent,inserted;const form={action:'/sub/admin/tickets/service'};
  const group={find(){return{after(option){inserted=option;}}},selectpicker(){}};
  const $=(selector,attrs)=>{if(selector===form)return{serialize:()=> 'name=test'};if(selector==='body')return{hasClass:()=>true};if(selector==='select#service')return group;if(selector==='#ticket-service-modal')return{modal(){}};if(selector==='<option>')return{attrs,text(value){this.label=value;return this;}};throw Error('Unexpected selector');};
  $.post=(url,data)=>{sent={url,data};return{done(fn){fn(response);}}};const context={$: $,window:{location:{reload(){throw Error('ticket area should update its existing selector');}}}};vm.runInNewContext(script,context);assert.equal(context.manage_ticket_services(form),false);assert.equal(sent.url,form.action);assert.match(sent.data,/ticket_area=true/);assert.equal(inserted.label,'<img src=x onerror=bad()> ');assert.equal(inserted.attrs.value,'107374182401');
 }
});
