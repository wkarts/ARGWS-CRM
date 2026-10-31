'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const main=fs.readFileSync('assets/js/main.js','utf8');
const todos=fs.readFileSync('assets/js/native-admin-todos.js','utf8');
const notes=fs.readFileSync('assets/js/native-admin-notes.js','utf8');
for(const [source,names] of [[todos,['update_todo_items','delete_todo_item','edit_todo_item']],[notes,['toggle_edit_note','edit_note','delete_sales_note','get_sales_notes']]])for(const name of names){assert.match(source,new RegExp('function '+name+'\\('));assert.doesNotMatch(main,new RegExp('function '+name+'\\('));}
const handlers={},posts=[];let revision='a'.repeat(64),reloads=0;
const checkbox={};const $=selector=>{
 if(selector==='body')return {on(event,target,fn){handlers[event+'|'+target]=fn;},hasClass(name){return name==='native-company-admin';}};
 if(selector==='.todos-sortable')return {length:0};
 if(selector===checkbox)return {prop(name,value){if(value===undefined)return true;assert.equal(name,'disabled');return this;},val(){return '1';},closest(){return {attr(){return revision;}};}};
 throw new Error('unexpected selector '+selector);
};
$.post=(url,data)=>{posts.push({url,data});return {done(fn){fn();return this;},fail(){return this;}};};
const context={$,$: $,jQuery:fn=>fn(),admin_url:'/crm/admin/',window:{location:{reload(){reloads++;}}},Error};vm.createContext(context);vm.runInContext(todos,context);
const change=handlers['change|.todo input[type="checkbox"]'];assert.equal(typeof change,'function');change.call(checkbox);
assert.equal(posts.length,1);assert.equal(posts[0].url,'/crm/admin/todo/change_todo_status/1/1');assert.equal(posts[0].data.revision,'a'.repeat(64));assert.equal(reloads,1);assert.equal(context.window.location.href,undefined);
revision=undefined;assert.throws(()=>change.call(checkbox),/native_todo_revision_missing/);assert.equal(posts.length,1);
console.log('Shared todo/note source and native checkbox POST/CAS (no GET fallback): passed.');
