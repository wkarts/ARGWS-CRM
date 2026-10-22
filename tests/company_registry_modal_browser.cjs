'use strict';
// Local presentation fixture: real browser + real assets, no CRM session/database/provider.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'..');
const rows = Array.from({length:27},(_,i)=>`<tr><td>Empresa ${i+1}</td><td>Documento ${i+1}</td><td>Ativa</td><td>—</td></tr>`).join('');
const html = `<!doctype html><html lang="pt-BR"><head><link rel="stylesheet" href="/assets/plugins/bootstrap/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/company-registry.css"></head><body><div data-company-registry class="company-registry"><button data-new-company>Nova empresa</button><label>Buscar<input id="company-grid-search"></label><select data-page-size><option>10</option><option>25</option></select><table><thead><tr>${['Empresa','Documento','Situação','Contexto'].map((x,i)=>`<th><button data-sort="${i}">${x}</button></th>`).join('')}</tr></thead><tbody>${rows}</tbody></table><p data-company-empty hidden>Vazio</p><p data-company-count></p><button data-page-prev>Anterior</button><button data-page-next>Próxima</button><div class="modal fade" id="company-create-modal" data-open-on-load="0" tabindex="-1" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content"><button data-dismiss="modal">Fechar</button><iframe name="company-create-frame" src="about:blank"></iframe></div></div></div><form id="company-wizard-open" method="post" target="company-create-frame" action="/wizard" hidden><input name="action" value="show"><input name="_company_context" value="old-tab-token"><input name="csrf_test_name" value="first-csrf"></form></div><script src="/assets/plugins/jquery/jquery.min.js"></script><script src="/assets/plugins/bootstrap/js/bootstrap.min.js"></script><script src="/assets/js/company-registry.js"></script></body></html>`;
let posts=[];
const server=http.createServer((req,res)=>{
 if(req.url==='/'){res.setHeader('Content-Type','text/html');res.end(html);return;}
 if(req.url==='/wizard'){let body='';req.on('data',x=>body+=x);req.on('end',()=>{posts.push(Object.fromEntries(new URLSearchParams(body)));res.setHeader('Content-Type','text/html');res.end(`<h1>Nova empresa</h1><input id="wizard-field"><button data-wizard-close>Sair</button><script src="/assets/js/company-onboarding-modal.js"></script><script>parent.postMessage({type:'company-onboarding-csrf',name:'csrf_test_name',hash:'next-csrf'},location.origin)</script>`);});return;}
 const file=path.resolve(root,'.'+req.url);if(!file.startsWith(root+'/assets/')){res.writeHead(404);res.end();return;}
 fs.readFile(file,(err,data)=>{if(err){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'application/javascript');res.end(data);});
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const url='http://127.0.0.1:'+server.address().port;
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(url);assert.equal(await page.locator('tbody tr:visible').count(),10);
  assert.equal(await page.locator('[data-page-prev]').isDisabled(),true);
  await page.locator('[data-page-next]').click();assert.match(await page.locator('[data-company-count]').innerText(),/^11–20/);
  await page.locator('#company-grid-search').fill('Empresa 27');assert.equal(await page.locator('tbody tr:visible').count(),1);
  await page.locator('#company-grid-search').fill('absent');assert.equal(await page.locator('tbody tr:visible').count(),0);assert.equal(await page.locator('[data-company-empty]').isVisible(),true);
  await page.locator('#company-grid-search').fill('');await page.locator('[data-sort="0"]').click();assert.equal(await page.locator('tbody tr:visible').first().locator('td').first().innerText(),'Empresa 27');
  await page.locator('[data-new-company]').click();let frame=page.frame({name:'company-create-frame'});await frame.locator('h1').waitFor();
  assert.deepEqual(posts[0],{action:'show',_company_context:'old-tab-token',csrf_test_name:'first-csrf'});
  await frame.getByText('Sair',{exact:true}).click();await page.locator('#company-create-modal').waitFor({state:'hidden'});
  assert.equal(await page.locator('[data-new-company]').evaluate(el=>el===document.activeElement),true);
  await page.locator('[data-new-company]').click();frame=page.frame({name:'company-create-frame'});await frame.locator('h1').waitFor();
  assert.equal(posts[1].csrf_test_name,'next-csrf');assert.equal(posts[1]._company_context,'old-tab-token','CSRF refresh must never replace captured company token');
  await page.evaluate(()=>window.postMessage({type:'company-onboarding-close'},location.origin));assert.equal(await page.locator('#company-create-modal').isVisible(),true,'messages outside the iframe are ignored');
  await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
  await frame.locator('#wizard-field').focus();await frame.locator('#wizard-field').press('Escape');await page.locator('#company-create-modal').waitFor({state:'hidden'});
  assert.deepEqual(errors,[]);console.log('company registry/modal browser fixture passed: pagination, search, sort, cancel/reopen, context preservation, CSRF refresh, message isolation, mobile');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
