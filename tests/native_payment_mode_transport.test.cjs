const {test}=require('node:test');const assert=require('node:assert/strict');const vm=require('node:vm');const fs=require('node:fs');
const source=fs.readFileSync('assets/js/native-admin-payment-modes.js','utf8');
function fixture(remove=false){let listener,request,done,fail,always,reloads=0;const $=()=>({DataTable:()=>({ajax:{reload(){reloads++;}}})});$.post=(url,data)=>{request={url,data};return{done(fn){done=fn;return this;},fail(fn){fail=fn;return this;},always(fn){always=fn;return this;}};};const target={checked:true,href:'/admin/paymentmodes/delete/123',dataset:{},classList:{contains(c){return remove?c==='_delete':c==='onoffswitch-checkbox';}},getAttribute(k){return {'data-revision':'a'.repeat(64),'data-id':'123','data-switch-url':'/admin/paymentmodes/change_payment_mode_status'}[k];}};let prevented=0,stopped=0;vm.runInNewContext(source,{document:{addEventListener(name,fn,capture){assert.equal(name,'click');assert.equal(capture,true);listener=fn;}},window:{confirm:()=>true},$,alert_float(){}});const click=()=>listener({target:{closest:()=>target},preventDefault(){prevented++;},stopImmediatePropagation(){stopped++;}});return{click,target,get request(){return request;},get prevented(){return prevented;},get stopped(){return stopped;},success(){done();always();},failure(){fail();always();},get reloads(){return reloads;}};}
test('native toggle POST revision, intercepts generic GET, suppresses duplicate while busy',()=>{const f=fixture();f.click();assert.equal(f.request.url,'/admin/paymentmodes/change_payment_mode_status/123/1');assert.equal(f.request.data.revision,'a'.repeat(64));assert.equal(f.prevented,1);assert.equal(f.stopped,1);const old=f.request;f.click();assert.equal(f.request,old);f.success();assert.equal(f.reloads,1);assert.equal(f.target.dataset.busy,undefined);});
test('failure restores previous state without automatic replay',()=>{const f=fixture();f.click();const request=f.request;f.failure();assert.equal(f.target.checked,false);assert.equal(f.request,request);assert.equal(f.reloads,0);});
test('native delete remains same URL but POST with revision',()=>{const f=fixture(true);f.click();assert.equal(f.request.url,'/admin/paymentmodes/delete/123');assert.equal(f.request.data.revision,'a'.repeat(64));});

// Execute the actual shared form callback, including jQuery's JSON response type.
function submitMode(response) {
 const view=fs.readFileSync('application/views/admin/paymentmodes/paymentmode.php','utf8');
 const handler=view.slice(view.indexOf('    function manage_payment_modes(form) {')).split('</script>')[0];
 const form={action:'/admin/paymentmodes/manage'};let request,reloads=0,hidden=0;const alerts=[];
 const $=value=>{
  if(value===form)return{serialize:()=> 'name=Synthetic&revision=owned'};
  if(value==='.table-payment-modes')return{DataTable:()=>({ajax:{reload(){reloads++;}}})};
  if(value==='#payment_mode_modal')return{modal(action){assert.equal(action,'hide');hidden++;}};
  throw new Error('Unexpected form selector');
 };
 $.post=(url,data)=>{request={url,data};return{done(callback){callback(response);}};};
 const context={$ ,alert_float:(kind,message)=>alerts.push({kind,message})};vm.createContext(context);vm.runInContext(handler,context);
 assert.equal(context.manage_payment_modes(form),false,'AJAX submission prevents default navigation');
 return{request,reloads,hidden,alerts};
}
for(const wire of ['json-object','legacy-text'])test('shared mode form accepts '+wire+' without a second JSON decode',()=>{
 const payload={success:true,message:'Synthetic saved',id:123};const r=submitMode(wire==='legacy-text'?JSON.stringify(payload):payload);
 assert.deepEqual(r.request,{url:'/admin/paymentmodes/manage',data:'name=Synthetic&revision=owned'});
 assert.equal(r.reloads,1);assert.equal(r.hidden,1);assert.deepEqual(r.alerts,[{kind:'success',message:'Synthetic saved'}]);
});
test('shared mode form does not claim success for a rejected response',()=>{
 const r=submitMode({success:false,message:'Synthetic rejection'});assert.equal(r.reloads,0);assert.deepEqual(r.alerts,[]);
});
