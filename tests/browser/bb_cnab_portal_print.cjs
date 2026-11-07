'use strict';
// SQL-produced real PHP view, served under the portal CSP. Not an authenticated CRM test.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const {chromium}=require('playwright');
(async()=>{
 const html=fs.readFileSync(process.env.BB_CNAB_PORTAL_HTML,'utf8');
 const server=http.createServer((req,res)=>{
  if(req.url!=='/boleto'){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type','text/html; charset=utf-8');
  res.setHeader('Cache-Control','no-store');
  res.setHeader('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-synthetic-print-nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
  res.end(html);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage();const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.addInitScript(()=>{window.print=()=>{window.__printed=true;};document.addEventListener('securitypolicyviolation',()=>{window.__cspViolation=true;});});
  const response=await page.goto('http://127.0.0.1:'+server.address().port+'/boleto');
  assert.equal(response.status(),200);assert.equal(response.headers()['cache-control'],'no-store');
  assert.equal(await page.locator('.barcode svg').count(),1);
  await page.locator('#bb-print-boleto').click();
  assert.equal(await page.evaluate(()=>window.__printed),true,'actual nonce-authorized handler invokes print');
  assert.equal(await page.evaluate(()=>Boolean(window.__cspViolation)),false);
  assert.deepEqual(errors,[]);
  console.log('CNAB portal print: real PHP view, HTTP CSP nonce and print click passed; SQL-generated synthetic data, no authenticated CRM claim.');
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
