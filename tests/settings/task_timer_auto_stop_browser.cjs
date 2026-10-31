'use strict';

const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const {
    createNativeProject,
    createNativeProjectTask,
    openNativeTask,
    toggleNativeTaskTimer,
} = require('../helpers/native_browser_forms.cjs');

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const env = process.env;
const base = env.ISSUERS_BROWSER_URL;
const php = env.ISSUERS_BROWSER_PHP_BINARY || 'php';
const fixturePath = env.TASK_TIMER_STOP_BROWSER_FIXTURE_PHP;
const output = env.ISSUERS_BROWSER_ARTIFACTS;

assert.match(base || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert(fixturePath && path.isAbsolute(fixturePath));
assert.equal(env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');

function fixture(...args) {
    return JSON.parse(execFileSync(php, [fixturePath, ...args.map(String)], {
        encoding: 'utf8',
        env,
    }));
}

async function nav(page, button) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}

async function hidden(form) {
    return form.evaluate((element) => Object.fromEntries(
        Array.from(new FormData(element).entries()),
    ));
}

const TASK_RADIX = 8589934592n;

(async () => {
    const setup = fixture('inspect');
    assert(setup.native_marker >= 451 && setup.read_only);

    const browser = await chromium.launch({
        headless: true,
        ...(env.ISSUERS_BROWSER_EXECUTABLE_PATH
            ? { executablePath: env.ISSUERS_BROWSER_EXECUTABLE_PATH }
            : {}),
    });
    const context = await browser.newContext();
    await context.route('**/*', (route) =>
        new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const page = await workspacePage(context);
    const errors = [];
    const projects = {};
    const taskCanonicals = {};
    const results = [];
    page.on('pageerror', (error) => errors.push(error.message));

    try {
        await page.goto(base + '/admin/authentication');
        await page.locator('[name=email]').fill(env.ISSUERS_BROWSER_USERNAME || 'admin-ci@example.invalid');
        await page.locator('[name=password]').fill(env.ISSUERS_BROWSER_PASSWORD);
        await nav(page, page.locator('button[type=submit]'));

        async function select(owner) {
            await page.goto(base + '/admin/');
            await selectCompanyOption(page, String(owner));
        }

        const route = base + '/admin/settings?group=task_timer_stop';
        const setting = '#company-setting-auto_stop_tasks_timers_on_new_timer';

        async function policy(value) {
            await page.goto(route);
            await page.locator(setting).selectOption(value);
            await nav(
                page,
                page.locator('form')
                    .filter({ has: page.locator(setting) })
                    .locator('button[type=submit]'),
            );
            assert.match(await page.locator('body').innerText(), /Definições salvas/);
        }

        async function create(owner, kind, label) {
            if (kind === 'tasks') {
                const task = await createNativeProjectTask(page, base, {
                    projectCanonical: projects[owner].canonical,
                    name: 'Auto-stop451 ' + label,
                    dueDate: '01/11/2026',
                    assignee: setup.actor,
                });
                taskCanonicals[task.sourceId] = task.canonical;
                const id = Number(task.sourceId);
                assert.equal(fixture('task', owner, kind, id).timer_count, 0);
                return id;
            }

            await page.goto(base + '/admin/tasks/standalone');
            await page.locator('#task-new-assignee').selectOption(String(setup.actor));
            await page.locator('#task-new-status').selectOption('todo');
            await page.locator('#task-new-name').fill('Auto-stop451 ' + label);
            await page.locator('#task-new-due').fill('2026-11-01');
            await nav(page, page.getByRole('button', { name: 'Criar tarefa avulsa', exact: true }));
            const id = Number(new URL(page.url()).searchParams.get('task'));
            assert(id > 0);
            taskCanonicals[id] = String(TASK_RADIX + BigInt(id));
            assert.equal(fixture('task', owner, kind, id).timer_count, 0);
            return id;
        }

        function detail(id) {
            assert(taskCanonicals[id], 'native task canonical id is registered');
            return base + '/admin/tasks/list_tasks/' + taskCanonicals[id];
        }

        async function start(id) {
            await openNativeTask(page, base, taskCanonicals[id]);
            await toggleNativeTaskTimer(page);
        }

        async function stop(id) {
            await openNativeTask(page, base, taskCanonicals[id]);
            await toggleNativeTaskTimer(page);
        }

        async function timerFields(id) {
            await openNativeTask(page, base, taskCanonicals[id]);
            const detailNode = page.locator('[data-native-task-detail]').first();
            const attributes = await detailNode.evaluate((node) => ({
                policy: node.dataset.policyHash,
                revision: node.dataset.taskRevision,
            }));
            const hiddenFields = await page.locator('form[action*="/tasks/mark_as/"]').first().evaluate(
                (form) => Object.fromEntries(new FormData(form)),
            );
            return {
                ...hiddenFields,
                task_id: taskCanonicals[id],
                timer_id: '',
                note: '',
                native_request_key: 'a'.repeat(64),
                native_policy_hash: attributes.policy,
                native_task_revision: attributes.revision,
            };
        }

        await select(setup.owners.primary);
        await page.goto(route);
        const initial = fixture('snapshot');

        for (const [label, owner] of Object.entries(setup.owners)) {
            await select(owner);
            const prior = setup.own_timer_stop_policies[owner];
            assert.equal(
                await page.locator(setting).inputValue(),
                prior ? prior.auto_stop_tasks_timers_on_new_timer : '0',
            );
            assert.equal(await page.locator(setting + ' option').count(), 2);
            assert.match(await page.locator('body').innerText(), /Um cronômetro de outra empresa precisa ser encerrado nela/);
            await policy('0');

            await page.goto(base + '/admin/clients/client?source=company');
            await page.locator('input[name="company"]').fill('Auto-stop451 client ' + label);
            await nav(page, page.locator('button.only-save'));
            const client = new URL(page.url()).searchParams.get('client');
            assert.match(client || '', /^[1-9]\d*$/);

            projects[owner] = await createNativeProject(page, base, {
                client,
                name: 'Auto-stop451 project ' + label,
                startDate: '19/10/2026',
                dueDate: '19/11/2026',
            });

            const pairs = [
                ['tasks', 'tasks'],
                ['tasks', 'standalone_tasks'],
                ['standalone_tasks', 'tasks'],
                ['standalone_tasks', 'standalone_tasks'],
            ];

            for (const [oldKind, newKind] of pairs) {
                const old = await create(owner, oldKind, label + ' old ' + oldKind + ' ' + newKind);
                await start(old);
                const oldBefore = fixture('task', owner, oldKind, old);

                const next = await create(owner, newKind, label + ' next ' + oldKind + ' ' + newKind);
                await start(next);
                const oldOff = fixture('task', owner, oldKind, old);
                const nextOff = fixture('task', owner, newKind, next);
                assert.equal(oldOff.timer_rows[0].stopped_at, null, 'OFF preserves the previous timer');
                assert.equal(nextOff.timer_count, 1);
                assert.equal(nextOff.timer_rows[0].stopped_at, null);
                await stop(next);
                await stop(old);

                await policy('1');
                const active = await create(owner, oldKind, label + ' active ' + oldKind + ' ' + newKind);
                await start(active);
                const target = await create(owner, newKind, label + ' target ' + oldKind + ' ' + newKind);
                await start(target);

                const previous = fixture('task', owner, oldKind, active);
                const current = fixture('task', owner, newKind, target);
                assert.equal(previous.status, oldBefore.status, 'auto-stop preserves old task status');
                assert.equal(previous.timer_count, 1);
                assert(previous.timer_rows[0].stopped_at);
                assert.equal(current.timer_count, 1);
                assert.equal(current.timer_rows[0].stopped_at, null);
                assert.equal(previous.timer_rows[0].stopped_at, current.timer_rows[0].started_at);
                assert(previous.timer_rows[0].seconds >= 0);
                await stop(target);
                results.push({
                    owner,
                    oldKind,
                    newKind,
                    old: active,
                    next: target,
                    same_boundary: true,
                    retry_exact: true,
                });
                await policy('0');
            }

            if (output) {
                fs.mkdirSync(output, { recursive: true });
                await page.screenshot({
                    path: path.join(output, 'task-auto-stop-' + label + '.png'),
                    fullPage: true,
                });
            }
        }

        await select(setup.owners.a);
        const old = await create(setup.owners.a, 'tasks', 'foreign A old');
        await start(old);
        const foreignBefore = fixture('snapshot');

        await select(setup.owners.b);
        const next = await create(setup.owners.b, 'standalone_tasks', 'foreign B next');
        const rejected = await timerFields(next);
        delete rejected._native_company_csrf;
        assert.equal(
            (await context.request.post(base + '/admin/tasks/timer_tracking?single_task=true', {
                form: rejected,
                maxRedirects: 0,
            })).status(),
            403,
        );
        assert.deepEqual(fixture('snapshot'), foreignBefore, 'missing native CSRF has no timer effect');

        const foreignAttempt = await timerFields(next);
        const foreignResponse = await context.request.post(base + '/admin/tasks/timer_tracking?single_task=true', {
            form: foreignAttempt,
            maxRedirects: 0,
        });
        assert.notEqual(foreignResponse.status(), 200, 'active timer from another company cannot be mutated');

        await select(setup.owners.a);
        await stop(old);
        await select(setup.owners.b);
        await start(next);
        assert.equal(fixture('task', setup.owners.b, 'standalone_tasks', next).timer_count, 1);
        await stop(next);

        await select(setup.owners.a);
        const oldForm = await timerFields(old);
        await policy('0');
        const current = fixture('snapshot');
        const staleResponse = await context.request.post(
            base + '/admin/tasks/timer_tracking?single_task=true',
            { form: oldForm, maxRedirects: 0 },
        );
        assert.notEqual(staleResponse.status(), 200, 'replayed timer receipt is rejected after the task changed');
        assert.deepEqual(fixture('snapshot'), current);

        assert.deepEqual(
            current.financial,
            initial.financial,
            'own automatic timer stops never alter global options, bank, invoice, credit or financial history',
        );
        assert.deepEqual(errors, []);

        if (output) {
            fs.writeFileSync(
                path.join(output, 'task-auto-stop451.json'),
                JSON.stringify({
                    native_marker: setup.native_marker,
                    read_only_fixture: true,
                    results,
                    foreign403: true,
                    csrf403: true,
                    stale_rejected: true,
                    financial_and_global_unchanged: true,
                    external_transport: false,
                }, null, 2),
            );
        }
        console.log(
            'Own auto-stop451 Chromium PASS: A/B/principal, all four native modality pairs, OFF/ON, one captured stop/start boundary, foreign-company refusal, CSRF/stale, unchanged finances/options.',
        );
    } finally {
        await context.close();
        await browser.close();
    }
})().catch((error) => {
    console.error(error.stack);
    process.exitCode = 1;
});

