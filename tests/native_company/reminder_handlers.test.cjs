'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/js/native-admin-reminders.js','utf8');
const main=fs.readFileSync('assets/js/main.js','utf8');
for(const name of ['init_form_reminder','new_task_reminder','edit_reminder','reminderFormHandler','reload_reminders_tables']){
  assert.equal((source.match(new RegExp('function '+name+'\\(','g'))||[]).length,1);
  assert.equal((main.match(new RegExp('function '+name+'\\(','g'))||[]).length,0,'single source '+name);
}
const records=[];
const form={appFormValidator(o){records.push(['validate',o]);},serialize(){return 'description=Owned&revision=3&_company_context=bound&_native_company_csrf=sealed';},attr(k){assert.equal(k,'action');return '/crm/admin/tasks/add_reminder/8589934593';},trigger(e){records.push(['trigger',e]);},find(selector){return {val(){return selector.includes('rel_type')?'task':'8589934593';}}}};
const jquery=(selector)=>{
  if(selector===form)return form;
  if(selector==='#form-reminder-task')return [form];
  if(selector==='#task-modal')return {is(){return false;}};
  if(selector==='body')return {hasClass(){return false;}};
  return {modal(action){records.push(['modal',selector,action]);}};
};
jquery.each=(rows,fn)=>rows.forEach((r,i)=>fn(i,r));
jquery.fn={DataTable:{isDataTable(){return false;}}};
jquery.post=(url,data)=>{records.push(['post',url,data]);return {done(fn){fn(JSON.stringify({message:'',alert_type:'success'}));}}};
const context={jQuery:function(fn){},$:jquery,alert_float(){throw Error('unexpected alert');},JSON,admin_url:'/crm/admin/'};
vm.createContext(context);vm.runInContext(source,context);
context.init_form_reminder('task');
assert.equal(records[0][1].rules.date,'required');
assert.equal(records[0][1].rules.staff,'required');
assert.equal(records[0][1].rules.description,'required');
assert.equal(records[0][1].submitHandler,context.reminderFormHandler);
assert.equal(context.reminderFormHandler(form),false);
const post=records.find(r=>r[0]==='post');assert.equal(post[1],'/crm/admin/tasks/add_reminder/8589934593');
assert.match(post[2],/_native_company_csrf=sealed/);assert.match(post[2],/revision=3/);
assert.ok(records.some(r=>r[0]==='trigger'&&r[1]==='reinitialize.areYouSure'));
assert.ok(records.some(r=>r[0]==='modal'&&r[1]==='.reminder-modal-task-8589934593'&&r[2]==='hide'));
console.log('Shared native reminder source/validation/POST/form contract: passed (no browser claim).');
