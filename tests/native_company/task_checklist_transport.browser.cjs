'use strict';
// Real browser, original jQuery handlers and context transport; isolated HTTP responses.
const assert=require('node:assert/strict'),fs=require('node:fs'),http=require('node:http'),path=require('node:path');
const {chromium}=require('playwright');const root=path.resolve(__dirname,'../..');
const files={'/jquery.js':'assets/plugins/jquery/jquery.min.js','/tasks.js':'assets/js/native-tasks.js','/transport.js':'assets/js/native-company-transport.js'};
const captured=[];const revision='a'.repeat(64),identifier='214748364801';
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://127.0.0.1'),pathname=url.pathname.replace(/\/{2,}/g,'/');
 if(files[pathname]){res.writeHead(200,{'Content-Type':'application/javascript'});fs.createReadStream(path.join(root,files[pathname])).pipe(res);return;}
 if(pathname==='/'){res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'});res.end(`<!doctype html><html><body class="native-company-admin"><div class="checklist" data-checklist-id="1"><textarea>&lt;img src=x onerror=alert(1)&gt; &amp; Item</textarea><button id="save"></button></div><div class="checklist-templates-wrapper"><select class="checklist-items-template-select"><option value=""></option></select></div><div id="task-modal"></div><script src="/jquery.js"></script><script>window.admin_url=location.origin+'/admin/';window.NativeCompanyHeaderConfig={root:window.admin_url,context:'issuer2:actor7',csrf:'native-token',frameworkCsrf:{name:'csrf_name',value:'framework-token'}};jQuery.fn.selectpicker=function(){return this};</script><script src="/transport.js"></script><script src="/tasks.js"></script></body></html>`);return;}
 if(req.method==='POST'&&(pathname==='/admin/tasks/save_checklist_item_template'||pathname==='/admin/tasks/remove_checklist_item_template/'+identifier)){
  let body='';req.setEncoding('utf8');req.on('data',part=>body+=part);req.on('end',()=>{const values=Object.fromEntries(new URLSearchParams(body));captured.push({pathname,values,context:req.headers['x-company-context'],csrf:req.headers['x-native-company-csrf']});res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify(pathname.includes('/save_')?{id:identifier,revision}:{success:true}));});return;
 }
 res.writeHead(404).end();
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));await page.goto('http://127.0.0.1:'+server.address().port+'/');
 await page.evaluate(()=>save_checklist_item_template('1',document.getElementById('save')));await page.waitForFunction(id=>document.querySelector('select option[value="'+id+'"]'),identifier);
 const option=await page.$eval('select option[value="'+identifier+'"]',e=>({text:e.textContent,revision:e.getAttribute('data-template-revision'),images:e.querySelectorAll('img').length}));
 assert.deepEqual(option,{text:'<img src=x onerror=alert(1)> & Item',revision,images:0});
 await page.evaluate(id=>remove_checklist_item_template(id),identifier);await page.waitForFunction(id=>!document.querySelector('select option[value="'+id+'"]'),identifier);
 assert.equal(captured.length,2);for(const request of captured){assert.equal(request.context,'issuer2:actor7');assert.equal(request.csrf,'native-token');assert.equal(request.values.csrf_name,'framework-token');assert.match(request.values.native_request_key,/^[a-f0-9]{64}$/);}
 assert.equal(captured[0].values.description,'<img src=x onerror=alert(1)> & Item');assert.equal(captured[1].values.native_revision,revision);assert.notEqual(captured[0].values.native_request_key,captured[1].values.native_request_key);assert.deepEqual(errors,[]);
 console.log('Real Chromium/jQuery checklist create/delete: scoped context, CSRF, revision, request identity and safe text verified against synthetic HTTP.');
 }finally{await browser.close();await new Promise(r=>server.close(r));}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
