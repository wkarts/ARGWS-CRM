'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {chromium}=require('playwright');
(async()=>{
 const html=fs.readFileSync(process.env.BB_BOLETO_HTML,'utf8');
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage({viewport:{width:1100,height:1200}});
  let requests=0;await page.route('**/*',route=>{requests++;return route.abort();});
  await page.setContent(html);await page.emulateMedia({media:'print'});
  const geometry=await page.evaluate(()=>{
   const box=s=>{const r=document.querySelector(s).getBoundingClientRect();return{x:r.x,y:r.y,w:r.width,h:r.height,bottom:r.bottom,right:r.right};};
   const svg=document.querySelector('.barcode svg');const raw=document.querySelector('.barcode svg g').getBBox();
   return {viewBox:svg.viewBox.baseVal.width,rawWidth:raw.width,sheet:box('.sheet'),ficha:box('.compensation'),bar:box('.barcode svg'),bars:box('.barcode svg g'),line:box('.line'),bank:box('.bank'),toolbar:getComputedStyle(document.querySelector('.toolbar')).display};
  });
  const mm=96/25.4;
  assert(Math.abs(geometry.ficha.w/mm-190)<0.1);
  assert(Math.abs(geometry.ficha.h/mm-105)<0.1);
  assert(Math.abs(geometry.bar.w/mm-103)<0.1);
  assert(Math.abs(geometry.bar.h/mm-13)<0.1);
  assert(geometry.viewBox>0,'barcode defines a coordinate transform');
  assert(Math.abs(geometry.bars.w-geometry.rawWidth*geometry.bar.w/geometry.viewBox)<0.2,'all bars scale including the I25 trailing white module');
  assert(geometry.bars.right<=geometry.bar.right+0.2,'last bar is never clipped');
  assert(Math.abs(geometry.bars.h-geometry.bar.h)<0.2,'encoded bars occupy full13mm height');
  assert(geometry.bar.x-geometry.ficha.x>=5*mm-0.2,'left barcode quiet zone');
  assert(geometry.ficha.bottom-(geometry.bar.y+geometry.bar.h/2)>=12*mm-0.2,'bottom barcode center clearance');
  assert(geometry.line.right<=geometry.bank.right+0.2,'digit line fits paper');
  assert(geometry.bar.bottom<=geometry.ficha.bottom,'barcode fits compensation slip');
  assert.equal(geometry.toolbar,'none');assert.equal(requests,0,'print document makes no external requests');
  assert(await page.getByText('HOMOLOGAÇÃO — NÃO PAGAR',{exact:true}).count());
  const output=process.env.BB_BOLETO_SCREENSHOT;if(output)await page.screenshot({path:output,fullPage:true});
  console.log('CNAB boleto Chromium:190x105mm slip,103x13mm I25, quiet zones, line fit, print toolbar and no external requests passed. Synthetic SQL-produced HTML; no authenticated HTTP or bank validation.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
