const { selectCompanyOption, ensureCompanySidebar } = require('./helpers/company_selector_driver.cjs');
// Synthetic UI contract: no application login, database, or production requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const selector = fs.readFileSync(path.join(root, 'application/views/admin/includes/company_selector.php'), 'utf8');
const head = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_head.php'), 'utf8');
const tail = fs.readFileSync(path.join(root, 'application/views/admin/companies/layout_tail.php'), 'utf8');
assert.match(selector, /data-company-selector-toggle/);
assert.match(selector, /type="submit" name="company_id"/);
assert.match(selector, /data-company-choice/);
assert.doesNotMatch(selector, /<select|Confirmar empresa selecionada/);
assert.match(selector, /name="_company_switch_csrf"/);
assert.match(selector, /company_selection\/select/);
assert.match(selector, /form_open/);
assert.match(selector, /CompanySettingsIdentity/);
assert.match(selector, /->displayName\(\$company\['id'\]\)/);
assert.match(selector, /e\(\$companySelectorNames\[\$companySelectorId\]\)/);
assert.match(selector, /\(principal\)/);

assert.doesNotMatch(selector, />Trocar<|onchange=/);
assert.doesNotMatch(head + tail, /init_head\(|init_tail\(|app_admin_head\(|app_compile_css\(/);
assert.ok(head.indexOf('admin/includes/company_selector.php') < head.indexOf('sidebar-user-profile'), 'isolated selector precedes user profile');
const nativeHeader = fs.readFileSync(path.join(root, 'application/views/admin/includes/header.php'), 'utf8');
const nativeAside = fs.readFileSync(path.join(root, 'application/views/admin/includes/aside.php'), 'utf8');
const nativeAccountClasses = nativeAside.match(/<div class="([^"]*company-sidebar-account[^"]*)">/)?.[1];
assert.ok(nativeAccountClasses, 'native sidebar account wrapper is preserved in the focused fixture');
assert.doesNotMatch(nativeHeader, /includes\/company_selector/);
assert.ok(nativeAside.indexOf('includes/company_selector') < nativeAside.indexOf('sidebar-user-profile'));
const assets = [...head.matchAll(/'(assets\/[^']+\.css)'/g)].map(m => m[1]);
assert.deepEqual(assets, ['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/builds/vendor-admin.css', 'assets/plugins/font-awesome/css/fontawesome.min.css', 'assets/plugins/font-awesome/css/brands.min.css', 'assets/plugins/font-awesome/css/solid.min.css', 'assets/plugins/font-awesome/css/regular.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css', 'assets/css/company-workspace.css']);
const scripts = [...tail.matchAll(/'(assets\/[^']+\.js)'/g)].map(m => m[1]);
if (process.env.STATIC_ONLY) { console.log('PASS: selector semantics, preserved POST/CSRF markup, isolated assets and no legacy hooks'); process.exit(0); }
const {execFileSync} = require('node:child_process');
execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support/CompanyShellRouteFixture.php')], {stdio:'inherit'});
const renderSelector = selected => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support/NativeHeaderFixture.php'), '--selector-only', ...(selected === '2' ? ['--selected-two'] : [])], {encoding:'utf8'});
const markup = {'1': renderSelector('1'), '2': renderSelector('2')};
let currentHtml = '', selected = '1';
const submissions = [];
const pageHtml = () => currentHtml.replace('<!--ACTUAL_SELECTOR-->', markup[selected]);
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin === 'http://fixture.test' && url.pathname === '/sub/admin/company_selection/select') {
        assert.equal(route.request().method(), 'POST');
        const fields = new URLSearchParams(route.request().postData());
        assert.deepEqual(fields.getAll('company_id').length, 1, 'native submitter supplies one company ID');
        assert.equal(fields.get('_company_switch_csrf'), 'synthetic-fixture-only');
        assert.equal(fields.get('_company_context'), 'synthetic-company-context', 'native form carries the original selected-company binding');
        assert.ok(['1', '2'].includes(fields.get('company_id')));
        submissions.push(Object.fromEntries(fields)); selected = fields.get('company_id');
        return route.fulfill({status:303, headers:{location:'/sub/admin/'}, body:''});
      }
      if (url.origin === 'http://fixture.test' && url.pathname === '/sub/admin/' && route.request().method()==='GET') {
        return route.fulfill({status:200, contentType:'t