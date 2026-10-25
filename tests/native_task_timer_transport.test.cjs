const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const {test} = require('node:test');
const context = vm.createContext({});
vm.runInContext(fs.readFileSync('assets/js/native-tasks.js', 'utf8'), context);
const request = context.native_task_timer_request;
const key='a'.repeat(64), policy='b'.repeat(64);
test('original header task stop carries exact revisions and idempotency key',()=>{
 const data={task_id:'12884901889',timer_id:'51539607553',note:'note'};
 const out=request(data,{policy,taskRevision:'2',timerRevision:'2',noteRevision:'1'},key);
 assert.equal(out,data); assert.equal(out.native_request_key,key); assert.equal(out.native_task_revision,'2'); assert.equal(out.native_note_revision,'1');
});
test('blank start does not fabricate a task revision',()=>{
 const out=request({task_id:0,timer_id:'',note:''},{policy},key);
 assert.equal(out.native_policy_hash,policy);assert.equal('native_task_revision' in out,false);
});
test('assignment independently retains target task and source blank revisions',()=>{
 const out=request({task_id:'12884901890',timer_id:'64424509441',note:''},{policy,taskRevision:'8',timerRevision:'1',noteRevision:'0'},key);
 assert.equal(out.native_task_revision,'8');assert.equal(out.native_timer_revision,'1');
});
test('missing, malformed and out-of-range facts fail before transport',()=>{
 for(const frame of [{policy,taskRevision:'0',timerRevision:'1',noteRevision:'0'},{policy,taskRevision:'2',timerRevision:'1',noteRevision:undefined},{policy,taskRevision:'2147483647',timerRevision:'1',noteRevision:'0'},{policy:'bad',taskRevision:'2',timerRevision:'1',noteRevision:'0'}])assert.throws(()=>request({task_id:'12884901889',timer_id:'51539607553'},frame,key));
});
test('actual generic picker preserves only a validated revision as option data',()=>{
 let captured;
 const picker={length:1,data(){return null},selectpicker(){return this},ajaxSelectPicker(options){captured=options}};
 const sandbox=vm.createContext({admin_url:'/admin/',app:{lang:{}},$:()=>({find:()=>picker})});
 vm.runInContext(fs.readFileSync('assets/js/native-ui-tools.js','utf8'),sandbox);
 sandbox.init_ajax_search('tasks','#timer_add_task_id',undefined,'/admin/tasks/ajax_search_assign_task_to_timer');
 const out=captured.preprocessData([{id:'12884901889',name:'Task',native_revision:7}]);assert.equal(out[0].data['native-task-revision'],'7');
 assert.throws(()=>captured.preprocessData([{id:'1',name:'bad',native_revision:'7'}]));
});

test('timer action uses matching local Staff row without colliding with header identity',()=>{
 const source=fs.readFileSync('assets/js/native-tasks.js','utf8');
 const begin=source.indexOf('    var $localTimer =');const end=source.indexOf('    var $selected =',begin);assert.ok(begin>0&&end>begin);
 for(const [id,expected] of [['51539607553','local'],['51539607554','header'],[undefined,'header']]){
  const local={attr:()=>id},header={},button={closest:()=>local};
  const box=vm.createContext({e:{},task_id:'12884901889',timer_id:'51539607553',$:v=>typeof v==='string'?header:button});vm.runInContext(source.slice(begin,end)+';globalThis.actual=$row;',box);
  assert.equal(box.actual,expected==='local'?local:header);
 }
});

test('actual timer start selects only the matching canonical task row',()=>{const source=fs.readFileSync('assets/js/native-tasks.js','utf8');const begin=source.indexOf('    var $localTimer ='),end=source.indexOf('    var $selected =',begin);for(const matching of [true,false]){const local={attr:()=>matching?'12884901889':'12884901890'},button={closest:()=>local};const box=vm.createContext({e:{},task_id:'12884901889',timer_id:'',$:()=>button});vm.runInContext(source.slice(begin,end)+';globalThis.actual=$row;',box);assert.equal(box.actual,matching?local:button);}});
