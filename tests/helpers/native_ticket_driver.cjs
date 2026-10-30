'use strict';
const assert = require('node:assert/strict');
const { picker } = require('./native_invoice_driver.cjs');

async function hidden(form) {
  return form.locator('input[type=hidden]').evaluateAll(xs => Object.fromEntries(xs.filter(x => x.name).map(x => [x.name, x.value])));
}
function current(page) {
  const id = new URL(page.url()).pathname.split('/').pop();
  assert.match(id, /^[1-9]\d*$/);
  assert.equal(BigInt(id) / 4294967296n, 4n, 'official ticket route carries its typed source');
  return { id, raw: String(BigInt(id) % 4294967296n) };
}
async function submit(page, form) {
  const target = new URL(await form.getAttribute('action'), page.url());
  const [response] = await Promise.all([
    page.waitForResponse(r => r.request().method()==='POST' && new URL(r.url()).pathname===target.pathname),
    form.locator('button[type=submit]').click(),
  ]);
  const request=response.request();
  const replay=await page.context().request.post(request.url(), {data:request.postDataBuffer(),headers:{'Content-Type':request.headers()['content-type']},maxRedirects:0});
  if(replay.status()>=400){const body=await replay.text();console.error('Original ticket error:',body.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').slice(0,1600));}
  assert.equal(replay.status(),303,'original ticket command and durable retry really redirect with 303');
  const location=new URL(replay.headers().location,target);
  assert.match(location.pathname,/^\/admin\/tickets\/ticket\/[1-9]\d*$/);
  await page.waitForURL(u=>u.pathname===location.pathname);
  await page.locator('.ticket-thread').first().waitFor({state:'visible'});
  return { request, ticket:current(page) };
}
async function create(page,url,sql,{issuer,client,subject,body}) {
  // Configure the prerequisite using the existing department form, never a
  // synthetic ticket writer or a DOM-injected department option.
  if(sql('SELECT COUNT(*) FROM tblcompany_departments WHERE issuer_id='+issuer)==='0') {
    assert.equal((await page.goto(url+'/admin/departments')).status(),200);
    await page.locator('a[onclick*="new_department"]').click();
    await page.locator('#department-form #name').fill('Synthetic support department');
    const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/departments/department'),page.locator('#department-form button[type=submit]').click()]);
    assert.equal(response.status(),200);assert.equal((await response.json()).success,true);
  }
  const contact=sql('SELECT id FROM tblcontacts WHERE userid='+client+' AND active=1 ORDER BY id LIMIT 1');
  assert.match(contact,/^[1-9]\d*$/,'fixture has a real authorized customer contact');
  assert.equal((await page.goto(url+'/admin/tickets/add?userid='+client+'&contact_id='+contact)).status(),200);
  await page.waitForFunction(value=>document.querySelector('#new_ticket_form input[name=userid]')?.value===value,client);
  await page.locator('#subject').fill(subject);
  const department=await page.locator('#department option').evaluateAll(xs=>xs.find(x=>x.value)?.value);
  const priority=await page.locator('#priority option').evaluateAll(xs=>xs.find(x=>x.value)?.value);
  assert(department&&priority);await picker(page,'#department',department);await picker(page,'#priority',priority);
  const editor=page.frameLocator('#message_ifr').locator('body');await editor.fill(body);
  return submit(page,page.locator('#new_ticket_form'));
}
async function reply(page,body) { await page.locator('#native-ticket-message').fill(body);return submit(page,page.locator('#ticket-reply')); }
async function status(page,code) { return submit(page,page.locator('form[data-ticket-status="'+code+'"]')); }
module.exports={hidden,current,submit,create,reply,status};
