'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
test('same native knowledge group callback handles both MIME-decoded and historical JSON safely',()=>{
 const php=fs.readFileSync(__dirname+'/../../application/views/admin/knowledge_base/group.php','utf8'),script=php.match(/<script>([\s\S]*?)<\/script>/)[1];
 for(const encoded of [false,true]){let callback,inserted,closed=false;const option={value:null,label:null,val(v){this.value=v;return this;},text(v){this.label=v;return this;}};const group={find(){return{after(v){inserted=v;}};},selectpicker(){return this;}};
 const $=v=>v==='body'?{hasClass:()=>true}:v==='<option>'?option:v==='#articlegroup'?group:v==='#kb_group_modal'?{modal(){closed=true;}}:{serialize:()=> 'name=test'};$.post=(url,data)=>({done(fn){callback=fn;return this;}});
 const ctx={$: $,window:{addEventListener(){},location:{reload(){throw Error('article group must not navigate');}}}};vm.createContext(ctx);vm.runInContext(script,ctx);assert.equal(ctx.manage_kb_groups({action:'/admin/knowledge_base/group'}),false);const result={success:true,id:'184683593729',name:'<img src=x onerror=alert(1)>'};callback(encoded?JSON.stringify(result):result);assert.equal(inserted,option);assert.equal(option.label,result.name);assert.equal(option.value,result.id);assert(closed);
 }
});
