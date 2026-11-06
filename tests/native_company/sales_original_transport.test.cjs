'use strict';
// Executes the shipped form transport/billing functions. DOM/transport seams are explicit; this is not browser evidence.
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/native-admin-sales.js','utf8'),shared=fs.readFileSync('assets/js/native-admin-sales-form.js','utf8');
function submitFixture(){
  const requests=[],navigation=[],callbacks={};let active=true,valid=true,disabled=false,notice='',removed=0;
  const disabledInputs=[{name:'currency',value:'1',type:'select-one'},{name:'newitems[1][taxname][]',value:['Tax A|5','Tax B|7'],type:'select-multiple'},{name:'newitems[1][is_optional]',value:'1',type:'checkbox',checked:false},{name:'newitems[2][is_optional]',value:'1',type:'checkbox',checked:true}];
  const alert={length:0,text(value){notice=value;return this;},removeClass(){return this;},addClass(){return this;},prependTo(){return this;}};
  const form={attr:()=>'/admin/estimates/estimate',valid:()=>valid,serializeArray:()=>[{name:'_native_sales_id',value:''},{name:'_native_sales_request_key',value:'a'.repeat(64)},{name:'newitems[1][description]',value:'Literal <script>'}],find(selector){
    if(selector===':input:disabled[name]')return{not:()=>({each:fn=>disabledInputs.forEach(row=>fn.call(row))})};
    if(selector==='.transaction-submit')return{prop:(_key,value)=>{disabled=value;}};
    if(selector==='[data-native-sales-error]')return alert;if(selector==='.col-md-12')return{first:()=>({})};if(selector.includes('save_and_send'))return{remove:()=>removed++};throw new Error('Unexpected form selector: '+selector);
  }};
  const $=value=>typeof value==='function'?undefined:typeof value==='string'&&value.startsWith('<div')?alert:value===form?form:{val:()=>value.value};
  $.param=values=>new URLSearchParams(values.map(row=>[row.name,row.value])).toString();
  $.ajax=options=>{requests.push(options);return{done(fn){callbacks.done=fn;return this;},fail(fn){callbacks.fail=fn;return this;},always(fn){callbacks.always=fn;return this;}};};
  const window={jQuery:$,NativeCompanyContextGuard:{isCurrent:()=>active},admin_url:'/admin/',location:{href:'https://synthetic.invalid/admin/estimates/estimate',assign:url=>navigation.push(url)}};
  vm.runInNewContext(source,{window,document:{},URL,admin_url:'/admin/'});
  return{window,form,requests,navigation,callbacks,setCurrent(value){active=value;},setValid(value){valid=value;},state:()=>({disabled,notice,removed})};
}
test('actual original transport keeps disabled currency and repeated line options with one outstanding command',()=>{
  const f=submitFixture();assert.equal(f.window.native_sales_original_submit(f.form),false);f.window.native_sales_original_submit(f.form);assert.equal(f.requests.length,1);
  const request=f.requests[0],wire=new URLSearchParams(request.data);assert.equal(request.url,'/admin/estimates/estimate');assert.equal(request.method,'POST');assert.equal(wire.get('currency'),'1');assert.deepEqual(wire.getAll('newitems[1][taxname][]'),['Tax A|5','Tax B|7']);assert.equal(wire.get('newitems[1][description]'),'Literal <script>');assert.equal(wire.has('newitems[1][is_optional]'),false);assert.equal(wire.get('newitems[2][is_optional]'),'1');
  f.callbacks.done({success:true,url:'/admin/estimates/list_estimates/8589934593'});f.callbacks.always();assert.deepEqual(f.navigation,['https://synthetic.invalid/admin/estimates/list_estimates/8589934593']);assert.equal(f.state().removed,1);
});
test('actual original transport refuses changed company before submit and after an in-flight response',()=>{
  const f=submitFixture();f.setCurrent(false);f.window.native_sales_original_submit(f.form);assert.equal(f.requests.length,0);f.setCurrent(true);f.window.native_sales_original_submit(f.form);f.setCurrent(false);f.callbacks.done({success:true,url:'/admin/estimates/list_estimates/8589934593'});f.callbacks.always();assert.deepEqual(f.navigation,[]);
});
test('actual original transport reports safe error text and allows the same unchanged form to be reviewed',()=>{
  const f=submitFixture();f.window.native_sales_original_submit(f.form);f.callbacks.fail({responseJSON:{message:'<img src=x onerror=alert(1)>'}});f.callbacks.always();assert.equal(f.state().notice,'<img src=x onerror=alert(1)>');assert.equal(f.state().disabled,false);assert.deepEqual(f.navigation,[]);f.window.native_sales_original_submit(f.form);assert.equal(f.requests.length,2);
});
test('actual original transport refuses external or non-admin redirect receipts',()=>{
  for(const target of ['https://other.invalid/admin/estimates','/administrator','/admin/../clients','javascript:alert(1)']){const f=submitFixture();f.window.native_sales_original_submit(f.form);f.callbacks.done({success:true,url:target});assert.deepEqual(f.navigation,[]);assert.equal(f.state().notice,'Destino de navegação inválido.');}
  const invalid=submitFixture();invalid.setValid(false);invalid.window.native_sales_original_submit(invalid.form);assert.equal(invalid.requests.length,0);
});
test('billing preview executes production escaping while preserving literal multiline addresses',()=>{
  const code=shared.match(/^function init_billing_and_shipping_details\([^\n]*\)[^{]*\{.*?^}/ms)[0],escape=fs.readFileSync('assets/js/app.js','utf8').match(/^function escapeHtml\([^\n]*\)[^{]*\{.*?^}/ms)[0];
  const fields=['billing_street','billing_city','billing_country','shipping_street'],values={billing_street:'<img src=x onerror=alert(1)>\nSecond & "line"',billing_city:"City <script>bad()</script>",billing_country:'Country <unsafe>',shipping_street:'Private shipping'},rendered={};
  const $=selector=>({length:1,prop:()=>false,val:()=>values[selector.match(/name="([^"]+)"/)?.[1]],data:()=>values.billing_country,html:value=>{rendered[selector]=value;},modal:()=>{}});
  const scope={$,billingAndShippingFields:fields};vm.runInNewContext(escape+'\n'+code,scope);scope.init_billing_and_shipping_details();assert.equal(rendered['.billing_street'],'&lt;img src=x onerror=alert(1)&gt;<br />Second &amp; &quot;line&quot;');assert.equal(rendered['.billing_city'],'City &lt;script&gt;bad()&lt;/script&gt;');assert.equal(rendered['.billing_country'],'Country &lt;unsafe&gt;');assert.equal(rendered['.shipping_street'],'--');
});
test('optional item handlers execute from the shared form bundle without the legacy admin bundle',()=>{
  const callbacks={},css=[],checks=[];const body={on(event,selector,fn){callbacks[event+':'+selector]=fn;return this;}};
  const node={parent:()=>({css:(_key,value)=>css.push(value),next:()=>({css:(_key,value)=>css.push(value)})})};
  let ready;const $=value=>{if(typeof value==='function'){ready=value;return;}if(value==='body')return body;return node;};
  const scope={$,app:{options:{decimal_places:2}}};vm.createContext(scope);vm.runInContext(shared,scope);
  // The first ready statements bind the optional handlers; stop at the next unrelated form initialization.
  scope.init_ajax_search=()=>{};scope.init_items_sortable=()=>{};
  try{ready();}catch(error){assert.match(error.message,/find|length|on|accounting/);}
  scope.calculate_total=()=>checks.push('total');callbacks['change:#main-optional']({target:{checked:true}});callbacks['change:.optional-item-checkbox']({target:{checked:false}});callbacks['change:.optional-choose-item-checkbox']({target:{checked:true}});assert.deepEqual(css,['block','none']);assert.deepEqual(checks,['total','total']);
  assert(!fs.readFileSync('assets/js/main.js','utf8').includes("on('change', '#main-optional'"));
});
