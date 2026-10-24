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
  for(const order of ['native-before-insertion','native-ready-after-insertion']) for(const scenario of [
   {width:1280,column:null,font:16}, {width:390,column:358,font:16},
   {width:390,column:280,font:16}, {width:390,column:358,font:18}
  ]) {
   const {width,column,font}=scenario; const caseName=width+'-'+(column||'wide')+'-font'+font+'-'+order;
   const context=await browser.newContext({viewport:{width,height:900}});
   const url='https://crm.example.test/admin/settings?group=payment_gateways';
   const script='var isRTL="false";'+nativeInit[0]+';fixture("1","1",true);bindNativeSettings();'+
    (order==='native-before-insertion'?'init_tabs_scrollable();':'jQuery(init_tabs_scrollable);')+'module.exports.initialize(document);';
   // Reproduce the real 358px viewport as well as the old narrow 280px case.
   // Font scaling exercises native rem padding; no forced click or scrollLeft.
   const fixture=html(script).replace('</head>','<style>html{font-size:'+font+'px}'+(column?'#online_payments_asaas_tab > .horizontal-scrollable-tabs{width:'+column+'px}':'')+'</style></head>');
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
    phase='native padding geometry';
    const spacing=await pane.evaluate(el=>{
      const wrapper=el.querySelector('.horizontal-scrollable-tabs'), nav=wrapper.querySelector('.nav-tabs-horizontal');
      // Disposable offscreen clone restores the original native box model. It
      // has no handlers or form controls and never changes the real scrollLeft.
      const clone=wrapper.cloneNode(true);clone.style.position='absolute';clone.style.left='-10000px';clone.style.width=wrapper.getBoundingClientRect().width+'px';
      clone.querySelectorAll('[id]').forEach(item=>item.removeAttribute('id'));
      const old=clone.querySelector('.nav-tabs-horizontal');old.style.paddingLeft='';old.style.paddingRight='';old.firstElementChild.style.paddingLeft='';old.lastElementChild.style.paddingRight='';
      clone.querySelectorAll('.scroller').forEach(arrow=>arrow.style.display='none');
      wrapper.parentNode.appendChild(clone);
      const oldStyle=getComputedStyle(old), edge=parseFloat(oldStyle.paddingLeft)+parseFloat(oldStyle.paddingRight);
      const sum=list=>Array.from(list.children).reduce((n,item)=>n+item.getBoundingClientRect().width,0);
      const result={before:sum(old)+edge,after:sum(nav),edge,beforeItems:sum(old),wrapper:wrapper.getBoundingClientRect().width,
       oldLinks:Array.from(old.querySelectorAll('a'),a=>a.getBoundingClientRect().width),links:Array.from(nav.querySelectorAll('a'),a=>a.getBoundingClientRect().width)};
      clone.remove();return result;
    });
    assert.ok(spacing.edge>0,'actual native horizontal padding exists');
    assert.ok(Math.abs(spacing.before-spacing.after)<1,'native total spacing is preserved');
    spacing.links.forEach((size,index)=>assert.ok(Math.abs(size-spacing.oldLinks[index])<1,'link hit area is unchanged'));
    if(column===358&&font===16) {
      assert.ok(spacing.beforeItems<=spacing.wrapper,'real threshold: item widths alone fit');
      assert.ok(spacing.before>spacing.wrapper,'real threshold: native padding creates overflow');
    }
    phase='single native binding';
    for(const direction of ['left','right']) assert.equal(await pane.locator('.arrow-'+direction).evaluate(el=>(jQuery._data(el,'events')?.click||[]).filter(handler=>handler.namespace==='horizontalTabs').length),1,order+': unique native '+direction+' handler');
    if(width===390) {
    assert.ok(await nav.evaluate(el=>el.scrollWidth>el.clientWidth),'actual narrow-column overflow');
    phase='arrows visible at real overflow';await pane.locator('.arrow-right').waitFor({state:'visible'});await pane.locator('.arrow-left').waitFor({state:'visible'});
    phase='right click';await pane.locator('.arrow-right').click();
    phase='right movement';await page.waitForFunction(()=>document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft>0,null,{timeout:10000});
    phase='left click';await pane.locator('.arrow-left').click();
    phase='left movement';await page.waitForFunction(()=>document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal').scrollLeft===0,null,{timeout:10000});
    } else {
      assert.ok(await nav.evaluate(el=>el.scrollWidth<=el.clientWidth),'desktop tabs fit without overflow');
      assert.equal(await pane.locator('.arrow-right').isVisible(),false,'desktop right arrow hidden');
      assert.equal(await pane.locator('.arrow-left').isVisible(),false,'desktop left arrow hidden');
    }
    for(const section of ['authentication','general']) {
      await pane.locator('#asaas-settings-tab-'+section).click();
      await page.evaluate(()=>jQuery(window).trigger('resize'));
      // Wait for the native debounced resize result, not a fixed sleep.
      await page.waitForFunction(()=>{
        const wrapper=document.querySelector('#online_payments_asaas_tab .horizontal-scrollable-tabs');
        const list=wrapper.querySelector('.nav-tabs-horizontal'), overflow=list.scrollWidth>list.clientWidth;
        return ['left','right'].every(direction=>jQuery(wrapper.querySelector('.arrow-'+direction)).is(':visible')===overflow);
      },null,{timeout:10000});
      const overflowing=await nav.evaluate(el=>el.scrollWidth>el.clientWidth);
      assert.equal(await pane.locator('.arrow-right').isVisible(),overflowing,'arrow visibility matches geometry after '+section+' resize');
      assert.equal(await pane.locator('.arrow-left').isVisible(),overflowing,'left visibility matches geometry after '+section+' resize');
    }
    assert.equal(await page.locator('#settings-form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries()))),baseline);
    assert.equal(await page.locator('#asaas-operation-environment').inputValue(),environment);
    await page.evaluate(()=>module.exports.initialize(document));
    assert.equal(await pane.locator('.arrow-right').evaluate(el=>(jQuery._data(el,'events')?.click||[]).filter(handler=>handler.namespace==='horizontalTabs').length),1,'idempotent module retains one native handler');
    await page.screenshot({path:path.join(output,'native-scroll-'+caseName+'.png'),fullPage:true});
    assert.deepEqual(errors,[]);console.log('Native Asaas scroll passed: '+width+' '+order);
   }catch(error){
    const geometry=await page.evaluate(()=>{const nav=document.querySelector('#online_payments_asaas_tab .nav-tabs-horizontal');return nav?{clientWidth:nav.clientWidth,scrollWidth:nav.scrollWidth,scrollLeft:nav.scrollLeft}:null;});
    console.error(JSON.stringify({test:'asaas-native-scroll',order,width,column,font,phase,geometry}));
    await page.screenshot({path:path.join(output,'native-scroll-'+caseName+'-failure.png'),fullPage:true}).catch(()=>{});throw error;
   }finally{await context.close();}
  }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
