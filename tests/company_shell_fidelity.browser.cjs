// Actual PHP native header and Google/Microsoft views; synthetic identity/permissions, no app/provider traffic.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const output = process.env.COMPANY_SHELL_SCREENSHOT_DIR || '/tmp/company-shell-fidelity';
fs.mkdirSync(output, { recursive: true });
const render = (...flags) => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support/NativeHeaderFixture.php'), ...flags], { encoding: 'utf8' });
const company = { google: render('--company-google'), microsoft: render('--company-microsoft') };
const head = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_head.php'), 'utf8');
const assets = [...head.matchAll(/'(assets\/[^']+\.css)'/g)].map(m => m[1]).filter(a => !a.includes('company-shell') && !a.includes('company-workspace'));
const native = '<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + assets.map(a => `<link rel="stylesheet" href="/${a}">`).join('') + '<style>.btn-primary{background-color:#32c977;border-color:#32c977}</style></head><body class="admin">' + render('--full-shell') + '<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body"><button class="btn btn-primary" id="native-save">Salvar Configurações</button></div></div></main></div></body></html>';
const assertHeaderContents = async page => {
 const result=await page.evaluate(() => {
  const header=document.querySelector('#header').getBoundingClientRect();
  const selectors='#header > .hide-menu, #header > .company-menu-toggle, #logo img, .company-installation-logo img, #search_input, [data-company-menu-search], #header .navbar-right > li > a, .company-header-actions > a';
  const visible=[...document.querySelectorAll(selectors)].filter(el=>el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
  return {charset:document.characterSet,checked:visible.length,outside:visible.filter(el=>{const r=el.getBoundingClientRect();return r.top<header.top-1 || r.bottom>header.bottom+1 || r.left<header.left-1 || r.right>header.right+1;}).map(el=>el.id || el.className)};
 });
 assert.equal(result.charset,'UTF-8','native and isolated envelopes decode Portuguese correctly');
 assert.ok(result.checked>0,'header containment checks visible controls');
 assert.deepEqual(result.outside,[],'visible logo, search and header actions stay inside the header');
};
const geometry = page => page.evaluate(() => {
 const read = selector => {const el=document.querySelector(selector),s=getComputedStyle(el),r=el.getBoundingClientRect();return {position:s.position,top:s.top,left:r.left,width:s.width,height:r.height,background:s.backgroundColor,overflowX:s.overflowX,overflowY:s.overflowY,border:s.borderRightColor};};
 const h=read('#header'),m=read('#menu'),w=read('#wrapper');
 return {header:{position:h.position,height:h.height,background:h.background},menu:{position:m.position,top:m.top,left:m.left,width:m.width,height:m.height,background:m.background,overflowX:m.overflowX,overflowY:m.overflowY,border:m.border},wrapper:{background:w.background,left:w.left,top:document.querySelector('#wrapper').getBoundingClientRect().top,minHeight:getComputedStyle(document.querySelector('#wrapper')).minHeight,padding:getComputedStyle(document.querySelector('#wrapper .content')).padding}};
});
const waitForOverlay = page => page.waitForFunction(() => {
 const wrapper=document.querySelector('#wrapper');
 const s=getComputedStyle(wrapper,'::after');
 return s.content !== 'none' && s.display !== 'none' && Number(s.opacity) === 1;
}, null, {polling:'raf'});
const overlay = page => page.locator('#wrapper').evaluate(el => {
 const s=getComputedStyle(el,'::after');
 return Object.fromEntries(['content','display','position','left','top','width','backgroundColor','pointerEvents','opacity','zIndex','transitionDuration'].map(key=>[key,s[key]]));
});
const assertDocumentScroll = async page => {
 await page.evaluate(()=>{document.body.style.minHeight='2200px';window.scrollTo(0,120);});
 assert.ok(await page.locator('#header').evaluate(el=>el.getBoundingClientRect().top<=-119),'header scrolls with document, not fixed');
 assert.ok(await page.locator('#menu').evaluate(el=>el.getBoundingClientRect().top<=-119),'sidebar scrolls with document, not independently fixed');
 await page.evaluate(()=>{window.scrollTo(0,0);document.body.style.minHeight='';});
};
const snapshot = page => page.locator('.company-settings-card form').evaluateAll(forms => forms.map(form => ({ action: form.getAttribute('action'), method: form.method, fields: [...form.elements].map(el => ({ tag:el.tagName, name:el.name, type:el.type, value:el.value, disabled:el.disabled, form:el.form===form })) })));
(async () => {
 const browser = await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try {
  const page = await browser.newPage(); let current=native, enhance=false; const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const u=new URL(route.request().url());
   assert.equal(u.origin,'http://fixture.test','no external requests');
   assert.equal(route.request().method(),'GET','no provider or form mutations');
   if(u.pathname==='/fixture')return route.fulfill({contentType:'text/html',body:current});
   const rel=decodeURIComponent(u.pathname).replace(/^\//,''),file=path.resolve(root,rel);
   if(!file.startsWith(root+path.sep)||!rel.startsWith('assets/')||!fs.existsSync(file))return route.abort();
   if(rel==='assets/js/company-shell.js'&&!enhance)return route.fulfill({contentType:'text/javascript',body:''});
   const ext=path.extname(file);return route.fulfill({body:fs.readFileSync(file),contentType:{'.css':'text/css','.js':'text/javascript','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2','.woff':'font/woff'}[ext]||'application/octet-stream'});
  });
  await page.setViewportSize({width:1440,height:1000});await page.goto('http://fixture.test/fixture');await page.evaluate(()=>document.fonts.ready);
  const nativeStyle=await page.locator('#native-save').evaluate(el=>{const s=getComputedStyle(el);return {background:s.backgroundColor,border:s.borderColor,font:s.fontFamily,size:s.fontSize,padding:s.padding,borderRadius:s.borderRadius};});
  const nativeLogo=await page.locator('#logo img').boundingBox();assert.ok(nativeLogo&&nativeLogo.height>0);
  await assertHeaderContents(page);
  const nativeDesktop=await geometry(page);assert.equal(nativeDesktop.header.position,'relative');assert.equal(nativeDesktop.menu.position,'absolute');
  await assertDocumentScroll(page);
  await page.screenshot({path:path.join(output,'native-desktop.png'),fullPage:true});
  await page.setViewportSize({width:390,height:900});
  // Native mobile state class is normally applied by app.js; set only presentation state, never bootstrap that global runtime.
  await page.evaluate(()=>document.body.classList.add('page-small'));
  await assertHeaderContents(page);
  const nativeMobile=await geometry(page);
  await page.screenshot({path:path.join(output,'native-mobile.png'),fullPage:true});
  await page.evaluate(()=>document.body.classList.add('show-sidebar'));
  await waitForOverlay(page);
  const nativeMobileOpen=await geometry(page);
  const nativeOverlay=await overlay(page);
  await page.screenshot({path:path.join(output,'native-mobile-menu.png'),fullPage:true});
  await page.setViewportSize({width:1440,height:1000});
  for(const [name,html] of Object.entries(company)){
   assert.ok(!html.includes('data-company-workspace'),'exercise views without workspace marker');
   current=html;enhance=false;await page.goto('http://fixture.test/fixture');const before=await snapshot(page);
   await page.evaluate(()=>{window.originalSyntheticTable=document.querySelector('#synthetic-table');window.originalSyntheticRow=document.querySelector('#synthetic-row');window.originalSyntheticForm=document.querySelector('#synthetic-table-owner');});
   enhance=true;await page.addScriptTag({url:'/assets/js/company-shell.js'});await page.evaluate(()=>document.fonts.ready);
   assert.ok(await page.evaluate(()=>document.querySelector('#synthetic-table')===window.originalSyntheticTable&&document.querySelector('#synthetic-row')===window.originalSyntheticRow&&document.querySelector('[name="synthetic_choice"]').form===window.originalSyntheticForm),'synthetic table keeps exact nodes and enclosing form');
   assert.equal(await page.locator('.table-responsive > #synthetic-table.table.table-hover').count(),1,'synthetic table gets one native responsive wrapper');
   assert.deepEqual(await snapshot(page),before,'form owners, values, action, method, disabled and CSRF remain identical');
   const button=page.locator('.company-settings-card button').filter({hasText:'Salvar credenciais próprias'}).first();
   const style=await button.evaluate(el=>{const s=getComputedStyle(el);return {background:s.backgroundColor,border:s.borderColor,font:s.fontFamily,size:s.fontSize,padding:s.padding,borderRadius:s.borderRadius};});
   assert.deepEqual(style,nativeStyle,'native save control computed styles match');
   await assertHeaderContents(page);
   assert.deepEqual(await geometry(page),nativeDesktop,'desktop header/sidebar placement, colors, scroll and content geometry match native');
   await assertDocumentScroll(page);
   assert.equal(await page.locator('.company-settings-card button.btn-danger').filter({hasText:'Revogar'}).count(),1,'destructive operation not promoted to primary');
   const logo=await page.locator('.company-installation-logo img').boundingBox();assert.equal(logo.height,nativeLogo.height,'native public logo height');
   assert.equal(await page.locator('.company-header-actions [aria-label="Calendário da empresa"]').count(),0,'denied route is not exposed');
   await page.locator('[data-company-menu-search]').fill('Clientes');assert.ok(await page.locator('#side-menu > li:not([hidden])').count()>0);await page.locator('[data-company-menu-search]').fill('');
   await page.screenshot({path:path.join(output,`${name}-desktop.png`),fullPage:true});
   await page.setViewportSize({width:390,height:900});await page.reload();
   await assertHeaderContents(page);
   assert.deepEqual(await geometry(page),nativeMobile,'mobile closed header/sidebar metrics and content geometry match native');
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile has no horizontal overflow');
   await page.locator('.company-menu-toggle').click();
   assert.deepEqual(await geometry(page),nativeMobileOpen,'opened mobile sidebar placement and wrapper match native');
   await waitForOverlay(page);
   assert.deepEqual(await overlay(page),nativeOverlay,'mobile scrim matches native presentation');
   await page.screenshot({path:path.join(output,`${name}-mobile-menu.png`),fullPage:true});
   const mobileForms=await snapshot(page);
   await page.mouse.click(300,180);
   assert.equal(await page.locator('.company-menu-toggle').getAttribute('aria-expanded'),'false','content scrim click closes menu');
   assert.deepEqual(await geometry(page),nativeMobile,'scrim dismissal restores closed placement');
   assert.deepEqual(await snapshot(page),mobileForms,'scrim dismissal preserves forms and CSRF');
   await page.screenshot({path:path.join(output,`${name}-mobile.png`),fullPage:true});
   await page.setViewportSize({width:1440,height:1000});
  }
  assert.deepEqual(errors,[]);console.log('PASS: actual Google/Microsoft isolated views match native controls/logo; responsive, scoped menu and form invariants preserved');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
