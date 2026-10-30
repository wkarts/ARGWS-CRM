'use strict';
const assert=require('node:assert/strict');
const {picker}=require('./native_invoice_driver.cjs');
async function richText(page,id,text){
  if(await page.locator('#'+id+'_ifr').count())await page.frameLocator('#'+id+'_ifr').locator('body').fill(text);
  else await page.locator('#'+id).fill(text);
}
async function save(page){
  const form=page.locator('#project_form'),endpoint=new URL(await form.getAttribute('action'),page.url()).pathname;
  const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname===endpoint),form.locator('button[type=submit]').click()]);
  const request=response.request();
  const replay=await page.context().request.post(request.url(),{data:request.postDataBuffer(),headers:{'content-type':request.headers()['content-type']},maxRedirects:0});
  assert.equal(replay.status(),303,'original project command and durable retry return a real 303');
  const location=new URL(replay.headers().location,page.url());assert.match(location.pathname,/^\/admin\/projects\/view\/[1-9]\d*$/);
  await page.waitForURL(u=>u.pathname===location.pathname);
  const id=location.pathname.split('/').pop();assert.equal(BigInt(id)/4294967296n,1n);
  return{id,raw:String(BigInt(id)%4294967296n)};
}
async function create(page,url,{client,name,description}){
  assert.equal((await page.goto(url+'/admin/projects/project?customer_id='+client)).status(),200);
  await page.locator('#name').fill(name);await picker(page,'#billing_type','1');await page.locator('input[name=project_cost]').fill('120.00');
  const format=await page.evaluate(()=>app.options.date_format);
  assert(['d-m-Y','d/m/Y','m-d-Y','m.d.Y','m/d/Y','Y-m-d','d.m.Y'].includes(format),'official company date format is recognized');
  const date=(month)=>format.replace(/[dmY]/g,part=>({d:'19',m:month,Y:'2026'})[part]);
  await page.locator('#start_date').fill(date('10'));await page.locator('#deadline').fill(date('11'));await richText(page,'description',description);
  await page.locator('#send_created_email').uncheck();return save(page);
}
module.exports={create,save,richText};
