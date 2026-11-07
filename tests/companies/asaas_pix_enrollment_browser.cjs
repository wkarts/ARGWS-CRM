const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const {readFileSync,mkdtempSync,rmSync}=require('node:fs');
const path=require('node:path'),os=require('node:os');
(async()=>{
 const dir=mkdtempSync(path.join(os.tmpdir(),'pix-private-'));
 try{
  const html=path.join(dir,'invoice.html');
  execFileSync(process.env.ASAAS_TEST_PHP||'php',['tests/companies/asaas_pix_portal_enrollment.php'],{env:{...process.env,ASAAS_PIX_ENROLLMENT_HTML:html},stdio:'pipe'});
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true});
  try{for(const width of [1280,375]){
   const page=await browser.newPage({viewport:{width,height:900}});let requests=0;await page.route('**/*',route=>{requests++;route.abort();});
   await page.setContent('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'+readFileSync(html,'utf8'));
   const qr=page.locator('section[aria-label="Pix privado"]');
   if(await qr.locator('svg[role="img"] path').count()!==1||!await qr.locator('#native-pix-payload').isVisible())throw Error('Private QR and copy/paste payload missing');
   if(await qr.locator('svg').evaluate(svg=>svg.getBoundingClientRect().width>window.innerWidth))throw Error('QR overflows viewport');
   if(await qr.locator('img,iframe,script').count()||requests!==0)throw Error('Remote resource in private QR');
   if(await page.evaluate(()=>localStorage.length).catch(()=>0))throw Error('Private data persisted');
   await page.close();
  }console.log('Native private Pix QR: actual PHP view in Chromium desktop/mobile, inline generated SVG and copy/paste, no external resource requests.');}finally{await browser.close();}
 }finally{rmSync(dir,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exit(1);});
