'use strict';
const test=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');
const source=fs.readFileSync('assets/js/native-tasks.js','utf8');const app=fs.readFileSync('assets/js/app.js','utf8');
test('original task chart handlers bind once and retain actual chart and cleanup behavior',()=>{
 const events=new Map(),calls=[],charts=[];
 const $=(selector)=>({on(event,target,handler){assert.equal(selector,'body');const key=event+':'+target;assert.equal(events.has(key),false);events.set(key,handler);return this;},find(target){return {selector:target};},modal(action){calls.push([selector,action]);},remove(){calls.push([selector,'remove']);}});
 const ctx=vm.createContext({$,setTimeout(fn,delay){assert.equal(delay,800);fn();},Chart:function(target,options){charts.push({target,options});this.destroy=()=>calls.push(['chart','destroy']);},taskTrackingStatsData:{labels:['Real'],datasets:[{data:[1.5]}]}});vm.runInContext(source,ctx);vm.runInContext(app.match(/^function decimalToHM\([^\n]*\) \{\n[\s\S]*?^\}/m)[0],ctx);
 ctx.init_task_tracking_stats_events();ctx.init_task_tracking_stats_events();assert.equal(events.size,3);
 events.get('show.bs.modal:#task-tracking-stats-modal')();assert.equal(charts.length,1);assert.equal(charts[0].options.type,'line');assert.equal(charts[0].options.data,ctx.taskTrackingStatsData);assert.equal(charts[0].options.options.tooltips.callbacks.label({yLabel:1.5}), '01:30');
 events.get('show.bs.modal:#task-tracking-stats-modal')();assert.ok(calls.some(x=>x[0]==='chart'&&x[1]==='destroy'));
 events.get('click:.close-task-stats')();events.get('hidden.bs.modal:#task-tracking-stats-modal')();assert.ok(calls.some(x=>x[0]==='#task-tracking-stats-modal'&&x[1]==='hide'));assert.ok(calls.some(x=>x[0]==='#tracking-stats'&&x[1]==='remove'));
});
test('same original modal invokes shared initializer and contains no second chart implementation',()=>{
 const view=fs.readFileSync('application/views/admin/tasks/tracking_stats.php','utf8');assert.match(view,/init_task_tracking_stats_events\(\)/);assert.match(view,/id="task-tracking-stats-chart"/);assert.doesNotMatch(view,/new Chart/);const main=fs.readFileSync('assets/js/main.js','utf8');assert.match(main,/init_task_tracking_stats_events\(\)/);assert.doesNotMatch(main,/on\("show.bs.modal", "#task-tracking-stats-modal"/);
});
