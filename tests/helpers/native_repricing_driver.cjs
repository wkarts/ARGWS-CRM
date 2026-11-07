'use strict';
const assert=require('node:assert/strict');
/** Real original CRM forms. Caller supplies its already guarded disposable SQL reader. */
async function repriceFuture({page,url,issuer,schedule,sql}){
 assert.match(String(issuer),/^[1-9]\d*$/);assert.match(String(schedule),/^[1-9]\d*$/);
 const description='Synthetic reviewed repricing '+issuer+' '+schedule+' '+require('node:crypto').randomBytes(4).toString('hex');
 const invoices=()=>sql('SELECT CONCAT(id,":",total,":",status) FROM tblinvoices WHERE issuer_id='+issuer+' ORDER BY id');
 const calendar=()=>sql('SELECT CONCAT(next_date,":",completed_cycles,":",state,":",payload_hash) FROM tblcompany_invoice_schedules WHERE issuer_id='+issuer+' AND id='+schedule);
 const invoiceBefore=invoices(),calendarBefore=calendar();
 await page.goto(url+'/admin/invoice_items');await page.locator('a[data-target="#sales_item_modal"]').click();
 const itemForm=page.locator('#invoice_item_form');await itemForm.locator('[name=description]').fill(description);await itemForm.locator('[name=rate]').fill('100.00');
 const [saved]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoice_items/manage'),itemForm.locator('button[type=submit]').click()]);assert.equal(saved.status(),200);
 const itemSource=sql("SELECT id FROM tblcompany_items WHERE issuer_id="+issuer+" AND description='"+description+"'");assert.match(itemSource,/^[1-9]\d*$/);
 assert.equal((await page.goto(url+'/admin/invoices/recurring?reprice=1')).status(),200);
 const row=page.locator('tr[data-schedule="'+schedule+'"][data-line="0"]');assert.equal(await row.locator('.reprice-selected').isDisabled(),true,'legacy line requires explicit review');
 await page.locator('#reprice-search').fill(description);await page.locator('#reprice-search-button').click();await page.waitForFunction(()=>document.getElementById('reprice-search-status').textContent.startsWith('1 itens'));
 const item=await row.locator('.reprice-link option').evaluateAll((xs,name)=>xs.find(x=>x.textContent.startsWith(name+' (#'))?.value,description);assert.match(item||'',/^[1-9]\d*$/);
 await row.locator('.reprice-link').selectOption(item);page.once('dialog',d=>d.accept());
 const [review]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),row.locator('.reprice-review').click()]);assert.equal(review.status(),200);
 await row.locator('.reprice-selected').check();await page.locator('.reprice-rule select').selectOption(item);await page.locator('.reprice-rule input').fill('125.00');await page.locator('#reprice-timezone').selectOption('America/Sao_Paulo');
 const [preview]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#reprice-form button[type=submit]').click()]);assert.equal(preview.status(),200);
 assert.match(await page.locator('#reprice-report').innerText(),/100\.00 → 125\.00/);assert.equal(await page.locator('#reprice-print').isVisible(),true);
 assert.equal(invoices(),invoiceBefore,'simulation does not modify issued invoices');assert.equal(calendar(),calendarBefore,'simulation does not advance calendar or overwrite source');
 const confirm=page.locator('form').filter({has:page.locator('[name=action][value=confirm]')});
 const fields=await confirm.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));
 const endpoint=await confirm.getAttribute('action');
 const denied=await page.context().request.post(endpoint,{form:{...fields,reviewed:'1',company_invoice_reprice_csrf:'invalid'}});assert.equal(denied.status(),403,'invalid repricing CSRF denied');
 assert.equal(invoices(),invoiceBefore);assert.equal(calendar(),calendarBefore);
 await confirm.locator('[name=reviewed]').check();const [applied]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),confirm.locator('button[type=submit]').click()]);assert.equal(applied.status(),200);
 assert.match(await page.locator('#reprice-report').innerText(),/Aplicado às bases futuras/);assert.equal(await page.locator('[name=action][value=confirm]').count(),0);
 assert.equal(invoices(),invoiceBefore,'confirmation preserves every issued invoice');assert.equal(calendar(),calendarBefore,'confirmation preserves calendar and original snapshot');
 assert.equal(Number(sql('SELECT rate FROM tblcompany_items WHERE issuer_id='+issuer+' AND id='+itemSource)),100,'catalog price unchanged');
 const prices=sql('SELECT COUNT(*) FROM tblcompany_invoice_schedule_prices WHERE issuer_id='+issuer+' AND schedule_id='+schedule);assert.equal(prices,'1','one future journal entry');
 const journal=JSON.parse(sql('SELECT payload_json FROM tblcompany_invoice_schedule_prices WHERE issuer_id='+issuer+' AND schedule_id='+schedule));assert.equal(journal.items[0].rate,'125.00');
 const replay=await page.context().request.post(endpoint,{form:{...fields,reviewed:'1'}});assert.equal(replay.status(),200,'same reviewed confirmation replays idempotently');
 assert.equal(sql('SELECT COUNT(*) FROM tblcompany_invoice_schedule_prices WHERE issuer_id='+issuer+' AND schedule_id='+schedule),prices);assert.equal(invoices(),invoiceBefore);assert.equal(calendar(),calendarBefore);
 return {proposalUrl:page.url(),item,requestKey:fields.request_key};
}
module.exports={repriceFuture};
