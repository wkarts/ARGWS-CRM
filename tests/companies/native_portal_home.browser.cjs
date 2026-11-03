'use strict';
// Render actual PHP controller/view with synthetic SQLite records, then execute
// the shipped chart scripts in Chromium. No application session or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'native-portal-home-'));
const output = path.join(dir, 'home.html');
try {
  execFileSync(process.env.PHP_BINARY || 'php', ['-d','error_reporting=32767','tests/companies/native_portal_home.php'], {
    cwd: root, env: {...process.env, NATIVE_PORTAL_HOME_TEST_HTML: output}, stdio: ['ignore','pipe','pipe']
  });
  const html = fs.readFileSync(output, 'utf8');
  (async () => {
    const browser = await chromium.launch({headless:true, ...(process.env.CHROMIUM_PATH ? {executablePath:process.env.CHROMIUM_PATH} : {})});
    try {
      const page = await browser.newPage();
      const errors=[];page.on('pageerror', error=>errors.push(error.message));
      await page.route('https://fixture.test/crm/assets/**', async route => {
        const pathname=new URL(route.request().url()).pathname.replace(/^\/crm\//,'');
        const file=path.resolve(root,pathname);
        if(!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file))return route.abort();
        return route.fulfill({path:file,contentType:'application/javascript'});
      });
      await page.setContent(html,{waitUntil:'load'});
      assert.equal(await page.locator('.section-client-dashboard').count(),1);
      assert.equal(await page.locator('#client-home-chart').count(),1);
      assert.equal(await page.locator('#greeting img').count(),0,'contact name stays plain text');
      assert.equal(await page.locator('#greeting').innerText(),'<img src=x onerror=alert(1)>');
      assert.equal(await page.locator('.invoice-quick-info a[href*="clients/invoices/2?issuer_id=41"]').count(),1);
      assert.equal(await page.evaluate(()=>typeof window.Chart),'function');
      const painted=await page.locator('#client-home-chart').evaluate(canvas=>{
        const data=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data;
        for(let i=3;i<data.length;i+=4)if(data[i]!==0)return true;return false;
      });
      assert.equal(painted,true,'original invoice chart is rendered from scoped aggregate data');
      await page.setViewportSize({width:375,height:812});
      assert.equal(await page.locator('.section-client-dashboard').isVisible(),true);
      assert.deepEqual(errors,[]);
      console.log('Native portal home: actual PHP view and chart rendered in Chromium, including mobile viewport.');
    } finally {await browser.close();}
  })().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>fs.rmSync(dir,{recursive:true,force:true}));
} catch(error) {fs.rmSync(dir,{recursive:true,force:true});throw error;}
