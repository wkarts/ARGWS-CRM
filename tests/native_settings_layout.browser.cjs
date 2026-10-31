'use strict';
// Responsive rendering of the official Settings parent with local assets and
// synthetic framework I/O. Only the official parent is loaded.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),out=process.env.SCREENSHOT_DIR||'/tmp/settings-envelope-fixture';fs.mkdirSync(out,{recursive:true});
const rendered=JSON.parse(execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'native_settings_layout.php'),'--html-json'],{encoding:'utf8'})).native;
const assets=['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/builds/vendor-admin.css','assets/plugins/font-awesome/css/fontawesome.min.css','assets/plugins/font-awesome/css/brands.min.css','assets/plugins/font-awesome/css/solid.min.css','assets/plugins/font-awesome/css/regular.min.css','assets/builds/tailwind.css','assets/css/style.min.css'];
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
 try{
  for(const width of [1280,768,390]){
   const page=await browser.newPage({viewport:{width,height:950}}),errors=[],failedAssets=[];
   page.on('pageerror',error=>errors.push(error.message));
   page.on('requestfailed',request=>failedAssets.push({path:new URL(request.url()).pathname,error:request.failure()?.errorText}));
   await page.route('**/*',route=>{
    const url=new URL(route.request().url()),relative=decodeURIComponent(url.pathname).slice(1),file=path.resolve(root,relative);
    if(url.origin!=='https://synthetic.invalid'||!relative.startsWith('assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.abort();
    return route.fulfill({body:fs.readFileSync(file),contentType:({'.css':'text/css','.woff2':'font/woff2','.woff':'font/woff'})[path.extname(file)]||'application/octet-stream'});
   });
   try{
    await page.setContent('<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+assets.map(asset=>'<link rel="stylesheet" href="https://synthetic.invalid/'+asset+'">').join('')+'</head><body class="admin"><p>CI synthetic fixture — official Settings envelope</p><div class="content" id="fixture"></div></body></html>');
    await page.evaluate(html=>{const source=new DOMParser().parseFromString(html,'text/html'),component=source.querySelector('.company-native-settings');if(!component)throw Error('Official Settings parent omitted shared component');document.querySelector('#fixture').appendChild(document.importNode(component,true));},rendered);
    await page.evaluate(()=>document.fonts.ready);
    const form=page.locator('#settings-form');assert.equal(await form.count(),1);const baseline=await form.evaluate(node=>JSON.stringify([...new FormData(node)]));
    assert.equal(await page.locator('form form').count(),0);assert.equal(await form.locator('button[type=submit]').evaluate(button=>button.form.id),'settings-form');
    const geometry=await page.evaluate(()=>{const selectors=['.company-native-settings > div','.company-native-settings .col-md-4','.company-native-settings .col-md-8','nav.panel_s','nav .panel-body','nav h4','nav .settings-group-company a','.company-native-settings .col-md-8 > h4','form .panel-footer button'];const items={};for(const selector of selectors){const element=document.querySelector(selector);if(!element)throw Error('Missing '+selector);const rect=element.getBoundingClientRect(),style=getComputedStyle(element);items[selector]={x:rect.x,width:rect.width,font:style.fontFamily,fontSize:style.fontSize,fontWeight:style.fontWeight,color:style.color,background:style.backgroundColor,padding:style.padding,border:style.border};}return {viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,overflow:document.documentElement.scrollWidth>innerWidth,items};});
    assert.equal(geometry.overflow,false,'official Settings overflow at '+width);assert.equal(await form.evaluate(node=>JSON.stringify([...new FormData(node)])),baseline,'render and measurement preserve official form fields');assert.deepEqual(errors,[]);assert.deepEqual(failedAssets,[]);
    fs.writeFileSync(path.join(out,'settings-envelope-official-'+width+'.json'),JSON.stringify({width,geometry,pageErrors:errors,failedAssets},null,2));
    await page.screenshot({path:path.join(out,'settings-envelope-official-'+width+'.png'),fullPage:true,mask:[page.locator('input,textarea,select')]});
   }finally{await page.close();}
  }
  console.log('PASS official Settings envelope responsive layout, form ownership and local assets; synthetic component fixture only');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
