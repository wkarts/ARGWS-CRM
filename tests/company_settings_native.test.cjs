'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const nativeView = fs.readFileSync(path.join(root, 'application/views/admin/settings/all.php'), 'utf8');
const presentation = fs.readFileSync(path.join(root, 'application/views/admin/settings/presentation_layout.php'), 'utf8');
const catalog = fs.readFileSync(path.join(root, 'application/services/CompanySettingsCatalog.php'), 'utf8');
const forms = fs.readFileSync(path.join(root, 'application/services/NativeCompanySettingsForms.php'), 'utf8');
const nativeController = fs.readFileSync(path.join(root, 'application/services/NativeCompanySettingsController.php'), 'utf8');
const settingsController = fs.readFileSync(path.join(root, 'application/controllers/admin/Settings.php'), 'utf8');
const sections = fs.readFileSync(path.join(root, 'application/services/NativeSettingsSections.php'), 'utf8');
const companyPartial = fs.readFileSync(path.join(root, 'application/views/admin/settings/includes/company.php'), 'utf8');
const companyIdentity = fs.readFileSync(path.join(root, 'application/views/admin/settings/includes/company_identity_fields.php'), 'utf8');
test('shared Settings route exposes exactly the native forms backed by owned adapters', () => {
  const start = forms.indexOf('public static function availableGroups');
  const end = forms.indexOf('public function authorize', start);
  const admitted = [...forms.slice(start, end).matchAll(/'([a-z_]+)'/g)].map(m => m[1]);
  assert.equal(new Set(admitted).size, admitted.length, 'native adapters are uniquely admitted');
  assert.match(nativeController, /NativeSettingsSections::collect\(\)/);
  assert.match(nativeController, /NativeCompanySettingsForms::availableGroups\(\)/);
  assert.match(nativeController, /\$section\['children'\]=array_values\(array_filter/);
  assert.match(nativeController, /\$ci->load->view\('admin\/settings\/all'/);
  assert.match(settingsController, /NativeCompanySettingsController::index\(\$this\)/);
  assert.match(sections, /admin\/settings\/includes/);
  assert.match(nativeView, /\$this->load->view\(\$group\['view'\]\)/, 'same original Settings partial is rendered');
  assert.match(nativeView, /init_head\(\).*init_tail\(\)/s, 'same native CRM head and footer are retained');
  assert.doesNotMatch(nativeController, /company_scoped\.php|CompanySettingsCatalog::groups/);
  assert.match(presentation, /aria-label="Seções de definições"/);
  assert.match(nativeView, /panel_s/);
  assert.match(nativeView, /panel-footer text-right/);
  assert.doesNotMatch(nativeView, /company_scoped\.php|company-settings-nav/);
  assert.doesNotMatch(presentation, /init_head\(|init_tail\(|hooks\(\)|get_option\(|CompanySettingsRuntime|\$this/);
});
test('native Settings POST enforces selected-company authorization, CSRF and revision', () => {
  assert.match(nativeView, /form_open_multipart\(\$actionUrl, \$formAttributes\)/);
  assert.match(nativeView, /form_hidden\('native_settings_revision',\$native_settings_revision\)/);
  assert.match(nativeView, /form_hidden\('_native_company_csrf',CompanyRequestScope::nativeCsrfToken/);
  assert.match(nativeView, /form_hidden\('_company_context',CompanyRequestScope::token\(\)\)/);
  assert.match(nativeController, /CompanyRequestScope::assertNativeMutation\(\$ci\)/);
  assert.match(nativeController, /\$forms->authorize\(\$issuer,\$actor,'edit'\)/);
  assert.match(nativeController, /\$forms->save\(\$issuer,\$actor,\$group/);
  assert.match(nativeController, /native_settings_revision/);
  assert.match(nativeController, /redirect\(admin_url\('settings\?group='/);
  assert.match(nativeController, /'native_settings_can_edit'=>\$canEdit/);
  assert.match(nativeView, /fieldset <\?= \$isNativeSettings && !\$native_settings_can_edit \? 'disabled'/);
});
test('native company identity and custom fields come only from the selected issuer DTO', () => {
  assert.match(forms, /'custom_field_definitions'=>\$definitions/);
  assert.match(nativeController, /'native_settings_company_values'=>\$group==='company'\?\$page\['values'\]:null/);
  assert.match(nativeController, /'native_settings_company_field_definitions'=>\$group==='company'\?\$page\['custom_field_definitions'\]:null/);
  assert.match(companyPartial, /\$native_settings_company_values\[\$name\]\?\?''/);
  assert.match(companyPartial, /\$native_settings_company_values\['company_info_format'\]/);
  assert.match(companyPartial, /\$native_settings_company_field_definitions/);
  assert.match(companyPartial, /render_custom_fields\('company', 0\)/, 'the existing scoped custom-field component still renders the form');
  assert.match(companyIdentity, /Company identity value adapter required/);
  const nativeIdentityBranch = companyPartial.slice(companyPartial.indexOf('if($nativeCompanySettings){'), companyPartial.indexOf("}else{$companyIdentityOption=static fn($name)=>get_option($name);}"));
  assert.doesNotMatch(nativeIdentityBranch, /get_option\(/, 'native identity fields never fall back to installation options');
  assert.match(companyPartial, /\$custom_company_fields = \$nativeCompanySettings \? \$native_settings_company_field_definitions : get_company_custom_fields\(\)/);
  assert.match(forms, /definitionsForResource\(\$issuer,\$actor,'company',\$issuer\)/);
  assert.match(forms, /storedInput\(\$issuer,\$actor,'company',\$issuer\)/);
});
test('native and primary Settings use the same native presentation component', () => {
  assert.match(nativeView,/require __DIR__\.'\/presentation_layout\.php'/);
  assert.doesNotMatch(nativeView,/company-settings-native\.css/);
  assert.doesNotMatch(presentation,/init_head\(|init_tail\(|hooks\(\)|get_option\(|CompanySettingsRuntime|\$this/);
  for(const cls of ['col-xs-12 col-md-12 col-lg-10','col-md-4 col-lg-3','col-md-8 col-lg-9','tw-flex tw-flex-col tw-gap-6','fa-fw fa-lg']) assert.ok(presentation.includes(cls),cls);
});
test('certificate entry uses only active company and settings edit permission', () => {
  assert.match(companyPartial, /settingsCertificateContext->issuerId\(\) !== null/);
  assert.match(companyPartial, /CompanySettingsRuntime::active\(\) && CompanySettingsRuntime::can\('edit'\)/);
  assert.match(companyPartial, /adminUrl\(\$this,\s*'settings\?group=certificates'\)/);
  assert.doesNotMatch(companyPartial, /company_certificates/, 'parallel certificate controller is not linked');
});

test('standalone shared-shell fixtures declare framework dependencies and fail before emitting warning HTML',()=>{
 for(const file of ['tests/settings/settings_model_controller.php','tests/companies/asaas_boundaries.php','tests/companies/asaas_checkout_views.php']) {
  const source=fs.readFileSync(path.join(root,file),'utf8');
  assert.match(source,/public(?: object)? \$db;/,file);
  assert.match(source,/public(?: object)? \$router;/,file);
  assert.match(source,/strictFixtureRender\(/,file);
 }
 const guard=fs.readFileSync(path.join(root,'tests/support/StrictViewFixture.php'),'utf8');
 assert.match(guard,/throw \$error/);
 assert.match(guard,/restore_error_handler\(\)/);
 assert.match(guard,/ob_get_level\(\) > \$level/);
 assert.doesNotMatch(guard,/error_reporting\(|ini_set\(/,'never suppress renderer diagnostics');
 const common=fs.readFileSync(path.join(root,'tests/issuers_core_support.php'),'utf8');
 assert.match(common,/'router' => new class \{ public function fetch_class\(\)/);
});

test('shared envelope validates navigation DTO and preserves trusted server form slots',()=>{
 assert.match(presentation,/Invalid settings item/);
 assert.match(presentation,/htmlspecialchars/);
 assert.match(presentation,/\$settingsPresentation\['content'\]/);
 assert.doesNotMatch(presentation,/<form\b|<\/form>|<script\b|include\s|require\s/,'component owns neither actions nor runtime');
 const fixture=fs.readFileSync(path.join(root,'tests/company_settings_layout.php'),'utf8');
 for(const text of ['settings/all.php','Native hooks changed','javascript:alert(1)','//external.invalid','Unsafe DTO accepted','strictFixtureRender']) assert.ok(fixture.includes(text));
 const browser=fs.readFileSync(path.join(root,'tests/company_settings_layout.browser.cjs'),'utf8');
 for(const text of ['[1280,768,390]','company_settings_layout.php','getComputedStyle','form form','new FormData','settings-envelope-official-']) assert.ok(browser.includes(text));
 assert.doesNotMatch(browser,/company_settings_native\.php|company_scoped/);
 assert.doesNotMatch(browser,/test\.skip|force:\s*true/);
});

test('native support settings are owned by the original Settings form, not duplicated in outgoing generic UI',()=>{
 const forms=fs.readFileSync(path.join(root,'application/services/NativeCompanySettingsForms.php'),'utf8');
 const native=fs.readFileSync(path.join(root,'application/views/admin/settings/includes/tickets.php'),'utf8');
 assert.match(forms,/'tickets'=>NativeCompanyTicketPreferences::fields\(\)/);
 assert.ok(native.includes('native_settings_ticket_values'));
 assert.match(catalog,/'tickets'=>NativeCompanyTicketPreferences::fields\(\)/);
});
