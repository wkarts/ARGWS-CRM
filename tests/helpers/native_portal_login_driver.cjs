'use strict';
const assert=require('node:assert/strict');
async function loginNativePortal(page,base,issuer,email,password){
 assert.match(String(issuer),/^[1-9]\d*$/);
 const response=await page.goto(base+'/authentication/login?issuer_id='+issuer);
 assert(response);assert.equal(response.status(),200,'official portal login must be reachable');
 const form=page.locator('form.login-form');assert.equal(await form.count(),1);
 assert.equal(await form.locator('input[name=issuer_id]').inputValue(),String(issuer));
 assert.match(await form.locator('input[name=_native_portal_csrf]').inputValue(),/^[a-f0-9]{64}$/);
 await form.locator('input[name=email]').fill(email);await form.locator('input[name=password]').fill(password);
 const trace=[];const onResponse=response=>{const pathname=new URL(response.url()).pathname;if(/\/(authentication|clients|verification)(\/|$)/.test(pathname))trace.push({status:response.status(),path:pathname});};page.on('response',onResponse);
 const [result]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[type=submit]').click()]);
 page.off('response',onResponse);
 if(await page.locator('#greeting').count()!==1){
  const notices=await page.locator('.alert,[role=alert],.text-danger').allTextContents();
  console.error(JSON.stringify({nativePortalLogin:{trace,cookies:(await page.context().cookies()).map(c=>({name:c.name,path:c.path,secure:c.secure,domain:c.domain})),status:result?.status(),title:await page.title(),destination:new URL(page.url()).pathname,notices:notices.map(v=>v.trim().slice(0,250)),loginForm:await page.locator('form.login-form').count(),emailFieldPresent:await page.locator('form.login-form input[name=email]').count()}}));
 }
 assert.equal(await page.locator('#greeting').count(),1,'official contact login must establish the scoped session; destination='+new URL(page.url()).pathname);
}
module.exports={loginNativePortal};
