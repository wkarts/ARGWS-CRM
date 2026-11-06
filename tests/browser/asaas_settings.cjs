'use strict';
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const http = require('node:http');
const fs = require('node:fs');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const html = execFileSync('php', [path.join(root, 'tests/fixtures/asaas_settings_page.php')], {maxBuffer: 8e6}).toString();
// Serve the actual PHP-rendered view; external assets/providers are never contacted.
const server = http.createServer((req,res) => {
  const asset = {'/jquery.js':'assets/plugins/jquery/jquery.min.js','/bootstrap.js':'assets/plugins/bootstrap/js/bootstrap.min.js','/bootstrap.css':'assets/plugins/bootstrap/css/bootstrap.min.css'}[req.url];
  if(asset){res.setHeader('Content-Type',req.url.endsWith('.css')?'text/css':'text/javascript');return res.end(fs.readFileSync(path.join(root,asset)));}
  if (req.url.split('?')[0] === '/tabs.js') {res.setHeader('Content-Type','text/javascript');return res.end(fs.readFileSync(path.join(root,'assets/js/native-asaas-settings.js')));}
  res.setHeader('Content-Type','text/html; charset=utf-8');
  res.end(html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace('</head>','<link rel="stylesheet" href="/bootstrap.css"></head>').replace('</body>','<script src="/jquery.js"></script><script src="/bootstrap.js"></script><script src="/tabs.js"></script></body>'));
});
(async () => {
  await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
  const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined,headless:true,args:['--no-sandbox']});
  try {
    const page = await browser.newPage();
    await page.route('**/*',route => route.request().url().startsWith('http://127.0.0.1:') ? route.continue() : route.abort());
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(`http://127.0.0.1:${server.address().port}/#credentials`);
    assert.equal(await page.locator('#credentials').isVisible(),true);
    assert.equal(await page.locator('#accounts').isVisible(),false);
    assert.equal(await page.locator('[name="api_key"]').evaluateAll(nodes=>nodes.every(n=>n.value==='')),true);
    assert.equal(await page.locator('[name="action"][value="reveal_api_key"]').count(),0);
    await page.locator('#asaas-tab-card').click();
    assert.equal(await page.locator('#card').isVisible(),true);
    assert.equal(await page.locator('#card [name=enabled]').count(),2);
    assert.equal(await page.locator('#card [name=environment]').evaluateAll(nodes=>nodes.map(n=>n.value).join(',')),'production,sandbox');
    await page.locator('#asaas-tab-jobs').click();
    assert.equal(await page.locator('#jobs').isVisible(),true);
    assert.equal(await page.locator('#credentials').isVisible(),false);
    await page.locator('#asaas-tab-jobs').focus();await page.keyboard.press('Home');
    assert.equal(await page.locator('#asaas-tab-accounts').getAttribute('aria-selected'),'true');
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.locator('#preferences').isVisible(),true);
    await page.evaluate(()=>{location.hash='asaas-new-key';});
    await page.waitForFunction(()=>!document.getElementById('credentials').hidden);
    const result = await page.locator('#credentials form').first().evaluate(form => {
      form.addEventListener('submit',e=>e.preventDefault());
      const before = form.getAttribute('action');form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
      return {before,after:form.getAttribute('action'),context:form.elements._company_context.value,csrf:form.elements.synthetic_csrf.value};
    });
    assert.equal(result.after,result.before+'#credentials');
    assert.equal(result.context,'synthetic-context');assert.equal(result.csrf,'synthetic-csrf-hash');
    await page.locator('#asaas-tab-accounts').click();
    await page.locator('#asaas-new-key').evaluate(input=>input.dispatchEvent(new Event('invalid')));
    assert.equal(await page.locator('#credentials').isVisible(),true);
    assert.deepEqual(errors,[]);
    console.log('Asaas actual PHP view in Chromium: tabs, deep links, keyboard, invalid-field recovery, context/CSRF preservation and non-exported API keys passed. Synthetic fixture; not provider homologation.');
  } finally {await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
