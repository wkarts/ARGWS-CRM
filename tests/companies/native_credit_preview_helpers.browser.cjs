'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
const inline=fs.readFileSync(path.join(root,'application/views/admin/credit_notes/native_preview.php'),'utf8').match(/<script>([^<]+)<\/script>/)[1];
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
try{for(const width of [1280,390]){const page=await browser.newPage({viewport:{width,height:800}}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.route('**/*',r=>r.abort());
await page.setContent('<html><body class="native-company-admin"><div class="_buttons"><button class="btn-with-tooltip" data-title="Visualizar">Icon</button></div><div class="horizontal-scrollable-tabs"><div class="scroller arrow-left">Left</div><div class="scroller arrow-right">Right</div><div class="horizontal-tabs"><ul class="nav nav-tabs nav-tabs-horizontal"><li><a href="#details">Details</a></li></ul></div></div></body></html>');
await page.addScriptTag({path:path.join(root,'assets/plugins/jquery/jquery.min.js')});
await page.addScriptTag({path:path.join(root,'assets/plugins/internal/bootstrap-nav-tabs-scrollable/bootstrap-nav-tab-scrollable.js')});
await page.evaluate(()=>{window.is_mobile=()=>window.innerWidth<768;window.isRTL='false';});
await page.addScriptTag({path:path.join(root,'assets/js/native-admin-tabs.js')});
await page.addScriptTag({content:inline});await page.addScriptTag({content:inline});
assert.deepEqual(errors,[],'actual credit preview script initializes without invoice-only bundle');
assert.equal(await page.locator('button').textContent(),width<768?'Icon Visualizar':'Icon','mobile labels initialize once, desktop preserves tooltip button');
await page.evaluate(()=>{window.isRTL='true';window.init_tabs_scrollable();});assert.equal(await page.locator('.horizontal-scrollable-tabs').count(),0,'RTL uses original non-scrolling tabs');
await page.close();}}finally{await browser.close();}
console.log('Native credit preview helpers: shared script, real jQuery/tab plugin, desktop/mobile/RTL and repeated fragment initialization passed. No authenticated session.');
})().catch(e=>{console.error(e);process.exitCode=1;});
