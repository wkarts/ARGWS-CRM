'use strict';
// Local presentation fixture: real browser + real assets, no CRM session/database/provider.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'..');
// Render the production view, not a hand-written imitation of its markup or script.
// Only framework/view dependencies are stubbed: no session, DB, hooks or external API.
const renderCode = String.raw`
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
define('BASEPATH', $argv[1]); define('APPPATH', $argv[1].'/application/');
function base_url($path=''){return '/'.ltrim($path,'/');}
function form_open($url){return '<form method="post" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="csrf_test_name" value="next-csrf">';}
function form_close(){return '</form>';}
class CompanyRequestScope {
 public static function token(){return 'old-tab-token';}
 public static function adminUrl($ci,$path=''){return '/admin/'.ltrim($path,'/');}
}
$view=new class {
 public $security;
 public function render($step,$edit=false){
  $this->security=new class {
   public function get_csrf_token_name(){return 'csrf_test_name';}
   public function get_csrf_hash(){return 'next-csrf';}
  };
  $draft=['draft_key'=>'fixture-only','step'=>$step,'revision'=>1,'payload'=>[]];
  $error=null; $company_onboarding_csrf='wizard-fixture-token';
  $selected=['id'=>41];$identity_values=['legal_name'=>'Existing fixture company','tax_id'=>'12345678000195','profile'=>['invoice_company_city'=>'Fixture city']];$identity_revision=str_repeat('b',64);$issuers_csrf=str_repeat('a',64);$notice=null;
  ob_start(); require APPPATH.'views/admin/companies/'.($edit?'identity_modal':'onboarding').'.php'; return ob_get_clean();
 }
};
echo $view->render((int)$argv[2],($argv[3]??'')==='edit');`;
const renderWizard = step => execFileSync(process.env.PHP_BINARY || 'php', ['-r',renderCode,root,String(step)], {encoding:'utf8'});
const wizardHtml = renderWizard(1), completedHtml = renderWizard(6);
const editHtml=execFileSync(process.env.PHP_BINARY||'php',['-r',renderCode,root,'1','edit'],{encoding:'utf8'});
assert.match(wizardHtml, /data-wizard-progress>Etapa 1 de 6 /, 'progress counts all six stages shown in navigation');
const nativeAssets = ['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/plugins/bootstrap/css/bootstrap.min.css','assets/builds/tailwind.css','assets/css/style.min.css','assets/css/company-registry.css'];
for (const rendered of [wizardHtml, completedHtml, editHtml]) {
 const assets = Array.from(rendered.matchAll(/<link rel="stylesheet" href="([^"]+)"/g), match=>match[1]);
 assert.deepEqual(assets.slice(0,nativeAssets.length),nativeAssets.map(asset=>'/'+asset),'wizard uses native CSS in native order');
 assert.doesNotMatch(rendered, /assets\/js\/(?:main|app)(?:\.min)?\.js/, 'wizard must not hydrate the global admin shell');
}
const nativeLinks = nativeAssets.map(asset=>`<link rel="stylesheet" href="/${asset}">`).join('');
const rows = Array.from({length:27},(_,i)=>`<tr><td>Empresa ${i+1}</td><td>Documento ${i+1}</td><td>Ativa</td><td>—</td></tr>`).join('');
let html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">${nativeLinks}</head><body class="admin"><button class="btn btn-primary" data-native-reference>Referência nativa</button><div data-company-registry class="company-registry"><button data-new-company>Nova empresa</button><label>Buscar<input id="company-grid-search"></label><select data-page-size><option>10</option><option>25</option></select><table><thead><tr>${['Empresa','Documento','Situação','Contexto'].map((x,i)=>`<th><button data-sort="${i}">${x}</button></th>`).join('')}</tr></thead><tbody>${rows}</tbody></table><p data-company-empty hidden>Vazio</p><p data-company-count></p><button data-page-prev>Anterior</button><button data-page-next>Próxima</button><div class="modal fade" id="company-create-modal" data-open-on-load="0" tabindex="-1" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content"><button data-dismiss="modal">Fechar</button><iframe name="company-create-frame" src="about:blank"></iframe></div></div></div><form id="company-wizard-open" method="post" target="company-create-frame" action="/wizard" hidden><input name="action" value="show"><input name="_company_context" value="old-tab-token"><input name="csrf_test_name" value="first-csrf"></form></div><script src="/assets/plugins/jquery/jquery.min.js"></script><script src="/assets/plugins/bootstrap/js/bootstrap.min.js"></script><script src="/assets/js/company-registry.js"></script></body></html>`;
html=html.replace('</body>','<a class="btn btn-default" data-edit-company href="/edit-document">Editar empresa existente</a><div class="modal fade" id="company-edit-modal" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body"><iframe name="company-edit-frame" title="Editar empresa existente" src="about:blank"></iframe></div></div></div></div><script src="/assets/js/company-identity-modal.js"></script></body>');
// Wait for both independent lifecycles: the new iframe document and Bootstrap's
// shown event. A visible heading can still belong to the previous document, and
// Bootstrap focuses the parent modal at the end of its opening transition.
async function openWizard(page) {
 await page.evaluate(() => {
  window.registryModalShown = false;
  window.jQuery('#company-create-modal').one('shown.bs.modal', () => { window.registryModalShown = true; });
 });
 const frame = page.frame({name:'company-create-frame'});
 await Promise.all([
  frame.waitForNavigation({waitUntil:'load'}),
  page.locator('[data-new-company]').click(),
 ]);
 await page.waitForFunction(() => window.registryModalShown === true);
 await frame.locator('h1').waitFor();
 return frame;
}
// Bootstrap hides the element before finishing its backdrop transition. Focus is restored
// by the application's hidden.bs.modal handler, not at the earlier display:none point.
async function dismissWizard(page, action) {
 await page.evaluate(() => {
  window.registryModalHidden = false;
  window.jQuery('#company-create-modal').one('hidden.bs.modal', () => { window.registryModalHidden = true; });
 });
 await action();
 await page.waitForFunction(() => window.registryModalHidden === true);
 await page.locator('#company-create-modal').waitFor({state:'hidden'});
 await page.frame({name:'company-create-frame'}).waitForURL('about:blank', {waitUntil:'load'});
}
let posts=[];
const server=http.createServer((req,res)=>{
 if(req.url==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
 if(req.url==='/wizard'){let body='';req.on('data',x=>body+=x);req.on('end',()=>{posts.push(Object.fromEntries(new URLSearchParams(body)));res.setHeader('Content-Type','text/html; charset=utf-8');res.end(wizardHtml);});return;}
 if(req.url==='/edit-document'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(editHtml);return;}
 if(req.url==='/wizard-completed'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(completedHtml);return;}
 const file=path.resolve(root,'.'+new URL(req.url,'http://fixture.local').pathname);if(!file.startsWith(root+'/assets/')){res.writeHead(404);res.end();return;}
 fs.readFile(file,(err,data)=>{if(err){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.css')?'text/css; charset=utf-8':file.endsWith('.woff2')?'font/woff2':file.endsWith('.woff')?'font/woff':'application/javascript; charset=utf-8');res.end(data);});
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const url='http://127.0.0.1:'+server.address().port;
 const browser=await chromium.launch({headless:true});
 let page;
 try{
  page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(url);assert.equal(await page.locator('tbody tr:visible').count(),10);
  assert.equal(await page.locator('[data-page-prev]').isDisabled(),true);
  await page.locator('[data-page-next]').click();assert.match(await page.locator('[data-company-count]').innerText(),/^11–20/);
  await page.locator('#company-grid-search').fill('Empresa 27');assert.equal(await page.locator('tbody tr:visible').count(),1);
  await page.locator('#company-grid-search').fill('absent');assert.equal(await page.locator('tbody tr:visible').count(),0);assert.equal(await page.locator('[data-company-empty]').isVisible(),true);
  await page.locator('#company-grid-search').fill('');await page.locator('[data-sort="0"]').click();assert.equal(await page.locator('tbody tr:visible').first().locator('td').first().innerText(),'Empresa 27');
  let frame=await openWizard(page);
  assert.deepEqual(posts[0],{action:'show',_company_context:'old-tab-token',csrf_test_name:'first-csrf'});
  await dismissWizard(page, () => frame.locator('[data-wizard-close]').click());
  assert.equal(await page.locator('[data-new-company]').evaluate(el=>el===document.activeElement),true);
  frame=await openWizard(page);
  assert.equal(posts[1].csrf_test_name,'next-csrf');assert.equal(posts[1]._company_context,'old-tab-token','CSRF refresh must never replace captured company token');
  await page.evaluate(()=>window.postMessage({type:'company-onboarding-close'},location.origin));assert.equal(await page.locator('#company-create-modal').isVisible(),true,'messages outside the iframe are ignored');
  const assertKeyboardActions = async () => {
   await frame.locator('#profile_company_state_registration').press('Tab');
   const submit=frame.locator('button[type="submit"]'), close=frame.locator('[data-wizard-close]');
   assert.equal(await submit.evaluate(el=>el===document.activeElement),true,'keyboard reaches submit inside iframe');
   await submit.press('Tab');assert.equal(await close.evaluate(el=>el===document.activeElement),true,'keyboard reaches exit without submitting');
   for(const control of [submit,close])assert.equal(await control.evaluate(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1&&r.left>=0&&r.right<=innerWidth+1;}),true,'iframe scroll reveals whole action controls');
  };
  await assertKeyboardActions();
  await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
  await frame.waitForFunction(() => innerWidth < 768);
  await frame.evaluate(() => new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  await Promise.all([page.evaluate(()=>document.fonts.ready),frame.evaluate(()=>document.fonts.ready)]);
  assert.equal(await frame.evaluate(()=>document.fonts.check('13.5px Inter')),true,'native font asset is loaded');
  const nativeSkin = await page.locator('[data-native-reference]').evaluate(button=>({font:getComputedStyle(document.body).fontFamily,color:getComputedStyle(document.body).color,background:getComputedStyle(button).backgroundColor,border:getComputedStyle(button).borderColor,radius:getComputedStyle(button).borderRadius}));
  const wizardSkin = await frame.locator('button[type="submit"]').evaluate(button=>({font:getComputedStyle(document.body).fontFamily,color:getComputedStyle(document.body).color,background:getComputedStyle(button).backgroundColor,border:getComputedStyle(button).borderColor,radius:getComputedStyle(button).borderRadius}));
  assert.deepEqual(wizardSkin,nativeSkin,'iframe uses the actual native typography, foreground and primary button skin');
  assert.equal(await frame.locator('.company-onboarding-steps.nav.nav-tabs li').count(),6,'all six steps remain present');
  assert.match(await frame.locator('[data-wizard-progress]').innerText(), /^Etapa 1 de 6 /, 'visible progress agrees with navigation');
  const compact = await frame.evaluate(() => {
   const steps=document.querySelector('.company-onboarding-steps'), sections=document.querySelector('.company-identity-sections');
   const row=element=>Array.from(element.children).every(item=>Math.abs(item.getBoundingClientRect().top-element.firstElementChild.getBoundingClientRect().top)<1);
   return {stepsRow:row(steps),sectionsRow:row(sections),stepsHeight:steps.clientHeight,sectionsHeight:sections.clientHeight,stepsScroll:steps.scrollWidth>steps.clientWidth,stepsKeyboard:steps.tabIndex,sectionsKeyboard:sections.tabIndex,width:document.documentElement.scrollWidth,viewport:innerWidth};
  });
  assert.equal(compact.stepsRow,true);assert.equal(compact.sectionsRow,true);
  assert.ok(compact.stepsHeight<80 && compact.sectionsHeight<65,'mobile navigation stays compact rather than three rows');
  assert.equal(compact.stepsScroll,true,'steps scroll horizontally instead of disappearing');
  assert.equal(compact.stepsKeyboard,0);assert.equal(compact.sectionsKeyboard,0);
  assert.ok(compact.width<=compact.viewport+1,'iframe has no page-wide horizontal overflow');
  await frame.getByRole('tab',{name:'2. Endereço',exact:true}).click();
  assert.equal(await frame.getByLabel('CEP',{exact:true}).isVisible(),true,'address fields remain reachable');
  await frame.getByRole('tab',{name:'3. Contato',exact:true}).click();
  assert.equal(await frame.getByLabel('Telefone',{exact:true}).isVisible(),true,'contact fields remain reachable');
  await frame.getByRole('tab',{name:'1. Identificação',exact:true}).click();
  assert.equal(await frame.getByLabel('CPF / CNPJ',{exact:true}).isVisible(),true);
  assert.equal(await frame.getByLabel('Razão social / nome',{exact:true}).isVisible(),true);
  await assertKeyboardActions();
  await frame.locator('#legal_name').focus();await dismissWizard(page, () => frame.locator('#legal_name').press('Escape'));
  assert.equal(await page.locator('[data-new-company]').evaluate(el=>el===document.activeElement),true,'Escape returns focus after Bootstrap dismissal completes');
  // Existing editor renders the real shared fields, never the creation wizard.
  const mutationsBeforeEdit=posts.length;
  for(const width of [1280,768,390]){
   await page.setViewportSize({width,height:900});
   await page.evaluate(()=>{window.editShown=false;jQuery('#company-edit-modal').one('shown.bs.modal',()=>{window.editShown=true;});});
   const editor=page.frame({name:'company-edit-frame'});
   await Promise.all([editor.waitForNavigation({waitUntil:'load'}),page.locator('[data-edit-company]').click()]);
   await page.waitForFunction(()=>window.editShown);
   await editor.getByRole('heading',{name:'Editar empresa',exact:true}).waitFor();
   assert.equal(await editor.locator('#legal_name').inputValue(),'Existing fixture company');
   assert.equal(await editor.locator('[name=issuer_id]').inputValue(),'41');
   assert.equal(await editor.locator('[name=action]').inputValue(),'edit_identity');
   assert.equal(await editor.locator('[name=identity_revision]').inputValue(),'b'.repeat(64));
   const before=await editor.locator('form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries())));
   await editor.getByRole('tab',{name:'2. Endereço',exact:true}).click();
   assert.equal(await editor.getByLabel('Cidade',{exact:true}).inputValue(),'Fixture city');
   await editor.getByRole('tab',{name:'3. Contato',exact:true}).click();
   assert.equal(await editor.getByLabel('Telefone',{exact:true}).isVisible(),true);
   assert.equal(await editor.locator('form').evaluate(form=>JSON.stringify(Array.from(new FormData(form).entries()))),before);
   assert.equal(await editor.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'editor has no page overflow');
   const output=process.env.SCREENSHOT_DIR||path.join(root,'artifacts/company-native-ui');fs.mkdirSync(output,{recursive:true});
   await page.screenshot({path:path.join(output,'company-edit-modal-'+width+'.png'),fullPage:true});
   await page.evaluate(()=>{window.editHidden=false;jQuery('#company-edit-modal').one('hidden.bs.modal',()=>{window.editHidden=true;});});
   await editor.locator('[data-company-edit-close]').press('Escape');
   await page.waitForFunction(()=>window.editHidden);
   assert.equal(await page.locator('[data-edit-company]').evaluate(el=>el===document.activeElement),true,'editing Escape restores exact opener');
  }
  assert.equal(posts.length,mutationsBeforeEdit,'opening/navigating/closing editor never creates or saves');
  const completed=await browser.newPage({viewport:{width:368,height:550}});
  completed.on('pageerror',error=>errors.push(error.message));
  try {
   await completed.goto(url+'/wizard-completed',{waitUntil:'load'});
   await completed.evaluate(()=>document.fonts.ready);
   const active=await completed.locator('.company-onboarding-steps').evaluate(nav=>{
    const step=nav.querySelector('[aria-current="step"]'), box=nav.getBoundingClientRect(), item=step.getBoundingClientRect();
    return {label:step.textContent,left:item.left,right:item.right,min:box.left,max:box.right,scroll:nav.scrollLeft,pageX:scrollX,pageY:scrollY};
   });
   assert.equal(active.label,'6. Conclusão');assert.ok(active.scroll>0,'last step is revealed inside its own scroll container');
   assert.ok(active.left>=active.min-1 && active.right<=active.max+1,'active completion step stays visible');
   assert.equal(active.pageX,0);assert.equal(active.pageY,0,'revealing the current step never scrolls the document');
   assert.equal(await completed.getByRole('button',{name:'Concluir',exact:true}).isVisible(),true);
  } finally {await completed.close();}
  assert.deepEqual(errors,[]);console.log('company registry/modal browser fixture passed: pagination, search, sort, cancel/reopen, context preservation, CSRF refresh, message isolation, mobile');
 }catch(error){
  if(page){
   const output=process.env.SCREENSHOT_DIR||path.join(root,'artifacts/company-native-ui');fs.mkdirSync(output,{recursive:true});
   await page.screenshot({path:path.join(output,'registry-modal-failure.png'),fullPage:true});
   const state=await page.evaluate(()=>({readyState:document.readyState,shown:window.registryModalShown,hidden:window.registryModalHidden,activeElement:document.activeElement?.outerHTML,modalClass:document.querySelector('#company-create-modal').className,isShown:window.jQuery('#company-create-modal').data('bs.modal')?.isShown}));
   state.frames=await Promise.all(page.frames().map(async frame=>({url:frame.url(),state:await frame.evaluate(()=>({readyState:document.readyState,activeElement:document.activeElement?.tagName})).catch(()=>null)})));
   fs.writeFileSync(path.join(output,'registry-modal-failure.json'),JSON.stringify(state,null,2));
  }
  throw error;
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
