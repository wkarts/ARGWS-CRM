'use strict';
// Real jQuery and browser, with disposable local HTTP fixtures and no CRM session.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const files = {
  '/jquery.js': path.join(root, 'assets/plugins/jquery/jquery.min.js'),
  '/native-tasks.js': path.join(root, 'assets/js/native-tasks.js'),
  '/native-ui-tools.js': path.join(root, 'assets/js/native-ui-tools.js'),
};
const seen = [];
const server = http.createServer((req, res) => {
  const pathname = new URL(req.url, 'http://127.0.0.1').pathname.replace(/\/{2,}/g, '/');
  if (files[pathname]) {
    res.writeHead(200, {'Content-Type':'application/javascript; charset=utf-8'});
    fs.createReadStream(files[pathname]).pipe(res);
    return;
  }
  if (pathname === '/') {
    res.writeHead(200, {'Content-Type':'text/html; charset=utf-8'});
    res.end(`<!doctype html><html><body class="native-company-admin">
      <form id="task-form" action="/admin/tasks/task" method="post" enctype="multipart/form-data">
        <input name="name" value="synthetic"><button type="submit">Salvar</button>
      </form>
      <div id="_task_modal"></div><div id="task-modal"></div><button id="timer-row">timer</button>
      <select id="timer_add_task_id"></select><script src="/jquery.js"></script>
      <script>window.admin_url=location.origin+'/admin/';window.tinymce={triggerSave:function(){}};
      window.alert_float=function(){};jQuery.fn.modal=function(){return this};
      jQuery.fn.selectpicker=function(){return this};
      jQuery('#timer_add_task_id').data('AjaxBootstrapSelect',{list:{cache:{old:true}}});
      window._timer_id='15"] [onclick="injected';document.getElementById('timer-row').setAttribute('data-timer-id',window._timer_id);
      document.getElementById('timer-row').addEventListener('click',function(){window.timerClicked=true});
      jQuery('#timer_add_task_id').on('change',function(){window.changed=true});</script>
      <script src="/native-ui-tools.js"></script><script src="/native-tasks.js"></script></body></html>`);
    return;
  }
  if (pathname === '/admin/tasks/task' && req.method === 'POST') {
    seen.push('post');req.resume();req.on('end',()=>{
      res.writeHead(200, {'Content-Type':'application/json; charset=utf-8'});
      res.end(JSON.stringify({success:true,id:'214748364819',message:'ok'}));
    });return;
  }
  if (pathname === '/admin/tasks/get_task_by_id/214748364819' && req.method === 'GET') {
    seen.push('get');res.writeHead(200, {'Content-Type':'application/json; charset=utf-8'});
    res.end(JSON.stringify({id:'214748364819',name:'<img src=x onerror=alert(1)> & Test'}));return;
  }
  res.writeHead(404).end();
});
(async () => {
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
  try {
    const page=await browser.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto('http://127.0.0.1:'+server.address().port+'/');
    await page.evaluate(()=>task_form_handler(document.getElementById('task-form')));
    await page.waitForFunction(()=>document.querySelector('#timer_add_task_id option'));
    const result=await page.evaluate(()=>{const option=document.querySelector('#timer_add_task_id option');return {id:option.value,text:option.textContent,title:option.title,selected:option.selected,images:option.querySelectorAll('img').length,timerClicked:window.timerClicked,changed:window.changed,timerCleared:typeof window._timer_id==='undefined'};});
    assert.deepEqual(result,{id:'214748364819',text:'<img src=x onerror=alert(1)> & Test',title:'<img src=x onerror=alert(1)> & Test',selected:true,images:0,timerClicked:true,changed:true,timerCleared:true});
    assert.deepEqual(seen,['post','get']);assert.deepEqual(errors,[]);
    console.log('Real Chromium/jQuery: task creation response, timer lookup and hostile task label passed. Synthetic local HTTP only.');
  } finally {await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
