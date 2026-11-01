'use strict';
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const {probeNativeSettings}=require('../helpers/native_settings_probe.cjs');
const {loginNativePortal}=require('../helpers/native_portal_login_driver.cjs');
const url=process.env.ISSUERS_BROWSER_URL,db=process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url||'',/^http:\/\/127\.0\.0\.1:\d+$/);assert.match(db||'',/^argws-crm-provision-db-\d+$/);assert.equal(process.env.ISSUERS_BROWSER_DB_NAME,'argws_upgrade_ci');
function sql(q){return execFileSync('docker',['exec','-e','MYSQL_PWD',db,'mysql','--protocol=tcp','--host=127.0.0.1','--user='+process.env.ISSUERS_BROWSER_DB_USER,'argws_upgrade_ci','--batch','--skip-column-names','-e',q],{env:{...process.env,MYSQL_PWD:process.env.ISSUERS_BROWSER_DB_PASSWORD},encoding:'utf8'}).trim();}
(async()=>{const browser=await chromium.launch({headless:true});try{
 for(const [tax,letter] of [['11222333000181','a'],['52998224725','b']]){
  const issuer=sql("SELECT id FROM tblissuers_registry WHERE tax_id='"+tax+"'"),client=sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client "+letter+"'");assert.match(issuer,/^[1-9]\d*$/);assert.match(client,/^[1-9]\d*$/);
  const email=letter==='a'?'changed-portal@example.invalid':'same-portal@example.invalid',contact=sql("SELECT id FROM tblcontacts WHERE userid="+client+" AND email='"+email+"'");assert.match(contact,/^[1-9]\d*$/);
  const active=sql('SELECT active FROM tblcontacts WHERE id='+contact+' AND userid='+client);assert.equal(active,letter==='a'?'1':'0','preceding recovery fixture leaves A active and B explicitly revoked');
  if(letter==='a')sql('UPDATE tblcontacts SET email_verified_at=COALESCE(email_verified_at,UTC_TIMESTAMP()) WHERE id='+contact+' AND userid='+client);
  if(letter==='a')assert.equal(sql('SELECT (password IS NOT NULL AND LENGTH(password)>0 AND email_verified_at IS NOT NULL) FROM tblcontacts WHERE id='+contact),'1');
  const ctx=await browser.newContext();try{await ctx.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());const page=await ctx.newPage();
   if(letter==='a'){await loginNativePortal(page,url,issuer,email,'synthetic-password-'+letter);}
   else{await page.goto(url+'/authentication/login?issuer_id='+issuer);const form=page.locator('form.login-form');assert.equal(await form.count(),1);assert.equal(await form.locator('input[name=issuer_id]').inputValue(),issuer);await form.locator('input[name=email]').fill(email);await form.locator('input[name=password]').fill('synthetic-password-'+letter);const [response]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[type=submit]').click()]);assert.equal(response.status(),200);assert.equal(await page.locator('#greeting').count(),0,'revoked B contact must remain unauthenticated');assert.equal(await page.locator('form.login-form').count(),1);assert.equal(sql('SELECT active FROM tblcontacts WHERE id='+contact),'0','probe must not reactivate the revoked contact');}
   console.log(JSON.stringify({nativePortalLogin:letter==='a'?'active contact passed':'revoked contact denied',fixture:letter}));}catch(error){console.error(JSON.stringify({fixture:letter,recentThrottle:sql('SELECT COUNT(*),COALESCE(MAX(attempts),0) FROM tblcompany_portal_limits WHERE window_start>=UNIX_TIMESTAMP()-900'),captchaEnabled:sql("SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings_json,'$.use_recaptcha_customers_area')),'0') FROM tblissuers_registry WHERE id="+issuer)}));throw error;}finally{await ctx.close();}
 }
 await probeNativeSettings(browser,url,process.env.ISSUERS_BROWSER_PASSWORD,[sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1'),sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"),sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'")]);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
