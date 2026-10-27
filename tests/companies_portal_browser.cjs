'use strict';
const { selectCompanyOption } = require('./helpers/company_selector_driver.cjs');
const { workspacePage } = require('./helpers/company_workspace_driver.cjs');
// Actual CRM/HTTP/MySQL/browser, exclusively on the disposable standalone-upgrade fixture.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
const output = process.env.ISSUERS_BROWSER_ARTIFACTS || '/tmp/issuer-browser-evidence';
fs.mkdirSync(output, { recursive: true });
function sql(query) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', query],
    { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
async function login(page, email, password) {
  await page.goto(url + '/admin/authentication');
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(password);
  await navigate(page, page.locator('button[type=submit]'));
}
async function navigate(page, button) {
  // Diagnose a missing control before starting a navigation that cannot occur.
  // The workspace driver still reveals hidden panels through their real buttons on click.
  await button.waitFor({ state: 'attached' });
  const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
  return response;
}
async function hidden(page) {
  return page.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(x => x.name && !x.name.startsWith('wizard[')).map(x => [x.name, x.value])));
}
async function next(page) { await navigate(page, page.locator('section form button[type=submit]')); }


(async () => {
  const browser = await chromium.launch({headless:true});
  const admin = await browser.newContext();
  await admin.route('**/*', r => new URL(r.request().url()).origin === url ? r.continue() : r.abort());
  const page = await workspacePage(admin);
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  try {
    await login(page,'admin-ci@example.invalid',process.env.ISSUERS_BROWSER_PASSWORD);
    const b=sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
    await page.goto(url+'/admin/company_onboarding');
    await page.locator('#company-create-modal').waitFor({state:'visible'});
    const wizard = page.frame({name:'company-create-frame'});
    await wizard.locator('section form button[type=submit]').waitFor();
    await next(wizard);
    await wizard.locator('#legal_name').fill('Synthetic portal A');await wizard.locator('#tax_id').fill('11222333000181');
    await next(wizard);await next(wizard);await next(wizard);await next(wizard);await next(wizard);
    const a=sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'");assert.match(a,/^[1-9]\d*$/);
    async function select(id){await page.goto(url+'/admin/');await selectCompanyOption(page, id);}
    async function configure(id,letter){
      await select(id);await page.goto(url+'/admin/settings?group=email');
      const form=page.locator('#settings-form');
      if (!await form.locator('#smtp').isChecked()) await form.locator('label[for="smtp"]').click();
      await form.locator('input[name="settings[smtp_host]"]').fill('smtp-'+letter+'.example.invalid');
      await form.locator('input[name="settings[smtp_port]"]').fill('587');
      await form.locator('input[name="settings[smtp_email]"]').fill(letter+'@example.invalid');
      await form.locator('input[name="settings[smtp_username]"]').fill('synthetic-'+letter);
      await form.locator('input[name="settings[smtp_password]"]').fill('synthetic-secret-'+letter);
      // The fixture reports only fixed field names, counts and validation outcomes.
      // Values, credentials, tokens and arbitrary submitted names never enter CI logs.
      const mailDiagnostics=await form.evaluate(node=>{
        const data=new FormData(node);
        const fields=['mail_engine','email_protocol','smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password','smtp_email_charset','bcc_emails','email_signature','email_header','email_footer','email_queue_enabled','email_queue_skip_with_attachments','google_mail_client_id','google_mail_client_secret','microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_azure_tenant_id'];
        const names=new Set(fields.map(name=>'settings['+name+']'));
        const value=name=>data.get('settings['+name+']');
        const host=value('smtp_host'),port=value('smtp_port'),encryption=value('smtp_encryption'),email=value('smtp_email');
        const csrfName=typeof window.csrfData?.token_name==='string'?window.csrfData.token_name:null;
        const topNames=new Set(['fakeusernameremembered','fakepasswordremembered','test_email','native_settings_revision','_native_company_csrf','_company_context']);
        if(csrfName)topNames.add(csrfName);
        return {
          present:fields.filter(name=>data.has('settings['+name+']')),
          missing:fields.filter(name=>!data.has('settings['+name+']')),
          extraSettingsCount:Array.from(data.keys()).filter(name=>name.startsWith('settings[')&&!names.has(name)).length,
          extraTopLevelCount:Array.from(data.keys()).filter(name=>!name.startsWith('settings[')&&!topNames.has(name)).length,
          revisionPresent:data.has('native_settings_revision'),
          companyContextPresent:data.has('_company_context'),
          companyCsrfPresent:data.has('_native_company_csrf'),
          csrfNameAvailable:csrfName!==null,
          frameworkCsrfPresent:csrfName!==null&&data.has(csrfName),
          smtpHostValid:typeof host==='string'&&(host===''||/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?|\[[a-fA-F0-9:]+\])$/.test(host)),
          smtpPortValid:typeof port==='string'&&(port===''||(/^[0-9]+$/.test(port)&&Number(port)>=1&&Number(port)<=65535)),
          smtpEncryptionValid:typeof encryption==='string'&&['','ssl','tls'].includes(encryption),
          // A browser-side indication, not a replacement for PHP FILTER_VALIDATE_EMAIL.
          smtpEmailBasicValid:typeof email==='string'&&(email===''||/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
        };
      });
      const saved=await navigate(page,form.locator('button[type=submit]'));
      if(saved.status()===422)console.error('Synthetic SMTP form diagnostics:',JSON.stringify(mailDiagnostics));
      assert.equal(saved.status(),200,'native settings POST redirects to its saved form');
      assert(new URL(page.url()).searchParams.get('group')==='email');
      assert.equal(await form.locator('input[name="settings[smtp_host]"]').inputValue(),'smtp-'+letter+'.example.invalid','SMTP settings persist in the selected company');
      assert.equal(await form.locator('input[name="settings[smtp_email]"]').inputValue(),letter+'@example.invalid');
      assert.equal(await form.locator('input[name="settings[smtp_password]"]').inputValue(),'','GUI never echoes SMTP password');
    }
    const clients={};
    for(const [id,letter] of [[a,'a'],[b,'b']]){
      await configure(id,letter);
      await page.goto(url+'/admin/company_clients');await page.locator('#new_company').fill('Synthetic portal client '+letter);
      await navigate(page,page.locator('form').filter({has:page.locator('#new_company')}).locator('button[type=submit]'));
      clients[id]=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client "+letter+"'");
      await page.locator('#contact-firstname').fill('Synthetic '+letter);await page.locator('#contact-lastname').fill('Portal');await page.locator('#contact-email').fill('same-portal@example.invalid');
      await navigate(page,page.locator('form').filter({has:page.locator('#contact-email')}).locator('button[type=submit]'));
      assert.equal(sql('SELECT COUNT(*) FROM tblcontacts WHERE userid='+clients[id]),'1');
    }
    assert.equal(sql("SELECT COUNT(*) FROM tblcontacts WHERE email='same-portal@example.invalid'"),'2');
    const tokens={};const publicContexts={};const sessions={};
    for(const [id,letter] of [[a,'a'],[b,'b']]){
      const ctx=await browser.newContext();publicContexts[id]=ctx;
      await ctx.route('**/*', r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
      const p=await workspacePage(ctx);sessions[id]=p;p.on('pageerror',e=>errors.push(e.message));
      const response=await p.goto(url+'/company_portal/index/'+id);assert.equal(response.status(),200);
      assert.equal(await p.locator('.company-selector').count(),0,'public portal has no administrative context');
      const invalid=await ctx.request.post(url+'/company_portal/index/'+id,{form:{action:'recovery',email:'same-portal@example.invalid',portal_csrf:'invalid'}});assert.equal(invalid.status(),403);
      await p.locator('#recovery-email').fill('same-portal@example.invalid');await navigate(p,p.locator('#portal-recovery button[type=submit]'));
      assert((await p.locator('[role=status]').innerText()).includes('Se houver uma conta ativa'));
      const message=sql("SELECT message FROM tblmail_queue WHERE issuer_id="+id+" AND email='same-portal@example.invalid' ORDER BY id DESC LIMIT 1");
      const token=message.match(/#token=([a-f0-9]{64})/)?.[1];assert(token,'actual HTTP recovery commits a usable origin-bound queue message');tokens[id]=token;
      const headers=sql("SELECT headers FROM tblmail_queue WHERE issuer_id="+id+" AND email='same-portal@example.invalid' ORDER BY id DESC LIMIT 1");
      assert(headers.includes(letter+'@example.invalid'),'queue sender belongs to own company');
      await p.goto(url+'/company_portal/index/'+id+'#token='+token);
      await p.waitForFunction(()=>document.getElementById('reset-token')?.value.length===64);
      assert.equal(await p.locator('#reset-token').inputValue(),token);
      assert.equal(new URL(p.url()).hash,'','fragment cleared without sending bearer in query');
      await p.locator('#reset-password').fill('synthetic-password-'+letter);await p.locator('#reset-repeat').fill('synthetic-password-'+letter);
      await navigate(p,p.locator('#portal-reset button[type=submit]'));
      assert((await p.locator('[role=status]').innerText()).includes('Senha alterada'));
      await p.locator('#login-email').fill('same-portal@example.invalid');await p.locator('#login-password').fill('synthetic-password-'+letter);
      await navigate(p,p.locator('#portal-login button[type=submit]'));
      assert((await p.locator('#portal-identity').innerText()).includes('Synthetic portal client '+letter));
      assert(!(await p.locator('#portal-identity').innerText()).includes('Synthetic portal client '+(letter==='a'?'b':'a')));
    }
    const p=sessions[a];await navigate(p,p.locator('#portal-identity button[type=submit]'));
    await p.goto(url+'/company_portal/index/'+a+'#token='+tokens[a]);await p.waitForFunction(()=>document.getElementById('reset-token')?.value.length===64);
    await p.locator('#reset-password').fill('forbidden-token-replay');await p.locator('#reset-repeat').fill('forbidden-token-replay');
    const replayResponse=await Promise.all([p.waitForNavigation(),p.locator('#portal-reset button[type=submit]').click()]);assert.equal(replayResponse[0].status(),422);
    await p.goto(url+'/company_portal/index/'+a);await p.locator('#login-email').fill('same-portal@example.invalid');await p.locator('#login-password').fill('synthetic-password-b');
    const wrong=await Promise.all([p.waitForNavigation(),p.locator('#portal-login button[type=submit]').click()]);assert.equal(wrong[0].status(),422,'equal email never selects other company password');
    await select(a);await page.goto(url+'/admin/company_clients?client='+clients[a]);
    const contact=sql('SELECT id FROM tblcontacts WHERE userid='+clients[a]);
    await page.locator('#contact-'+contact+'-email').fill('changed-portal@example.invalid');
    await navigate(page,page.locator('form').filter({has:page.locator('#contact-'+contact+'-email')}).locator('button[type=submit]'));
    assert.equal(sql('SELECT email FROM tblcontacts WHERE id='+contact),'changed-portal@example.invalid');
    await sessions[b].reload();assert.equal(await sessions[b].locator('#portal-identity').count(),1,'A contact change preserves independent B session');
    sql('UPDATE tblcontacts SET active=0 WHERE userid='+clients[b]);await sessions[b].reload();assert.equal(await sessions[b].locator('#portal-identity').count(),0,'deactivation revokes B portal session');
    assert.equal(sql("SELECT COUNT(*) FROM tblmail_queue WHERE email='same-portal@example.invalid' AND status='sent'"),'0','test queues only; no external deliveries');
    await p.screenshot({path:output+'/company-portal-recovery.png',fullPage:true});
    assert.deepEqual(errors,[]);
    for(const ctx of Object.values(publicContexts))await ctx.close();
    console.log('Private contacts + A/B duplicate-email portal + HTTP recovery/reset/session: real MySQL/Chromium checks passed; queue only, no external delivery.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
