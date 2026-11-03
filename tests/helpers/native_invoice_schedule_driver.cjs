'use strict';
const assert=require('node:assert/strict');
function invoiceScheduleId(value){
 const url=new URL(value),match=url.pathname.match(/\/admin\/invoices\/recurring(?:\/([1-9]\d*))?\/?$/);
 assert(match,'expected the canonical invoice recurrence route');
 const query=url.searchParams.get('schedule');
 if(match[1]&&query)assert.equal(match[1],query,'conflicting schedule references');
 const id=match[1]||query;assert.match(id||'',/^[1-9]\d*$/,'schedule creation must redirect to a positive schedule ID');return id;
}
function invoiceScheduleForm(page,action){assert(['create','run','pause','resume','cancel'].includes(action));return page.locator('form').filter({has:page.locator('input[name=action][value="'+action+'"]')});}
module.exports={invoiceScheduleId,invoiceScheduleForm};
