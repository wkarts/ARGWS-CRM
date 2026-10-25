const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(process.argv[2]||'/tmp/native-all-contacts.js','utf8');
const begin=source.indexOf("$('#send_file form').on('submit.nativeFileQueue'");
const end=source.indexOf("$('body').on('click','.native-customer-file-delete'",begin);
assert(begin>=0&&end>begin);let handler,reply,fail=false,posts=0,hidden=0,prevented=0;const alerts=[],data={};const button={disabled:false,prop(k,v){this[k]=v;return this;}};
const form={find(){return button;},data(k,v){if(arguments.length===2){data[k]=v;return this;}return data[k];},attr(){return '/admin/misc/send_file/';},serialize(){return 'request_key=stable&native_file_id=1';}};
function $(value){if(value===form)return form;if(value==='#send_file form')return {on(name,fn){handler=fn;}};if(value==='#send_file')return {modal(action){assert.equal(action,'hide');hidden++;}};throw Error('unexpected selector');}
$.post=(url,payload)=>{posts++;assert.equal(url,'/admin/misc/send_file/');assert(payload.includes('request_key=stable'));return {done(fn){if(!fail)fn(reply);return this;},fail(fn){if(fail)fn();return this;},always(fn){fn();return this;}};};
vm.runInNewContext(source.slice(begin,end),{$,alert_float:(kind,text)=>alerts.push([kind,text]),Number,JSON});
const submit=()=>handler.call(form,{preventDefault(){prevented++;}});
reply={success:true,queued:true,queue_id:12};submit();assert.equal(hidden,1);assert.equal(button.disabled,false);assert.match(alerts[0][1],/fila/);assert(!alerts[0][1].includes('enviado'));
reply={success:true};submit();assert.equal(hidden,1);assert.equal(alerts.at(-1)[0],'danger');
fail=true;submit();assert.equal(hidden,1);assert.equal(button.disabled,false);assert.equal(data.nativeFileBusy,false);
data.nativeFileBusy=true;submit();assert.equal(posts,3);assert.equal(prevented,4);
console.log('Native file queued UI: 4 cases passed; actual rendered handler, no browser claim.');
