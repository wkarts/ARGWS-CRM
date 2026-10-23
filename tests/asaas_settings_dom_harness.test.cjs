'use strict';
// Harness-only fault injection: this does not execute or certify browser DOM checks.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
for(const scenario of ['pass','assertion-failed','missing-marker','page-error']) {
 test('DOM harness remains fail-closed: '+scenario,()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'asaas-harness-'));
  try {
   const mock=path.join(dir,'driver.cjs');
   fs.writeFileSync(mock,`exports.chromium={launch:async()=>({
    close:async()=>console.log('MOCK_BROWSER_CLOSED'),
    newContext:async()=>({route:async()=>{},newPage:async()=>({
     on:(event,fn)=>{if(process.env.HARNESS_SCENARIO==='page-error')fn(new Error('synthetic uncaught error'));},
     setDefaultTimeout:()=>{},setContent:async html=>{if(!html.includes("'PASS '+checks"))throw new Error('assertions not injected');},
     locator:()=>({waitFor:async()=>{if(process.env.HARNESS_SCENARIO==='missing-marker')throw new Error('synthetic timeout');},
      textContent:async()=>process.env.HARNESS_SCENARIO==='assertion-failed'?'FAIL synthetic assertion':'PASS 42'})
    })})
   })};`);
   const result=spawnSync(process.execPath,[path.join(__dirname,'asaas_settings_tabs.test.js')],{
    encoding:'utf8',timeout:10000,env:{...process.env,CHROMIUM_BIN:'synthetic-only',ASAAS_PLAYWRIGHT_MODULE:mock,ASAAS_UI_SCREENSHOT:'',HARNESS_SCENARIO:scenario}
   });
   assert.equal(result.error,undefined);
   assert.equal(result.status,scenario==='pass'?0:1,result.stderr);
   assert.match(result.stdout,/MOCK_BROWSER_CLOSED/);
   if(scenario==='missing-marker')assert.match(result.stderr,/did not finish its DOM assertions/);
   if(scenario==='assertion-failed')assert.match(result.stderr,/FAIL synthetic assertion/);
   if(scenario==='page-error')assert.match(result.stderr,/uncaught browser errors/);
  } finally {fs.rmSync(dir,{recursive:true,force:true});}
 });
}
