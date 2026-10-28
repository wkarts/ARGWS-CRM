'use strict';
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');

function sql(query) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', query], { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
async function nav(page, button) { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]); }
async function hidden(form) { return form.locator('input[type=hidden]').evaluateAll(xs => Object.fromEntries(xs.filter(x => x.name).map(x => [x.name, x.value]))); }
function currentContract(page) {
  const canonical = new URL(page.url()).pathname.split('/').filter(Boolean).at(-1);
  assert.match(canonical || '', /^[1-9]\d*$/);
  const raw = Number(BigInt(canonical) % 4294967296n);
  assert(raw > 0);
  return { canonical, raw };
}

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  const ctx = await browser.newContext();
  await ctx.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
  const page = await workspacePage(ctx), errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(url + '/admin/authentication');
    await page.locator('input[name=email]').fill('admin-ci@example.invalid');
    await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await nav(page, page.locator('button[type=submit]'));
    const a = sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'");
    const b = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
    const primary = sql('SELECT primary_issuer_id FROM tblissuers_installation_identity WHERE id=1');
    const contracts = {}, tickets = {};
    async function select(id) { await page.goto(url + '/admin/'); await selectCompanyOption(page, id); }

    const legacyClient = sql('SELECT c.userid FROM tblclients c LEFT JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.client_id IS NULL ORDER BY c.userid LIMIT 1');
    assert.match(legacyClient, /^[1-9]\d*$/, 'upgrade fixture retains an ownerless primary customer');
    sql("INSERT INTO tblcontracts(content,description,subject,client,datestart,dateend,contract_type,project_id,addedfrom,dateadded,isexpirynotified,contract_value,trash,not_visible_to_client,hash,signed,signature,marked_as_signed) SELECT '<p>Historical primary body</p>','Historical primary description','Browser historical primary contract'," + legacyClient + ",'2020-01-01','2030-01-01',NULL,NULL,17,UTC_TIMESTAMP(),0,10.00,0,0,MD5('browser-historical-primary-contract'),0,NULL,0 WHERE NOT EXISTS(SELECT 1 FROM tblcontracts WHERE subject='Browser historical primary contract')");
    const historical = sql("SELECT id FROM tblcontracts WHERE subject='Browser historical primary contract'");
    const historicalBefore = sql('SELECT JSON_ARRAY(id,subject,client,content,hash,signed) FROM tblcontracts WHERE id=' + historical);
    await select(primary);
    await page.goto(url + '/admin/contracts/contract/' + historical);
    assert.equal(await page.locator('#contract-form input[name=subject]').inputValue(), 'Browser historical primary contract', 'principal starts on the unchanged legacy contract editor');

    for (const [issuer, letter] of [[a, 'a'], [b, 'b']]) {
      await select(issuer);
      const client = sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client " + letter + "'");
      await page.goto(url + '/admin/contracts/contract?source=company');
      await page.locator('#contract-client').selectOption(client);
      await page.locator('#contract-new-name').fill('Browser contract ' + letter + ' <script>');
      await page.locator('#contract-new-body').fill('Synthetic contract ' + letter + ' <script>literal</script>');
      await page.locator('#contract-new-start').fill('2026-10-19');
      await page.locator('#contract-new-due').fill('2026-11-19');
      await nav(page, page.locator('form').filter({ has: page.locator('#contract-client') }).locator('button[type=submit]'));
      const contract = currentContract(page);
      contracts[issuer] = contract;
      assert.equal(sql('SELECT issuer_id FROM tblcompany_contracts WHERE id=' + contract.raw), issuer);
      assert.equal(sql('SELECT status FROM tblcompany_contracts WHERE id=' + contract.raw), 'draft');

      const old = await hidden(page.locator('form').filter({ has: page.locator('input[name=action][value=cancel]') }));
      await nav(page, page.getByRole('button', { name: 'Ativar contrato e preservar texto', exact: true }));
      assert.equal(sql('SELECT status FROM tblcompany_contracts WHERE id=' + contract.raw), 'active');
      const pdf = await ctx.request.get(url + '/admin/contracts/pdf/' + contract.canonical);
      assert.equal(pdf.status(), 200);
      assert.equal((await pdf.body()).subarray(0, 5).toString(), '%PDF-');
      const current = await hidden(page.locator('form').filter({ has: page.locator('input[name=action][value=cancel]') }));
      const stale = await ctx.request.post(url + '/admin/contracts/contract/' + contract.canonical, { form: { ...current, ...old, request_key: current.request_key } });
      assert.equal(stale.status(), 409, 'stale contract cancel cannot overwrite activation');
      assert.equal(sql('SELECT status FROM tblcompany_contracts WHERE id=' + contract.raw), 'active');

      await nav(page, page.getByRole('button', { name: 'Copiar para novo rascunho', exact: true }));
      const copy = currentContract(page);
      assert.notEqual(copy.canonical, contract.canonical);
      await page.locator('#contract-edit-body').fill('New independent draft ' + letter);
      await nav(page, page.locator('form').filter({ has: page.locator('#contract-edit-body') }).locator('button[type=submit]'));
      assert.equal(sql('SELECT body FROM tblcompany_contracts WHERE id=' + copy.raw), 'New independent draft ' + letter);
      await nav(page, page.getByRole('button', { name: 'Cancelar e preservar histórico', exact: true }));
      assert.equal(sql('SELECT status FROM tblcompany_contracts WHERE id=' + copy.raw), 'cancelled');
      assert.equal(sql('SELECT status FROM tblcompany_contracts WHERE id=' + contract.raw), 'active', 'copy does not change source');

      await page.goto(url + '/admin/tickets');
      await page.locator('#ticket-client').selectOption(client);
      await page.locator('#ticket-subject').fill('Browser support ' + letter + ' <script>');
      await page.locator('#ticket-body').fill('Private support body ' + letter);
      await nav(page, page.locator('form').filter({ has: page.locator('#ticket-subject') }).locator('button[type=submit]'));
      const ticket = new URL(page.url()).searchParams.get('ticket');
      tickets[issuer] = ticket;
      assert.match(ticket || '', /^[1-9]\d*$/);
      assert.equal(sql('SELECT issuer_id FROM tblcompany_tickets WHERE id=' + ticket), issuer);
      const oldReply = await hidden(page.locator('form').filter({ has: page.locator('#ticket-reply') }));
      await page.locator('#ticket-reply').fill('Synthetic private reply ' + letter + ' <script>');
      await nav(page, page.locator('form').filter({ has: page.locator('#ticket-reply') }).locator('button[type=submit]'));
      const freshReply = await hidden(page.locator('form').filter({ has: page.locator('#ticket-reply') }));
      assert.equal((await ctx.request.post(url + '/admin/tickets', { form: { ...freshReply, ...oldReply, request_key: freshReply.request_key, reply: 'Stale reply' } })).status(), 409);
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_ticket_replies WHERE ticket_id=' + ticket), '1');
      await page.locator('#ticket-state').selectOption('resolved');
      await nav(page, page.getByRole('button', { name: 'Salvar estado e responsável', exact: true }));
      assert.equal(await page.locator('#ticket-reply').count(), 0);
      await page.locator('#ticket-state').selectOption('open');
      await nav(page, page.getByRole('button', { name: 'Salvar estado e responsável', exact: true }));
      await page.locator('#ticket-reply').fill('After explicit reopening ' + letter);
      await nav(page, page.locator('form').filter({ has: page.locator('#ticket-reply') }).locator('button[type=submit]'));
      await page.locator('#ticket-state').selectOption('closed');
      await nav(page, page.getByRole('button', { name: 'Salvar estado e responsável', exact: true }));
      assert.equal(sql('SELECT status FROM tblcompany_tickets WHERE id=' + ticket), 'closed');
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_ticket_replies WHERE ticket_id=' + ticket), '2');
    }

    assert.equal((await ctx.request.get(url + '/admin/contracts/contract/' + contracts[a].canonical)).status(), 403);
    assert.equal((await ctx.request.get(url + '/admin/contracts/pdf/' + contracts[a].canonical)).status(), 403);
    assert.equal((await ctx.request.get(url + '/admin/tickets?ticket=' + tickets[a])).status(), 409);

    await select(primary);
    let primaryClient = sql("SELECT userid FROM tblclients WHERE company='Browser primary contract customer'");
    if (primaryClient === '') {
      await page.goto(url + '/admin/clients/client');
      await page.locator('#company').fill('Browser primary contract customer');
      await nav(page, page.locator('#profile-save-section .only-save'));
      primaryClient = sql("SELECT userid FROM tblclients WHERE company='Browser primary contract customer'");
    }
    assert.match(primaryClient, /^[1-9]\d*$/);
    await page.goto(url + '/admin/contracts/contract?source=company');
    await page.locator('#contract-client').selectOption(primaryClient);
    await page.locator('#contract-new-name').fill('Browser principal owned contract');
    await page.locator('#contract-new-body').fill('Principal company-owned contract body');
    await page.locator('#contract-new-start').fill('2026-10-19');
    await page.locator('#contract-new-due').fill('2026-11-19');
    await nav(page, page.locator('form').filter({ has: page.locator('#contract-client') }).locator('button[type=submit]'));
    const principalOwned = currentContract(page);
    await nav(page, page.getByRole('button', { name: 'Ativar contrato e preservar texto', exact: true }));
    assert.equal(sql('SELECT issuer_id FROM tblcompany_contracts WHERE id=' + principalOwned.raw), primary);
    assert.equal(sql('SELECT COUNT(*) FROM tblcontracts WHERE id=' + principalOwned.raw), '0', 'fixture reserves the same raw number for the explicit collision proof');
    sql("INSERT INTO tblcontracts(id,content,description,subject,client,datestart,dateend,contract_type,project_id,addedfrom,dateadded,isexpirynotified,contract_value,trash,not_visible_to_client,hash,signed,signature,marked_as_signed) VALUES(" + principalOwned.raw + ",'<p>Colliding historical body</p>','Collision proof','Browser colliding historical contract'," + legacyClient + ",'2020-01-01','2030-01-01',NULL,NULL,17,UTC_TIMESTAMP(),0,20.00,0,0,MD5('browser-colliding-primary-contract'),0,NULL,0)");

    await page.goto(url + '/admin/contracts');
    const ownedLink = page.locator('#company-owned-contracts a[href$="/contracts/contract/' + principalOwned.canonical + '"]');
    assert.equal(await ownedLink.count(), 1, 'principal official index unions a typed company-owned contract into the legacy screen');
    assert((await page.locator('#company-owned-contracts').innerText()).includes('Browser principal owned contract'));
    await page.goto(url + '/admin/contracts/contract/' + principalOwned.raw);
    assert.equal(await page.locator('#contract-form input[name=subject]').inputValue(), 'Browser colliding historical contract', 'raw colliding ID remains on the original legacy editor');
    await page.goto(url + '/admin/contracts/contract/' + principalOwned.canonical);
    assert.equal(await page.locator('#contract-discussion').count(), 1, 'typed colliding ID enters the owned contract adapter');
    assert((await page.locator('.content').innerText()).includes('Browser principal owned contract'));
    assert.equal(sql('SELECT JSON_ARRAY(id,subject,client,content,hash,signed) FROM tblcontracts WHERE id=' + historical), historicalBefore, 'principal to A/B to principal leaves the historical contract unchanged');
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ contracts: 'official route principal to A/B to principal, legacy+typed union/collision, draft activation snapshot PDF stale copy cancel passed', support: 'A/B create reply resolve reopen close stale/cross-owner passed', external_transports: false, signatures: false }));
  } finally {
    await ctx.close();
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
