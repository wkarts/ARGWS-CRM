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
   const credits=await page.goto(url+'/admin/credit_notes');assert.equal(credits.status(),200,'original credit directory opens for '+issuer);
   const createCredit=page.locator('a[href$="/admin/credit_notes/credit_note"]');
   assert.equal(await createCredit.count(),1,'original directory exposes one authorized new-credit action');
   const [editor]=await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),createCredit.click()]);
   assert.equal(editor.status(),200,'original new-credit action opens the issuer-bound editor');
   const creditForm=page.locator('#credit-note-form');assert.equal(await creditForm.count(),1);
   assert.equal(await creditForm.locator('textarea[name="credit[items][0][description]"]').count(),1,'original item description remains multiline');
   assert.equal(await creditForm.locator('input[name="credit[items][0][rate]"]').count(),1,'credit item rate is distinct from tax fields');
   assert.equal(await creditForm.locator('input[name="credit[items][0][taxes][0][rate]"]').count(),1,'credit tax rate retains its separate canonical field');
  }
  console.log('Early native Settings groups HTTP/form/context probe passed for primary/A/B; no settings changed.');
 }finally{await context.close();}
}
module.exports={probeNativeSettings};
