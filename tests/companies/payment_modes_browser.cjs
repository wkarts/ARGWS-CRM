'use strict';
// Real disposable CRM/MySQL/Chromium. Only offline catalogue records; no payments/providers.
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {selectCompanyOption}=require('../helpers/native_selector_driver.cjs');
const base=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(db||'',/^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function sql(query){
 assert.match(query,/^SELECT /);
 return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();
}
function id(value){assert.match(String(value),/^[1-9]\d*$/);return String(value);}
(async()=>{
 const browser=await chromium.launch({headless:true}),context=await browser.newContext();
 await context.route('**/*',r=>new URL(r.request().url()).origin===base?r.continue():r.abort());
 const page=await context.newPage(),errors=[];page.on('pageerror',()=>errors.push('browser-runtime-error'));
 const owners=[['primary',sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1')],['a',sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'")],['b',sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'")]];
 const saved=[];
 const snapshot=()=>sql('SELECT SHA2(COALESCE(GROUP_CONCAT(CONCAT_WS(\':\',id,issuer_id,name,description,active,revision) ORDER BY id SEPARATOR \'|\'),\'\'),256) FROM tblcompany_payment_modes');
 const legacySnapshot=()=>sql("SELECT SHA2(COALESCE(GROUP_CONCAT(CONCAT_WS(':',id,name,description,active) ORDER BY id SEPARATOR '|'),''),256) FROM tblpayment_modes");
 const allSnapshot=()=>snapshot()+':'+legacySnapshot();
 async function select(issuer){await page.goto(base+'/admin/');await selectCompanyOption(page,id(issuer));assert.equal((await page.goto(base+'/admin/paymentmodes')).status(),200);await page.locator('.table-payment-modes').waitFor({state:'visible'});}
 async function mutation(path,click){
  const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/paymentmodes/'+path),click()]);
  assert.equal(response.status(),200);const result=await response.json();assert.equal(result.success,true);return{response,result};
 }
 function capture(request){
  const headers=request.headers(),form=Object.fromEntries(new URLSearchParams(request.postData()));
  const nativeHeaders={'x-company-context':headers['x-company-context'],'x-native-company-csrf':headers['x-native-company-csrf'],'x-requested-with':'XMLHttpRequest'};
  assert(nativeHeaders['x-company-context']&&nativeHeaders['x-native-company-csrf']&&form.csrf_token_name);
  return{headers:nativeHeaders,csrf:form.csrf_token_name,form};
 }
 try{
  await page.goto(base+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[type=submit]').click()]);
  for(const [letter,issuer] of owners){
   await select(issuer);const name='PR53 offline GUI '+letter;
   const table=letter==='primary'?'tblpayment_modes':'tblcompany_payment_modes',where=letter==='primary'?'1=1':'issuer_id='+id(issuer);const before=allSnapshot();assert.equal(sql("SELECT COUNT(*) FROM "+table+" WHERE "+where+" AND name='"+name+"'"),'0');
   await page.locator('a.btn-primary[data-target="#payment_mode_modal"]').click();
   const form=page.locator('#payment_modes_form');await form.locator('[name=name]').fill(name);await form.locator('[name=description]').fill('Literal <b>offline</b>\nSecond line');
   const created=await mutation('manage',()=>form.locator('button[type=submit]').click());
   await page.locator('#payment_mode_modal').waitFor({state:'hidden'});
   const wire=capture(created.response.request()),mode=id(created.result.id);assert.match(created.result.revision,/^[a-f0-9]{64}$/);
   let row=page.locator('.table-payment-modes tbody tr').filter({hasText:name});await row.waitFor({state:'visible'});
   assert.notEqual(allSnapshot(),before);assert.equal(sql("SELECT COUNT(*) FROM "+table+" WHERE "+where+" AND name='"+name+"' AND active=1"),'1');
   if(saved.length){
    const foreign=saved.at(-1),unchanged=allSnapshot();
    assert.equal((await context.request.post(base+'/admin/paymentmodes/delete/'+foreign.mode,{headers:wire.headers,form:{revision:foreign.revision,csrf_token_name:wire.csrf}})).status(),403);
    assert.equal((await context.request.post(base+'/admin/paymentmodes/delete/'+foreign.mode,{headers:foreign.wire.headers,form:{revision:foreign.revision,csrf_token_name:foreign.wire.csrf}})).status(),409);
    assert.equal(allSnapshot(),unchanged,'foreign issuer and stale context leave all catalogues unchanged');
   }
   await row.locator('td').nth(1).locator('a').click();assert.equal(await form.locator('[name=description]').inputValue(),'Literal <b>offline</b>\nSecond line');await form.locator('[name=name]').fill(name+' edited');
   const edited=await mutation('manage',()=>form.locator('button[type=submit]').click());await page.locator('#payment_mode_modal').waitFor({state:'hidden'});assert.notEqual(edited.result.revision,created.result.revision);
   row=page.locator('.table-payment-modes tbody tr').filter({hasText:name+' edited'});await row.waitFor({state:'visible'});
   const unchanged=allSnapshot();
   assert.equal((await context.request.post(base+'/admin/paymentmodes/manage',{headers:wire.headers,form:{...wire.form,paymentmodeid:mode,revision:created.result.revision}})).status(),409);
   const noCsrf={...wire.headers};delete noCsrf['x-native-company-csrf'];
   assert.equal((await context.request.post(base+'/admin/paymentmodes/delete/'+mode,{headers:noCsrf,form:{revision:edited.result.revision,csrf_token_name:wire.csrf}})).status(),403);
   assert.equal((await context.request.get(base+'/admin/paymentmodes/delete/'+mode)).status(),405);
   assert.equal(allSnapshot(),unchanged,'stale revision, missing native CSRF and GET cannot mutate');
   await mutation('change_payment_mode_status/'+mode+'/0',()=>row.locator('label.onoffswitch-label').click());
   await page.waitForFunction(mode=>{const input=document.getElementById('c_'+mode);return input&&!input.checked;},mode);
   assert.equal(sql("SELECT active FROM "+table+" WHERE "+where+" AND name='"+name+" edited'"),'0');
   const revision=await row.locator('input.onoffswitch-checkbox').getAttribute('data-revision');
   saved.push({issuer,mode,revision,wire,table,where,name:name+' edited'});
  }
  for(const item of saved){
   await select(item.issuer);const row=page.locator('.table-payment-modes tbody tr').filter({hasText:item.name});await row.waitFor({state:'visible'});
   page.once('dialog',dialog=>dialog.accept());await mutation('delete/'+item.mode,()=>row.locator('a._delete').click());await row.waitFor({state:'detached'});
   assert.equal(sql("SELECT COUNT(*) FROM "+item.table+" WHERE "+item.where+" AND name='"+item.name+"'"),'0');
  }
  assert.deepEqual(errors,[]);console.log('Offline payment modes real GUI primary/A/B: create/edit/literal description/toggle/delete, JSON response, stale revision/context, cross-company, native CSRF and GET denial PASS; no payment or provider transaction.');
 }finally{await context.close();await browser.close();}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
