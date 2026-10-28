'use strict';
const { selectCompanyOption } = require('../helpers/company_selector_driver.cjs');
const { workspacePage } = require('../helpers/company_workspace_driver.cjs');
// Rebuilt recovery proof. Business objects and declarations are created through the CRM UI.
// The only SQL mutation adjusts a synthetic running timer's start, avoiding a real one-hour wait.
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const {chromium}=require('playwright');
const base=process.env.ISSUERS_BROWSER_URL;
const db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
const output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';
assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
fs.mkdirSync(output,{recursive:true});
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function submit(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}
async function hidden(form){return form.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.ISSUERS_BROWSER_EXECUTABLE_PATH?{executablePath:process.env.ISSUERS_BROWSER_EXECUTABLE_PATH}:{})});
 const context=await browser.newContext();
 await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
 const page=await workspacePage(context),errors=[],results=[];
 page.on('pageerror',error=>errors.push(error.message));
 const stamp=Date.now();let staleA;
 try{
  await page.goto(base+'/admin/authentication');
  await page.locator('[name=email]').fill('admin-ci@example.invalid');
  await page.locator('[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
  await submit(page,page.locator('button[type=submit]'));
  const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'");
  const b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
  assert.match(a,/^[1-9]\d*$/);assert.match(b,/^[1-9]\d*$/);
  const cashBefore=sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords');
  async function select(issuer){await page.goto(base+'/admin/');await selectCompanyOption(page, issuer);}
  async function makeTimer(table,task,seconds){
   await submit(page,page.getByRole('button',{name:'Iniciar meu cronômetro',exact:true}));
   assert.equal(sql('SELECT COUNT(*) FROM '+table+' WHERE task_id='+task+' AND stopped_at IS NULL'),'1');
   sql('UPDATE '+table+' SET started_at=DATE_SUB(started_at,INTERVAL '+seconds+' SECOND) WHERE task_id='+task+' AND stopped_at IS NULL');
   await submit(page,page.getByRole('button',{name:'Parar meu cronômetro',exact:true}));
   return sql('SELECT id FROM '+table+' WHERE task_id='+task+' ORDER BY id DESC LIMIT 1');
  }
  async function cancelAndRetain(invoice,sourceUrl,table,field,source){
   await page.goto(base+'/admin/invoices?invoice='+invoice);
   await page.getByRole('tab',{name:'Editar fatura sem movimento',exact:true}).click();
   await submit(page,page.getByRole('button',{name:'Cancelar fatura sem movimento',exact:true}));
   assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+invoice),'5');
   await page.goto(sourceUrl);
   assert.equal(sql('SELECT state FROM '+table+' WHERE '+field+'='+source),'converted');
   assert.equal(await page.locator('#billing-invoice-status').innerText(),'Cancelada');
  }
  for(const [issuer,letter] of [[a,'a'],[b,'b']]){
   await select(issuer);
   const client=sql("SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id="+issuer+" AND c.company='Synthetic portal client "+letter+"' ORDER BY c.userid LIMIT 1");
   assert.match(client,/^[1-9]\d*$/);
   await page.goto(base+'/admin/tasks/standalone');
   await page.locator('#task-new-name').fill('Recovery439 standalone '+letter+' '+stamp);
   await page.locator('#task-new-due').fill('2026-11-19');
   await submit(page,page.locator('form').filter({has:page.locator('#task-new-name')}).locator('button[type=submit]'));
   const standalone=new URL(page.url()).searchParams.get('task');assert.match(standalone||'',/^[1-9]\d*$/);
   await makeTimer('tblcompany_standalone_timers',standalone,3600);
   await page.locator('#task-status').selectOption('done');
   await submit(page,page.locator('form').filter({has:page.locator('#task-edit-name')}).locator('button[type=submit]'));
   const standaloneUrl=base+'/admin/invoices/standalone_billing?task='+standalone;
   await page.goto(standaloneUrl);
   await page.locator('#standalone-billing-client').selectOption(client);
   await page.locator('#standalone-billing-rate').fill('100.00');
   await page.locator('#standalone-billing-date').fill('2026-10-19');
   await page.locator('#standalone-billing-due').fill('2026-11-19');
   await page.locator('#standalone-billing-confirm').check();
   await submit(page,page.locator('#standalone-billing-review'));
   assert.match(await page.locator('#standalone-billing-total').innerText(),/100\.00/);
   const conversion=await hidden(page.locator('form').filter({has:page.locator('#standalone-billing-issue')}));
   if(letter==='a')staleA=conversion;
   await submit(page,page.locator('#standalone-billing-issue'));
   const wholeInvoice=sql('SELECT invoice_id FROM tblcompany_standalone_billing_links WHERE task_id='+standalone);
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+wholeInvoice),'100.00');
   assert.equal((await context.request.post(base+'/admin/invoices/standalone_billing',{form:conversion})).status(),200);
   assert.equal(sql('SELECT invoice_id FROM tblcompany_standalone_billing_links WHERE task_id='+standalone),wholeInvoice);
   await cancelAndRetain(wholeInvoice,standaloneUrl,'tblcompany_standalone_billing_links','task_id',standalone);
   // Project and discussion also originate through their real forms.
   await page.goto(base+'/admin/projects');
   await page.locator('#project-client').selectOption(client);
   await page.locator('#project-new-name').fill('Recovery439 project '+letter+' '+stamp);
   await page.locator('#project-new-start').fill('2026-10-19');
   await page.locator('#project-new-due').fill('2026-11-19');
   await submit(page,page.locator('form').filter({has:page.locator('#project-client')}).locator('button[type=submit]'));
   const project=new URL(page.url()).searchParams.get('project');assert.match(project||'',/^[1-9]\d*$/);
   const discussionUrl=base+'/admin/projects/portal_rules?project='+project;
   await page.goto(discussionUrl);
   await page.locator('#project-discussion-subject').fill('Private recovery thread '+letter);
   await page.locator('#project-discussion-body').fill('Internal body <script> stays text');
   await submit(page,page.locator('#project-discussion-open button[type=submit]'));
   const thread=new URL(page.url()).searchParams.get('discussion');assert.match(thread||'',/^[1-9]\d*$/);
   assert.equal(sql('SELECT visible FROM tblcompany_project_discussions WHERE id='+thread),'0');
   await page.locator('#project-discussion-comment-body').fill('Original internal response '+letter);
   await submit(page,page.locator('#project-discussion-comment button[type=submit]'));
   const post=sql('SELECT id FROM tblcompany_project_discussion_posts WHERE discussion_id='+thread+' ORDER BY id DESC LIMIT 1');
   const row=page.locator('#project-discussion-posts li[data-post="'+post+'"]');
   await row.locator('.project-discussion-own-edit textarea[name=discussion_body]').fill('Revised internal response '+letter);
   await submit(page,row.locator('.project-discussion-own-edit button[type=submit]'));
   assert.equal(sql('SELECT revision FROM tblcompany_discussion_post_heads WHERE post_id='+post),'2');
   const pdf=Buffer.from('%PDF-1.4\nSynthetic recovery browser evidence\n%%EOF\n');
   await row.locator('.project-discussion-file-upload input[type=file]').setInputFiles({name:'proof.pdf',mimeType:'application/pdf',buffer:pdf});
   await submit(page,row.locator('.project-discussion-file-upload button[type=submit]'));
   const fileForm=row.locator('.project-discussion-file-download').first();
   const download=await context.request.post(base+'/admin/projects/portal_rules',{form:await hidden(fileForm)});
   assert.equal(download.status(),200);assert.match(download.headers()['content-type'],/application\/pdf/);assert((await download.body()).equals(pdf));
   // A task with two independently closed timers exercises whole-timer selection and cross-project batch forms.
   await page.goto(base+'/admin/tasks?project='+project);
   await page.locator('#task-new-name').fill('Recovery439 selected task '+letter+' '+stamp);
   await page.locator('#task-new-due').fill('2026-11-01');
   await submit(page,page.locator('form').filter({has:page.locator('#task-new-name')}).locator('button[type=submit]'));
   const task=new URL(page.url()).searchParams.get('task');assert.match(task||'',/^[1-9]\d*$/);
   const timer1=await makeTimer('tblcompany_task_timers',task,1800);
   const timer2=await makeTimer('tblcompany_task_timers',task,1800);
   await page.goto(base+'/admin/projects/time_billing?project='+project);
   await page.locator('#time-billing-selection').selectOption('selected_timers');
   await page.locator('input[name="billing[timer_ids][]"][value="'+timer1+'"]').check();
   await page.locator('#time-billing-price-source').selectOption('explicit');
   await page.locator('#time-billing-rate').fill('100.00');
   await page.locator('#time-billing-date').fill('2026-10-19');await page.locator('#time-billing-due').fill('2026-11-19');
   await page.locator('#time-billing-confirm').check();await submit(page,page.locator('#time-billing-review'));
   const timeOrigin=new URL(page.url()).searchParams.get('origin');assert.match(timeOrigin||'',/^[1-9]\d*$/);
   await submit(page,page.locator('#time-billing-issue'));
   const timeInvoice=sql('SELECT invoice_id FROM tblcompany_time_billing_batches WHERE origin_id='+timeOrigin);
   assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+timeInvoice),'50.00');
   await cancelAndRetain(timeInvoice,base+'/admin/projects/time_billing?project='+project+'&origin='+timeOrigin,'tblcompany_time_billing_batches','origin_id',timeOrigin);
   await page.goto(base+'/admin/tasks/time_batches?kind=projects&client='+client);
   const timerField=page.locator('input[name="billing[timers][]"]').filter({visible:true});
   const timerChoices=await timerField.evaluateAll(xs=>xs.map(x=>({value:x.value,checked:x.checked})));
   const chosen=timerChoices.find(x=>x.value.split(':')[0]===timer2);assert(chosen,'remaining timer appears in native batch GUI');
   await page.locator('input[name="billing[timers][]"][value="'+chosen.value+'"]').check();
   await page.locator('#time-batch-price-source').selectOption('explicit');await page.locator('#time-batch-rate').fill('100.00');
   await page.locator('#time-batch-date').fill('2026-10-19');await page.locator('#time-batch-due').fill('2026-11-19');await page.locator('#time-batch-confirm').check();
   await submit(page,page.locator('#time-batch-review'));
   const batchOrigin=new URL(page.url()).searchParams.get('origin');assert.match(batchOrigin||'',/^[1-9]\d*$/);
   await submit(page,page.locator('#time-batch-issue'));
   const batchInvoice=sql('SELECT invoice_id FROM tblcompany_task_time_batches WHERE origin_id='+batchOrigin);assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+batchInvoice),'50.00');
   await cancelAndRetain(batchInvoice,base+'/admin/tasks/time_batches?origin='+batchOrigin,'tblcompany_task_time_batches','origin_id',batchOrigin);
   results.push({issuer,standalone,wholeInvoice,project,thread,post,task,timeInvoice,batchInvoice,private_file:true,claims_retained_after_cancellation:true});
  }
  assert.equal((await context.request.post(base+'/admin/invoices/standalone_billing',{form:staleA})).status(),409,'saved A form cannot act while B active');
  // Refresh B deliberately: foreign ownership and stale A context are distinct native contracts.
  await select(b);
  assert.equal((await page.goto(base+'/admin/projects/portal_rules?project='+results[1].project+'&discussion='+results[1].thread)).status(),200,'current B project context is valid');
  const freshB=await hidden(page.locator('#project-discussion-comment'));
  assert.ok(freshB._company_context&&freshB.company_project_rules_csrf,'native B form carries current scope and dedicated CSRF');
  assert.equal(freshB.project_id,results[1].project);
  assert.equal((await context.request.get(base+'/admin/invoices/standalone_billing?task='+results[0].standalone)).status(),403,'standalone ownership contract is403 under fresh B');
  const foreignProject=await context.request.get(base+'/admin/projects/portal_rules?project='+results[0].project);
  assert.equal(foreignProject.status(),409,'legacy433 project editor ownership contract is409 with unavailable state');
  const unavailable=await foreignProject.text();assert.ok(unavailable.includes('Referências, publicação ou permissões indisponíveis. Nenhuma alteração foi confirmada.'));
  for(const privateText of ['Recovery439 project a '+stamp,'Private recovery thread a','Original internal response a','Revised internal response a','proof.pdf'])assert.ok(!unavailable.includes(privateText),'foreign editor emits no A source text/file: '+privateText);
  assert.ok(!unavailable.includes('id="project-discussion-posts"')&&!unavailable.includes('id="project-discussion-open"'),'foreign editor renders no discussion state or controls');
  assert.equal((await page.goto(base+'/admin/invoices?invoice='+results[1].wholeInvoice)).status(),200,'current B invoice is accessible before cross-owner PDF request');
  const foreignPdf=await context.request.get(base+'/admin/invoices?invoice='+results[0].wholeInvoice+'&pdf=1');
  assert.equal(foreignPdf.status(),403,'foreign private invoice PDF has the explicit403 ownership contract');
  assert.ok(!String(foreignPdf.headers()['content-type']||'').includes('application/pdf'));
  assert.ok(!(await foreignPdf.body()).subarray(0,5).equals(Buffer.from('%PDF-')),'foreign PDF request emits no PDF bytes');
  assert.equal(sql('SELECT COUNT(*) FROM tblinvoicepaymentrecords'),cashBefore,'new operational billing creates receivables and no payments');
  assert.deepEqual(errors,[]);
  const proof={source:'rebuilt recovery439 test;442 exact legacy433 ownership forward expectation',ownership_contracts:{stale_A_form:409,standalone_under_current_B:403,project_editor_under_current_B:409,private_invoice_PDF_under_current_B:403},results,timer_fixture:'only started_at of GUI-created active synthetic timers adjusted; no hidden business configuration',external_transports:false};
  fs.writeFileSync(output+'/operations-resume439.json',JSON.stringify(proof,null,2));console.log(JSON.stringify(proof));
 }finally{await context.close();await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
