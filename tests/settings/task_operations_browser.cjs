'use strict';

const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const {
    createNativeProject,
    createNativeProjectTask,
    openNativeTask,
} = require('../helpers/native_browser_forms.cjs');

const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const url = process.env.ISSUERS_BROWSER_URL;
const fixturePath = process.env.TASK_OPERATIONS_BROWSER_FIXTURE_PHP;
const php = process.env.ISSUERS_BROWSER_PHP_BINARY || 'php';
const artifacts = process.env.ISSUERS_BROWSER_ARTIFACTS;

assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert(fixturePath && path.isAbsolute(fixturePath));
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');

function fixture(...args) {
    return JSON.parse(execFileSync(php, [fixturePath, ...args.map(String)], {
        encoding: 'utf8',
        env: process.env,
    }));
}

async function nav(page, button) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}

async function fields(form) {
    return form.evaluate((element) => Object.fromEntries(new FormData(element)));
}

(async () => {
    const setup = fixture('inspect');
    assert(setup.native_marker >= 448 && setup.read_only);

    const browser = await chromium.launch({
        headless: true,
        ...(process.env.ISSUERS_BROWSER_EXECUTABLE_PATH
            ? { executablePath: process.env.ISSUERS_BROWSER_EXECUTABLE_PATH }
            : {}),
    });
    const context = await browser.newContext();
    await context.route('**/*', (route) =>
        new URL(route.request().url()).origin === url ? route.continue() : route.abort());
    const page = await workspacePage(context);
    const errors = [];
    const created = {};
    const canonicalIds = {};
    page.on('pageerror', (error) => errors.push(error.message));

    try {
        await page.goto(url + '/admin/authentication');
        await page.locator('input[name=email]').fill(
            process.env.ISSUERS_BROWSER_USERNAME || 'admin-ci@example.invalid',
        );
        await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
        await nav(page, page.locator('button[type=submit]'));

        async function select(owner) {
            await page.goto(url + '/admin/');
            await selectCompanyOption(page, String(owner));
        }

        const settings = url + '/admin/settings?group=task_operations';

        async function policy(priority, follow) {
            await page.goto(settings);
            await page.locator('#company-setting-default_task_priority').selectOption(priority);
            await page.locator('#company-setting-new_task_auto_follower_current_member').selectOption(follow);
            await nav(
                page,
                page.locator('form')
                    .filter({ has: page.locator('#company-setting-default_task_priority') })
                    .locator('button[type=submit]'),
            );
            assert.match(await page.locator('body').innerText(), /Definições salvas/);
        }

        await select(setup.owners.primary);
        assert.equal((await page.goto(settings)).status(), 200);
        const initial = fixture('snapshot');

        for (const [label, owner] of Object.entries(setup.owners)) {
            await select(owner);
            const priority = { a: '4', b: '1', primary: '3' }[label];
            const follow = label === 'b' ? '0' : '1';
            await policy(priority, follow);
            assert.equal(await page.locator('#company-setting-default_task_priority option').count(), 4);
            assert.match(await page.locator('body').innerText(), /tarefas avulsas não possuem esse canal/);

            await page.goto(url + '/admin/clients/client?source=company');
            await page.locator('input[name="company"]').fill('Priority native448 ' + label);
            await nav(page, page.locator('button.only-save'));
            const client = new URL(page.url()).searchParams.get('client');
            assert.match(client || '', /^[1-9]\d*$/);

            const projectInfo = await createNativeProject(page, url, {
                client,
                name: 'Priority448 project ' + label,
                startDate: '19/10/2026',
                dueDate: '19/11/2026',
            });
            const project = projectInfo.sourceId;
            assert.match(project || '', /^[1-9]\d*$/);

            for (const kind of ['tasks', 'standalone_tasks']) {
                if (kind === 'tasks') {
                    const task = await createNativeProjectTask(page, url, {
                        projectCanonical: projectInfo.canonical,
                        name: 'Priority ' + kind + ' ' + label + ' <script>literal</script>',
                        dueDate: '01/11/2026',
                        assignee: setup.actor,
                        priority: '2',
                    });
                    const id = Number(task.sourceId);
                    created[label + '-' + kind] = id;
                    canonicalIds[label + '-' + kind] = task.canonical;

                    let state = fixture('task', owner, kind, id);
                    assert.equal(state.priority, 2);
                    assert.equal(state.timer_count, 0);
                    assert.deepEqual(state.followers, follow === '1' ? [setup.actor] : []);

                    await openNativeTask(page, url, task.canonical);
                    assert.equal(
                        await page.locator('select[data-native-action-template*="/change_priority/"]').inputValue(),
                        '2',
                    );
                    assert(!(await page.locator('body').innerHTML()).includes('<script>literal</script>'));

                    if (follow === '1') {
                        const removeFollower = page.locator('form[action*="/tasks/remove_follower/"]').first();
                        if (await removeFollower.count()) {
                            const responsePromise = page.waitForResponse((response) =>
                                response.request().method() === 'POST' &&
                                response.url().includes('/admin/tasks/remove_follower/'));
                            await removeFollower.locator('button[type=submit]').click();
                            const response = await responsePromise;
                            assert.equal(response.status(), 200);
                            state = fixture('task', owner, kind, id);
                            assert.deepEqual(state.followers, []);
                        }
                    }

                    const omittedTask = await createNativeProjectTask(page, url, {
                        projectCanonical: projectInfo.canonical,
                        name: 'Omitted priority ' + label + ' ' + kind,
                        dueDate: '01/11/2026',
                        assignee: setup.actor,
                    });
                    const omittedState = fixture('task', owner, kind, Number(omittedTask.sourceId));
                    assert.equal(omittedState.priority, Number(priority));
                    assert.deepEqual(omittedState.followers, follow === '1' ? [setup.actor] : []);
                } else {
                    const route = url + '/admin/tasks/standalone';
                    const post = route;
                    await page.goto(route);
                    assert.equal(await page.locator('#task-new-priority').inputValue(), priority);
                    await page.locator('#task-new-assignee').selectOption(String(setup.actor));
                    await page.locator('#task-new-priority').selectOption('2');
                    await page.locator('#task-new-name').fill(
                        'Priority ' + kind + ' ' + label + ' <script>literal</script>',
                    );
                    await page.locator('#task-new-due').fill('2026-11-01');
                    const form = page.locator('form').filter({ has: page.locator('#task-new-name') });
                    const replay = await fields(form);
                    await nav(page, page.getByRole('button', { name: 'Criar tarefa avulsa', exact: true }));
                    const id = Number(new URL(page.url()).searchParams.get('task'));
                    created[label + '-' + kind] = id;
                    const state = fixture('task', owner, kind, id);
                    assert.equal(state.priority, 2);
                    assert.equal(state.timer_count, 0);
                    assert.deepEqual(state.followers, []);
                    assert.match(await page.locator('body').innerText(), /Prioridade: Média/);
                    assert(!(await page.locator('body').innerHTML()).includes('<script>literal</script>'));
                    assert.equal(await page.locator('#task-edit-priority').inputValue(), '2');

                    const beforeReplay = fixture('snapshot');
                    assert.equal(
                        (await context.request.post(post, { form: replay, maxRedirects: 0 })).status(),
                        303,
                    );
                    assert.deepEqual(
                        fixture('snapshot'),
                        beforeReplay,
                        'old creation receipt never reapplies priority/default after receipt',
                    );

                    await page.goto(route);
                    await page.locator('#task-new-assignee').selectOption(String(setup.actor));
                    await page.locator('#task-new-name').fill('Omitted priority ' + label + ' ' + kind);
                    await page.locator('#task-new-due').fill('2026-11-01');
                    const omitted = await fields(page.locator('form').filter({ has: page.locator('#task-new-name') }));
                    delete omitted['task[priority]'];
                    const noCsrf = { ...omitted };
                    delete noCsrf.company_standalone_tasks_csrf;
                    assert.equal(
                        (await context.request.post(post, { form: noCsrf, maxRedirects: 0 })).status(),
                        403,
                    );
                    const response = await context.request.post(post, { form: omitted, maxRedirects: 0 });
                    assert.equal(response.status(), 303);
                    const omittedId = Number(new URL(response.headers().location, url).searchParams.get('task'));
                    const omittedState = fixture('task', owner, kind, omittedId);
                    assert.equal(omittedState.priority, Number(priority));
                    assert.deepEqual(omittedState.followers, []);
                }
            }

            if (artifacts) {
                fs.mkdirSync(artifacts, { recursive: true });
                await page.screenshot({ path: path.join(artifacts, 'task-priority-' + label + '.png'), fullPage: true });
            }
        }

        await select(setup.owners.a);
        await page.goto(settings);
        const stale = await fields(
            page.locator('form').filter({ has: page.locator('#company-setting-default_task_priority') }),
        );
        await policy('3', '0');
        const state = fixture('snapshot');
        assert.equal((await context.request.post(settings, { form: stale, maxRedirects: 0 })).status(), 409);
        assert.deepEqual(fixture('snapshot'), state, 'stale preference revision rejected before effects');

        await select(setup.owners.b);
        assert.equal((await context.request.post(settings, { form: stale, maxRedirects: 0 })).status(), 409);
        assert.equal(
            (await context.request.get(url + '/admin/tasks/list_tasks/' + canonicalIds['a-tasks'])).status(),
            409,
        );
        assert.equal(
            (await context.request.get(url + '/admin/tasks/standalone?task=' + created['a-standalone_tasks'])).status(),
            409,
        );
        assert.deepEqual(
            fixture('snapshot').financial,
            initial.financial,
            'no timer, money, bank, credit or global option effects',
        );
        assert.deepEqual(errors, []);
        console.log(
            'Native448 priority/follow Chromium: A/B/principal settings; native project + standalone GUI/default/explicit/omitted; self-unfollow; CSRF/stale context/revision/cross-company; immutable finance/options PASS.',
        );
    } finally {
        await context.close();
        await browser.close();
    }
})().catch((error) => {
    console.error(error.stack);
    process.exitCode = 1;
});


