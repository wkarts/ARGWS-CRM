'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'application/views/admin/settings/company_scoped.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/company-settings-native.css'), 'utf8');
const catalog = fs.readFileSync(path.join(root, 'application/services/CompanySettingsCatalog.php'), 'utf8');
test('native Settings sidebar covers every admitted group without global hooks', () => {
  const admitted = [...catalog.slice(catalog.indexOf('public static function groups'), catalog.indexOf('public static function fields')).matchAll(/'([a-z_]+)'\s*=>/g)].map(m => m[1]);
  const sections = view.slice(view.indexOf('$settingsSections = ['), view.indexOf('$listedGroups ='));
  for (const key of admitted) assert.equal((sections.match(new RegExp("'" + key + "'", 'g')) || []).length, 1, key);
  assert.match(view, /isset\(\$groups\[\$key\], \$company_urls\[\$key\]\)/);
  assert.match(view, /array_diff\(array_keys\(\$groups\), \$listedGroups\)/, 'new admitted groups must not disappear');
  assert.match(view, /aria-label="Seções de definições"/);
  assert.match(view, /panel_s/);
  assert.match(view, /panel-footer text-right/);
  assert.doesNotMatch(view, /init_head\(|init_tail\(|hooks\(\)|company-settings-nav/);
});
test('existing settings action, authorization, CSRF and revision contracts are retained', () => {
  const formStart = view.indexOf('<form id="company-settings-form"');
  const formEnd = view.indexOf('</form>', formStart);
  assert.ok(formStart > 0 && formEnd > formStart);
  assert.equal((view.match(/<form\b/g) || []).length, 1);
  assert.equal((view.match(/<\/form>/g) || []).length, 1);
  const form = view.slice(formStart, formEnd);
  assert.match(form, /method="post" action="<\?= \$escape\(\$company_urls\[\$group\]\); \?>"/);
  assert.match(form, /name="_company_context"/);
  assert.match(form, /get_csrf_token_name\(\)/);
  assert.match(form, /get_csrf_hash\(\)/);
  assert.match(form, /fieldset <\?= !\$can_edit \? 'disabled'/);
  assert.match(form, /if \(\$can_edit\).*?[\s\S]*type="submit">Salvar definições/);
  for (const revision of ['pdf_typography', 'task_timer_stop', 'task_timers', 'task_operations', 'task_defaults', 'ticket_preferences', 'project_files']) assert.ok(form.includes('name="'+revision+'_revision"'));
  assert.match(form, /name="settings\[smtp_password\]" type="password" value=""/);
  assert.match(form, /name="clear_smtp_password"/);
  for (const route of ['company_google_mail_oauth', 'company_microsoft_mail_oauth', 'company_connect_api']) assert.ok(view.includes(route));
});
test('presentation overrides are scoped to Settings, preserving shared shell', () => {
  assert.match(css, /\.company-native-settings/);
  assert.doesNotMatch(css, /#header|#menu|body\s*\{|position:\s*fixed/);
  assert.match(css, /focus-visible/);
  assert.match(view, /company-settings-native\.css/);
});
test('certificate entry uses only active company and settings edit permission', () => {
  const native = fs.readFileSync(path.join(root, 'application/views/admin/settings/includes/company.php'), 'utf8');
  for (const source of [view, native]) {
    assert.match(source, /settingsCertificateContext->issuerId\(\) !== null/);
    assert.match(source, /CompanySettingsRuntime::active\(\) && CompanySettingsRuntime::can\('edit'\)/);
    assert.match(source, /adminUrl\(\$this,\s*'company_certificates'\)/);
    assert.doesNotMatch(source, /company_certificates\/index\//, 'do not pin certificate operation to an unselected company');
  }
  assert.match(view, /\$settingsCanCertificate = \$can_edit &&/);
});

test('settings browser records sanitized geometry and screenshots before retaining strict overflow assertion',()=>{
 const browser=fs.readFileSync(path.join(root,'tests/company_settings_native_browser.cjs'),'utf8');
 assert.ok(browser.indexOf('const evidence=await recordLayout')<browser.indexOf("'no horizontal overflow:"));
 assert.match(browser,/document\.fonts\.ready/);
 assert.match(browser,/settings-\$\{width\}-geometry\.json/);
 assert.match(browser,/settings-\$\{width\}\.png/);
 assert.match(browser,/evidence\.geometry\.documentWidth <= evidence\.geometry\.viewport/);
 const diagnostics=browser.slice(browser.indexOf('async function recordLayout'),browser.indexOf('const rendered'));
 assert.doesNotMatch(diagnostics,/\.value\b|textContent|innerHTML|outerHTML/,'diagnostics must not capture form values or document contents');
});
