// Production PHP views and native gateway/helpers with synthetic data, NOT an authenticated CRM/DB test.
// PHP_BIN=php BB_CHECKOUT_SCREENSHOT_DIR=/tmp/bb-checkout node tests/banco_brasil_checkout_browser.cjs
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const http=require('node:http');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const output=process.env.BB_CHECKOUT_SCREENSHOT_DIR||'/tmp/bb-checkout-render';
fs.mkdirSync(output,{recursive:true});
const scenarios=['catalog','admin','admin-unreviewed','portal','portal-unpublished','portal-other-invoice'];
const rendered=Object.fromEntries(scenarios.map(s=>[s,execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'support/BancoBrasilCheckoutFixture.php'),s],{encoding:'utf8',maxBuffer:4*1024*1024})]));
// Independent PHP invocation also proves a saved active/default=1 never offers legacy checkout and is not overwritten.
execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'support/BancoBrasilCheckoutFixture.php'),'catalog','--saved-enabled'],{encoding:'utf8',maxBuffer:4*1024*1024});
const requests=[],violations=[],errors=[];
const types={'.css':'text/css','.js':'text/javascript','.woff':'font/woff','.woff2':'font/woff2','.ttf':'font/ttf','.png':'image/png','.svg':'image/svg+xml'};
const server=http.createServer((req,res)=>{
 const pathname=new URL(req.url,'http://fixture.invalid').pathname;requests.push({method:req.method,path:pathname});
 if(req.method!=='GET'){violations.push('mutating request');res.writeHead(405);return res.end();}
 if(pathname.startsWith('/fixture/')&&rendered[pathname.slice(9)]){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(rendered[pathname.slice(9)]);}
 const filename=path.resolve(root,'.'+pathname);
 if(pathname.startsWith('/assets/')&&filename.startsWith(root+path.sep+'assets'+path.sep)&&fs.existsSync(filename)&&fs.statSync(filename).isFile()){
  res.setHeader('Content-Type',types[path.extname(filename)]||'application/octet-stream');return fs.createReadStream(filename).pipe(res);
 }
 if(pathname==='/favicon.ico'){res.writeHead(204);return res.end();}
 violations.push('unexpected local path: '+pathname);res.writeHead(404);res.end();
});
(async()=>{
 let browser,page,phase='startup';
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const origin=`http://127.0.0.1:${server.address().port}`;
 try{
  browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_BIN?{executablePath:process.env.CHROMIUM_BIN}:{})});
  const context=await browser.newContext();
  await context.route('**/*',route=>{
   const req=route.request();
   if(!req.url().startsWith(origin+'/')||req.method()!=='GET'){violations.push('blocked nonfixture or nonGET request');return route.abort();}
   return route.continue();
  });
  page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  for(const width of [1280,768,390])for(const scenario of scenarios){
   phase=`${width}-${scenario}`;await page.setViewportSize({width,height:1000});
   const response=await page.goto(`${origin}/fixture/${scenario}`,{waitUntil:'networkidle'});assert.equal(response.status(),200);
   await page.evaluate(()=>document.fonts.ready);assert.equal(await page.locator('form form').count(),0,'no nested forms (PHP fixture also checks source nesting)');
   assert.equal(await page.locator('form').evaluateAll(forms=>forms.every(f=>f.method==='post'&&Boolean(f.querySelector('[name="csrf_test_name"]')))),true);
   if(scenario==='catalog'){
    await page.waitForFunction(()=>window.jQuery&&typeof jQuery.fn.tab==='function');
    await page.locator('a[href="#online_payments_banco_brasil_tab"]').click();
    const pane=page.locator('#online_payments_banco_brasil_tab');await pane.waitFor({state:'visible'});
    for(const name of ['active','default_selected'])assert.equal(await pane.locator(`input[name="settings[paymentmethod_banco_brasil_${name}]"]:checked`).inputValue(),'0');
    assert.equal(await pane.locator('input[name="settings[paymentmethod_banco_brasil_currencies]"]').inputValue(),'BRL');
    assert.equal(await page.evaluate(()=>[...document.styleSheets].some(sheet=>sheet.href&&sheet.href.endsWith('/assets/builds/vendor-admin.css')&&sheet.cssRules.length>0)),true,'actual native stylesheet parsed');
   }else if(scenario.startsWith('admin')){
    const review=page.locator('form[action="/admin/banco_brasil/preview_checkout"]');assert.equal(await review.count(),1);
    assert.equal(await review.locator('[name="origin_id"]').inputValue(),'701');
    const hide=page.locator('form[action="/admin/banco_brasil/publish_checkout"]').filter({has:page.locator('[name="enabled"][value="0"]')});
    assert.equal(await hide.count(),1,'revocation remains available without a preview or valid instrument');
    assert.equal(await hide.locator('[name="issuer_id"]').inputValue(),'41');
    assert.equal(await hide.locator('[name="origin_id"]').inputValue(),'701');
    const tableRegion=page.locator('.table-responsive').first();
    assert.equal(await tableRegion.getAttribute('tabindex'),'0','overflow region keyboard-accessible');
    await tableRegion.focus();assert.equal(await tableRegion.evaluate(el=>el===document.activeElement),true);
    if(width===390){const overflow=await tableRegion.evaluate(el=>({document:document.documentElement.scrollWidth,viewport:innerWidth,internal:el.scrollWidth,client:el.clientWidth}));assert.ok(overflow.document<=overflow.viewport+1,'table overflow stays local');}

    const publish=page.locator('form[action="/admin/banco_brasil/publish_checkout"]').filter({has:page.locator('[name="enabled"][value="1"]')});
    assert.equal(await publish.count(),scenario==='admin'?1:0,'publication needs explicitly reviewed instrument');
    if(scenario==='admin'){
     assert.equal(await publish.locator('[name="revision"]').inputValue(),'a'.repeat(64));
     assert.equal(await publish.locator('[name="issuer_id"]').inputValue(),'41');
     assert.ok((await page.locator('body').innerText()).includes('105.53'));
     assert.ok((await page.locator('body').innerText()).includes('2030-12-31'));
     assert.equal(await page.locator('#bb-checkout-reviewed-line').inputValue(),'1'.repeat(47));
     assert.equal(await page.locator('#bb-checkout-reviewed-line').getAttribute('readonly'),'');
    }
    assert.equal(await page.locator('form[action$="/issue_api"],form[action$="/settle_api"],form[action$="/cancel_api"]').count(),0,'fixture lacks financial mutation capabilities');
   }else{
    const button=page.getByRole('button',{name:'Consultar boleto Banco do Brasil',exact:true});
    assert.equal(await button.count(),scenario==='portal-unpublished'?0:1);
    const instrument=page.locator('section[aria-label="Boleto Banco do Brasil"]');
    assert.equal(await instrument.count(),scenario==='portal'?1:0,'instrument cannot bleed into another invoice or unpublished projection');
    if(scenario==='portal'){
     assert.equal(await instrument.locator('textarea').inputValue(),'1'.repeat(47));
     assert.equal(await instrument.locator('textarea').getAttribute('readonly'),'');
     assert.ok((await instrument.innerText()).includes('Homologação — sem pagamento real'));
     assert.ok((await instrument.innerText()).includes('2030-12-31'));
     await instrument.locator('textarea').focus();assert.equal(await instrument.locator('textarea').evaluate(el=>el===document.activeElement),true);
    }
   }
   const geometry=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth}));
   assert.ok(geometry.scroll<=geometry.width+1,`no document overflow ${phase}: ${JSON.stringify(geometry)}`);
   await page.screenshot({path:path.join(output,`${phase}.png`),fullPage:true});
   assert.deepEqual(violations,[]);assert.deepEqual(errors,[]);
  }
  fs.writeFileSync(path.join(output,'receipt.json'),JSON.stringify({kind:'synthetic-data-production-view-render',scenarios,widths:[1280,768,390],requests,violations,errors},null,2));
  console.log('BB checkout production-view browser PASS: 18 renders; native catalog defaults, explicit publication and invoice-scoped presentation. Backend ACL is covered separately by MySQL tests.');
 }catch(error){
  if(page){await page.screenshot({path:path.join(output,'failure-synthetic-fixture.png'),fullPage:true}).catch(()=>{});}
  fs.writeFileSync(path.join(output,'failure.json'),JSON.stringify({phase,message:String(error.message),violations,errors},null,2));throw error;
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
