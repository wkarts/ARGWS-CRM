'use strict';
const { selectCompanyOption } = require('../helpers/company_selector_driver.cjs');
const { workspacePage } = require('../helpers/company_workspace_driver.cjs');
// Official staff and contact contract routes with private publication. Disposable fixture only.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
function sql(q) {
  return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', q], { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim();
}
const key = value => createHash('sha256').update(value).digest('hex');
async function go(page, button) { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]); }
async function hidden(form) { return form.locator('input[type=hidden]').evaluateAll(inputs => Object.fromEntries(inputs.filter(input => input.name).map(input => [input.name, input.value]))); }

(async () => {
  const browser = await chromium.launch({ headless: true });
  const contexts = [], own = {}, errors = [];
  try {
    async function context() {
      const ctx = await browser.newContext();
      contexts.push(ctx);
      await ctx.route('**/*', route => new URL(route.request().url()).origin === url ? route.continue() : route.abort());
      const page = await workspacePage(ctx);
      page.on('pageerror', error => errors.push(error.message));
      return { ctx, page };
    }
    const { page: admin } = await context();
    await admin.goto(url + '/admin/authentication');
    await admin.locator('input[name=email]').fill('admin-ci@example.invalid');
    await admin.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await go(admin, admin.locator('button[type=submit]'));
    const a = sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'");
    const b = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
    const proofPdf = Buffer.from('%PDF-1.4\nOfficial contract attachment proof\n%%EOF\n');
    async function select(issuer) { await admin.goto(url + '/admin/'); await selectCompanyOption(admin, issuer); }
    async function visibility(comment, visible) {
      const form = admin.locator('#contract-discussion form').filter({ has: admin.locator('input[name=comment_id][value="' + comment + '"]') });
      await form.locator('input[name=visible]').setChecked(visible);
      await go(admin, form.locator('button[type=submit]'));
    }

    for (const [issuer, letter] of [[a, 'a'], [b, 'b']]) {
      await select(issuer);
      const client = sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client " + letter + "'");
      const email = letter === 'a' ? 'changed-portal@example.invalid' : 'same-portal@example.invalid';
      const contact = sql("SELECT id FROM tblcontacts WHERE userid=" + client + " AND email='" + email + "'");
      sql('UPDATE tblcontacts SET email_verified_at=COALESCE(email_verified_at,UTC_TIMESTAMP()) WHERE id=' + contact);

      await admin.goto(url + '/admin/contracts/contract?source=company');
      await admin.locator('#contract-client').selectOption(client);
      await admin.locator('#contract-new-name').fill('Native discussion ' + letter);
      await admin.locator('#contract-new-body').fill('Immutable contractual text ' + letter + ' <script>literal</script>');
      await admin.locator('#contract-new-start').fill('2026-10-19');
      await admin.locator('#contract-new-due').fill('2026-11-19');
      await go(admin, admin.locator('form').filter({ has: admin.locator('#contract-client') }).locator('button[type=submit]'));
      const canonical = new URL(admin.url()).pathname.split('/').filter(Boolean).at(-1);
      assert.match(canonical || '', /^[1-9]\d*$/);
      const id = Number(BigInt(canonical) % 4294967296n);
      assert(id > 0);
      await go(admin, admin.getByRole('button', { name: 'Ativar contrato e preservar texto', exact: true }));
      await admin.locator('#operation-file').setInputFiles({ name: 'official-contract.pdf', mimeType: 'application/pdf', buffer: proofPdf });
      await go(admin, admin.locator('form').filter({ has: admin.locator('#operation-file') }).locator('button[type=submit]'));
      const file = sql("SELECT file_key FROM tblcompany_contract_files WHERE contract_id=" + id + " AND filename='official-contract.pdf'");
      const snapshot = sql('SELECT HEX(snapshot_json),number,status,HEX(body) FROM tblcompany_contracts WHERE id=' + id);
      const parent = admin.locator('#operation-portal-publication form').filter({ has: admin.locator('input[name=publication_type][value=record]') });
      await parent.locator('input[name=visible]').check();
      await go(admin, parent.locator('button[type=submit]'));
      const filePublication = admin.locator('#operation-portal-publication form').filter({ has: admin.locator('input[name=publication_type][value=file]') }).filter({ has: admin.locator('input[name=file_key][value="' + file + '"]') });
      await filePublication.locator('input[name=visible]').check();
      await go(admin, filePublication.locator('button[type=submit]'));
      const grant = admin.locator('#operation-portal-publication form').filter({ has: admin.locator('input[name=action][value=portal_permission]') }).filter({ has: admin.locator('input[name=contact_id][value="' + contact + '"]') });
      await grant.locator('input[name=allowed]').check();
      await go(admin, grant.locator('button[type=submit]'));

      const staffBody = 'Internal ' + letter + ' <script>throw new Error("executed")</script>';
      await admin.locator('#contract-discussion-body').fill(staffBody);
      await go(admin, admin.locator('#contract-discussion-add button'));
      assert.equal(new URL(admin.url()).pathname.split('/').filter(Boolean).at(-1), canonical, 'redirect preserves parent rather than comment id');
      const comment = sql('SELECT MAX(id) FROM tblcompany_contract_discussions WHERE contract_id=' + id);
      assert.equal(sql('SELECT visible FROM tblcompany_contract_discussions WHERE id=' + comment), '0');
      assert((await admin.locator('#contract-discussion').innerText()).includes(staffBody));

      const customer = await context();
      own[issuer] = { ...customer, id, canonical, contact };
      await customer.page.goto(url + '/authentication/login?issuer_id=' + issuer);
      await customer.page.locator('#email').fill(email);
      await customer.page.locator('#password').fill('synthetic-password-' + letter);
      await go(customer.page, customer.page.locator('.login-form button[type=submit]'));
      assert.equal(await customer.page.locator('#greeting').count(), 1, 'official contact login establishes the scoped portal session');
      const detail = url + '/clients/contracts/' + canonical + '?issuer_id=' + issuer;
      own[issuer].detail = detail;
      await customer.page.goto(detail);
      assert(!(await customer.page.locator('#native-portal-contract-discussion').innerText()).includes(staffBody));
      const fileForm = customer.page.locator('#native-portal-contract-files form').filter({ has: customer.page.locator('input[name=native_contract_action][value=file]') });
      const fileResponse = await customer.ctx.request.post(detail, { form: await hidden(fileForm) });
      assert.equal(fileResponse.status(), 200);
      assert.deepEqual(await fileResponse.body(), proofPdf);
      assert.match(fileResponse.headers()['content-security-policy'], /sandbox/);
      const pdfForm = customer.page.locator('#native-portal-contract-detail form').filter({ has: customer.page.locator('input[name=native_contract_action][value=pdf]') });
      const pdfResponse = await customer.ctx.request.post(detail, { form: await hidden(pdfForm) });
      assert.equal(pdfResponse.status(), 200);
      assert.equal((await pdfResponse.body()).subarray(0, 5).toString(), '%PDF-');
      await visibility(comment, true);
      await customer.page.reload();
      assert((await customer.page.locator('#native-portal-contract-discussion').innerText()).includes(staffBody));
      assert.match(await customer.page.locator('#native-portal-contract-discussion').innerText(), /não representam assinatura/i);

      const fields = await hidden(customer.page.locator('#native-portal-contract-comment'));
      const body = 'Customer ' + letter + ' <img src=x onerror=alert(1)> literal';
      await customer.page.locator('#native-portal-contract-comment-body').fill(body);
      await go(customer.page, customer.page.locator('#native-portal-contract-comment button'));
      assert((await customer.page.locator('#native-portal-contract-discussion').innerText()).includes(body));
      assert.equal(await customer.page.locator('#native-portal-contract-discussion img').count(), 0);
      const customerComment = sql('SELECT MAX(id) FROM tblcompany_contract_discussions WHERE contract_id=' + id);
      assert.equal(sql('SELECT CONCAT(author_type,":",IFNULL(staff_id,"NULL"),":",contact_id,":",visible) FROM tblcompany_contract_discussions WHERE id=' + customerComment), 'contact:NULL:' + contact + ':1');
      assert.equal((await customer.ctx.request.post(detail, { form: { ...fields, discussion_body: body }, maxRedirects: 0 })).status(), 303);
      assert.equal((await customer.ctx.request.post(detail, { form: { ...fields, request_key: key('discussion-stale-' + letter), discussion_body: body }, maxRedirects: 0 })).status(), 409);

      await admin.reload();
      assert((await admin.locator('#contract-discussion').innerText()).includes(body));
      await visibility(customerComment, false);
      await customer.page.reload();
      assert(!(await customer.page.locator('#native-portal-contract-discussion').innerText()).includes(body));
      assert.equal((await customer.ctx.request.post(detail, { form: { ...fields, discussion_body: body }, maxRedirects: 0 })).status(), 303);
      assert.equal(sql('SELECT visible FROM tblcompany_contract_discussions WHERE id=' + customerComment), '0', 'old receipt cannot republish a withdrawn comment');
      assert.equal(sql('SELECT HEX(snapshot_json),number,status,HEX(body) FROM tblcompany_contracts WHERE id=' + id), snapshot);
      const fresh = await hidden(customer.page.locator('#native-portal-contract-comment'));
      assert.equal((await customer.ctx.request.post(detail, { form: { ...fresh, discussion_body: ' ' }, maxRedirects: 0 })).status(), 422);
      const noCsrf = { ...fresh, discussion_body: 'No native csrf' };
      delete noCsrf._native_portal_csrf;
      assert.equal((await customer.ctx.request.post(detail, { form: noCsrf, maxRedirects: 0 })).status(), 403);
      own[issuer].fields = fresh;
    }

    const before = sql('SELECT revision FROM tblcompany_contracts WHERE id=' + own[a].id);
    const cross = url + '/clients/contracts/' + own[a].canonical + '?issuer_id=' + b;
    assert.equal((await own[b].ctx.request.post(cross, { form: { ...own[b].fields, discussion_body: 'Cross tenant attempt' }, maxRedirects: 0 })).status(), 404);
    assert.equal(sql('SELECT revision FROM tblcompany_contracts WHERE id=' + own[a].id), before);
    const grant = admin.locator('#operation-portal-publication form').filter({ has: admin.locator('input[name=action][value=portal_permission]') }).filter({ has: admin.locator('input[name=contact_id][value="' + own[b].contact + '"]') });
    await grant.locator('input[name=allowed]').uncheck();
    await go(admin, grant.locator('button[type=submit]'));
    assert.equal((await own[b].ctx.request.post(own[b].detail, { form: { ...own[b].fields, discussion_body: 'Revoked form' }, maxRedirects: 0 })).status(), 404);
    assert.equal((await own[b].ctx.request.get(url + '/clients/contracts?issuer_id=' + b)).status(), 404);
    await own[b].page.goto(url + '/clients?issuer_id=' + b);
    assert.equal(await own[b].page.locator('a[href*="/clients/contracts"]').count(), 0);
    assert.deepEqual(errors, []);
    console.log('Official contract routes A+B staff/contact3 list/detail/discussion, typed IDs, literal text, receipts, stale, revoke, cross owner, CSRF and immutable snapshot passed. No signature or external delivery.');
  } finally {
    for (const ctx of contexts) await ctx.close();
    await browser.close();
  }
})().catch(error => { console.error(error.stack || error.message); process.exitCode = 1; });
