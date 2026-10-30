'use strict';
const assert=require('node:assert/strict');
const {randomBytes}=require('node:crypto');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {workspacePage}=require('../helpers/native_workspace_driver.cjs');
const {selectCompanyOption}=require('../helpers/native_selector_driver.cjs');
const projectDriver=require('../helpers/native_project_driver.cjs');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function sql(query){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
async function command(page,form){
  const endpoint=new URL(await form.getAttribute('action'),url).pathname;
  const [response]=await Promise.all([page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname===endpoint),form.locator('button[type=submit]').click()]);
  const request=response.request(),headers={'content-type':request.headers()['content-type']};
  const replay=await page.context().request.post(request.url(),{data:request.postDataBuffer(),headers,maxRedirects:0});
  assert.equal(replay.status(),303,'original project action and exact replay return real 303: '+endpoint);
  const target=new URL(replay.headers().location,url);await page.waitForURL(u=>u.pathname===target.pathname&&u.search===target.search);
  const stale=new URLSearchParams(request.postData());stale.set('request_key',randomBytes(32).toString('hex'));
  return{endpoint,headers,request,stale};
}
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const context=await browser.newContext();await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
  const page=await workspacePage(context),errors=[],failedTaskRequests=[];
  page.on('pageerror',e=>errors.push(e.message));
  // Record the real failing HTTP route/status instead of suppressing the DataTables warning.
  page.on('response',response=>{
   if(response.status()<400)return;
   const pathname=new URL(response.url()).pathname;
   if(!pathname.includes('/tasks/'))return;
   failedTaskRequests.push({path:pathname,status:response.status(),method:response.request().method()});
  });
  await page.goto(url+'/admin/authentication');await page.locator('[name=email]').fill('admin-ci@example.invalid');await page.locator('[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
  await Promise.all([page.waitForNavigation(),page.locator('button[type=submit]').click()]);
  const primary=sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
  const companies=[['11222333000181','a'],['52998224725','b'],[null,'primary']],created=[];
  for(const[tax,letter]of companies){
   const issuer=tax===null?primary:sql("SELECT id FROM tblissuers_registry WHERE tax_id='"+tax+"'");
   await page.goto(url+'/admin/');await selectCompanyOption(page,issuer);
   let client=sql("SELECT userid FROM tblclients WHERE company='"+(tax===null?'Browser primary project customer':'Synthetic portal client '+letter)+"'");
   if(tax===null&&client===''){
    await page.goto(url+'/admin/clients/client');await page.locator('#company').fill('Browser primary project customer');
    await Promise.all([page.waitForNavigation(),page.locator('#profile-save-section .only-save').click()]);
    client=sql("SELECT userid FROM tblclients WHERE company='Browser primary project customer'");
   }
   assert.match(client,/^[1-9]\d*$/);
   const project=await projectDriver.create(page,url,{client,name:'Original actions '+letter+' '+randomBytes(4).toString('hex'),description:'Original project tab composition'});created.push(project);
   for(const group of ['project_tasks','project_milestones','project_discussions','project_timesheets','project_gantt']){
    const target=url+'/admin/projects/view/'+project.id+'?group='+group;
    assert.equal((await page.goto(target)).status(),200,'original project tab '+group);
    assert.equal((await context.request.head(target)).status(),200,'HEAD preserves project tab '+group);
    assert.equal(await page.locator('form#native-project-file-upload').count(),0,'selected tab does not duplicate unrelated file form');
   }
   await page.goto(url+'/admin/projects/view/'+project.id+'?group=project_milestones');
   let form=page.locator('#project-milestone-form');await form.locator('[name=name]').fill('Original milestone '+letter);await form.locator('[name=due_date]').fill('2026-11-05');
   let sent=await command(page,form);
   assert.equal(sql('SELECT COUNT(*) FROM tblcompany_project_milestones WHERE issuer_id='+issuer+' AND project_id='+project.raw),'1');
   assert.equal((await context.request.post(sent.request.url(),{data:sent.stale.toString(),headers:sent.headers,maxRedirects:0})).status(),409,'old parent revision rejects another milestone');
   await page.locator('#project-milestone-rules a').filter({hasText:'Original milestone '+letter}).click();
   form=page.locator('#milestone-details-form');await form.locator('[name=start_date]').fill('2026-10-20');await form.locator('[name=description]').fill('Stored milestone <script> remains literal');await form.locator('[name=sort_order]').fill('3');
   sent=await command(page,form);
   assert.equal(sql('SELECT CONCAT(start_date,CHAR(58),description,CHAR(58),sort_order) FROM tblcompany_milestone_details WHERE issuer_id='+issuer+' AND project_id='+project.raw),'2026-10-20:Stored milestone <script> remains literal:3');
   assert.equal((await context.request.post(sent.request.url(),{data:sent.stale.toString(),headers:sent.headers,maxRedirects:0})).status(),409,'old milestone detail revision is rejected');
   await page.goto(url+'/admin/projects/view/'+project.id+'?group=project_discussions');
   form=page.locator('#project-discussion-open');await form.locator('[name=discussion_subject]').fill('Original discussion '+letter);await form.locator('[name=discussion_body]').fill('Private original body');await command(page,form);
   const thread=sql('SELECT id FROM tblcompany_project_discussions WHERE issuer_id='+issuer+' AND project_id='+project.raw);
   assert.match(thread,/^[1-9]\d*$/);assert.equal(await page.locator('#project-discussion-detail h3').innerText(),'Original discussion '+letter);
   form=page.locator('#project-discussion-comment');await form.locator('[name=discussion_body]').fill('Original comment '+letter+' <script>');sent=await command(page,form);
   assert.equal(sql('SELECT COUNT(*) FROM tblcompany_project_discussion_posts WHERE issuer_id='+issuer+' AND project_id='+project.raw+' AND discussion_id='+thread),'1');
   assert.equal((await context.request.post(sent.request.url(),{data:sent.stale.toString(),headers:sent.headers,maxRedirects:0})).status(),409,'stale discussion revision is rejected');
   form=page.locator('.project-discussion-own-edit');await form.locator('[name=discussion_body]').fill('Edited original '+letter);await command(page,form);
   assert((await page.locator('#project-discussion-posts').innerText()).includes('Edited original '+letter));
   const post=sql('SELECT id FROM tblcompany_project_discussion_posts WHERE issuer_id='+issuer+' AND project_id='+project.raw+' AND discussion_id='+thread);
   form=page.locator('.project-discussion-own-delete');await command(page,form);
   assert.equal(sql('SELECT active FROM tblcompany_project_discussion_posts WHERE id='+post),'0','original delete retires the comment and preserves its history');
   const noCsrf=new URLSearchParams(sent.request.postData());noCsrf.delete('company_project_rules_csrf');noCsrf.set('request_key',randomBytes(32).toString('hex'));
   assert.equal((await context.request.post(sent.request.url(),{data:noCsrf.toString(),headers:sent.headers,maxRedirects:0})).status(),403,'project action requires its genuine CSRF');
   const foreign=sql("SELECT id FROM tblissuers_registry WHERE tax_id='"+(letter==='a'?'52998224725':'11222333000181')+"'");
   await page.goto(url+'/admin/');await selectCompanyOption(page,foreign);
   assert.equal((await context.request.get(url+'/admin/projects/view/'+project.id+'?group=project_discussions')).status(),403,'foreign project cannot be read through an original tab');
   console.log('Original project actions '+letter+': tabs GET/HEAD, milestone/details, discussion/comments/edit/retirement, real 303/replay, 409, CSRF and isolation PASS');
  }
  assert.deepEqual({errors,failedTaskRequests},{errors:[],failedTaskRequests:[]},'original project tabs have no JavaScript errors or failed task Ajax requests');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
