'use strict';
// Focused synthetic document, actual Asaas-generated DOM and unmodified native
// init_tabs_scrollable/horizontalTabs implementations. No CRM/provider requests.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require(process.env.ASAAS_PLAYWRIGHT_MODULE||'playwright');
const {html}=require('./support/AsaasSettingsFixture.cjs');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/main.js'),'utf8');
const nativeInit=source.match(/^function init_tabs_scrollable\(\) \{[\s\S]*?^\}/m);
assert.ok(nativeInit,'actual native initialization function available');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_BIN?{executablePath:process.env.CHROMIUM_BIN}:{})});
 const output=path.resolve(process.env.ASAAS_UI_ARTIFACTS||'artifacts/asaas-settings');fs.mkdirSync(output,{recursive:true});
 try {
  for(const order of ['native-before-insertion','native-ready-after-insertion']) for(const width of [1280,390]) {
   const context=await browser.newContext({viewport:{width,height:900}});
   const url='https://crm.example.test/admin/settings?group=payment_gateways';
   const script='var isRTL="false";'+nativeInit[0]+';fixture("1","1",true);bindNativeSettings();'+
    (order==='native-before-insertion'?'init_tabs_scrollable();':'jQuery(init_tabs_scrollable);')+'module.exports.initialize(document);';
   // A narrow settings column at the real 390px mobile viewport exercises
   // genuine overflow; no forced click, mocked plugin, or assigned scrollLeft.
   const fixture=width===390?html(script).replace('</head>','<style>#settings-form{max-width:280px}</style></head>'):html(script);
   await context.route('**/*',route=>route.request().isNavigationRequest()&&route.request().url()===url?route.fulfill({contentType:'text/html',body:fixture}):route.abort());
   const page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
   let phase='load';
   try {
    await page.goto(url);await page.evaluate(()=>document.fonts.ready);
    const pane=page.locator('#online_payments_asaas_tab'),nav=pane.locator('.nav-tabs-horizontal');
    assert.equal(await pane.isVisible(),false,'gateway initially hidden during native plugin initialization');
    phase='await native handler while hidden';
    await page.waitForFunction(()=>{const arrow=document.querySelector('#online_payments_asaas_tab .arrow-right');return (jQuery._data(arrow,'events')?.click||[]).some(handler=>handler.namespace==='horizontalTabs');},null,{timeout:10000});
    await page.locator('#gateway-tab').click();
    const baseline=await page.locator('#settings-form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries())));
    const environment=await page.locator('#asaas-operation-environment').inputValue();
    phase='single native binding';
    for(const direction of ['left','right']) assert.equal(await pane.locator('.arrow-'+direction).evaluate(el=>(jQuery._data(el,'events')?.click||[]).filter(handler=>handler.namespace==='horizontalTabs').length),1,order+': unique native '+direction+' handler');
    if(width===390) {
    assert.ok(await nav.evaluate(el=>el.scrollWidth>el.clientWidth),'actual narrow-column overflow');
    phase='right click';await pane.locator('.arrow-right').click();
    phase='right movement';await page.waitForFunction(()=>document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft>0,null,{timeout:10000});
    phase='left click';await pane.locator('.arrow-left').click();
    phase='left movement';await page.waitForFunction(()=>document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft===0,null,{timeout:10000});
    } else {
      assert.ok(await nav.evaluate(el=>el.scrollWidth<=el.clientWidth),'desktop tabs fit without overflow');
      assert.equal(await pane.locator('.arrow-right').isVisible(),false,'desktop right arrow hidden');
      assert.equal(await pane.locator('.arrow-left').isVisible(),false,'desktop left arrow hidden');
    }
    assert.equal(await page.locator('#settings-form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries()))),baseline);
    assert.equal(await page.locator('#asaas-operation-environment').inputValue(),environment);
    await page.evaluate(()=>module.exports.initialize(document));
    assert.equal(await pane.locator('.arrow-right').evaluate(el=>(jQuery._data(el,'events')?.click||[]).filter(handler=>handler.namespace==='horizontalTabs').length),1,'idempotent module retains one native handler');
    await page.screenshot({path:path.join(output,'native-scroll-'+width+'-'+order+'.png'),fullPage:true});
    assert.deepEqual(errors,[]);console.log('Native Asaas scroll passed: '+width+' '+order);
   }catch(error){
    const geometry=await page.evaluate(()=>{const nav=document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal');return nav?{clientWidth:nav.clientWidth,scrollWidth:nav.scrollWidth,scrollLeft:nav.scrollLeft}:null;});
    console.error(JSON.stringify({test:'asaas-native-scroll',order,width,phase,geometry}));
    await page.screenshot({path:path.join(output,'native-scroll-'+width+'-'+order+'-failure.png'),fullPage:true}).catch(()=>{});throw error;
   }finally{await context.close();}
  }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
