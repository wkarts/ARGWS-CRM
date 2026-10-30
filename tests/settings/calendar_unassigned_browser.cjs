'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const {workspacePage}=require('../helpers/native_workspace_driver.cjs');
const {selectCompanyOption}=require('../helpers/native_selector_driver.cjs');
const env=process.env,base=env.ISSUERS_BROWSER_URL,app=env.ISSUERS_BROWSER_APP_CONTAINER,output=env.ISSUERS_BROWSER_ARTIFACTS;
assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(app||'',/^argws-crm-upgrade-\d+$/);assert.equal(env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function fixture(mode){return JSON.parse(execFileSync('docker',['exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',app,'php','tests/settings/calendar_unassigned_http_fixture.php',mode],{encoding:'utf8'}));}
async function navigate(page,button){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);}

(async()=>{
 execFileSync('docker',['exec',app,'mkdir','-p','/app/tests/settings']);execFileSync('docker',['cp',path.join(env.ISSUERS_BROWSER_SOURCE_ROOT||process.cwd(),'tests/settings/calendar_unassigned_http_fixture.php'),app+':/app/tests/settings/calendar_unassigned_http_fixture.php']);
 const setup=fixture('inspect');assert(setup.read_only&&setup.native_marker>=450);const browser=await chromium.launch({headless:true,...(env.ISSUERS_BROWSER_EXECUTABLE_PATH?{executablePath:env.ISSUERS_BROWSER_EXECUTABLE_PATH}:{})}),context=await browser.newContext(),page=await workspacePage(context),errors=[],results=[];
 await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());page.on('pageerror',error=>errors.push(error.message));
 try{
  await page.goto(base+'/admin/authentication');await page.locator('[name=email]').fill('admin-ci@example.invalid');await page.locator('[name=password]').fill(env.ISSUERS_BROWSER_PASSWORD);await navigate(page,page.locator('button[type=submit]'));const before=fixture('snapshot');
  for(const[letter,owner]of Object.entries(setup.owners)){
   await page.goto(base+'/admin/');await selectCompanyOption(page,String(owner));
   let response=await page.goto(base+'/admin/settings?group=calendar');assert.equal(response.status(),200);const form=page.locator('#settings-form'),fields=await form.evaluate(node=>Object.fromEntries(new FormData(node).entries()));assert.equal(fields['settings[calendar_first_day]'],letter==='a'?'0':'1');assert.equal(fields['settings[calendar_only_assigned_tasks]'],'0');
   response=await page.goto(base+'/admin/utilities/calendar');assert.equal(response.status(),200,'official calendar page renders for unassigned task owner');const config=await page.evaluate(()=>window.nativeCalendarConfig);assert.equal(config.options.calendar_first_day,letter==='a'?'0':'1');assert.equal(config.options.calendar_only_assigned_tasks,'0');assert.equal(await page.locator('#calendar').count(),1);assert.equal(await page.locator('[data-company-calendar]').count(),0,'parallel calendar grid is absent');
   const query=new URLSearchParams({start:'2026-11-01',end:'2026-11-02',calendar_filters:'true',tasks:'true'}),feedResponse=await context.request.get(base+'/admin/utilities/get_calendar_data?'+query);assert.equal(feedResponse.status(),200);const events=await feedResponse.json();assert(Array.isArray(events));
   for(const task of setup.tasks[owner]){assert.equal(task.assignee_id,0);assert(events.some(event=>event.id==='project_tasks:'+task.id),'own unassigned portal task appears through official feed');}
   for(const[foreign,rows]of Object.entries(setup.tasks))if(Number(foreign)!==owner)for(const task of rows)assert(!events.some(event=>event.id==='project_tasks:'+task.id),'unassigned sentinel never exposes another company task');
   assert(!JSON.stringify(events).includes('contact_id'));assert(events.filter(event=>event.id.startsWith('project_tasks:')).every(event=>event.native_path.startsWith('tasks/view/')));
   results.push({owner,settings200:true,calendar200:true,feed200:true,unassigned:setup.tasks[owner].map(task=>task.id),first_day:Number(config.options.calendar_first_day)});
   if(output){fs.mkdirSync(output,{recursive:true});await page.screenshot({path:path.join(output,'calendar-unassigned-official-'+letter+'.png'),fullPage:true});}
  }
  assert.deepEqual(fixture('snapshot'),before,'official calendar reads retain unassigned sources, settings, options and financial rows');assert.deepEqual(errors,[]);
  if(output)fs.writeFileSync(path.join(output,'calendar-unassigned-official.json'),JSON.stringify({native_marker:setup.native_marker,read_only_fixture:true,official_calendar:'/admin/utilities/calendar',official_feed:'/admin/utilities/get_calendar_data',results,sources_and_finances_unchanged:true,no_fake_staff:true,external_transport:false},null,2));
  console.log('Official Calendar unassigned A/B PASS: Settings preference, Utilities page/feed, own task IDs and unchanged source/configuration/financial rows.');
 }finally{await context.close();await browser.close();}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
