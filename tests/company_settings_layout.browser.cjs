'use strict';
// Component comparison, not full CRM/session/screenshots. Both parents are real PHP
// views; their I/O is synthetic. Native CSS is loaded unchanged from the repository.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),out=process.env.SCREENSHOT_DIR||'/tmp/settings-envelope-fixture';fs.mkdirSync(out,{recursive:true});
const php=file=>JSON.parse(execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,file),'--html-json'],{encoding:'utf8'}));
const variants={native:php('company_settings_layout.php').native,scoped:php('company_settings_native.php').editable};
const assets=['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/builds/vendor-admin.css','assets/plugins/font-awesome/css/fontawesome.min.css','assets/plugins/font-awesome/css/brands.min.css','assets/plugins/font-awesome/css/solid.min.css','assets/plugins/font-awesome/css/regular.min.css','assets/builds/tailwind.css','assets/css/style.min.css'];
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  for(const width of [1280,768,390]){
   const measured={};
   for(const [variant,html]of Object.entries(variants)){
    const page=await browser.newPage({viewport:{width,height:950}}),errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    await page.route('**/*',route=>{
     const url=new URL(route.request().url()),relative=decodeURIComponent(url.pathname).slice(1),file=path.resolve(root,relative);
     if(url.origin!=='https://synthetic.invalid'||!relative.startsWith('assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.abort();
     return route.fulfill({body:fs.readFileSync(file),contentType:({'.css':'text/css','.woff2':'font/woff2','.woff':'font/woff'})[path.extname(file)]||'application/octet-stream'});
    });
    try{
     await page.setContent('<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+assets.map(a=>'<link rel="stylesheet" href="https://synthetic.invalid/'+a+'">').join('')+'</head><body class="admin"><p>CI synthetic fixture — shared Settings envelope only</p><div class="content" id="fixture"></div></body></html>');
     await page.evaluate(html=>{
      const source=new DOMParser().parseFromString(html,'text/html'),component=source.querySelector('.company-native-settings');
      if(!component)throw Error('Actual parent omitted shared component');
      // Only server-rendered envelope, not unrelated parent runtime scripts.
      document.querySelector('#fixture').appendChild(document.importNode(component,true));
     },html);
     await page.evaluate(()=>document.fonts.ready);
     const form=page.locator('form');assert.equal(await form.count(),1);
     const baseline=await form.evaluate(f=>JSON.stringify([...new FormData(f)]));
     assert.equal(await page.locator('form form').count(),0);
     assert.equal(await form.locator('button[type=submit]').evaluate(b=>b.form.id),await form.getAttribute('id'));
     measured[variant]=await page.evaluate(()=>{
      const selectors=['.company-native-settings > div','.company-native-settings .col-md-4','.company-native-settings .col-md-8','nav.panel_s','nav .panel-body','nav h4','nav .settings-group-company a','.company-native-settings .col-md-8 > h4','form .panel-footer button'];
      const result={overflow:document.documentElement.scrollWidth>innerWidth,items:{}};
      for(const selector of selectors){const el=document.querySelector(selector);if(!el)throw Error('Missing '+selector);const r=el.getBoundingClientRect(),s=getComputedStyle(el);result.items[selector]={x:r.x,width:r.width,font:s.fontFamily,fontSize:s.fontSize,fontWeight:s.fontWeight,color:s.color,background:s.backgroundColor,padding:s.padding,border:s.border};}
      return result;
     });
     assert.equal(measured[variant].overflow,false,variant+' overflow at '+width);
     assert.equal(await form.evaluate(f=>JSON.stringify([...new FormData(f)])),baseline,'render/measure cannot alter fields');
     await page.screenshot({path:path.join(out,'settings-envelope-synthetic-'+variant+'-'+width+'.png'),fullPage:true,mask:[page.locator('input,textarea,select')]});
     assert.deepEqual(errors,[]);
    }catch(error){fs.writeFileSync(path.join(out,'settings-envelope-'+variant+'-'+width+'-failure.json'),JSON.stringify({variant,width,type:error.name,geometry:measured[variant]||null}));throw error;}
    finally{await page.close();}
   }
   for(const [selector,native]of Object.entries(measured.native.items)){
    const scoped=measured.scoped.items[selector];
    // Button text may be localized differently by synthetic adapters; compare
    // actual visual tokens, not content-dependent width or vertical form length.
    const keys=selector.endsWith('button')?['font','fontSize','fontWeight','color','background','padding','border']:Object.keys(native);
    for(const key of keys)assert.equal(scoped[key],native[key],width+' '+selector+' '+key);
   }
   fs.writeFileSync(path.join(out,'settings-envelope-synthetic-'+width+'.json'),JSON.stringify(measured));
  }
  console.log('PASS shared native/scoped envelope computed styles, responsive grid, real PHP parents and unchanged form ownership; synthetic component fixture only');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
