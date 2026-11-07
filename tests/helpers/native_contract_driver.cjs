'use strict';
const assert = require('node:assert/strict');
const {picker}=require('./native_invoice_driver.cjs');

/** Bootstrap-select keeps the original select hidden. Use its visible dropdown and await the real change transport and hydration. */
async function selectContractClient(page, client) {
  client = String(client);
  const readsProjects = await page.locator('#contract-form[data-native-contract-editor] #contract-project').count() === 1;
  const response = readsProjects ? page.waitForResponse(r => {
    const u = new URL(r.url());
    return u.pathname.endsWith('/admin/contracts/client_change_data') && u.searchParams.get('client_id') === client && r.request().method() === 'GET';
  }) : null;
  await picker(page, '#contract-client', client);
  if (!response) return null;
  const result = await response;
  assert.equal(result.status(), 200, 'contract client projects confirmed under the captured company context');
  const data = await result.json(); assert.equal(String(data.client_id), client);
  await page.waitForFunction(count => {
    const project = document.querySelector('#contract-project');
    return project && !project.disabled && project.options.length === count + 1;
  }, data.projects.length);
  assert(await page.locator('#contract-form-error').evaluate(node => node.classList.contains('hide')));
  return data;
}

module.exports = {selectContractClient};
