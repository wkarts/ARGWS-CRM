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
   assert.equal(await page.evaluate(()=>{custom_fields_hyperlink();return typeof cfh_popover_templates==='object';}),true,'original contact helper and template catalog are available in the native shell');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'no page overflow');
   const value=await page.locator('#native-field').inputValue();
   if(width<769){await page.locator('#header > .hide-menu').click();await page.waitForFunction(()=>document.body.classList.contains('show-sidebar'));await page.keyboard.press('Escape');assert.equal(await page.locator('#header > .hide-menu').getAttribute('aria-expanded'),'false');}
   else {await page.locator('#side-menu .menu-item-sales > a').click();assert.equal(await page.locator('#side-menu .menu-item-sales > a').getAttribute('aria-expanded'),'true');}
   assert.equal(await page.locator('#native-field').inputValue(),value,'navigation leaves form intact');
   await page.screenshot({path:path.join(output,`company-${width}.png`),fullPage:true});
  }
  // Actual PHP-emitted hyperlink editor, including already persisted markup.
  // The wrapper uses attribute setters; only the production helper parses it.
  const hostileLabel='Field <img src=x onerror="window.linkExecuted=true">';
  await page.evaluate(label=>{
   const wrapper=document.createElement('div');wrapper.className='cf-hyperlink';wrapper.id='literal-link-fixture';
   wrapper.setAttribute('data-fieldto','contacts');wrapper.setAttribute('data-field-id','987');wrapper.setAttribute('data-field-name',label);
   wrapper.setAttribute('data-value','<a href="https://example.invalid/"><img src=x onerror="window.linkExecuted=true">Original title</a>');
   wrapper.innerHTML='<a id="custom_fields_contacts_987_popover" href="#">Tip</a><input type="hidden" id="custom_fields[contacts][987]" name="custom_fields[contacts][987]">';
   cfh_popover_templates['987']='<div><input id="custom_fields_contacts_987_title"><input id="custom_fields_contacts_987_link"><a id="cf_hyperlink_open_987">Open</a><button type="button" id="custom_fields_contacts_987_btn-save">Save</button><button type="button" id="custom_fields_contacts_987_btn-cancel">Cancel</button></div>';
   document.body.append(wrapper);custom_fields_hyperlink();
  },hostileLabel);
  assert.equal(await page.locator('#custom_fields_contacts_987_popover').textContent(),'Original title','stored link HTML contributes text only');
  assert.equal(await page.locator('#literal-link-fixture img').count(),0);
  await page.evaluate(()=>$('#custom_fields_contacts_987_popover').trigger('click'));
  assert.equal(await page.locator('.popover-title').textContent(),hostileLabel,'popover title treats the definition as literal text');
  assert.equal(await page.locator('.popover img').count(),0);
  const literalTitle='<svg onload="window.linkExecuted=true"> & "quote"';
  const literalUrl='https://example.invalid/?q="/><img src=x onerror="window.linkExecuted=true">';
  await page.locator('#custom_fields_contacts_987_title').fill(literalTitle);
  await page.locator('#custom_fields_contacts_987_link').fill(literalUrl);
  await page.evaluate(()=>$('#custom_fields_contacts_987_btn-save').trigger('click'));
  const stored=await page.locator('[name="custom_fields[contacts][987]"]').inputValue();
  assert.deepEqual(await page.evaluate(value=>{const template=document.createElement('template');template.innerHTML=value;const a=template.content.querySelector('a');return{href:a.getAttribute('href'),title:a.textContent,unsafe:template.content.querySelectorAll('img,svg,script,[onerror],[onload]').length};},stored),{href:literalUrl,title:literalTitle,unsafe:0},'editing serializes text and URL through DOM attributes without active markup');
  assert.equal(await page.locator('#custom_fields_contacts_987_popover').textContent(),literalTitle);
  await page.evaluate(()=>$('#custom_fields_contacts_987_popover').trigger('click'));
  await page.locator('#custom_fields_contacts_987_link').fill('javascript:window.linkExecuted=true');
  await page.evaluate(()=>$('#custom_fields_contacts_987_btn-save').trigger('click'));
  assert.equal(await page.locator('[name="custom_fields[contacts][987]"]').inputValue(),stored,'unsupported executable scheme cannot replace the stored value');
  assert.equal(await page.locator('#custom_fields_contacts_987_link').evaluate(node=>node.validity.customError),true);
  await page.evaluate(()=>$('#custom_fields_contacts_987_btn-cancel').trigger('click'));
  assert.equal(await page.evaluate(()=>Boolean(window.linkExecuted)),false);
  // Repeated custom-field identities are expected in original document rows.
  // Open both editors together: only the second owning row may be changed.
  await page.evaluate(()=>{
   cfh_popover_templates['988']='<div><input id="custom_fields_items_988_title"><input id="custom_fields_items_988_link"><a id="cf_hyperlink_open_988">Open</a><button type="button" id="custom_fields_items_988_btn-save">Save</button><button type="button" id="custom_fields_items_988_btn-cancel">Cancel</button></div>';
   for(const index of [1,2]){
    const wrapper=document.createElement('div');wrapper.className='cf-hyperlink';wrapper.id='item-link-row-'+index;
    wrapper.setAttribute('data-fieldto','items');wrapper.setAttribute('data-field-id','988');wrapper.setAttribute('data-field-name','Item link');
    wrapper.setAttribute('data-value','<a href="https://example.invalid/'+index+'">Row '+index+'</a>');
    wrapper.innerHTML='<a id="custom_fields_items_988_popover" href="#">Tip</a><input type="hidden" id="custom_fields[items][988]" data-fieldid="988" data-fieldto="items" name="newitems['+index+'][custom_fields][items][988]">';
    document.body.append(wrapper);
   }
   custom_fields_hyperlink();
  });
  const firstStored=await page.locator('#item-link-row-1 > input').inputValue();
  await page.locator('#item-link-row-1 > a').click();
  await page.locator('#item-link-row-2 > a').click();
  const secondPopover=page.locator('#'+await page.locator('#item-link-row-2 > a').getAttribute('aria-describedby'));
  assert.equal(await secondPopover.locator('[id="custom_fields_items_988_title"]').inputValue(),'Row 2');
  await secondPopover.locator('[id="custom_fields_items_988_title"]').fill('Second edited');
  await secondPopover.locator('[id="custom_fields_items_988_link"]').fill('https://example.invalid/second-edited');
  await secondPopover.locator('[id="custom_fields_items_988_btn-save"]').click();
  assert.equal(await page.locator('#item-link-row-1 > input').inputValue(),firstStored,'a repeated field ID cannot overwrite a different document row');
  assert.equal(await page.locator('#item-link-row-1 > a').textContent(),'Row 1');
  assert.match(await page.locator('#item-link-row-2 > input').inputValue(),/second-edited/);
  assert.equal(await page.locator('#item-link-row-2 > a').textContent(),'Second edited');
  const firstPopover=page.locator('#'+await page.locator('#item-link-row-1 > a').getAttribute('aria-describedby'));
  await firstPopover.locator('[id="custom_fields_items_988_btn-cancel"]').click();
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'receipt.json'),JSON.stringify({kind:'synthetic-provider-real-native-template',widths:[1280,768,390],custom_field_link:'literal DOM and safe transport checked',errors,limitations:['search/timers/notifications need scoped providers','no authenticated application or database exercised']},null,2));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
