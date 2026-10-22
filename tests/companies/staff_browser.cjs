'use strict';
const { selectCompanyOption } = require('../helpers/company_selector_driver.cjs');
const { workspacePage } = require('../helpers/company_workspace_driver.cjs');
// Real HTTP/Chromium forms, native login and a deliberately isolated upgraded database.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {randomBytes, createHash} = require('node:crypto');
const {chromium} = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
const output = process.env.ISSUERS_BROWSER_ARTIFACTS;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
assert(output && path.isAbsolute(output), 'Explicit evidence directory required');
fs.mkdirSync(output, {recursive:true});
function sql(query) {
  return execFileSync('docker', ['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',query], {env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();
}
function identity(id) {
  assert.match(String(id), /^[1-9]\d*$/);
  return sql('SELECT JSON_ARRAY(staffid,firstname,lastname,email,password,admin,active,role,is_not_staff,phonenumber) FROM tblstaff WHERE staffid='+id);
}
function members(id) { return sql('SELECT issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations FROM tblissuers_staff WHERE staff_id='+id+' ORDER BY issuer_id'); }
function grants(id,issuer) { return sql('SELECT feature,capability FROM tblissuers_resource_permissions WHERE staff_id='+id+' AND issuer_id='+issuer+' ORDER BY feature,capability'); }
function preference(id) { return sql('SELECT issuer_id FROM tblissuers_staff_defaults WHERE staff_id='+id); }
function digest(value) { return createHash('sha256').update(value).digest('hex'); }
async function submit(page, button) { return (await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]))[0]; }
async function values(form) { return form.evaluate(node=>Object.fromEntries(new FormData(node).entries())); }
async function hidden(form) { return form.locator('input[type=hidden][name]').evaluateAll(nodes=>Object.fromEntries(nodes.map(node=>[node.name,node.value]))); }
async function selectCompany(page,id) {
  await page.goto(url+'/admin/company_context');
  await selectCompanyOption(page, String(id));
  await submit(page,page.locator('.company-selector-form button[type=submit]'));
  // The primary native dashboard starts its task-table XHR after DOMContentLoaded.
  // Finish that real render before navigating again; do not abort its JSON response.
  await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('#company-context-select').inputValue(),String(id));
}
async function login(page,email,password) {
  assert.equal((await page.goto(url+'/admin/authentication')).status(),200);
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(password);
  await submit(page,page.locator('button[type=submit]'));
  await page.waitForLoadState('networkidle');
  assert(!page.url().includes('/authentication'),'native authentication established');
}
(async()=>{
  const suffix=randomBytes(5).toString('hex'), password='synthetic-staff453-'+suffix;
  const installed=sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1");
  assert.match(installed,/^17:1:(installer|legacy_designation)$/,'existing legitimate master, no new designation during this test');
  const companies={a:sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),b:sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'"),primary:'1'};
  for(const id of Object.values(companies))assert.match(id,/^[1-9]\d*$/,'wizard A/B must already exist');
  const beforeMaster={identity:identity(17),members:members(17),default:preference(17)};
  const browser=await chromium.launch({headless:true}), contexts=[],errors=[],proof=[];
  async function surface() {
    const ctx=await browser.newContext({viewport:{width:1440,height:960}});contexts.push(ctx);
    await ctx.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
    const page=await workspacePage(ctx);page.on('pageerror',e=>errors.push(e.message));return{ctx,page};
  }
  const master=await surface();let manager;
  const staff={},emails={};let originalAForm;
  async function create(page, label, capabilities=['view','create','edit','delete']) {
    assert.equal((await page.goto(url+'/admin/company_staff')).status(),200);
    const form=page.locator('#company-staff-create');
    await form.locator('#new-firstname').fill('Synthetic '+label);
    await form.locator('#new-lastname').fill('Staff453');
    const email='staff453-'+label+'-'+suffix+'@example.invalid';
    await form.locator('#new-email').fill(email);
    await form.locator('#new-phonenumber').fill('000');
    await form.locator('#new-password').fill(password);
    await form.locator('details > summary').click();
    for(const cap of capabilities)await form.locator('#new-staff-'+cap).check();
    await form.locator('#new-tasks-view').check();
    const response=await submit(page,form.locator('button[type=submit]'));
    const id=new URL(page.url()).searchParams.get('staff');
    assert.match(id||'',/^[1-9]\d*$/,'creation redirects to actual committed identity; HTTP '+response.status());
    assert.equal(sql('SELECT CONCAT(admin,":",active,":",role,":",is_not_staff) FROM tblstaff WHERE staffid='+id),'0:1:0:0','no implicit native administrator or role');
    assert.equal(sql("SELECT COUNT(*) FROM tblstaff WHERE email='"+email+"'"),'1');
    return{id,email};
  }
  try {
    await login(master.page,'admin-ci@example.invalid',process.env.ISSUERS_BROWSER_PASSWORD);
    for(const [label,issuer] of Object.entries(companies)) {
      await selectCompany(master.page,issuer);
      const made=await create(master.page,label);staff[label]=made.id;emails[label]=made.email;
      assert.equal(preference(made.id),issuer,'new employee has explicit selected default');
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+made.id),'1');
      assert.equal(sql('SELECT COUNT(*) FROM tblissuers_resource_permissions WHERE staff_id='+made.id+' AND issuer_id='+issuer+' AND feature="staff"'),'4');
      const edit=master.page.locator('#company-staff-update'), payload=await values(edit), before=identity(made.id);
      const badOwn=await master.ctx.request.post(url+'/admin/company_staff',{form:{...payload,company_staff_csrf:'0'.repeat(64)},maxRedirects:0});
      assert.equal(badOwn.status(),403,'own form CSRF');
      const nativeToken=Object.keys(payload).find(key=>key.toLowerCase().includes('csrf')&&key!=='company_staff_csrf');
      assert(nativeToken,'native CSRF field is present');
      assert.equal((await master.ctx.request.post(url+'/admin/company_staff',{form:{...payload,[nativeToken]:'invalid'},maxRedirects:0})).status(),403,'native framework CSRF');
      assert.equal(identity(made.id),before,'both CSRF failures preserve native profile');
      if(label==='a')originalAForm=payload;
      proof.push({company:label,issuer:Number(issuer),created:Number(made.id),explicit_default:true,native_admin:0,own_csrf:403,native_csrf:403});
    }
    assert.equal((await master.ctx.request.get(url+'/admin/company_staff?staff='+staff.a)).status(),403,'principal cannot hydrate A identity before sharing');
    assert.equal((await master.ctx.request.post(url+'/admin/company_staff',{form:originalAForm,maxRedirects:0})).status(),409,'old A form cannot execute after switching to principal');
    await selectCompany(master.page,companies.a);
    assert.equal((await master.ctx.request.get(url+'/admin/company_staff?staff='+staff.b)).status(),403,'A cannot hydrate B-only identity');
    assert.equal((await master.ctx.request.get(url+'/admin/staff/member/'+staff.b)).status(),403,'native legacy CRUD remains closed in secondary');
    assert.equal((await master.ctx.request.get(url+'/admin/company_staff/unsupported')).status(),404,'only reviewed own index route is exposed');
    await master.page.goto(url+'/admin/company_staff?staff=17');
    assert.equal(await master.page.locator('#company-staff-revoke').count(),0,'master cannot be revoked');
    assert.match(await master.page.locator('section').innerText(),/Master já reconhecido/);
    manager=await surface();await login(manager.page,emails.a,password);await selectCompany(manager.page,companies.a);
    const shared=await create(manager.page,'shared',['view']);staff.shared=shared.id;emails.shared=shared.email;
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+shared.id+' AND issuer_id='+companies.a),'1','nonmaster company manager creates actual member');
    const ownEdit=manager.page.locator('#company-staff-update');
    const stale=await values(ownEdit);
    await ownEdit.locator('#edit-firstname').fill('Exclusive <script>literal</script>');
    await submit(manager.page,ownEdit.locator('button[type=submit]'));
    assert.equal(sql('SELECT firstname FROM tblstaff WHERE staffid='+shared.id),'Exclusive <script>literal</script>','exclusive identity profile is writable by company manager');
    assert.equal(await manager.page.locator('section h2 script').count(),0,'identity text is escaped');
    assert.match(await manager.page.locator('section h2').first().innerText(),/Exclusive <script>literal<\/script>/);
    assert.equal((await manager.ctx.request.post(url+'/admin/company_staff',{form:stale,maxRedirects:0})).status(),409,'old profile revision refuses overwrite');
    const beforeShared={identity:identity(shared.id),a:grants(shared.id,companies.a),default:preference(shared.id)};
    await selectCompany(master.page,companies.b);await master.page.goto(url+'/admin/company_staff');
    const link=master.page.locator('#company-staff-link');await link.locator('#existing-email').fill(shared.email);await link.locator('details > summary').click();await link.locator('#link-tasks-view').check();await link.locator('#link-staff-view').check();
    const linkPayload=await values(link);await submit(master.page,link.locator('button[type=submit]'));
    assert.equal(new URL(master.page.url()).searchParams.get('staff'),shared.id);
    assert.equal(identity(shared.id),beforeShared.identity,'link shares identity without rewriting global profile');
    assert.equal(grants(shared.id,companies.a),beforeShared.a,'A grants survive B link');
    assert.equal(preference(shared.id),beforeShared.default,'unchecked make-default preserves old preference');
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+shared.id),'2');
    const replay=await master.ctx.request.post(url+'/admin/company_staff',{form:linkPayload,maxRedirects:0});
    assert.equal(replay.status(),303,'same command safely replays confirmed result');
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+shared.id),'2','replay creates no membership duplicate');
    await manager.page.goto(url+'/admin/company_staff?staff='+shared.id);
    assert.equal(await manager.page.locator('#edit-firstname').count(),0,'manager cannot edit a now-shared global identity');
    const sharedPayload=await hidden(manager.page.locator('#company-staff-update'));
    assert.equal((await manager.ctx.request.post(url+'/admin/company_staff',{form:{...sharedPayload,'profile[firstname]':'Forged shared overwrite'},maxRedirects:0})).status(),403,'forged shared profile denied');
    assert.equal(identity(shared.id),beforeShared.identity,'shared profile unchanged after rejected request');
    const grantForm=manager.page.locator('#company-staff-update');await grantForm.locator('details > summary').click();await grantForm.locator('#edit-tasks-view').uncheck();await submit(manager.page,grantForm.locator('button[type=submit]'));
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_resource_permissions WHERE staff_id='+shared.id+' AND issuer_id='+companies.a+' AND feature="tasks"'),'0','manager changes only own company ACL of shared identity');
    assert.match(grants(shared.id,companies.b),/tasks\tview/,'B keeps its independent ACL');
    const beforeRevoke={identity:identity(shared.id),b:grants(shared.id,companies.b)};
    await submit(manager.page,manager.page.locator('#company-staff-revoke button[type=submit]'));
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+shared.id+' AND issuer_id='+companies.a),'0','A membership is revoked');
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+shared.id+' AND issuer_id='+companies.b),'1','B membership is retained');
    assert.equal(identity(shared.id),beforeRevoke.identity,'revocation never deletes login identity');
    assert.equal(grants(shared.id,companies.b),beforeRevoke.b,'revocation preserves B ACL');
    assert.equal(preference(shared.id),'','revoked default cleared without silently choosing another company');
    assert.equal((await manager.ctx.request.get(url+'/admin/company_staff?staff='+shared.id)).status(),403,'revoked identity no longer hydratable from A');
    await manager.page.goto(url+'/admin/company_staff');
    await manager.page.screenshot({path:path.join(output,'staff-manager-desktop.png'),fullPage:true});
    await manager.page.setViewportSize({width:390,height:844});await manager.page.reload();
    assert(await manager.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'own team page fits mobile viewport');
    await manager.page.screenshot({path:path.join(output,'staff-manager-mobile.png'),fullPage:true});
    await selectCompany(master.page,companies.a);await master.page.goto(url+'/admin/company_staff?staff='+staff.a);
    const revokeManager=master.page.locator('#company-staff-update');await revokeManager.locator('details > summary').click();await revokeManager.locator('#edit-staff-view').uncheck();await submit(master.page,revokeManager.locator('button[type=submit]'));
    assert.equal((await manager.ctx.request.get(url+'/admin/company_staff')).status(),403,'revocation applies to the next real request of an existing session');
    assert.equal(sql('SELECT admin FROM tblstaff WHERE staffid='+staff.a),'0','manager still has no native administration');
    // Native primary form remains functional, including the originally broken empty role.
    await selectCompany(master.page,companies.primary);await master.page.goto(url+'/admin/staff/member');
    const native=master.page.locator('form.staff-form');
    await native.locator('#firstname').fill('Native composed');await native.locator('#lastname').fill('Staff453');
    const nativeEmail='staff453-native-'+suffix+'@example.invalid';await native.locator('#email').fill(nativeEmail);
    await native.locator('input[name=password]').fill(password);await native.locator('#send_welcome_email').uncheck();
    await native.locator('a[href="#staff_permissions"]').click();
    await native.locator('[name="issuer_memberships['+companies.primary+']"]').check();await native.locator('[name="issuer_memberships['+companies.a+']"]').check();
    await native.locator('[name=issuer_default]').selectOption(companies.a);
    await native.locator('select[name=role]').selectOption('');
    await submit(master.page,native.locator('button[type=submit]'));
    await master.page.waitForLoadState('networkidle');
    const nativeId=sql("SELECT staffid FROM tblstaff WHERE email='"+nativeEmail+"'");assert.match(nativeId,/^[1-9]\d*$/,'native profile and ACL actually commit');
    assert.equal(sql('SELECT role FROM tblstaff WHERE staffid='+nativeId),'0','empty native role normalized to zero');
    assert.equal(sql('SELECT COUNT(*) FROM tblissuers_staff WHERE staff_id='+nativeId),'2','native form owns both requested memberships');
    assert.equal(preference(nativeId),companies.a);
    assert.equal(sql("SELECT CONCAT(master_staff_id,':',primary_issuer_id,':',source) FROM tblissuers_installation_identity WHERE id=1"),installed);
    assert.equal(identity(17),beforeMaster.identity,'master global identity never rewritten');
    assert.equal(members(17),beforeMaster.members,'master prior membership flags preserved');
    assert.equal(preference(17),beforeMaster.default,'context switching does not rewrite master default');
    assert.deepEqual(errors,[],'no browser execution errors');
    const result={result:'PASS',browser_version:browser.version(),actual_http:true,companies:proof,shared_identity:{id:Number(shared.id),link_preserves_profile:true,manager_shared_profile_denied:403,own_acl_edit:true,revoked_a_retained_b:true,no_default_fallback:true},native_primary:{id:Number(nativeId),role:0,company_links:2},master_identity:installed,master_identity_sha256:digest(beforeMaster.identity),stale_context:409,stale_revision:409,live_acl_revocation:403,page_errors:errors};
    fs.writeFileSync(path.join(output,'staff-browser-proof.json'),JSON.stringify(result,null,2)+'\n');
    console.log('Staff native HTTP A+B+primary, manager identity and ACL, shared-link/revoke, both CSRFs, stale context/revision, actual native form and master preservation PASS.');
  } catch(error) {
    fs.writeFileSync(path.join(output,'staff-browser-failure.json'),JSON.stringify({result:'FAIL',message:error.message,url:master.page.url().replace(/\?.*/,''),page_errors:errors},null,2)+'\n');
    await master.page.screenshot({path:path.join(output,'staff-browser-failure.png'),fullPage:true}).catch(()=>{});throw error;
  } finally { for(const ctx of contexts)await ctx.close();await browser.close(); }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
