const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
// Reconstructed HTTP440 runner. Original recovered440 assertions retained; native schema must preexist.
'use strict';
const native=require('../helpers/native_invoice_driver.cjs');
const {invoiceScheduleId}=require('../helpers/native_invoice_schedule_driver.cjs');
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{createHash}=require('node:crypto'),fs=require('node:fs'),{chromium}=require('playwright');
const url=process.env.ISSUERS_BROWSER_URL,app=process.env.ISSUERS_BROWSER_APP_CONTAINER,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
const output=process.env.ISSUERS_BROWSER_ARTIFACTS||'/tmp/issuer-browser-evidence';fs.mkdirSync(output,{recursive:true});
execFileSync('docker',['exec',app,'mkdir','-p','/app/tests/companies','/app/tests/support']);
for(const rel of['tests/companies/invoice_recurring_modes_http_fixture.php','tests/support/BbPdoDatabase.php'])execFileSync('docker',['cp',rel,app+':/app/'+rel]);
const key=s=>createHash('sha256').update(s).digest('hex'),batch=key('recurring-modes-browser:'+Date.now());
function sql(q){const compound=/^\s*CREATE\s+TRIGGER\b/i.test(q)&&/\bBEGIN\b/i.test(q);return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names',...(compound?['--delimiter=//']:[]),'-e',compound?q+'//':q],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function hidden(f){return f.locator('input[type=hidden]').evaluateAll(xs=>Object.fromEntries(xs.filter(x=>x.name).map(x=>[x.name,x.value])));}
async function submit(p,b){await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}),b.click()]);}
(async()=>{
 const fixture=JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME','-e','ISSUERS_RECURRING_MODES_BATCH='+batch,app,'php','tests/companies/invoice_recurring_modes_http_fixture.php'],{env:process.env,encoding:'utf8'}));
 assert.ok(Number.isInteger(fixture.expected_marker)&&fixture.expected_marker>=440);assert.equal(fixture.native_marker,fixture.expected_marker);assert.equal(fixture.read_only,true);assert.equal(fixture.schemas.length,5);
 const browser=await chromium.launch({headless:true,...(process.env.ISSUERS_BROWSER_EXECUTABLE_PATH?{executablePath:process.env.ISSUERS_BROWSER_EXECUTABLE_PATH}:{})});let context;
 try{
  context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await workspacePage(context),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(url+'/admin/authentication');await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);await submit(page,page.locator('button[type=submit]'));
  const form=action=>page.locator('form').filter({has:page.locator('input[name=action][value="'+action+'"]')});
  async function select(issuer){await page.goto(url+'/admin/');await selectCompanyOption(page, String(issuer));}
  async function schedule(owner,mode,principal,cycles,notificationProbed=false){
   const [tableResponse]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoices/table'&&new URL(r.url()).searchParams.get('recurring')==='1'),page.goto(url+'/admin/invoices/recurring')]);
   assert.equal(tableResponse.status(),200,'original recurring directory loads actual owned schedule data');
   const columns=await page.evaluate(()=>{const table=window.jQuery('.table-recurring-invoices').DataTable();return{total:table.columns().count(),visible:table.columns(':visible').count(),yearVisible:table.column(2).visible(),yearClass:table.column(2).header().classList.contains('not_visible')};});
   assert.equal(columns.total,8,'original recurring DataTable keeps all eight functional columns');
   assert.equal(columns.visible,7,'seven original columns remain visible');
   assert.equal(columns.yearVisible,false,'original year column remains hidden by the native table policy');
   assert.equal(columns.yearClass,true,'hidden column is exactly the original year header, not a dropped field');
   const createLink=page.getByRole('link',{name:'Nova recorrência',exact:true});
   assert.equal(await createLink.count(),1,'authorized recurring directory exposes one creation link for mode '+mode);
   await createLink.waitFor({state:'visible'});
   const target=new URL(await createLink.getAttribute('href'),page.url());assert.equal(target.pathname,'/admin/invoices/recurring');assert.equal(target.searchParams.get('new'),'1');
   await submit(page,createLink);const f=form('create');await f.locator('select[name="invoice[client_id]"]').selectOption(String(owner.client));await f.locator('select[name="invoice[currency]"]').selectOption({label:'BRL'});await f.locator('input[name="invoice[date]"]').fill('2026-01-01');await f.locator('input[name="invoice[due_date]"]').fill('2026-01-10');await f.locator('input[name="invoice[items][0][description]"]').fill('Private recurring mode '+mode);await f.locator('input[name="invoice[items][0][rate]"]').fill('10.10');await f.locator('input[name=cycles]').fill(String(cycles));await f.locator('select[name=mode]').selectOption(mode);await f.locator('input[name=principal_invoice_id]').fill(String(principal));assert.equal(await f.locator('#schedule-send[name=send][type=checkbox]').count(),1,'actual notification checkbox is a valid named form control');assert.equal(await f.locator('#schedule-send').isChecked(),false,'notifications are opt-in');
   if(mode==='draft'&&!notificationProbed){
    const before=sql('SELECT CONCAT((SELECT COUNT(*) FROM tblcompany_invoice_schedules),"/",(SELECT COUNT(*) FROM tblinvoices),"/",(SELECT COALESCE(SUM(next_number),0) FROM tblissuers_sequences),"/",(SELECT COUNT(*) FROM tblmail_queue))');
    await f.locator('#schedule-send').check();assert.equal(await f.evaluate(x=>new FormData(x).get('send')),'1','real browser form includes checked notification');
    const [denied]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),f.locator('button[type=submit]').click()]);assert.equal(denied.status(),422,'draft auto-send is rejected');
    assert.equal(sql('SELECT CONCAT((SELECT COUNT(*) FROM tblcompany_invoice_schedules),"/",(SELECT COUNT(*) FROM tblinvoices),"/",(SELECT COALESCE(SUM(next_number),0) FROM tblissuers_sequences),"/",(SELECT COUNT(*) FROM tblmail_queue))'),before,'invalid draft notification has no schedule, invoice, sequence or queue side effect');
    // Validation page does not repopulate a failed form: reopen and refill the independent valid attempt.
    return schedule(owner,mode,principal,cycles,true);
   }
   await submit(page,f.locator('button[type=submit]'));const id=invoiceScheduleId(page.url());if(!id){fs.writeFileSync(output+'/mode-create-failure.html',await page.content());throw Error('Schedule create remained at '+page.url()+': '+await page.locator('body').innerText());}assert.match(id||'',/^[1-9]\d*$/);
   const saved={id,url:page.url(),run:await hidden(form('run'))};
   if(!notificationProbed){
    await page.goto(url+'/admin/invoices/recurring');
    const table=page.locator('.table-recurring-invoices');await table.waitFor();
    await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/admin/invoices/table'),page.locator('.dataTables_filter input').fill('Série #'+id)]);
    const [preview]=await Promise.all([page.waitForResponse(r=>new URL(r.url()).pathname==='/admin/invoices/recurring_preview/'+id),page.locator('a[onclick="init_recurring_schedule('+id+'); return false;"]').click()]);
    assert.equal(preview.status(),200,'typed schedule split preview is served by actual HTTP');
    await page.locator('#invoice #invoice-schedule-detail').waitFor();
    assert.equal(await page.locator('#invoice #wrapper').count(),0,'split preview does not nest another application shell');
    await page.screenshot({path:output+'/invoice-recurring-directory.png',fullPage:true});
    await page.goto(saved.url);
   }
   return saved;}
  const own={};
  for(const letter of['a','b']){
   const owner=own[letter]={...fixture.owners[letter]};await select(owner.issuer);
   await native.createForm(page,url,String(owner.client));await native.date(page,'#date','2026-01-01');await native.date(page,'#duedate','2026-01-10');await native.line(page,{description:'Synthetic declared principal '+letter,rate:'10.10'});owner.principal=(await native.save(page,url)).id;assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+owner.principal),'10.10');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+owner.principal),'1');

   const paid=owner.paid=await schedule(owner,'issue',owner.principal,2);const invoiceCount=sql('SELECT COUNT(*) FROM tblinvoices');await submit(page,form('run').locator('button[type=submit]'));
   assert.equal(sql('SELECT COUNT(*) FROM tblinvoices'),invoiceCount);assert.equal(sql('SELECT completed_cycles FROM tblcompany_invoice_schedules WHERE id='+paid.id),'0');assert((await page.locator('#invoice-schedule-detail').innerText()).includes('aguardando quitação'));
   await native.payment(page,url,owner.principal,'10.10','recurring-mode-principal-'+batch+'-'+letter,'Synthetic local receipt, no bank transfer');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+owner.principal),'2');
   await page.goto(paid.url);await submit(page,form('run').locator('button[type=submit]'));const child=sql('SELECT invoice_id FROM tblcompany_invoice_schedule_runs WHERE schedule_id='+paid.id+' ORDER BY run_date LIMIT 1');assert.match(child,/^[1-9]\d*$/);assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+child),'1');
   await submit(page,form('run').locator('button[type=submit]'));assert.equal(sql('SELECT completed_cycles FROM tblcompany_invoice_schedules WHERE id='+paid.id),'2');assert.equal(sql('SELECT status FROM tblinvoices WHERE id='+child),'1','condition uses original principal rather than unpaid previous child');
   const draft=owner.draft=await schedule(owner,'draft',0,1);const beforeInvoices=sql('SELECT COUNT(*) FROM tblinvoices'),beforeNumbers=sql('SELECT COALESCE(SUM(next_number),0) FROM tblissuers_sequences');
   if(letter==='a'){
    sql("CREATE TRIGGER tbl_recurring_mode_link_fault BEFORE INSERT ON tblcompany_invoice_schedule_drafts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic private link failure'");
    try{await submit(page,form('run').locator('button[type=submit]'));assert((await page.locator('body').innerText()).includes('mesma ocorrência'));}finally{sql('DROP TRIGGER tbl_recurring_mode_link_fault');}
    assert.equal(sql('SELECT state FROM tblcompany_invoice_schedules WHERE id='+draft.id),'processing');await page.goto(draft.url);assert((await page.locator('#invoice-schedule-detail').innerText()).includes('vínculo pendente'));
    const pending=sql("SELECT id FROM tblcompany_invoice_drafts WHERE origin_key='"+key('company-invoice-schedule:'+owner.issuer+':'+draft.id+':2026-01-01')+"'");assert.match(pending,/^[1-9]\d*$/);owner.pending=pending;
   }
   await submit(page,form('run').locator('button[type=submit]'));assert.equal(sql('SELECT COUNT(*) FROM tblinvoices'),beforeInvoices);assert.equal(sql('SELECT COALESCE(SUM(next_number),0) FROM tblissuers_sequences'),beforeNumbers);
   owner.private=sql('SELECT draft_id FROM tblcompany_invoice_schedule_drafts WHERE schedule_id='+draft.id);if(owner.pending)assert.equal(owner.private,owner.pending);assert.equal(sql('SELECT invoice_id IS NULL FROM tblcompany_invoice_schedule_runs WHERE schedule_id='+draft.id),'1');
   assert((await page.locator('#invoice-schedule-detail').innerText()).includes('Sem envio automático de rascunho'));await page.getByRole('link',{name:'Abrir rascunho privado #'+owner.private,exact:true}).click();assert((await page.locator('#invoice-draft-state').innerText()).includes('Rascunho privado'));
   const revision=Number(await page.locator('#invoice-draft-editor input[name=revision]').inputValue());await page.locator('#draft-note').fill('Reviewed private draft '+letter);await page.locator('input[name="payload[items][0][rate]"]').fill('11.10');await submit(page,page.locator('#draft-save'));assert.equal(await page.locator('#draft-note').inputValue(),'Reviewed private draft '+letter);assert.equal(Number(await page.locator('#invoice-draft-editor input[name=revision]').inputValue()),revision+1);assert.equal(await page.locator('input[name="payload[items][0][rate]"]').inputValue(),'11.10');assert.equal(sql('SELECT COUNT(*) FROM tblinvoices'),beforeInvoices,'draft edit does not create invoice');assert.equal(sql('SELECT COALESCE(SUM(next_number),0) FROM tblissuers_sequences'),beforeNumbers,'draft edit does not reserve numbering');
   await page.locator('#draft-confirm-issue').check();await submit(page,page.locator('#draft-issue'));
   const issued=sql('SELECT invoice_id FROM tblcompany_invoice_drafts WHERE id='+owner.private);assert.match(issued,/^[1-9]\d*$/);assert.equal(sql('SELECT total FROM tblinvoices WHERE id='+issued),'11.10','explicit issue uses saved reviewed values');await page.goto(draft.url);assert.equal(await page.getByRole('link',{name:'Abrir fatura',exact:true}).count(),1);
   const pdf=await context.request.get(url+'/admin/invoices/pdf/'+issued+'?output_type=I');assert.equal(pdf.status(),200);assert((await pdf.body()).subarray(0,4).equals(Buffer.from('%PDF')));
  }
  await select(own.b.issuer);await page.goto(url+'/admin/invoices/recurring?new=1');const current=await hidden(form('create'));
  assert.equal((await context.request.get(url+'/admin/invoices/recurring?schedule='+own.a.draft.id)).status(),403);assert.equal((await context.request.get(url+'/admin/invoices/recurring_preview/'+own.a.draft.id)).status(),403,'foreign split preview remains denied after switch');const foreignDraft=await context.request.get(url+'/admin/invoices/drafts?draft='+own.a.private);assert.equal(foreignDraft.status(),404,'private Drafts controller conceals foreign IDs with its exact unavailable contract');assert(!(await foreignDraft.text()).includes('Private recurring mode draft'),'foreign draft bytes remain undisclosed');
  assert.equal((await context.request.post(url+'/admin/invoices/recurring',{form:{...current,action:'run',schedule_id:own.a.paid.id,request_key:key('cross-'+batch)}})).status(),403);
  assert.equal((await context.request.post(url+'/admin/invoices/recurring',{form:own.a.paid.run})).status(),409);
  assert.equal((await context.request.post(url+'/admin/invoices/recurring',{form:{...current,action:'create',company_invoice_schedule_csrf:'invalid'}})).status(),403);
  assert.deepEqual(errors,[]);await page.screenshot({path:output+'/invoice-recurring-modes.png',fullPage:true});
  console.log('Invoice recurrence modes browser440 (reconstructed read-only prerequisite; GUI principal creation and draft edit; real checkbox): declared paid principal (not previous child), private drafts without native numbering, real late-link fault and exact same-ID resume, explicit later issue, PDF bytes, native contexts and cross-owner denial passed. No external bank or SMTP calls.');
 }finally{if(context)await context.close();await browser.close();}
})().catch(e=>{console.error(e.stack);if(e.stderr)console.error(String(e.stderr));process.exitCode=1;});
