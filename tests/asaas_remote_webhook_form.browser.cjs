'use strict';
const assert=require('node:assert/strict'),http=require('node:http'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BINARY||'php',['-r',`
define('BASEPATH',__DIR__);define('FCPATH',getcwd().'/');
$remote_form_action='/save';$remote_prefix='synthetic-webhook';
$remote_hidden=static function(){echo '<input type="hidden" name="_company_context" value="synthetic-context"><input type="hidden" name="company_asaas_webhook_csrf" value="synthetic-csrf"><input type="hidden" name="account_row" value="12">';};
require 'modules/asaas/views/remote_webhook_form.php';
`],{cwd:root,encoding:'utf8'});
let requests=[];
const server=http.createServer((req,res)=>{
 if(req.method==='POST'){let body='';req.on('data',c=>body+=c);req.on('end',()=>{requests.push(new URLSearchParams(body));res.writeHead(200,{'Content-Type':'text/html'});res.end('<p>Saved synthetic response</p>');});return;}
 if(req.url==='/bootstrap.css'){res.writeHead(200,{'Content-Type':'text/css'});res.end(fs.readFileSync(path.join(root,'assets/plugins/bootstrap/css/bootstrap.min.css')));return;}
 res.writeHead(200,{'Content-Type':'text/html'});res.end('<!doctype html><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/bootstrap.css"><main style="padding:16px">'+html+'</main>');
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let browser;
 try{
  browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:390,height:844}});const base='http://127.0.0.1:'+server.address().port;
  const open=async()=>{await page.goto(base);await page.locator('summary').click();};
  await open();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'remote controls fit mobile viewport');
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Consultar webhook no Asaas',exact:true}).click()]);
  assert.equal(requests.at(-1).get('remote_action'),'inspect','read operation needs no configuration fields');
  await open();await page.locator('[name="remote_config[email]"]').fill('ops@example.test');await page.locator('form').nth(1).locator('[name=confirm]').check();
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Criar webhook no Asaas',exact:true}).click()]);
  const sent=requests.at(-1);assert.equal(sent.get('remote_action'),'create');assert.deepEqual(sent.getAll('remote_config[events][]'),['PAYMENT_CONFIRMED','PAYMENT_RECEIVED']);
  for(const name of ['_company_context','company_asaas_webhook_csrf','account_row'])assert.equal(sent.getAll(name).length,1,'one '+name);
  assert(!sent.has('authToken')&&!sent.has('api_key')&&!sent.has('url'),'browser cannot choose destination or remote credentials');
  await open();const deletion=page.locator('form').filter({has:page.locator('[name=remote_action][value=delete]')});const count=requests.length;
  await deletion.locator('button').click();assert.equal(requests.length,count,'deletion blocked without confirmation');
  await deletion.locator('[name=confirm]').check();await Promise.all([page.waitForNavigation(),deletion.locator('button').click()]);assert.equal(requests.at(-1).get('remote_action'),'delete');
  console.log('Webhook forms: mobile geometry, native POST payloads, event selection and explicit deletion verified in Chromium.');
 }finally{if(browser)await browser.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
