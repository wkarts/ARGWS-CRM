// Actual company Efí PHP view + shipped assets, synthetic data; no API/database/form submission.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..'),out=process.env.COMPANY_SHELL_SCREENSHOT_DIR||'/tmp/company-shell-fidelity';fs.mkdirSync(out,{recursive:true});
const html=execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/support/NativeHeaderFixture.php'),'--company-efi'],{encoding:'utf8'});
assert.ok(!/PHP (?:Warning|Fatal)|Warning:|Fatal error:/.test(html),'renderer fails closed before HTML');
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});try{
const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',async route=>{const u=new URL(route.request().url());assert.equal(u.origin,'http://fixture.test');assert.equal(route.request().method(),'GET');if(u.pathname==='/fixture')return route.fulfill({body:html,contentType:'text/html;charset=utf-8'});const file=path.resolve(root,'.'+u.pathname);assert.ok(file.startsWith(root+path.sep)&&fs.existsSync(file),'only shipped public assets');return route.fulfill({body:fs.readFileSync(file),contentType:{'.css':'text/css','.js':'text/javascript','.png':'image/png','.woff2':'font/woff2','.woff':'font/woff'}[path.extname(file)]||'application/octet-stream'});});
for(const width of [1440,768,390]){await page.setViewportSize({width,height:1000});await page.goto('http://fixture.test/fixture');await page.evaluate(()=>document.fonts.ready);
assert.equal(await page.locator('#header').count(),1);assert.equal(await page.locator('#menu').count(),1);assert.equal(await page.locator('.company-installation-logo img').count(),1);
assert.ok(await page.locator('.btn-primary').count()>0);assert.ok(await page.locator('.btn-danger').count()>0);
assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Efí mobile document has no horizontal overflow');
assert.equal(await page.locator('input[name="client_secret"]').evaluateAll(els=>els.every(el=>el.type==='password'&&el.value==='')),true,'credentials never prefilled');
assert.equal(await page.locator('form').evaluateAll(els=>els.filter(el=>!el.hasAttribute('data-synthetic-table-fixture')).every(el=>el.method==='post'&&el.action.includes('/admin/company_efi')&&el.querySelector('[name="company_efi_csrf"]')?.value==='synthetic-form-csrf'&&el.querySelector('[name="confirm"]')?.required)),true,'every real action keeps explicit CSRF/confirmation/form ownership');
await page.screenshot({path:path.join(out,`efi-company-${width}.png`),fullPage:true,mask:[page.locator('input,textarea,select')]});}
assert.deepEqual(errors,[]);console.log('PASS: real Efí view desktop/mobile with native shell/assets and inert synthetic forms; not ACL/provider evidence');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
