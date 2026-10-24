'use strict';
// Real localhost PHP + Chromium/SW. No intercepted network, DB or production data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const {spawn} = require('node:child_process');
const {once} = require('node:events');
const {chromium} = require('playwright');
const repo = path.resolve(__dirname, '..');
const artifacts=path.resolve(process.env.PWA_BROWSER_ARTIFACTS||'artifacts/native-pwa-fixture');
fs.mkdirSync(artifacts,{recursive:true});
async function capture(page,name) {await page.screenshot({path:path.join(artifacts,name+'.png'),fullPage:true,mask:[page.locator('input,textarea,select')]});}
const files = ['pwa.php','application/helpers/app_branding_helper.php','application/config/branding.php','application/views/includes/pwa.php',
  ...['worker.js','install.js','icon-192.png','icon-512.png','icon-maskable-512.png'].map(x=>'assets/pwa/'+x)];
async function freePort() {
  const socket=net.createServer(); socket.listen(0,'127.0.0.1'); await once(socket,'listening');
  const port=socket.address().port; await new Promise(resolve=>socket.close(resolve)); return port;
}
async function emptyStores(page) {
  assert.deepEqual(await page.evaluate(()=>caches.keys()), [], 'no CacheStorage');
  assert.deepEqual(await page.evaluate(()=>indexedDB.databases()), [], 'no IndexedDB');
  assert.equal(await page.evaluate(()=>localStorage.length+sessionStorage.length),0,'no web storage');
}
(async()=>{
  assert.equal(require('playwright/package.json').version,'1.55.1');
  const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'argws-pwa-browser-'));
  let server,browser; let serverError='';
  try {
    for(const file of files) {const to=path.join(tmp,file);fs.mkdirSync(path.dirname(to),{recursive:true});fs.copyFileSync(path.join(repo,file),to);}
    const port=await freePort(), origin='http://127.0.0.1:'+port;
    server=spawn('php',['-S','127.0.0.1:'+port,path.join(__dirname,'native_pwa_router.php')],{cwd:tmp,env:{...process.env,PWA_TEST_ROOT:tmp},stdio:['ignore','ignore','pipe']});
    server.on('error',e=>{serverError=e.message;}); server.stderr.on('data',()=>{});
    let ready=false;
    for(let n=0;n<100;n++) {
      if(serverError||server.exitCode!==null) throw Error('PHP fixture unavailable: '+serverError);
      try { const r=await fetch(origin+'/pwa.php'); if(r.ok){ready=true;break;} } catch {}
      await new Promise(resolve=>setTimeout(resolve,50));
    }
    assert.ok(ready,'real PHP HTTP fixture ready (never skip)');
    browser=await chromium.launch({headless:true});
    for(const prefix of ['/','/crm/']) for(const width of [1280,390]) {
      const label='synthetic-'+(prefix==='/'?'root':'subpath')+'-'+width; let phase='load', page;
      // Context isolation prevents root-scope registration masking subpath bugs.
      const context=await browser.newContext({serviceWorkers:'allow',viewport:{width,height:900}});
      try {
        page=await context.newPage();const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        const url=origin+prefix+'admin';
        await page.goto(url);
        phase='manifest';
        const manifest=await page.evaluate(async()=>{
          const r=await fetch(document.querySelector('link[rel=manifest]').href);
          return {status:r.status,mime:r.headers.get('content-type'),cache:r.headers.get('cache-control'),data:await r.json()};
        });
        assert.equal(manifest.status,200);assert.match(manifest.mime,/application\/manifest\+json/);assert.match(manifest.cache,/no-store/);
        assert.equal(manifest.data.scope,prefix);assert.equal(manifest.data.id,prefix);assert.equal(manifest.data.start_url,prefix+'admin');
        assert.equal(manifest.data.display,'standalone');
        assert.deepEqual(manifest.data.icons.map(i=>[i.sizes,i.purpose]),[['192x192','any'],['512x512','any'],['512x512','maskable']]);
        for(const icon of manifest.data.icons) {
          const result=await page.evaluate(async icon=>{
            const r=await fetch(icon.src);const blob=await r.blob();const bitmap=await createImageBitmap(blob);
            const result={mime:r.headers.get('content-type'),width:bitmap.width,height:bitmap.height};bitmap.close();return result;
          },icon);
          assert.equal(result.mime,'image/png');assert.equal(result.width,parseInt(icon.sizes));assert.equal(result.height,result.width);
        }
        const manifestPage=await context.newPage();await manifestPage.goto(origin+'/outside');
        // Display the already verified real HTTP response, not browser-specific
        // handling/download behavior for application/manifest+json navigation.
        await manifestPage.evaluate(response=>{
          const title=document.createElement('h1');title.textContent='Synthetic fixture: real HTTP manifest response';
          const pre=document.createElement('pre');pre.style.whiteSpace='pre-wrap';pre.textContent=JSON.stringify(response,null,2);
          document.body.replaceChildren(title,pre);
        },manifest);
        await capture(manifestPage,label+'-manifest');await manifestPage.close();
        phase='registration';await page.waitForFunction(()=>!!navigator.serviceWorker.controller);
        const registration=await page.evaluate(async()=>{const r=await navigator.serviceWorker.ready;return {scope:r.scope,script:r.active.scriptURL,cache:r.updateViaCache};});
        assert.equal(registration.scope,origin+prefix);assert.equal(registration.script,origin+prefix+'pwa.php?resource=worker');assert.equal(registration.cache,'none');
        assert.match(await page.evaluate(async prefix=>(await fetch(prefix+'api/private')).text(),prefix),/PRIVATE_FIXTURE/);
        await emptyStores(page);
        phase='synthetic-install-ui';
        // Exercise the real application UI via a clearly synthetic event. This is
        // NOT evidence of Chromium/OS install eligibility or native installation.
        await page.evaluate(()=>{
          window.fixturePromptCalls=0;
          const event=new Event('beforeinstallprompt',{cancelable:true});
          Object.defineProperties(event,{prompt:{value:async()=>{window.fixturePromptCalls++;}},userChoice:{value:Promise.resolve({outcome:'dismissed'})}});
          window.dispatchEvent(event);
        });
        await page.getByRole('button',{name:'Instalar aplicativo',exact:true}).waitFor();
        assert.equal(await page.evaluate(()=>window.fixturePromptCalls),0);
        await capture(page,label+'-install-ui-SYNTHETIC-EVENT');
        await page.getByRole('button',{name:'Instalar aplicativo',exact:true}).click();
        assert.equal(await page.evaluate(()=>window.fixturePromptCalls),1);
        phase='update';
        // Change only the disposable copy. A byte-different worker must WAIT,
        // not take over/reload a page with unsaved form state.
        await page.locator('input').fill('still-unsaved');
        await page.evaluate(()=>{window.fixtureControllerChanges=0;navigator.serviceWorker.addEventListener('controllerchange',()=>window.fixtureControllerChanges++);});
        fs.appendFileSync(path.join(tmp,'assets/pwa/worker.js'),'\n// disposable update '+prefix+'\n');
        await page.evaluate(async()=>{const r=await navigator.serviceWorker.ready;await r.update();});
        await page.waitForFunction(async()=>!!(await navigator.serviceWorker.getRegistration()).waiting);
        await page.getByText('Nova versão disponível.',{exact:false}).waitFor();
        assert.equal(await page.locator('input').inputValue(),'still-unsaved');assert.equal(await page.evaluate(()=>window.fixtureControllerChanges),0);
        await capture(page,label+'-update');
        phase='offline';await context.setOffline(true);
        assert.equal(await page.evaluate(async prefix=>{try{await fetch(prefix+'api/mutate',{method:'POST',body:'synthetic'});return 'unexpected';}catch{return 'network-failure';}},prefix),'network-failure');
        assert.equal(await page.evaluate(async prefix=>{try{await fetch(prefix+'api/private');return 'unexpected';}catch{return 'network-failure';}},prefix),'network-failure');
        const offline=await page.goto(url+'?offline=1');assert.equal(offline.status(),503);
        assert.match(await page.locator('body').innerText(),/Sem conexão/);
        assert.doesNotMatch(await page.content(),/PRIVATE_FIXTURE|still-unsaved|<form|<script/);
        assert.match(offline.headers()['cache-control'],/no-store/);await emptyStores(page);
        await capture(page,label+'-offline');
        phase='reconnect';await context.setOffline(false);await page.goto(url);
        assert.equal(await page.evaluate(async prefix=>(await fetch(prefix+'api/mutate')).text(),prefix),'0','offline mutation never queued/replayed');
        if(prefix==='/crm/') {
          const outside=await context.newPage();await outside.goto(origin+'/outside');
          assert.equal(await outside.evaluate(()=>navigator.serviceWorker.controller),null,'subpath cannot control outside');await outside.close();
        }
        assert.deepEqual(errors,[]);
        await page.close();
        const reopened=await context.newPage();await reopened.goto(url);
        await reopened.waitForFunction(async()=>{const r=await navigator.serviceWorker.getRegistration();return !!navigator.serviceWorker.controller&&!!r.active&&!r.waiting&&!r.installing;});
        await emptyStores(reopened);await reopened.close();
        console.log('Real PWA HTTP/browser PASS '+prefix+' (synthetic; no native installation prompt claim)');
      } catch(error) {
        // No HTML, form values, URLs with query strings, response bodies or tokens.
        const diagnostic={fixture:label,phase,errorType:error.name};
        if(page&&!page.isClosed()) {
          diagnostic.geometry=await page.evaluate(()=>({width:innerWidth,height:innerHeight,scrollWidth:document.documentElement.scrollWidth})).catch(()=>null);
          await capture(page,label+'-failure').catch(()=>{});
        }
        fs.writeFileSync(path.join(artifacts,label+'-diagnostic.json'),JSON.stringify(diagnostic));
        console.error(JSON.stringify(diagnostic));throw error;
      } finally {await context.close();}
    }
  } finally {
    if(browser) await browser.close();
    if(server && server.exitCode===null) {server.kill('SIGTERM');await Promise.race([once(server,'exit'),new Promise(resolve=>setTimeout(()=>{server.kill('SIGKILL');resolve();},2000))]);}
    fs.rmSync(tmp,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
