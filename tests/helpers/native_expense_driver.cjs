'use strict';
const assert = require('node:assert/strict');

// Exercise the retained current financial workspace through its actual visible
// action on the restored original list; never change hidden DOM or bypass ACL.
async function openNativeExpenseWorkspace(page, baseUrl) {
  const response = await page.goto(baseUrl + '/admin/expenses', { waitUntil: 'domcontentloaded' });
  assert.equal(response?.status(), 200, 'original expense list must be accessible');
  const link = page.locator('[data-native-expenses] a[href*="workspace=1"]');
  await link.waitFor({ state: 'visible' });
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), link.click()]);
  assert.equal(new URL(page.url()).searchParams.get('workspace'), '1');
}

module.exports = { openNativeExpenseWorkspace };
