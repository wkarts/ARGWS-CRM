const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const { readFileSync, mkdtempSync, rmSync } = require('node:fs');
const os = require('node:os');
const path = require('node:path');
(async () => {
 const dir = mkdtempSync(path.join(os.tmpdir(), 'asaas-pix-portal-'));
 try {
  const html = path.join(dir, 'invoice.html');
  execFileSync(process.env.ASAAS_TEST_PHP || 'php', ['tests/companies/asaas_pix_portal_controller.php'], {env:{...process.env, ASAAS_PIX_PORTAL_HTML:html}, stdio:'pipe'});
  const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true});
  try {
   for(const width of [1280,375]) {
    const page = await browser.newPage({viewport:{width,height:800}});
    await page.setContent('<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1">'+readFileSync(html,'utf8'));
    const panel=page.locator('section[aria-labelledby="native-pix-consent-title"]');
    const checkbox=panel.locator('input[name="pix_consent_accept"]');
    if(await checkbox.isChecked())throw Error('Consent must be opt-in');
    if(await panel.locator('form').evaluate(form=>form.checkValidity()))throw Error('Unchecked consent must block browser submit');
    await checkbox.check();
    if(!await panel.locator('form').evaluate(form=>form.checkValidity()))throw Error('Explicit checkbox should permit submit');
    if(await panel.locator('input[name="_native_portal_csrf"]').count()!==1||await panel.locator('input[name="pix_consent_revision"]').count()!==1)throw Error('Missing protected revision');
    if(await panel.locator('img,iframe').count())throw Error('Consent panel must not embed payable resources');
    await page.close();
   }
   console.log('Pix consent: native PHP invoice in Chromium desktop/mobile; opt-in, required validation, CSRF/revision, no payable resource. HTTP controller tested separately.');
  }finally{await browser.close();}
 }finally{rmSync(dir,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exit(1);});
