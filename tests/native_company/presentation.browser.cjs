'use strict';
// Real PHP native templates, synthetic identities and dependency-negative providers; not a live authenticated app.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..'),output=process.env.NATIVE_COMPANY_SCREENSHOT_DIR||'/tmp/native-company-presentation';fs.mkdirSync(output,{recursive:true});
const php=(file,...args)=>execFileSync(process.env.PHP_BIN||'php',[path.join(root,file),...args],{encoding:'utf8'});
const contextual=php('tests/native_company/presentation.php','--render');
// The rendered PHP fixture declares this installation origin and subpath.
// Serve both native and contextual documents there; cross-origin transport must remain forbidden.
const fixtureUrl='https://crm.example.test/sub/admin/presentation-fixture';
const css=[...fs.readFileSync(path.join(root,'application/services/NativeCompanyAdminPresentation.php'),'utf8').matchAll(/'(assets\/[^']+\.css)'/g)].map(m=>m[1]);
const sharedScripts=['assets/plugins/jquery/jquery.min.js','assets/plugins/bootstrap/js/bootstrap.min.js','assets/plugins/metisMenu/metisMenu.min.js','assets/js/native-device.js','assets/js/native-admin-navigation.js'];
const native='<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+css.map(p=>`<link rel="stylesheet" href="/${p}">`).join('')+'</head><body class="app admin h-full">'+php('tests/support/NativeHeaderFixture.php','--full-shell')+'<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body">Referência nativa</div></div></main></div>'+sharedScripts.map(p=>`<script src="/${p}"></script>`).join('')+'<script>NativeAdminNavigation.init({isMobile:function(){return false;},isRTL:"false"});</script></body></html>';
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  const page=await browser.newPage();let document=native;const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url());assert.equal(url.origin,new URL(fixtureUrl).origin);assert.equal(route.request().method(),'GET');
   if(url.href===fixtureUrl)return route.fulfill({contentType:'text/html',body:document});
   const relative=url.pathname.replace(/^\/sub\//,'/').replace(/^\//,''),file=path.resolve(root,relative);
   if(!relative.startsWith('assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.abort();
   const type={'.css':'text/css','.js':'text/javascript','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2','.woff':'font/woff'}[path.extname(file)]||'application/octet-stream';return route.fulfill({body:fs.readFileSync(file),contentType:type,headers:{'Access-Control-Allow-Origin':'*'}});
  });
  async function dimensions(){return page.evaluate(()=>{const header=document.querySelector('#header').getBoundingClientRect(),menu=document.querySelector('#menu').getBoundingClientRect();return {height:header.height,width:menu.width,padding:getComputedStyle(document.querySelector('#wrapper .content')).padding};});}
  for(const width of [1280,768,390]){
   await page.setViewportSize({width,height:900});document=native;await page.goto(fixtureUrl);await page.evaluate(()=>document.fonts.ready);await page.evaluate(w=>document.body.classList.toggle('page-small',w<769),width);const expected=await dimensions();await page.screenshot({path:path.join(output,`native-${width}.png`),fullPage:true});
   document=contextual;await page.goto(fixtureUrl);assert.equal(await page.evaluate(()=>new URL(NativeCompanyHeaderConfig.root).origin===location.origin&&location.pathname.startsWith(new URL(NativeCompanyHeaderConfig.root).pathname)),true,'actual document and captured admin root share installation origin/subpath');await page.evaluate(()=>document.fonts.ready);assert.deepEqual(await dimensions(),expected,'same native header/menu geometry and content padding');
   assert.equal(await page.locator('#header > .hide-menu').count(),1);assert.equal(await page.locator('#menu details').count(),0,'native menu rather than parallel details catalog');
   assert.equal(await page.locator('#search_input').isDisabled(),true,'missing search adapter is not advertised as functional');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'no page overflow');
   const value=await page.locator('#native-field').inputValue();
   if(width<769){await page.locator('#header > .hide-menu').click();await page.waitForFunction(()=>document.body.classList.contains('show-sidebar'));await page.keyboard.press('Escape');assert.equal(await page.locator('#header > .hide-menu').getAttribute('aria-expanded'),'false');}
   else {await page.locator('#side-menu .menu-item-sales > a').click();assert.equal(await page.locator('#side-menu .menu-item-sales > a').getAttribute('aria-expanded'),'true');}
   assert.equal(await page.locator('#native-field').inputValue(),value,'navigation leaves form intact');
   await page.screenshot({path:path.join(output,`company-${width}.png`),fullPage:true});
  }
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'receipt.json'),JSON.stringify({kind:'synthetic-provider-real-native-template',widths:[1280,768,390],errors,limitations:['search/timers/notifications need scoped providers','no authenticated application or database exercised']},null,2));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
