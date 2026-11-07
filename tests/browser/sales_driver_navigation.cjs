'use strict';
// Real Chromium/HTTP regression for an AJAX body whose owning document navigates.
// The server is synthetic; no application/financial or authenticated-CRM claim.
const assert=require('node:assert/strict'),http=require('node:http');
const {chromium}=require('playwright');
const {workspacePage}=require('../helpers/native_workspace_driver.cjs');
const driver=require(process.env.SALES_DRIVER_PATH||'../helpers/native_sales_driver.cjs');
const posts=[];
const server=http.createServer((req,res)=>{
 const route=new URL(req.url,'http://127.0.0.1').pathname;
 if(req.method==='POST'&&route==='/admin/estimates/estimate'){
  let body='';req.on('data',part=>body+=part);req.on('end',()=>{
   posts.push(body);res.writeHead(200,{'Content-Type':'application/json'});
   res.end(JSON.stringify({success:true,id:'123',url:'/admin/estimates/list_estimates/123'}));
  });return;
 }
 res.writeHead(200,{'Content-Type':'text/html','Cache-Control':'no-store'});
 if(route==='/admin/estimates/list_estimates/123'){
  // Let the document replacement finish before the old driver's late body read.
  setTimeout(()=>res.end('<!doctype html><div data-native-sales-preview="123">Saved</div>'),300);return;
 }
 res.end(`<!doctype html><form id="estimate-form" action="/admin/estimates/estimate"><input name="request_key" value="synthetic-stable-key"><button class="transaction-submit">Save</button></form><script>
 document.querySelector('form').addEventListener('submit',async function(event){event.preventDefault();const reply=await fetch(this.action,{method:'POST',body:new URLSearchParams(new FormData(this))});const result=await reply.json();location.assign(result.url);});
 </script>`);
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  const context=await browser.newContext(),page=await workspacePage(context);
  const origin='http://127.0.0.1:'+server.address().port;
  await context.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
  await page.goto(origin+'/admin/estimates/estimate');
  assert.equal((await driver.save(page,origin,'estimates')).id,'123');
  assert.equal(posts.length,2,'original POST and durable replay are both checked');
  assert.equal(posts[0],posts[1],'replay retains the exact submitted request');
  await context.close();console.log('Sales driver: AJAX JSON preserved through document navigation; destination, preview and exact replay verified in Chromium.');
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
