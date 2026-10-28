'use strict';
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
// Existing installer master creates companies through the normal wizard, with no new designation.
const assert=require('node:assert/strict'), {execFileSync}=require('node:child_process'), fs=require('node:fs'), {chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL, db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/); assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
assert.match(db||'',/^argws-crm-provision-db-\d+$/);
function sql(query) { return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim(); }
async function submit(page,button) { await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]); }
(async()=>{
 const identity=sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"); assert.equal(identity,'17:1:installer');
 const browser=await chromium.launch({headless:true}),context=await browser.newContext({viewport:{width:1280,height:960}}),page=await context.newPage(),errors=[];
 await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort()); page.on('pageerror',e=>errors.push(e.message));
 try {
  await page.goto(url+'/admin/authentication'); await page.locator('input[name=email]').fill('admin-ci@example.invalid'); await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD); await submit(page,page.locator('button[type=submit]'));
  assert.equal((await context.request.get(url+'/admin/company_master')).status(),404,'retired responsibility controller is physically absent');
  assert.equal((await page.goto(url+'/admin/issuers')).status(),200);assert.equal(await page.locator('[data-new-company]').count(),1,'persisted installer master administers companies from the original registry without another procedure');
  const created={};
  for (const [label,tax] of [['a','11222333000181'],['b','52998224725']]) {
   let id=sql("SELECT id FROM tblissuers_registry WHERE tax_id='"+tax+"'");
   if (!id) {
    await page.goto(url+'/admin/issuers/onboarding');
    await page.locator('#company-create-modal').waitFor({state:'visible'});
    const wizard = page.frame({name:'company-create-frame'});
    await wizard.locator('section form button[type=submit]').waitFor();
    await submit(wizard,wizard.locator('section form button[type=submit]'));
    await wizard.locator('#legal_name').fill('Synthetic native453 '+label.toUpperCase()); await wizard.locator('#tax_id').fill(tax); await wizard.locator('a[href="#company-address"]').click(); await wizard.locator('#profile_invoice_company_city').fill('Own city '+label);
    for (let step=1;step<=5;step++) { await submit(wizard,wizard.locator('section form button[type=submit]')); }
    assert.match(await wizard.locator('[role=status]').innerText(),/Empresa criada/); id=sql("SELECT id FROM tblissuers_registry WHERE tax_id='"+tax+"'");
    assert.match(id,/^[1-9]\d*$/); assert.equal(sql("SELECT COUNT(*) FROM tblissuers_registry WHERE tax_id='"+tax+"'"),'1');
   }
   created[label]=Number(id);
   await page.goto(url+'/admin/'); await selectCompanyOption(page, id);
   for (const route of ['staff','company_task_recurring?kind=project','company_task_recurring?kind=standalone']) { assert.equal((await page.goto(url+'/admin/'+route)).status(),200,route+' usable for existing master'); }
  }
  assert.equal(sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"),identity); assert.deepEqual(errors,[]);
  if(process.env.ISSUERS_BROWSER_ARTIFACTS){fs.mkdirSync(process.env.ISSUERS_BROWSER_ARTIFACTS,{recursive:true});fs.writeFileSync(process.env.ISSUERS_BROWSER_ARTIFACTS+'/installed-master.json',JSON.stringify({identity,created,result:'PASS'},null,2)+'\n');await page.screenshot({path:process.env.ISSUERS_BROWSER_ARTIFACTS+'/installed-master-companies.png',fullPage:true});}
  console.log('Existing installer master17/principal1, native wizard A/B, no repeated designation, positive staff and both recurrence pages PASS.');
 } catch (error) {
  console.error(JSON.stringify({path:new URL(page.url()).pathname,body:(await page.locator('body').innerText().catch(()=>'' )).slice(0,1200)}));
  throw error;
 } finally { await context.close(); await browser.close(); }
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
