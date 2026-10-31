const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/pwa/worker.js','utf8');
const pwaEndpoint = fs.readFileSync('pwa.php','utf8');
const pwaIdentity = fs.readFileSync('application/services/NativePwaIdentity.php','utf8');
const pwaHelper = fs.readFileSync('application/helpers/pwa_helper.php','utf8');
function worker(offline = false) {
  const handlers = {}, calls = [];
  const self = {registration:{scope:'https://crm.test/sub/'}, clients:{claim:async()=>{}}, addEventListener:(n,fn)=>handlers[n]=fn};
  vm.runInNewContext(source,{self, URL, Response, fetch:async(req,opts)=>{
    calls.push({req,opts}); if(offline) throw Error('offline'); return new Response('live');
  }});
  return {handlers,calls};
}
test('authenticated navigation is always network/no-store; never replayed offline',async()=>{
  const w=worker(); let promise;
  const request={url:'https://crm.test/sub/admin/companies/7',method:'GET',mode:'navigate'};
  w.handlers.fetch({request,respondWith:p=>promise=p});
  assert.equal(await (await promise).text(),'live');
  assert.equal(w.calls[0].opts.cache,'no-store'); assert.equal(w.calls[0].req,request);
  const off=worker(true); off.handlers.fetch({request,respondWith:p=>promise=p});
  const response=await promise; assert.equal(response.status,503); assert.equal(response.headers.get('cache-control'),'no-store');
  const text=await response.text(); assert.match(text,/Sem conexão/); assert.doesNotMatch(text,/companies|7|<form|<script/);
});
test('POST, APIs, financial assets, credentials, certificates, other origins/scopes are not intercepted',()=>{
  const w=worker();
  for(const [url,method,mode] of [
    ['/sub/admin/pay','POST','navigate'],['/sub/api/finance','GET','cors'],['/sub/cert.pfx','GET','cors'],
    ['/sub/admin/authentication','POST','navigate'],['/sub/asset.js','GET','no-cors'],
    ['/outside','GET','navigate'],['https://external.test/sub/','GET','navigate'],['/submarine','GET','navigate']]) {
    w.handlers.fetch({request:{url:new URL(url,'https://crm.test').href,method,mode},respondWith:()=>assert.fail('must not intercept')});
  }
  assert.equal(w.calls.length,0);
});
test('no persistence, takeover messages, force refresh, financial replay or cache API',()=>{
  assert.doesNotMatch(source,/caches\.|indexedDB|localStorage|skipWaiting\(|addEventListener\(['"](?:sync|message)/);
  const install=fs.readFileSync('assets/pwa/install.js','utf8');
  assert.doesNotMatch(install,/location\.reload|skipWaiting|localStorage|sessionStorage/);
  assert.match(install,/isSecureContext/); assert.match(install,/updateViaCache: 'none'/);
});
test('PNG fallback dimensions, masks and native views',()=>{
  for(const [file,size] of [['192',192],['512',512],['maskable-512',512]]) {
    const png=fs.readFileSync(`assets/pwa/icon-${file}.png`);
    assert.equal(png.toString('hex',0,8),'89504e470d0a1a0a');
    assert.equal(png.readUInt32BE(16),size);assert.equal(png.readUInt32BE(20),size);
  }
  for (const file of ['admin/includes/head.php','authentication/includes/head.php','themes/perfex/head.php']) {
    assert.match(fs.readFileSync(`application/views/${file}`,'utf8'),/load->view\('includes\/pwa'\)/);
  }
});

test('PWA identity is a dynamic, atomic projection with installation/company/V2 precedence',()=>{
  assert.match(pwaIdentity,/pwa_installation_name/);
  assert.match(pwaIdentity,/pwa_icon_maskable_512/);
  assert.match(pwaIdentity,/CompanySettingsIdentity/);
  assert.match(pwaIdentity,/CompanyAssets/);
  assert.match(pwaIdentity,/name_source/);
  assert.match(pwaIdentity,/icon_source/);
  assert.match(pwaIdentity,/if\(\$maskable===null\)\$maskable=self::readFile/);
  assert.match(pwaIdentity,/if\(self::projectionExists\(\)\)return null/);
  assert.match(pwaIdentity,/format'=>2/);
  assert.match(pwaIdentity,/hashes/);
  assert.match(pwaIdentity,/rename\(\$tmp,\$dir\.'\/identity\.json'/);
  assert.match(pwaEndpoint,/identity\.json/);
  assert.match(pwaEndpoint,/in_array\(\$doc\['format'\]\?\?null,\[1,2\]/);
  assert.match(pwaEndpoint,/hash_file\('sha256'/);
  assert.match(pwaHelper,/runtime\/identity\.json/);
  assert.match(fs.readFileSync('application/views/includes/pwa.php','utf8'),/data-pwa-revision/);
});
test('V2 identity assets are wired without replacing company custom branding',()=>{
  const pngs=[['assets/images/argws/favicon-16.png',16],['assets/images/argws/favicon-32.png',32],['assets/pwa/apple-touch-icon-180.png',180]];
  for(const [file,size] of pngs){const png=fs.readFileSync(file);assert.equal(png.toString('hex',0,8),'89504e470d0a1a0a');assert.equal(png.readUInt32BE(16),size);assert.equal(png.readUInt32BE(20),size);}
  for(const file of ['logo-horizontal-colorido.svg','logo-horizontal-negativo.svg','simbolo-colorido.svg','simbolo-negativo.svg']) assert.match(fs.readFileSync(`assets/images/argws/identity-v2/svg/${file}`,'utf8'),/^<svg/);
  const helper=fs.readFileSync('application/helpers/assets_helper.php','utf8');assert.match(helper,/apple-touch-icon-180\.png/);assert.match(helper,/favicon-16\.png/);
  const pdf=fs.readFileSync('application/services/CompanyDocumentBranding.php','utf8');assert.match(pdf,/assets\/images\/argws\/logo-dark\.png/);
});

test('browser owns install eligibility, dismissal and standalone; duplicate includes register once',async()=>{
  const source=fs.readFileSync('assets/pwa/install.js','utf8');
  function harness({secure=true,root='https://crm.test/sub/',worker=true,standalone=false}={}) {
    const events={}, registrations=[];
    const registration={waiting:{state:'installed'}};
    const document={currentScript:{dataset:{pwaRoot:root}},createElement(){assert.fail('must not create install/update panels')},body:{appendChild(){assert.fail('must not add per-route UI')}}};
    const window={isSecureContext:secure,matchMedia:()=>({matches:standalone}),addEventListener:(n,f)=>events[n]=f};
    const navigator=worker?{standalone,serviceWorker:{register:async(url,options)=>{registrations.push({url,options});return registration}}}:{};
    const context=vm.createContext({window,document,navigator,location:{href:'https://crm.test/sub/admin',origin:'https://crm.test'},URL});
    const run=()=>vm.runInContext(source,context);run();
    return {events,registrations,run};
  }
  for (const options of [{secure:false},{root:'https://foreign.test/'},{root:'http://['},{worker:false}]) assert.equal(harness(options).registrations.length,0);
  for (const standalone of [false,true]) {
    const h=harness({standalone});h.run();h.run();await new Promise(resolve=>setImmediate(resolve));
    assert.equal(h.registrations.length,1,'one registration even if a layout repeats the script');
    assert.equal(h.registrations[0].url,'https://crm.test/sub/pwa.php?resource=worker');
    assert.equal(h.registrations[0].options.scope,'/sub/');
    assert.equal(h.registrations[0].options.updateViaCache,'none');
    assert.deepEqual(Object.keys(h.events),[],'no installation interception or update invitations');
  }
});

test('PWA worker registration carries the committed identity revision',()=>{
  const install=fs.readFileSync('assets/pwa/install.js','utf8');
  assert.match(install,/pwaRevision/);
  assert.match(install,/searchParams\.set\('v', revision\)/);
  assert.doesNotMatch(install,/addEventListener\(['"]beforeinstallprompt|\.prompt\(/);
});

test('real HTTP browser gate is mandatory, pinned and has no dependency installation or mocked fetch',()=>{
  const workflow=fs.readFileSync('.github/workflows/native-pwa.yml','utf8');
  const browserJob=workflow.slice(workflow.indexOf('  browser:'));
  assert.match(browserJob,/sha256:c6246e2740c225e7db4f0db2b8ba8657fb72cb9638efdedcfe1a8853fc081a14/);
  assert.match(browserJob,/node tests\/native_pwa.browser.cjs/);
  assert.doesNotMatch(browserJob,/continue-on-error|npm install|apt-get/);
  const real=fs.readFileSync('tests/native_pwa.browser.cjs','utf8');
  assert.doesNotMatch(real,/route\.fulfill|context\.route|page\.route|setContent|test\.skip/);
  for(const contract of ["spawn('php'","['/','/crm/']",'createImageBitmap','setOffline(true)',"method:'POST'",'r.update()','r.waiting','fixtureControllerChanges','indexedDB.databases','caches.keys','offline mutation never queued/replayed']) assert.ok(real.includes(contract),contract);
});

test('PWA evidence covers both widths, masks controls, preserves post-merge validation',()=>{
  const workflow=fs.readFileSync('.github/workflows/native-pwa.yml','utf8');
  assert.match(workflow,/push:\n    branches: \[main, develop\]/);
  assert.match(workflow,/if: always\(\)/);assert.match(workflow,/name: native-pwa-synthetic-browser/);
  const real=fs.readFileSync('tests/native_pwa.browser.cjs','utf8');
  for(const text of ['[1280,390]',"mask:[page.locator('input,textarea,select')]",'-manifest','-install-ui-SYNTHETIC-EVENT','-update','-offline','errorType:error.name']) assert.ok(real.includes(text),text);
});

test('HTTP fixture has native mobile viewport and accessible synthetic field',()=>{
 const router=fs.readFileSync('tests/native_pwa_router.php','utf8');
 assert.match(router,/<meta name="viewport" content="width=device-width,initial-scale=1">/);
 assert.match(router,/<label for="synthetic">Synthetic unsaved fixture field<\/label><input id="synthetic"/);
});
