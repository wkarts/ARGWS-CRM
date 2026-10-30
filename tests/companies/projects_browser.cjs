'use strict';
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const { picker } = require('../helpers/native_invoice_driver.cjs');
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const url = process.env.ISSUERS_BROWSER_URL, app = process.env.ISSUERS_BROWSER_APP_CONTAINER, db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');
function sql(q) { return execFileSync('docker', ['exec', '-e', 'MYSQL_PWD', db, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--user=' + process.env.ISSUERS_BROWSER_DB_USER, 'argws_upgrade_ci', '--batch', '--skip-column-names', '-e', q], { env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD }, encoding: 'utf8' }).trim(); }
async function nav(page, button) { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]); }
async function hidden(form) { return form.locator('input[type=hidden]').evaluateAll(xs => Object.fromEntries(xs.filter(x => x.name).map(x => [x.name, x.value]))); }
async function richText(page, id, text) {
  if (await page.locator('#' + id + '_ifr').count()) await page.frameLocator('#' + id + '_ifr').locator('body').fill(text);
  else await page.locator('#' + id).fill(text);
}
async function projectSave(page) {
  const form = page.locator('#project_form'), endpoint = new URL(await form.getAttribute('action'), url).pathname;
  const [response] = await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === endpoint), form.locator('button[type=submit]').click()]);
  const request = response.request();
  const replay = await page.context().request.post(request.url(), { data: request.postDataBuffer(), headers: { 'content-type': request.headers()['content-type'] }, maxRedirects: 0 });
  assert.equal(replay.status(), 303, 'original project POST and durable replay redirect with 303');
  assert.match(new URL(replay.headers().location, url).pathname, /^\/admin\/projects\/view\/[1-9]\d*$/);
  if (response.status() !== 303) { assert.equal(response.fromServiceWorker(), true); assert.equal(response.status(), 200); }
  await page.waitForURL(/\/admin\/projects\/view\/[1-9]\d*$/);
  return new URL(page.url()).pathname.split('/').pop();
}
async function taskCommand(page, endpoint, click, { refresh = true } = {}) {
  const before = await page.locator('[data-native-task-detail]').getAttribute('data-task-revision');
  const [response] = await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname.startsWith('/admin/tasks/' + endpoint)), click()]);
  assert.equal(response.status(), 200, 'original task action ' + endpoint);
  const json = await response.json();
  assert.equal(json.success, true, 'original task action confirms its stored effect');
  if (refresh) await page.waitForFunction(revision => document.querySelector('[data-native-task-detail]')?.dataset.taskRevision !== revision, before);
  return response;
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  await context.route('**/*', r => new URL(r.request().url()).origin === url ? r.continue() : r.abort());
  const page = await workspacePage(context), errors = [];
  page.on('pageerror', e => { errors.push(e.stack || e.message); console.error('Browser JavaScript:', e.stack || e.message); });
  try {
    await page.goto(url + '/admin/authentication');
    await page.locator('input[name=email]').fill('admin-ci@example.invalid');
    await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
    await nav(page, page.locator('button[type=submit]'));
    const a = sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'"), b = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
    const projects = {}, tasks = {};
    for (const [issuer, letter] of [[a, 'a'], [b, 'b']]) {
      await page.goto(url + '/admin/'); await selectCompanyOption(page, issuer);
      const client = sql("SELECT userid FROM tblclients WHERE company='Synthetic portal client " + letter + "'");
      assert.equal((await page.goto(url + '/admin/projects/project?customer_id=' + client)).status(), 200);
      await page.locator('#name').fill('Browser project ' + letter + ' <script>');
      await picker(page, '#billing_type', '1');
      await page.locator('input[name=project_cost]').fill('120.00');
      await page.locator('#start_date').fill('19/10/2026');
      await page.locator('#deadline').fill('19/11/2026');
      await richText(page, 'description', 'Private project ' + letter);
      await page.locator('#send_created_email').uncheck();
      projects[issuer] = await projectSave(page);
      const project = String(BigInt(projects[issuer]) - 4294967296n);
      assert.equal(sql('SELECT issuer_id FROM tblcompany_projects WHERE id=' + project), issuer);
      assert.equal(await page.locator('h3').filter({ hasText: 'Browser project ' + letter + ' <script>' }).count(), 1, 'project name rendered as escaped text');
      const [formResponse, relationResponse] = await Promise.all([page.waitForResponse(r => r.request().method() === 'GET' && new URL(r.url()).pathname === '/admin/tasks/task'), page.waitForResponse(r => new URL(r.url()).pathname.startsWith('/admin/projects/get_rel_project_data/')), page.locator('a[onclick*="new_task_from_relation"]').click()]);
      assert.equal(formResponse.status(), 200);
      assert.equal(relationResponse.status(), 200, 'original relation callback resolves this project and its actual members');
      await page.locator('#_task_modal').waitFor({ state: 'visible' });
      await page.locator('#task-form #name').fill('Browser task ' + letter);
      await picker(page, '#assignees', ['17']);
      const [created] = await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/admin/tasks/task'), page.locator('#task-form button[type=submit]').click()]);
      assert.equal(created.status(), 200);
      const creation = await created.json(); assert.equal(creation.success, true);
      tasks[issuer] = String(creation.id);
      assert.match(tasks[issuer], /^[1-9]\d*$/);
      const task = String(BigInt(tasks[issuer]) - 8589934592n);
      assert.equal(sql('SELECT issuer_id FROM tblcompany_tasks WHERE id=' + task), issuer);
      await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
      await taskCommand(page, 'checklist_command/', () => page.locator('button[onclick*=\'"initialize"\']').click(), { refresh: false });
      // Re-read the original task route after checklist transactions: all forms
      // must carry the committed task revision, not a test-supplied replacement.
      await page.locator('[data-native-task-detail] .modal-header .close').click();
      await page.goto(url + '/admin/tasks/list_tasks/' + tasks[issuer]);
      await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
      await page.locator('button[onclick*="add_task_checklist_item"]').click();
      const checklistText = page.locator('textarea[name=checklist-description]').last();
      await checklistText.fill('Synthetic checklist');
      await taskCommand(page, 'add_checklist_item', () => checklistText.press('Tab'), { refresh: false });
      await page.goto(url + '/admin/tasks/list_tasks/' + tasks[issuer]);
      await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
      const commentForm = page.locator('form[action$="/tasks/add_task_comment"]');
      const stale = await hidden(commentForm);
      await commentForm.locator('textarea[name=content]').fill('Private comment ' + letter + ' <script>');
      await taskCommand(page, 'add_task_comment', () => commentForm.locator('button[type=submit]').click());
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_comments WHERE task_id=' + task), '1');
      assert((await page.locator('[data-native-task-detail]').innerText()).includes('Private comment ' + letter + ' <script>'));
      const old = await context.request.post(url + '/admin/tasks/add_task_comment', { form: { ...stale, native_request_key: randomBytes(32).toString('hex'), content: 'Stale must fail' } });
      assert.equal(old.status(), 409);
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_comments WHERE task_id=' + task), '1', 'stale comment has no side effect');
      await taskCommand(page, 'timer_tracking', () => page.locator('[data-native-task-detail] button[onclick*="timer_action"]').click());
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_timers WHERE task_id=' + task + ' AND stopped_at IS NULL'), '1');
      await taskCommand(page, 'timer_tracking', () => page.locator('[data-native-task-detail] button[onclick*="timer_action"]').click());
      assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_timers WHERE task_id=' + task + ' AND stopped_at IS NULL'), '0');
      await taskCommand(page, 'checkbox_action/', () => page.locator('input[name=checklist-box]').check(), { refresh: false });
      await page.goto(url + '/admin/tasks/list_tasks/' + tasks[issuer]);
      await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
      assert.equal(await page.locator('input[name=checklist-box]').isChecked(), true, 'stored checklist completion survives re-read');
      const statusForm = page.locator('form[action*="/tasks/mark_as/"]');
      await statusForm.locator('select').selectOption('5');
      await taskCommand(page, 'mark_as/5/', () => statusForm.locator('button[type=submit]').click());
      assert.equal(sql('SELECT status FROM tblcompany_tasks WHERE id=' + task), 'done');
      await page.goto(url + '/admin/projects/project/' + projects[issuer]);
      await picker(page, '#status', String(85899345922n));
      for (const name of ['project_marked_as_finished_email_to_contacts', 'mark_all_tasks_as_completed', 'notify_project_members_status_change', 'cancel_recurring_tasks']) {
        const option = page.locator('input[name="' + name + '"]'); if (await option.count() && await option.isVisible()) await option.uncheck();
      }
      assert.equal(await projectSave(page), projects[issuer]);
      assert.equal(sql('SELECT status FROM tblcompany_projects WHERE id=' + project), 'completed');
    }
    assert.equal((await context.request.get(url + '/admin/projects/view/' + projects[a])).status(), 403, 'B cannot read A project');
    assert.equal((await context.request.get(url + '/admin/tasks/get_task_data/' + tasks[a])).status(), 403, 'B cannot read A task/comments/timer');
    assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_comments WHERE issuer_id=' + a), '1');
    assert.equal(sql('SELECT COUNT(*) FROM tblcompany_task_comments WHERE issuer_id=' + b), '1');
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ projects: 'A/B original forms, actual 303 and replay, own tasks/checklist/comment/timer completed, parent closed, exact stale 409/cross-owner 403', external_transports: false }));
  } finally { await context.close(); await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
