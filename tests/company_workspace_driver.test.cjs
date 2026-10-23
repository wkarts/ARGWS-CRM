'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { workspacePage, revealWorkspaceControl } = require('./helpers/company_workspace_driver.cjs');

test('workspace driver navigates by a real accessible button click before filling without mutating visibility', async () => {
    const events = [];
    let active = false;
    const control = {count: async () => 1, evaluate: async () => active ? [] : ['company-workspace-0-1'], fill: async value => events.push(['fill', value]), first() { return this; }};
    const button = {count: async () => 1, evaluate: async () => [], click: async () => {events.push(['click']); active = true;}};
    const page = {locator: selector => selector.includes('aria-controls') ? button : control};
    const result = await workspacePage({newPage: async () => page});
    await result.locator('input[name=name]').first().fill('Synthetic company');
    assert.deepEqual(events, [['click'], ['fill', 'Synthetic company']]);
    await result.locator('input[name=name]').fill('Changed');
    assert.deepEqual(events.at(-1), ['fill', 'Changed']);
    assert.equal(events.filter(event => event[0] === 'click').length, 1);
});
test('missing/ambiguous controls are not replaced with another permitted control', async () => {
    for (const count of [0, 2]) await revealWorkspaceControl({locator: () => {throw new Error('must not navigate');}}, {count: async () => count});
});
test('navigation adapter cannot inject hidden state, submit requests, or bypass actionability', () => {
    const source = fs.readFileSync(__dirname + '/helpers/company_workspace_driver.cjs', 'utf8');
    assert.doesNotMatch(source, /\.hidden\s*=|\.style\s*[.=]|force:\s*true|dispatchEvent|\.request\.|fetch\(|\.submit\(/);
    assert.match(source, /await button\.click\(\)/);
});
test('workspace browser suites do not omit admin page aliases and keep public/negative pages raw', () => {
    const path = require('node:path');
    const browserFiles = directory => fs.readdirSync(directory, {withFileTypes:true}).flatMap(entry => {
        const file = path.join(directory, entry.name);
        return entry.isDirectory() ? browserFiles(file) : /browser.*\.cjs$/.test(entry.name) && !entry.name.endsWith('.test.cjs') ? [file] : [];
    });
    // Presentation-only mobile wizard and employee ACL probe never interact with
    // operational workspace panels. Portal contact page must not inherit admin navigation.
    const rawExceptions = new Map([
        ['companies_onboarding_browser.cjs', ['mobilePage:context', 'employeePage:regular']],
        ['companies/portal_operations_browser.cjs', ['page:publicCtx']]
    ]);
    const audited = [];
    for (const file of browserFiles(__dirname)) {
        const source = fs.readFileSync(file, 'utf8');
        if (!source.includes("helpers/company_workspace_driver.cjs")) continue;
        const relative = path.relative(__dirname, file).split(path.sep).join('/');
        audited.push(relative);
        const raw = Array.from(source.matchAll(/\b(\w+)\s*=\s*await\s+(\w+)\.newPage\(/g), match => match[1] + ':' + match[2]);
        assert.deepEqual(raw.sort(), (rawExceptions.get(relative) || []).slice().sort(), relative + ': classify each raw constructor instead of missing an admin alias');
    }
    for (const file of ['companies/portal_operations_browser.cjs', 'companies_onboarding_browser.cjs']) assert.ok(audited.includes(file), file + ' must retain the navigation driver');
    const portal = fs.readFileSync(__dirname + '/companies/portal_operations_browser.cjs', 'utf8');
    assert.match(portal, /admin\s*=\s*await workspacePage\(adminCtx\)/);
    assert.match(portal, /page\s*=\s*await publicCtx\.newPage\(\)/);
    assert.doesNotMatch(portal, /workspacePage\(publicCtx\)/);
});
test('role-filtered hidden rows require explicit visible-tab navigation, not a relaxed helper', async () => {
    let open = false, tabClicks = 0, actions = 0;
    const rowAction = {count:async()=>open?1:0,evaluate:async()=>[],click:async()=>{assert.ok(open,'role-filtered row cannot resolve while its panel is hidden');actions++;}};
    const tab = {count:async()=>1,evaluate:async()=>[],click:async()=>{tabClicks++;open=true;}};
    const page = await workspacePage({newPage:async()=>({locator:selector=>selector==='tab'?tab:rowAction})});
    await assert.rejects(()=>page.locator('role-filtered row').click(), /cannot resolve/);
    assert.equal(tabClicks,0,'driver cannot guess a tab from an empty role locator');
    await page.locator('tab').click();
    await page.locator('role-filtered row').click();
    assert.equal(tabClicks,1); assert.equal(actions,1);
});
test('nested role-filter audit stays explicit, including dotted page aliases', () => {
    const path = require('node:path');
    const walk = dir => fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?walk(path.join(dir,entry.name)):[path.join(dir,entry.name)]);
    const found = {}, aliases = {};
    for (const file of walk(__dirname).filter(file=>/browser.*\.cjs$/.test(file)&&!file.endsWith('.test.cjs'))) {
        const source=fs.readFileSync(file,'utf8');
        const count=Array.from(source.matchAll(/filter\(\s*\{\s*has:\s*[\w.]+\.getByRole\(/g)).length;
        if(count) found[path.relative(__dirname,file).split(path.sep).join('/')]=count;
        const indirect=Array.from(source.matchAll(/filter\(\s*\{\s*has:\s*([\w.]+)\s*\}/g),match=>match[1]);
        if(indirect.length) aliases[path.relative(__dirname,file).split(path.sep).join('/')]=indirect;
    }
    // Operational attachments/timers belong to the first selected-record panel.
    // Microsoft/Connect use ordinary settings, without generated workspace panels.
    // Sales attachments alone need the explicitly opened portal-access panel below.
    assert.deepEqual(found, {
        'companies/operation_files_browser.cjs':1,
        'companies_sales_extras_browser.cjs':1,
        'settings/resume_439_connect_browser.cjs':1,
        'settings/resume_439_microsoft_browser.cjs':2,
        'settings/task_timer_auto_stop_browser.cjs':1
    }, 'new nested role filters require a visibility audit; constructor coverage is not visibility proof');
    assert.deepEqual(aliases, {'settings/task_timer_auto_stop_browser.cjs':['button','button','button','button'],'settings/task_timer_browser.cjs':['start']}, 'indirect role locators also require review; both timer aliases are in the selected-record default panel');
    const sales=fs.readFileSync(__dirname+'/companies_sales_extras_browser.cjs','utf8');
    const selected=sales.slice(sales.indexOf("await page.goto(url+'/admin/company_sales?kind=estimates&document='+sources[a])"));
    const tab=selected.indexOf("getByRole('tab',{name:'Acesso no portal por contato',exact:true}).click()");
    const visible=selected.indexOf("getByRole('link',{name:'sales-browser-a.pdf',exact:true}).isVisible()");
    const row=selected.indexOf("const row=page.locator('li').filter({has:page.getByRole");
    assert.ok(tab>=0&&visible>tab&&row>visible,'open actual panel and prove PDF visible before nested role lookup');
    assert.doesNotMatch(selected,/includeHidden:\s*true|force:\s*true/);
});
