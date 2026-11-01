'use strict';
const assert=require('node:assert/strict');
const {selectCompanyOption}=require('./native_selector_driver.cjs');
// Read-only early HTTP probe. No changing settings or permissions to make a test pass.
async function probeNativeSettings(browser,url,password,issuers){
 const context=await browser.newContext();
 try{
  await context.route('**/*',r=>new URL(r.request().url()).origin===url?r.continue():r.abort());
  const page=await context.newPage();await page.goto(url+'/admin/authentication');
  await page.locator('input[name=email]').fill('admin-ci@example.invalid');await page.locator('input[name=password]').fill(password);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[type=submit]').click()]);
  for(const issuer of issuers){await page.goto(url+'/admin/');await selectCompanyOption(page,issuer);
   for(const [group,field] of [['estimates','estimate_prefix'],['proposals','proposal_number_prefix'],['pdf','pdf_format_invoice']]){
    const response=await page.goto(url+'/admin/settings?group='+group);assert.equal(response.status(),200,'native '+group+' settings must open for '+issuer);
    const form=page.locator('#settings-form');assert.equal(await form.count(),1);
    assert.equal(await form.locator('[name="settings['+field+']"]').count(),1);
    assert.match(await form.locator('[name=native_settings_revision]').inputValue(),/^[a-f0-9]{64}$/);
    const tokens=await form.locator('[name=_native_company_csrf]').evaluateAll(xs=>xs.map(x=>x.value));
    assert.equal(tokens.length>0&&tokens.every(value=>/^[a-f0-9]{64}$/.test(value)&&value===tokens[0]),true,'all native CSRF fields must contain the same valid token');
   }
  }
  console.log('Early native Settings groups HTTP/form/context probe passed for primary/A/B; no settings changed.');
 }finally{await context.close();}
}
module.exports={probeNativeSettings};
