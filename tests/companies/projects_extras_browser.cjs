'use strict';

const { selectCompanyOption } = require('../helpers/native_selector_driver.cjs');
const { workspacePage } = require('../helpers/native_workspace_driver.cjs');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const url = process.env.ISSUERS_BROWSER_URL;
const app = process.env.ISSUERS_BROWSER_APP_CONTAINER;
const db = process.env.ISSUERS_BROWSER_DB_CONTAINER;
const RESOURCE_RADIX = 4294967296;

assert.match(url || '', /^http:\/\/127\.0\.0\.1:\d+$/);
assert.match(app || '', /^argws-crm-upgrade-\d+$/);
assert.match(db || '', /^argws-crm-provision-db-\d+$/);
assert.equal(process.env.ISSUERS_BROWSER_DB_NAME, 'argws_upgrade_ci');

function sql(query) {
    return execFileSync(
        'docker',
        [
            'exec',
            '-e',
            'MYSQL_PWD',
            db,
            'mysql',
            '--protocol=tcp',
            '--host=127.0.0.1',
            '--user=' + process.env.ISSUERS_BROWSER_DB_USER,
            'argws_upgrade_ci',
            '--batch',
            '--skip-column-names',
            '-e',
            query,
        ],
        {
            env: { ...process.env, MYSQL_PWD: process.env.ISSUERS_BROWSER_DB_PASSWORD },
            encoding: 'utf8',
        },
    ).trim();
}

async function nav(page, button) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}

async function hidden(form) {
    return form.locator('input[type=hidden]').evaluateAll((inputs) =>
        Object.fromEntries(inputs.filter((input) => input.name).map((input) => [input.name, input.value])),
    );
}

async function createNativeTask(page, projectCanonical, name) {
    if (projectCanonical) {
        await page.goto(url + '/admin/projects/view/' + projectCanonical + '?group=project_tasks');
        await page.locator('a[onclick*="new_task_from_relation"]').first().click();
    } else {
        await page.goto(url + '/admin/tasks');
        await page.locator('a[onclick^="new_task("]').first().click();
    }

    const form = page.locator('#task-form');
    await form.waitFor({ state: 'attached' });
    await page.locator('#_task_modal').waitFor({ state: 'visible' });
    await form.locator('input[name="name"]').fill(name);
    await form.locator('input[name="startdate"]').fill('19/10/2026');
    await form.locator('input[name="duedate"]').fill('19/11/2026');

    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST' && response.url().includes('/admin/tasks/task'),
    );
    await form.locator('button[type=submit]').click();
    const response = await responsePromise;
    assert.equal(response.status(), 200);
    const payload = await response.json();
    assert.equal(payload.success, true);
    assert.match(String(payload.id), /^[1-9][0-9]*$/);
    return String(payload.id);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext();
    await ctx.route('**/*', (route) =>
        new URL(route.request().url()).origin === url ? route.continue() : route.abort(),
    );
    const page = await workspacePage(ctx);
    const errors = [];

    page.on('pageerror', (error) => errors.push(error.message));

    try {
        await page.goto(url + '/admin/authentication');
        await page.locator('input[name=email]').fill('admin-ci@example.invalid');
        await page.locator('input[name=password]').fill(process.env.ISSUERS_BROWSER_PASSWORD);
        await nav(page, page.locator('button[type=submit]'));

        const issuerA = sql("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'");
        const issuerB = sql("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'");
        const projects = {};
        const standalones = {};
        const files = {};

        async function select(issuer) {
            await page.goto(url + '/admin/');
            await selectCompanyOption(page, issuer);
        }

        for (const [issuer, letter] of [
            [issuerA, 'a'],
            [issuerB, 'b'],
        ]) {
            await select(issuer);
            const client = sql(
                "SELECT userid FROM tblclients WHERE company='Synthetic portal client " + letter + "'",
            );
            const projectName = 'Extra board ' + letter;

            // Use the original CRM project form composed with the native
            // company scope. The removed parallel form must not return.
            await page.goto(url + '/admin/projects/project?customer_id=' + encodeURIComponent(client));
            const projectForm = page.locator('#project_form');
            await projectForm.locator('#clientid').selectOption(client);
            await projectForm.locator('#name').fill(projectName);
            await projectForm.locator('#start_date').fill('19/10/2026');
            await projectForm.locator('#deadline').fill('19/11/2026');
            await projectForm.locator('#billing_type').selectOption('1');
            await nav(page, projectForm.locator('button[type=submit]'));

            const projectPath = new URL(page.url()).pathname.split('/').filter(Boolean);
            const projectCanonical = projectPath[projectPath.length - 1];
            assert.match(projectCanonical, /^[1-9][0-9]*$/);
            const project = sql(
                "SELECT id FROM tblcompany_projects WHERE issuer_id=" +
                    issuer +
                    " AND name='" +
                    projectName +
                    "' ORDER BY id DESC LIMIT 1",
            );
            assert.equal(projectCanonical, String(Number(project) + RESOURCE_RADIX));
            projects[issuer] = project;

            await page.goto(url + '/admin/projects/extras?kind=project&id=' + project);
            await nav(page, page.getByRole('button', { name: 'Seguir este recurso', exact: true }));
            assert.equal(
                sql(
                    'SELECT COUNT(*) FROM tblcompany_project_followers WHERE issuer_id=' +
                        issuer +
                        ' AND project_id=' +
                        project,
                ),
                '1',
            );
            await page.locator('#project-milestone-name').fill('Browser milestone ' + letter + ' <script>');
            await page.locator('#project-milestone-due').fill('2026-11-01');
            await nav(page, page.getByRole('button', { name: 'Adicionar marco', exact: true }));
            await nav(page, page.getByRole('button', { name: 'Concluir marco', exact: true }));
            assert.equal(
                sql('SELECT done FROM tblcompany_project_milestones WHERE project_id=' + project),
                '1',
            );

            const taskIds = [];
            for (const suffix of ['pre', 'dependent']) {
                const taskName = 'Board ' + letter + ' ' + suffix;
                const taskCanonical = await createNativeTask(page, projectCanonical, taskName);
                const task = sql(
                    "SELECT id FROM tblcompany_tasks WHERE issuer_id=" +
                        issuer +
                        " AND project_id=" +
                        project +
                        " AND name='" +
                        taskName +
                        "' ORDER BY id DESC LIMIT 1",
                );
                assert.equal(taskCanonical, String(Number(task) + RESOURCE_RADIX * 2));
                taskIds.push(task);
            }

            const [pre, dependent] = taskIds;
            await page.goto(url + '/admin/projects/extras?kind=task&id=' + dependent);
            await page.locator('input[name="dependencies[]"][value="' + pre + '"]').check();
            await nav(page, page.getByRole('button', { name: 'Salvar dependências', exact: true }));
            assert.equal(
                sql(
                    'SELECT depends_on FROM tblcompany_task_dependencies WHERE task_id=' + dependent,
                ),
                pre,
            );

            const dependentMove = page.locator('form').filter({
                has: page.locator('#task-board-' + dependent),
            });
            const denied = await ctx.request.post(url + '/admin/projects/extras', {
                form: { ...(await hidden(dependentMove)), status: 'done' },
            });
            assert.equal(denied.status(), 409, 'board cannot bypass uncompleted dependency');
            assert.equal(
                sql('SELECT status FROM tblcompany_tasks WHERE id=' + dependent),
                'todo',
            );

            await page.locator('#task-board-' + pre).selectOption('done');
            await nav(
                page,
                page.locator('form').filter({ has: page.locator('#task-board-' + pre) }).locator(
                    'button[type=submit]',
                ),
            );
            await page.locator('#task-board-' + dependent).selectOption('done');
            await nav(
                page,
                page.locator('form').filter({ has: page.locator('#task-board-' + dependent) }).locator(
                    'button[type=submit]',
                ),
            );
            assert.equal(
                sql('SELECT status FROM tblcompany_tasks WHERE id=' + dependent),
                'done',
            );

            await page.goto(url + '/admin/projects/extras?kind=project&id=' + project);
            const pdf = Buffer.from('%PDF-1.4\nSynthetic browser project file\n%%EOF\n');
            await page.locator('#project-private-file').setInputFiles({
                name: 'project-' + letter + '.pdf',
                mimeType: 'application/pdf',
                buffer: pdf,
            });
            await nav(page, page.getByRole('button', { name: 'Adicionar anexo privado', exact: true }));
            const file = sql(
                'SELECT file_key FROM tblcompany_project_files WHERE issuer_id=' +
                    issuer +
                    ' AND project_id=' +
                    project +
                    ' AND task_id IS NULL',
            );
            files[issuer] = file;
            const response = await ctx.request.get(
                url + '/admin/projects/extras?kind=project&id=' + project + '&file=' + file,
            );
            assert.equal(response.status(), 200);
            assert.equal((await response.body()).toString(), pdf.toString());
            await nav(
                page,
                page.getByRole('button', {
                    name: 'Ocultar arquivo e preservar histórico',
                    exact: true,
                }),
            );
            assert.equal(
                (
                    await ctx.request.get(
                        url + '/admin/projects/extras?kind=project&id=' + project + '&file=' + file,
                    )
                ).status(),
                409,
            );
            assert.equal(
                sql("SELECT active FROM tblcompany_project_files WHERE file_key='" + file + "'"),
                '0',
            );

            // The dedicated native standalone workspace remains the canonical
            // UI for its checklist/comment/timer lifecycle.
            await page.goto(url + '/admin/tasks/standalone');
            await page.locator('#task-new-name').fill('Standalone browser ' + letter);
            await page.locator('#task-new-lines').fill('Independent checklist');
            await nav(page, page.getByRole('button', { name: 'Criar tarefa avulsa', exact: true }));
            const single = new URL(page.url()).searchParams.get('task');
            standalones[issuer] = single;
            assert.equal(
                sql('SELECT issuer_id FROM tblcompany_standalone_tasks WHERE id=' + single),
                issuer,
            );
            await page.locator('#task-comment').fill('Independent comment ' + letter + ' <script>');
            await nav(page, page.getByRole('button', { name: 'Registrar comentário', exact: true }));
            await nav(page, page.getByRole('button', { name: 'Iniciar meu cronômetro', exact: true }));
            await nav(page, page.getByRole('button', { name: 'Parar meu cronômetro', exact: true }));
            await page.locator('#task-edit-check-0').selectOption('1');
            await page.locator('#task-status').selectOption('done');
            await nav(page, page.getByRole('button', { name: 'Salvar tarefa', exact: true }));
            assert.equal(
                sql('SELECT status FROM tblcompany_standalone_tasks WHERE id=' + single),
                'done',
            );
            assert.equal(
                sql('SELECT COUNT(*) FROM tblcompany_standalone_comments WHERE task_id=' + single),
                '1',
            );
        }

        assert.equal(
            (await ctx.request.get(url + '/admin/projects/extras?kind=project&id=' + projects[issuerA]))
                .status(),
            409,
        );
        assert.equal(
            (
                await ctx.request.get(
                    url + '/admin/projects/extras?kind=project&id=' + projects[issuerA] + '&file=' + files[issuerA],
                )
            ).status(),
            409,
        );
        assert.equal(
            (await ctx.request.get(url + '/admin/tasks/standalone?task=' + standalones[issuerA])).status(),
            409,
        );
        assert.deepEqual(errors, []);
        console.log(
            JSON.stringify({
                extras: 'A/B milestones follow dependency-gated kanban private multipart/download/hide passed',
                standalone: 'A/B no-project task checklist comments timer completion passed',
                external_transports: false,
            }),
        );
    } finally {
        await ctx.close();
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
