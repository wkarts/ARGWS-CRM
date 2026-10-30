'use strict';
// Real PHP partials, native field helpers and Chromium with synthetic A/B data.
// This does not admit or exercise authenticated CRM Settings routes.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
const php=process.env.PHP_BINARY||'php';
const views={};
for(const group of ['estimates','proposals'])for(const company of ['a','b']){
  views[group+'/'+company]=execFileSync(php,['tests/companies/native_sales_settings_view_fixture.php',group,company],{cwd:root,encoding:'utf8'});
}
const server=http.createServer((req,res)=>{
  const pathname=new URL(req.url,'http://127.0.0.1').pathname;
  if(pathname.startsWith('/view/')){const body=views[pathname.slice(6)];if(!body)return res.writeHead(404).end();return res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}).end(body);}
  if(!pathname.startsWith('/assets/'))return res.writeHead(404).end();
  const file=path.resolve(root,'.'+pathname);if(!file.startsWith(path.join(root,'assets')+path.sep))return res.writeHead(404).end();
  fs.readFile(file,(error,data)=>{if(error)return res.writeHead(404).end();const ext=path.extname(file);res.writeHead(200,{'Content-Type':ext==='.css'?'text/css':ext==='.woff2'?'font/woff2':'application/octet-stream'});res.end(data);});
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const options={headless:true};if(process.env.CHROMIUM_PATH)options.executablePath=process.env.CHROMIUM_PATH;
 const browser=await chromium.launch(options);
 try{
  const origin='http://127.0.0.1:'+server.address().port;
  for(const viewport of [{width:1280,height:800},{width:390,height:844}]){
   const page=await browser.newPage({viewport});
   for(const group of ['estimates','proposals'])for(const company of ['a','b']){
    await page.goto(origin+'/view/'+group+'/'+company);
    const prefix=group==='estimates'?'estimate_prefix':'proposal_number_prefix';
    const project=group==='estimates'?'show_project_on_estimate':'show_project_on_proposal';
    const expected=(group==='estimates'?'EST-':'PROP-')+company.toUpperCase()+'-';
    const values=await page.locator('#settings-form').evaluate(form=>Object.fromEntries(new FormData(form).entries()));
    assert.equal(values[`settings[${prefix}]`],expected);
    assert.equal(values[`settings[${project}]`],company==='a'?'0':'1');
    assert.equal(Object.keys(values).length,group==='estimates'?17:12);
    const metrics=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('*')].map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,left:e.getBoundingClientRect().left,text:e.textContent?.slice(0,90),sw:e.scrollWidth,cw:e.clientWidth})).filter(e=>e.right>innerWidth+1||e.left< -1||e.sw>e.cw+1).slice(0,12)}));
    assert.ok(metrics.scroll<=metrics.width+1,'no horizontal overflow '+group+'/'+viewport.width+': '+JSON.stringify(metrics));
    if(group==='proposals')assert.equal(await page.getByText('Campo privado A').count(),company==='a'?1:0);
   }
   await page.close();
  }
  process.stdout.write('Original sales Settings partials render owned A/B values at desktop and mobile widths. No authenticated HTTP tested.\n');
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
