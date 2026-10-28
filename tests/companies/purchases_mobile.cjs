'use strict';
const { selectCompanyOption } = require('../helpers/company_selector_driver.cjs');

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const url = process.env.ISSUERS_BROWSER_URL;
const artifacts = process.env.ISSUERS_BROWSER_ARTIFACTS;
const previous = process.env.ISSUERS_PURCHASE_PRIOR_PROOF;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert(artifacts && path.isAbsolute(artifacts));
assert(previous && path.isAbsolute(previous));
const owner = JSON.parse(fs.readFileSync(previous, 'utf8')).owners.a;
assert(Number.isSafeInteger(owner.issuer) && Number.isSafeInteger(owner.request));
fs.mkdirSync(artifacts, {recursive: true});

(async () => {
  const browser = await chromium.launch({headless: true, ...(process.env.ISSUERS_BROWSER_EXECUTABLE_PATH ? {executablePath: process.env.ISSUERS_BROWSER_EXECUTABLE_PATH} : {})});
  const context = await browser.newContext({viewport: {width: 1360, height: 980}});
  await context.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
  const page = await context.newPage();
  const proof = {result: 'RUNNING', source_fixture: previous, pages: [], errors: []};
  page.on('pageerror', error => proof.errors.push(error.message));
  async function submit(form) {
    await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), form.locator('button').last().click()]);
    await page.waitForLoadState('networkidle');
  }
  try {
    await page.goto(url + '/admin/authentication');
    await page.locator('input[name=email]').fill('admin-ci@example.invalid');
    await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await submit(page.locator('form').filter({has: page.locator('input[name=email]')}));
    assert(!new URL(page.url()).pathname.includes('authentication'));
    await page.goto(url + '/admin/');
    await selectCompanyOption(page, String(owner.issuer));
    for (const width of [390, 320]) {
      await page.setViewportSize({width, height: 844});
      for (const query of ['request=' + owner.request, 'tab=requests', 'tab=suppliers', 'tab=orders', 'tab=payables', 'tab=report', 'tab=settings']) {
        assert.equal((await page.goto(url + '/admin/expenses/purchases?' + query)).status(), 200);
        await page.waitForLoadState('networkidle');
        const metrics = await page.evaluate(() => ({
          viewport: window.innerWidth,
          document: document.documentElement.scrollWidth,
          outside: [...document.querySelectorAll('.company-settings-card, .company-settings-card *')].filter(element => {
            const rect = element.getBoundingClientRect();
            if (!rect.width || !rect.height || rect.right <= window.innerWidth + 1) return false;
            for (let parent = element.parentElement; parent; parent = parent.parentElement) {
              if (['auto', 'scroll', 'hidden', 'clip'].includes(getComputedStyle(parent).overflowX) && parent.getBoundingClientRect().right <= window.innerWidth + 1) return false;
            }
            return true;
          }).map(element => ({tag: element.tagName, class: element.className, text: element.textContent.trim().slice(0, 110), right: element.getBoundingClientRect().right}))
        }));
        proof.pages.push({width, query, ...metrics});
        await page.screenshot({path: path.join(artifacts, 'mobile-' + width + '-' + query.replace('=', '-') + '.png'), fullPage: true});
        assert(metrics.document <= metrics.viewport + 1, 'Purchase page fits mobile viewport without horizontal overflow');
      }
    }
    assert.deepEqual(proof.errors, []);
    proof.result = 'PASS';
  } catch (error) {
    proof.result = 'FAIL';
    proof.error = error.stack;
    throw error;
  } finally {
    fs.writeFileSync(path.join(artifacts, 'purchases-mobile-proof.json'), JSON.stringify(proof, null, 2) + '\n');
    console.log(JSON.stringify({result: proof.result, pages: proof.pages, errors: proof.errors}));
    await context.close();
    await browser.close();
  }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
