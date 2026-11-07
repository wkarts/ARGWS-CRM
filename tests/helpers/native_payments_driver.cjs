'use strict';
const assert=require('node:assert/strict');
async function paymentsTable(page,url){
 const match=r=>new URL(r.url()).pathname.endsWith('/payments/table')&&r.request().method()==='POST';
 const loaded=page.waitForResponse(match);
 assert.equal((await page.goto(url+'/admin/payments')).status(),200,'original payments page responds');
 const response=await loaded;assert.equal(response.status(),200,'initial payments data responds');
 assert.ok(Array.isArray((await response.json()).aaData),'payments table uses native response contract');
 await page.locator('.table-payments').waitFor({state:'visible'});
 const filtered=page.waitForResponse(match);await page.locator('.dataTables_filter input').fill('synthetic-no-match-parity');
 assert.equal((await filtered).status(),200,'native payments search responds');
 await page.waitForFunction(()=>!document.querySelector('.dataTables_processing')||getComputedStyle(document.querySelector('.dataTables_processing')).display==='none');
}
module.exports={paymentsTable};
