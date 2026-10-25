'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../assets/js/tickets.js'),'utf8');
const start=source.indexOf('            }).done(function (response) {',source.indexOf('$.post(admin_url + "tickets/ticket_change_data/"'));
const end=source.indexOf('\n            });',start);
const callback='function consume(response) {'+source.slice(start+'            }).done(function (response) {'.length,end)+'\n}';
test('contact response object/string share typed path; stale requests cannot overwrite a newer choice',()=>{
 for(const typed of [true,false]){
  let selected='2',warnings=0;const values={};
  const $=selector=>({val(value){if(arguments.length){values[selector]=value;return this;}return selected;}});
  const context={$: $,contactid:'2',projectAutoSelected:false,projectsWrapper:{removeClass(){values.projects=true;},addClass(){values.projects=false;}},show_ticket_no_contact_email_warning(){warnings++;},clear_ticket_no_contact_email_warning(){warnings=0;}};
  vm.runInNewContext(callback,context);
  const reply={contact_data:{id:2,userid:2,firstname:'Own',lastname:'Contact',email:'a@example.invalid',ticket_emails:0},customer_has_projects:true};
  context.consume(typed?reply:JSON.stringify(reply));assert.equal(values['input[name="email"]'],'a@example.invalid');assert.equal(warnings,1);assert.equal(values.projects,true);
  selected='3';reply.contact_data.email='late@example.invalid';context.consume(typed?reply:JSON.stringify(reply));assert.equal(values['input[name="email"]'],'a@example.invalid');
 }
});
const kbStart=source.indexOf('function insert_ticket_knowledgebase_link(e) {'),kbEnd=source.indexOf('\nfunction tickets_bulk_action',kbStart);
const kb=source.slice(kbStart,kbEnd);
test('native knowledge link uses issuer-bound approved URL and textContent; legacy subject is escaped too',()=>{
 for(const native of [true,false]){
  let inserted,reads=0;
  const selected={attr(){return 'https://crm.invalid/knowledge-base/article/a?issuer_id=2';},text(){return '<img src=x onerror=bad()>';}};
  const $=selector=>selector==='#new_ticket_form'?{attr(){return native?'1':'0';}}:{val(){return '107374182401';},find(){return selected;},selectpicker(){}};
  const escape=s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
  const context={$: $,site_url:'https://crm.invalid/',document:{createElement(){return{get outerHTML(){return '<a href="'+escape(this.href)+'">'+escape(this.textContent)+'</a>';}};}},tinymce:{activeEditor:{execCommand(command,unused,value){inserted=value;}}},requestGetJSON(){reads++;return{done(fn){fn({slug:'a',subject:'<img src=x onerror=bad()>'});}}}};
  vm.runInNewContext(kb,context);context.insert_ticket_knowledgebase_link({});assert.match(inserted,/&lt;img/);assert.doesNotMatch(inserted,/<img/);assert.equal(reads,native?0:1);if(native)assert.match(inserted,/issuer_id=2/);
 }
});
