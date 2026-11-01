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
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[type=submit]').click()]);
 assert.equal(await page.locator('#greeting').count(),1,'official contact login must establish the scoped session');
}
module.exports={loginNativePortal};
