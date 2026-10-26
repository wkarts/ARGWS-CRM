'use strict';
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
// Actual native UI/controller/MySQL; canonical remote transport is simulated only by CLI fixture.
const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');const fs=require('node:fs');const {chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER,app=process.env.ISSUERS_BROWSER_APP_CONTAINER;assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');const output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';fs.mkdirSync(output,{recursive:true});
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
function fixture(letter,mode,origin=''){return JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_http_fixture.php',letter,mode,...(origin?[String(origin)]:[])],{encoding:'utf8'}));}
async function go(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
async function hidden(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
(async()=>{const browser=await chromium.launch({headless:true});const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
try{
 for(const file of ['tests/support/AsaasHttpNativeTarget.php','tests/companies/asaas_install_remote_http_fixture.php','tests/companies/asaas_http_fixture.php','tests/companies/asaas_preferences_fixture.php'])execFileSync('docker',['cp',file,app+':/app/'+file]);
 execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/companies/asaas_install_remote_http_fixture.php'],{stdio:'pipe'});
 await page.goto(url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await go(page,page.locator('button[type=submit]'));
 const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");let oldForm,originA;
 async function select(id){await page.goto(url+'/admin/');await selectCompanyOption(page, id);}
 for(const [issuer,letter]of[[a,'a'],[b,'b']]){
  await select(issuer);const x=fixture(letter,'prepare_unpaid');await page.goto(url+'/admin/company_asaas');const before=sql('SELECT status FROM tblinvoices WHERE id='+x.invoice);
  const form=page.locator('#asaas-origin-'+x.origin+' form').filter({has:page.locator('input[name=action][value=remote_remove]')});assert.equal(await form.count(),1,'actual pending origin offers scoped removal form');assert.equal(await form.locator('input[name=confirm][required]').count(),1);
  const fields=await hidden(form);assert.ok(fields._company_context&&Object.keys(fields).some(k=>k.includes('csrf')),'actual removal form contains company context and native CSRF');
  assert.equal(fixture(letter,'remote_remove',x.origin).state,'deleted','canonical CLI transport confirms provider deletion without real Asaas network');
  await form.locator('input[name=confirm]').check();await go(page,form.locator('button[type=submit]'));
  assert.ok((await page.locator('[role=status]').allTextContents()).some(s=>s.includes('fatura local')&&s.includes('preservados')),'controller idempotent retry reports local preservation');
  assert.equal(sql('SELECT COUNT(*) FROM tblasaas_company_remote_operations WHERE origin_id='+x.origin),'1');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+x.invoice),before);assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords WHERE invoiceid='+x.invoice),'0');
  assert.equal(await page.locator('#asaas-origin-'+x.origin+' input[name=action][value=remote_remove]').count(),0,'confirmed origin cannot submit another remote mutation');
  if(letter==='a'){oldForm={...fields,confirm:'1'};originA=x.origin;}
 }
 assert.equal((await context.request.post(url+'/admin/company_asaas',{form:oldForm})).status(),409,'stale company A remote form denied before effects');
 const ownToken=await hidden(page.locator('form').filter({has:page.locator('input[name=action][value=rotate]')}).first());assert.equal((await context.request.post(url+'/admin/company_asaas',{form:{...ownToken,action:'remote_remove',origin_id:String(originA),confirm:'1'}})).status(),422,'current B token cannot remove A origin');
 assert.equal(await page.locator('#asaas-origin-'+originA).count(),0,'B DOM contains no A origin');await page.screenshot({path:output+'/company-asaas-remote.png',fullPage:true});assert.deepEqual(errors,[]);console.log('Asaas150 actual A/B UI/controller scoped removal, CSRF/stale/isolation/local preservation/retry passed. Canonical GET/DELETE transport simulated through CLI; no external bank homologation.');
}finally{await browser.close();}})().catch(e=>{console.error(e.message);process.exitCode=1;});
