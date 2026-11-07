'use strict';

const assert = require('node:assert/strict');
const { formatDate } = require('./native_invoice_driver.cjs');

const PROJECT_RADIX = 4294967296;
const TASK_RADIX = PROJECT_RADIX * 2;

async function fillNativeDate(page, input, value) {
    if (value === '') { await input.fill(''); return; }
    assert.match(value, /^\d{2}\/\d{2}\/\d{4}$/);
    const [day, month, year] = value.split('/');
    const format = await page.evaluate(() => window.app.options.date_format);
    await input.fill(formatDate(year + '-' + month + '-' + day, format));
}

async function submitAndNavigate(page, button) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.click()]);
}

async function createNativeProject(page, baseUrl, options) {
    const {
        client,
        name,
        startDate = '19/10/2026',
        dueDate = '19/11/2026',
        description = '',
        billingType = '1',
    } = options;
    await page.goto(baseUrl + '/admin/projects/project?customer_id=' + encodeURIComponent(client));
    const form = page.locator('#project_form');
    await form.locator('#clientid').selectOption(String(client));
    await form.locator('#name').fill(name);
    await fillNativeDate(page, form.locator('#start_date'), startDate);
    await fillNativeDate(page, form.locator('#deadline'), dueDate);
    if (description !== '') await form.locator('textarea[name="description"]').fill(description);
    await form.locator('#billing_type').selectOption(String(billingType));
    if (options.portalTasks === false || options.disabledPortalFlags?.length || options.disabledPortalFeatures?.length) {
        await page.locator('a[href="#tab_settings"]').click();
        for (const flag of options.disabledPortalFlags || []) {
            assert.match(flag, /^(?:view_[a-z_]+|upload_files)$/);
            await form.locator('#' + flag).uncheck();
        }
        if (options.disabledPortalFeatures?.length) {
            const features = form.locator('#available_features');
            const selected = await features.locator('option:checked:not(:disabled)').evaluateAll(xs => xs.map(x => x.value));
            for (const feature of options.disabledPortalFeatures) {
                assert.match(feature, /^project_[a-z_]+$/);
                assert.equal(await features.locator('option[value="' + feature + '"]').count(), 1);
            }
            await features.selectOption(selected.filter(value => !options.disabledPortalFeatures.includes(value)));
        }
    }
    if (options.portalTasks === false) {
        await form.locator('#view_team_members').uncheck();
        await form.locator('#view_tasks').uncheck();
    }
    await submitAndNavigate(page, form.locator('button[type=submit]'));

    const path = new URL(page.url()).pathname.split('/').filter(Boolean);
    const canonical = path[path.length - 1];
    assert.match(canonical || '', /^[1-9][0-9]*$/);
    const sourceId = Number(canonical) - PROJECT_RADIX;
    assert(Number.isSafeInteger(sourceId) && sourceId > 0);
    return { canonical, sourceId: String(sourceId) };
}

// Do not wait for decorative images or service-worker activity before using the form.
// A failed document or missing task initializer still fails the test.
async function openNativeProjectTasks(page, projectUrl) {
    const response = await page.goto(projectUrl, { waitUntil: 'domcontentloaded' });
    assert.equal(response?.status(), 200, 'project task document must return HTTP 200');
    await page.waitForFunction(() => typeof window.new_task_from_relation === 'function');
    await page.locator('a[onclick*="new_task_from_relation"]').first().waitFor({ state: 'visible' });
}

async function createNativeProjectTask(page, baseUrl, options) {
    const {
        projectCanonical,
        name,
        startDate = '19/10/2026',
        dueDate = '19/11/2026',
        description = '',
        assignee,
        priority,
    } = options;

    const projectUrl = baseUrl + '/admin/projects/view/' + projectCanonical + '?group=project_tasks';
    if (!options.reuseProjectPage || page.url() !== projectUrl) await openNativeProjectTasks(page, projectUrl);
    else {
        const detail = page.locator('#task-modal:visible');
        if (await detail.count()) {
            await detail.locator('[data-dismiss="modal"]').first().click();
            await detail.waitFor({ state: 'hidden' });
        }
    }
    await page.locator('a[onclick*="new_task_from_relation"]').first().click();
    const form = page.locator('#task-form');
    await form.waitFor({ state: 'attached' });
    await page.locator('#_task_modal').waitFor({ state: 'visible' });
    await form.locator('input[name="name"]').fill(name);
    await fillNativeDate(page, form.locator('input[name="startdate"]'), startDate);
    await fillNativeDate(page, form.locator('input[name="duedate"]'), dueDate);
    if (description !== '') await form.locator('textarea[name="description"]').fill(description);
    if (assignee !== undefined && assignee !== null) {
        await form.locator('#assignees').selectOption(String(assignee));
    }
    if (priority !== undefined && priority !== null) {
        await form.locator('#priority').selectOption(String(priority));
    }

    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST' && response.url().includes('/admin/tasks/task'),
    );
    await form.locator('button[type=submit]').click();
    const response = await responsePromise;
    assert.equal(response.status(), 200);
    const payload = await response.json();
    assert.equal(payload.success, true);
    assert.match(String(payload.id), /^[1-9][0-9]*$/);
    const sourceId = Number(payload.id) - TASK_RADIX;
    assert(Number.isSafeInteger(sourceId) && sourceId > 0);
    return { canonical: String(payload.id), sourceId: String(sourceId) };
}

async function addNativeTaskChecklistItem(page, baseUrl, taskCanonical, text) {
    await page.goto(baseUrl + '/admin/tasks/list_tasks/' + taskCanonical);
    await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
    const initialize = page.locator('button[onclick*="native_task_checklist_command"][onclick*="initialize"]').first();
    if (await initialize.count()) {
        const responsePromise = page.waitForResponse((response) =>
            response.request().method() === 'POST' && response.url().includes('/admin/tasks/checklist_command/'),
        );
        // Initialization refreshes the modal through AJAX, not document navigation.
        // Observe and finish that refresh before testing persistence with reload.
        const detailPath='/admin/tasks/get_task_data/'+taskCanonical;
        const refreshed=page.waitForResponse(r=>r.request().method()==='GET'&&new URL(r.url()).pathname===detailPath).then(response=>({response}),error=>({error}));
        await initialize.click();
        const response = await responsePromise;
        assert.equal(response.status(), 200);
        const payload = await response.json();
        assert.equal(payload.success, true);
        const refresh=await refreshed;if(refresh.error)throw refresh.error;
        assert.equal(refresh.response.status(),200,'initialized checklist detail refresh succeeds');
        await page.locator('[data-native-task-detail] button[onclick*="add_task_checklist_item"]').waitFor({state:'visible'});
        const reloaded=page.waitForResponse(r=>r.request().method()==='GET'&&new URL(r.url()).pathname===detailPath).then(response=>({response}),error=>({error}));
        const navigation=await page.reload({ waitUntil: 'domcontentloaded' });
        assert.equal(navigation.status(),200,'task index reload succeeds after checklist initialization');
        const detail=await reloaded;if(detail.error)throw detail.error;
        assert.equal(detail.response.status(),200,'persisted checklist detail remains readable after reload');
        await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
    }
    await page.locator('button[onclick*="add_task_checklist_item"]').click();
    const field = page.locator('textarea[name="checklist-description"]').last();
    await field.fill(text);
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST' && response.url().includes('/admin/tasks/add_checklist_item'),
    );
    await field.press('Tab');
    const response = await responsePromise;
    assert.equal(response.status(), 200);
}

async function nativeTaskCommand(page, endpoint, action, { refresh = true } = {}) {
    const detail = page.locator('[data-native-task-detail]').first();
    const before = await detail.getAttribute('data-task-revision');
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST' && response.url().includes('/admin/tasks/' + endpoint),
    );
    await action();
    const response = await responsePromise;
    assert.equal(response.status(), 200);
    const payload = await response.json();
    assert.equal(payload.success, true);
    if (refresh) {
        await page.waitForFunction((revision) =>
            document.querySelector('[data-native-task-detail]')?.dataset.taskRevision !== revision,
        before);
    }
    return payload;
}

async function openNativeTask(page, baseUrl, taskCanonical) {
    await page.goto(baseUrl + '/admin/tasks/list_tasks/' + taskCanonical);
    await page.locator('[data-native-task-detail]').waitFor({ state: 'visible' });
}

async function addNativeTaskComment(page, text) {
    const form = page.locator('form[action$="/tasks/add_task_comment"]').first();
    await form.locator('textarea[name="content"]').fill(text);
    await nativeTaskCommand(page, 'add_task_comment', () => form.locator('button[type=submit]').click());
}

async function toggleNativeTaskTimer(page) {
    // The shared header also renders blank-timer buttons in its dropdowns.
    // Exercise the open task's control, retaining Playwright's strict locator.
    const button = page.locator('[data-native-task-detail] button[onclick*="timer_action"]');
    await nativeTaskCommand(page, 'timer_tracking', () => button.click());
}

async function completeNativeTaskChecklistItem(page) {
    const checkbox = page.locator('input[name="checklist-box"]').first();
    await nativeTaskCommand(page, 'checkbox_action/', () => checkbox.check(), { refresh: false });
}

async function markNativeTaskDone(page) {
    const form = page.locator('form[action*="/tasks/mark_as/"]').first();
    await form.locator('select').selectOption('5');
    await nativeTaskCommand(page, 'mark_as/5/', () => form.locator('button[type=submit]').click());
}

module.exports = {
    PROJECT_RADIX,
    TASK_RADIX,
    createNativeProject,
    createNativeProjectTask,
    openNativeProjectTasks,
    addNativeTaskChecklistItem,
    nativeTaskCommand,
    openNativeTask,
    addNativeTaskComment,
    toggleNativeTaskTimer,
    completeNativeTaskChecklistItem,
    markNativeTaskDone,
    submitAndNavigate,
};
