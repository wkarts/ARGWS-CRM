'use strict';
// Actual PHP view, shipped CSS, picker and shared lifecycle; presentation fixture only.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const {workspacePage}=require('../helpers/native_workspace_driver.cjs');
const root=path.resolve(__dirname,'../..');
const body=execFileSync('php',[path.join(root,'tests/fixtures/native_contract_selects.php')]).toString();
const css=['assets/css/reset.min.css','assets/builds/vendor-admin.css','assets/builds/tailwind.css','assets/css/style.min.css'];
const scripts=['assets/builds/vendor-admin.js','assets/plugins/datatables/datatables.min.js','assets/builds/bootstrap-select.min.js','assets/plugins/jquery-validation/jquery.validate.min.js','assets/builds/common.js','assets/js/native-admin-tables.js'];
const server=http.createServer((req,res)=>{
 const requestUrl=new URL(req.url,'http://127.0.0.1'),name=requestUrl.pathname;
 const delay=requestUrl.searchParams.get('delayed')==='1'?300:0;
 if(name.startsWith('/assets/')){const file=path.join(root,name);if(!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',name.endsWith('.css')?'text/css':name.endsWith('.js')?'text/javascript':'application/octet-stream');res.end(fs.readFileSync(file));return;}
 res.setHeader('Content-Type','text/html; charset=utf-8');
 res.end('<!doctype html><html><head>'+css.map(x=>'<link rel="stylesheet" href="/'+x+'">').join('')+'</head><body class="native-company-admin"><main style="padding:40px">'+body+'</main><script>window.app={options:{},lang:{}};</script>'+scripts.map(x=>'<script src="/'+x+'"></script>').join('')+'<script>setTimeout(function(){NativeAdminWidgets.initSelects();NativeAdminWidgets.initSelects();},'+delay+');</script></body></html>');
});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});try{
 const context=await browser.newContext(),page=await workspacePage(context),errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const delayed of [0,1]){
 await page.goto('http://127.0.0.1:'+server.address().port+'/?delayed='+delayed);
 const button=page.locator('[data-target="#native-contract-type-modal"]');
 await button.waitFor({state:'visible'});assert.equal(await page.locator('#contract-form .select-placeholder').count(),0,'rendered selectors release the original placeholder CSS');
 await page.locator('button[data-id="contract-new-type"]').click();await page.locator('.bootstrap-select.open').waitFor({state:'visible'});await page.locator('button[data-id="contract-new-type"]').click();
 await button.click();await page.locator('#native-contract-type-modal').waitFor({state:'visible'});await page.locator('#native-contract-type-name').fill('Synthetic type');await page.locator('#native-contract-type-modal button[data-dismiss=modal]').last().click();await page.locator('#native-contract-type-modal').waitFor({state:'hidden'});
 await page.setViewportSize({width:390,height:844});await button.click();await page.locator('#native-contract-type-modal').waitFor({state:'visible'});assert.deepEqual(errors,[]);
 }
 console.log('Native contract view: rendered picker, inline type modal and mobile access passed with original CSS/shared lifecycle. Presentation only.');await context.close();
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
