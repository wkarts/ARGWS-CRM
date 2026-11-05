'use strict';
const {salesSourceId,salesPublicId}=require('./helpers/native_sales_ids.cjs');
const { workspacePage } = require('./helpers/native_workspace_driver.cjs');
const originalSales=require('./helpers/native_sales_driver.cjs');
const { selectCompanyOption } = require('./helpers/native_selector_driver.cjs');
// Future overlay suite, after existing native sales/browser fixtures. No external providers or mail dispatch.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,app=process.env.ISSUERS_BROWSER_APP_CONTAINER,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function sql(q){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',q],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function nav(page,button){await originalSales.reveal(page,button);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);await originalSales.settle(page);}async function openSalesSettings(page,group='estimates'){const response=await originalSales.open(page,url+'/admin/settings?group='+group);assert.ok(response,'sales settings navigation returned no response');assert.equal(response.status(),200,'sales settings must be available for the selected company');}async function fields(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
(async()=>{const browser=await chromium.launch({headless:true}),context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await workspacePage(context),second=await workspacePage(context),errors=[];page.on('pageerror',e=>errors.push(e.message));try{
 await originalSales.open(page,url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await nav(page,page.locator('button[type=submit]'));
 const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'"),docs={};let stale;
 async function select(owner){await originalSales.open(page,url+'/admin/');await selectCompanyOption(page, owner);}
 for(const [owner,letter] of [[a,'A'],[b,'B']]){
  await select(owner);
  for(const [group,prefixField,termsField,prefix,terms] of [['estimates','estimate_prefix','predefined_terms_estimate',letter+'-ORC-','Quote terms '+letter+' <script>literal only</script>'],['proposals','proposal_number_prefix','predefined_terms_proposal',letter+'-PROP-','Proposal terms '+letter]]){
   await openSalesSettings(page,group);const form=page.locator('#settings-form');
   await form.locator('input[name="settings['+prefixField+']"]').fill(prefix);
   await form.locator('textarea[name="settings['+termsField+']"]').fill(terms);
   await nav(page,form.locator('button[type=submit]'));
   assert.equal(await page.locator('input[name="settings['+prefixField+']"]').inputValue(),prefix);
   assert.equal(await page.locator('script').filter({hasText:'literal only'}).count(),0);
  }
  if(owner===a){await openSalesSettings(second);stale=await fields(second.locator('form').filter({has:second.locator('input[name="settings[estimate_prefix]"]')}));}
  const client=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client "+letter.toLowerCase()+"'");for(const kind of ['estimates','proposals']){
   await originalSales.createForm(page,url,kind,client);await originalSales.line(page,kind,{description:'Preferences browser '+letter+' '+kind,rate:'100.00'});const id=(await originalSales.save(page,url,kind)).id;assert.match(id||'',/^[1-9]\d*$/);docs[owner+kind]=id;const prefix=letter+(kind==='estimates'?'-ORC-':'-PROP-');assert.match(await page.locator('body').innerText(),new RegExp(prefix+'\\d{6}'));assert.match(await page.locator('body').innerText(),new RegExp((kind==='estimates'?'Quote':'Proposal')+' terms '+letter));
   const pdf=await context.request.get(url+'/admin/'+kind+'?document='+id+'&pdf=1');assert.equal(pdf.status(),200);assert.equal((await pdf.body()).subarray(0,5).toString(),'%PDF-');await nav(page,page.getByRole('button',{name:'Registrar aceite',exact:true}));await nav(page,originalSales.command(page,'convert'));assert.equal(sql('SELECT state FROM tblcompany_sales_documents WHERE id='+salesSourceId(kind,id)),'converted');
  }
 }
 const before=sql('SELECT settings_json FROM tblissuers_registry WHERE id='+a);assert.equal((await context.request.post(url+'/admin/settings?group=estimates',{form:{...stale,'settings[estimate_prefix]':'STALE-MUST-NOT-WRITE'}})).status(),409,'old settings tab denied before any write');assert.equal(sql('SELECT settings_json FROM tblissuers_registry WHERE id='+a),before);
 await select(a);await openSalesSettings(page);const form=page.locator('form').filter({has:page.locator('input[name="settings[estimate_prefix]"]')});await form.locator('input[name="settings[estimate_prefix]"]').fill('NEW-');await nav(page,form.locator('button[type=submit]'));await originalSales.open(page,url+'/admin/estimates?document='+docs[a+'estimates']);assert.match(await page.locator('body').innerText(),/A-ORC-\d{6}/,'old source number stays frozen after graphical preference edit');assert.deepEqual(errors,[]);console.log('Future sales settings GUI A/B, snapshots, PDF/conversion and stale409 passed. No external mail/provider.');
}finally{await context.close();await browser.close();}})().catch(error=>{console.error(error.message);process.exitCode=1;});
