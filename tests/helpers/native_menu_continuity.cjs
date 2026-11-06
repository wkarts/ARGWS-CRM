'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Called only inside the disposable authenticated banking fixture. Never stores
// form values, URLs containing context tokens, session cookies or customer text.
module.exports = async function nativeMenuContinuity(page, origin, issuer, output) {
  const steps = [
    ['dashboard-before', '/admin/'],
    ['bb-operation', '/admin/banco_brasil'],
    ['bb-settings', '/admin/settings?group=payment_gateways&gateway=banco_brasil'],
    ['company-settings', '/admin/settings?group=company'],
    ['dashboard-after', '/admin/'],
    ['company-settings-after', '/admin/settings?group=company'],
  ];
  const evidence = [];
  let baseline;
  try {
    for (const [step, route] of steps) {
      const response = await page.goto(origin + route, {waitUntil:'domcontentloaded'});
      assert.equal(response.status(), 200, 'authenticated navigation: ' + step);
      await page.locator('#side-menu').waitFor({state:'attached'});
      await page.waitForFunction(() => window.jQuery && window.jQuery('#setup-menu').data('metisMenu') && window.jQuery('#side-menu').data('metisMenu'));
      assert.equal(await page.locator('.company-selector').getAttribute('data-company-current'), String(issuer), 'company preserved: ' + step);
      const readMenu = () => page.evaluate(() => {
        const topology = selector => Array.from(document.querySelectorAll(selector + ' li')).map(li => {
          const slug = Array.from(li.classList).find(name => name.startsWith('menu-item-') || name.startsWith('sub-menu-item-'));
          return slug || null;
        }).filter(Boolean).sort();
        return {sidebar:topology('#side-menu'), settings:topology('#setup-menu')};
      });
      const before = await readMenu();
      assert(before.sidebar.length > 0 && before.settings.length > 0, 'real menus are nonempty: ' + step);
      const toggle = page.locator('#setup-menu-item .open-customizer');
      assert.equal(await toggle.count(), 1, 'native definitions toggle exists: ' + step);
      if (!(await page.locator('#setup-menu-wrapper').isVisible())) await toggle.click();
      await page.locator('#setup-menu-wrapper').waitFor({state:'visible'});
      const opened = await readMenu();
      evidence.push({step, ...opened});
      assert.deepEqual(opened, before, 'opening definitions preserves the complete menu tree: ' + step);
      if (!baseline) baseline = opened;
      assert.deepEqual(opened, baseline, 'same authorized menus across routes: ' + step);
    }
  } finally {
    fs.mkdirSync(output, {recursive:true});
    fs.writeFileSync(path.join(output, 'menu-continuity-company-' + issuer + '.json'), JSON.stringify({issuer,steps:evidence}, null, 2));
  }
};
