'use strict';
// The PHP bootstrap fixture supplies the actual rendered shared head. Execute
// it with the shipped transport; the network/DOM boundary is an explicit double.
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const allowed=new Set(['probe_input','probe_script','native_context_header_count','native_context_header_binding','native_csrf_header_count','legacy_context_header_count','legacy_context_header_binding','legacy_csrf_header_count','conflicting_header_not_denied','form_binding','form_old_binding','form_external_binding','pending_read_not_cancelled','completed_or_external_cancelled']);
function check(ok,code){if(!ok)throw Error(code);}
let stage='input';
function run(mode,input){
 const scripts=[...input.html.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)];check(scripts.length===1,'probe_script');
 function Xhr(){this.headers={};this.listeners={};this.aborted=false;}Xhr.prototype.open=function(method,url){this.method=method;this.url=url;};Xhr.prototype.setRequestHeader=function(name,value){(this.headers[name.toLowerCase()]??=[]).push(String(value));};Xhr.prototype.send=function(){};Xhr.prototype.addEventListener=function(name,fn){this.listeners[name]=fn;};Xhr.prototype.abort=function(){this.aborted=true;this.listeners.loadend?.();};
 function Form(action=input.config.root+'utilities/calendar',method='post'){this.action=action;this.method=method;this.fields=[];}Form.prototype.submit=function(){};Form.prototype.querySelector=function(selector){const name=selector.match(/name="([^"]+)"/)[1];return this.fields.find(field=>field.name===name)||null;};Form.prototype.appendChild=function(field){this.fields.push(field);};
 const forms=[],listeners={},windowListeners={},observers=[],storage=new Map();
 const prefilters=[];function $(selector){throw Error('probe_input');}
 $.ajaxPrefilter=fn=>prefilters.push(fn);$.ajax=function(options){for(const filter of prefilters)filter(options);const xhr=new sandbox.XMLHttpRequest();xhr.open(options.type||'GET',options.url);for(const[name,value]of Object.entries(options.headers||{}))xhr.setRequestHeader(name,value);xhr.send();return xhr;};
 const sandbox={URL,URLSearchParams,Headers,Request,location:{href:input.config.root,origin:new URL(input.config.root).origin,replace(){}},addEventListener(name,callback){windowListeners[name]=callback;},localStorage:{getItem:key=>storage.get(key)??null,setItem:(key,value)=>storage.set(key,value)},document:{readyState:'loading',body:{classList:{contains:name=>mode==='native'&&name==='native-company-admin'}},documentElement:{appendChild(){}},querySelectorAll:()=>forms,createElement:()=>({}),addEventListener(name,callback){listeners[name]=callback;}},MutationObserver:class{constructor(callback){observers.push(callback);}observe(){}},HTMLFormElement:Form,XMLHttpRequest:Xhr,jQuery:$,$,NativeCompanyHeaderConfig:input.config};sandbox.window=sandbox;
 vm.createContext(sandbox);stage=mode+'_guard';vm.runInContext(fs.readFileSync(path.join(__dirname,'../../assets/js/native-company-context.js'),'utf8'),sandbox);stage=mode+'_head';vm.runInContext(scripts[0][1],sandbox);stage=mode+'_transport';vm.runInContext(fs.readFileSync(path.join(__dirname,'../../assets/js/native-company-transport.js'),'utf8'),sandbox);
 for(const method of ['GET','POST']){
  stage=mode+'_'+method;
  const xhr=mode==='native'?sandbox.NativeCompanyTransport.request('utilities/calendar',method,{}):$.ajax({url:input.config.root+'utilities/calendar',type:method});
  const contexts=xhr.headers['x-company-context']||[],csrf=xhr.headers['x-native-company-csrf']||[];
  check(contexts.length===1,mode+'_context_header_count');check(contexts[0]===input.config.context,mode+'_context_header_binding');check(csrf.length===(mode==='legacy'||method==='POST'?1:0),mode+'_csrf_header_count');
 }
 if(mode==='native'){let denied=false;try{sandbox.NativeCompanyTransport.request('utilities/calendar','POST',{}, {headers:{'X-Company-Context':'synthetic-foreign-context'}});}catch(error){denied=error.message==='native_transport_token_mismatch';}check(denied,'conflicting_header_not_denied');}
 const currentForm=new Form();forms.push(currentForm);currentForm.submit();sandbox.document.readyState='interactive';listeners.DOMContentLoaded();check(currentForm.fields.length===2&&currentForm.querySelector('input[name="_company_context"]').value===input.config.context&&currentForm.querySelector('input[name="_native_company_csrf"]').value===input.config.csrf,'form_binding');
 const oldForm=new Form();oldForm.fields=[{name:'_company_context',value:'synthetic-old-context'},{name:'_native_company_csrf',value:'synthetic-old-csrf'}];forms.push(oldForm);for(const observe of observers)observe();check(oldForm.fields.length===2&&oldForm.fields[0].value==='synthetic-old-context'&&oldForm.fields[1].value==='synthetic-old-csrf','form_old_binding');
 for(const form of [new Form('https://foreign.invalid/endpoint'),new Form(input.config.root+'utilities/calendar','get')]){forms.push(form);form.submit();check(form.fields.length===0,'form_external_binding');}
 if(storage.size){
  const waiting=$.ajax({url:input.config.root+'tasks/table',type:'POST'});
  const completed=$.ajax({url:input.config.root+'tasks/table',type:'POST'});completed.listeners.loadend();
  const external=$.ajax({url:'https://external.invalid/public',type:'GET'});
  const [key,value]=[...storage.entries()][0];windowListeners.storage({key,newValue:String(Number(value)+1)});
  check(waiting.aborted,'pending_read_not_cancelled');check(!completed.aborted&&!external.aborted,'completed_or_external_cancelled');
  let denied=false;try{if(mode==='native')sandbox.NativeCompanyTransport.request('utilities/calendar','POST',{});else $.ajax({url:input.config.root+'utilities/calendar',type:'POST'});}catch(error){denied=error.message==='native_company_context_obsolete';}check(denied,'conflicting_header_not_denied');
  denied=false;try{currentForm.submit();}catch(error){denied=error.message==='native_company_context_obsolete';}check(denied,'form_binding');
 }
}
try{const cases=JSON.parse(fs.readFileSync(0,'utf8'));check(cases&&cases.native&&cases.legacy,'probe_input');run('legacy',cases.legacy);run('native',cases.native);process.stdout.write('Native network owner: rendered shared head + shipped transport, GET/POST single context, CSRF purpose and conflicting-header denial passed.\n');}catch(error){process.stderr.write('Native network probe failed ['+(allowed.has(error.message)?error.message:'unclassified_'+stage)+']; no tokens logged.\n');process.exitCode=1;}
