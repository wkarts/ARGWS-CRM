'use strict';

const assert = require('node:assert/strict');

const PROJECT_RADIX = 4294967296;
const TASK_RADIX = PROJECT_RADIX * 2;

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
    } = options;
    await page.goto(baseUrl + '/admin/projects/project?customer_id=' + encodeURIComponent(client));
    const form = page.locator('#project_form');
    await form.locator('#clientid').selectOption(String(client));
    await form.locator('#name').fill(name);
    await form.locator('#start_date').fill(startDate);
    await form.locator('#deadline').fill(dueDate);
    if (description !== '') await form.locator('textarea[name="description"]').fill(description);
    await form.locator('#billing_type').selectOption('1');
    await submitAndNavigate(page, form.locator('button[type=submit]'));

    const path = new URL(page.url()).pathname.split('/').filter(Boolean);
    const canonical = path[path.length - 1];
    assert.match(canonical || '', /^[1-9][0-9]*$/);
    const sourceId = Number(canonical) - PROJECT_RADIX;
    assert(Number.isSafeInteger(sourceId) && sourceId > 0);
    return { canonical, sourceId: String(sourceId) };
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

    await page.goto(baseUrl + '/admin/projects/view/' + projectCanonical + '?group=project_tasks');
    await page.locator('a[onclick*="new_task_from_relation"]').first().click();
    const form = page.locator('#task-form');
    await form.waitFor({ state: 'attached' });
    await page.locator('#_task_modal').waitFor({ state: 'visible' });
    await form.locator('input[name="name"]').fill(name);
    await form.locator('input[name="startdate"]').fill(startDate);
    await form.locator('input[name="duedate"]').fill(dueDate);
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
        await initialize.click();
        const response = await responsePromise;
        assert.equal(response.status(), 200);
        const payload = await response.json();
        assert.equal(payload.success, true);
        await page.waitForLoadState('domcontentloaded');
        await page.reload({ waitUntil: 'domcontentloaded' });
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
    const button = page.locator('button[onclick*="timer_action"]').first();
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
    addNativeTaskChecklistItem,
    nativeTaskCommand,
    openNativeTask,
    addNativeTaskComment,
    toggleNativeTaskTimer,
    completeNativeTaskChecklistItem,
    markNativeTaskDone,
    submitAndNavigate,
};
