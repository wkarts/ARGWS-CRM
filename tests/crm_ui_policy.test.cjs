const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

test('nome central do produto e instalador mantêm papéis independentes do fiscal', () => {
  for (const file of ['application/helpers/template_helper.php',
    'application/views/admin/settings/includes/update.php']) {
    assert.doesNotMatch(read(file), /ARGWS CRM/, file);
  }
  assert.match(read('application/views/admin/settings/includes/argws_platform.php'), /crm_app_display_name_html\(\).*Grupo fornecedor: ARGWS/);
  assert.match(read('application/helpers/template_helper.php'), /crm_app_display_name\(\)/);
  assert.doesNotMatch(read('application/helpers/app_branding_helper.php'), /get_option|companyname|issuer|app-config\.php/);
  assert.match(read('application/helpers/installer_branding_helper.php'), /'ARGWS CRM'/);
  assert.match(read('application/helpers/installer_branding_helper.php'), /getenv\('INSTALLER_DISPLAY_NAME'\)/);
  assert.match(read('application/helpers/app_branding_helper.php'), /getenv\('APP_DISPLAY_NAME'\)/);
  assert.match(read('application/config/constants.php'), /ARGWS_RELEASES_URL/);
  assert.match(read('application/config/constants.php'), /https:\/\/github.com\/wkarts\/argws-crm\/releases/);
});

test('nomes de produto e instalador chegam ao container sem alterar ambiente real', () => {
  for (const file of ['compose.yaml', 'deploy/develop/compose.yaml', 'deploy/production/compose.yaml']) {
    assert.match(read(file), /INSTALLER_DISPLAY_NAME: \$\{INSTALLER_DISPLAY_NAME:-ARGWS CRM\}/);
    assert.match(read(file), /APP_DISPLAY_NAME: \$\{APP_DISPLAY_NAME:-\}/);
  }
  for (const file of ['container.env.example', 'deploy/develop/.env.example', 'deploy/production/.env.example']) {
    assert.match(read(file), /^INSTALLER_DISPLAY_NAME=ARGWS CRM$/m);
    assert.match(read(file), /^APP_DISPLAY_NAME=$/m);
  }
  assert.match(read('docker/setup-web.php'), /require_once '\/app\/application\/helpers\/installer_branding_helper.php'/);
  assert.match(read('application/config/autoload.php'), /'app_branding'/);
});

test('normalização de idioma cobre carregadores, cookies, rota e formulários', () => {
  assert.match(read('application/config/autoload.php'), /'ui_language'/);
  for (const file of ['admin_helper.php', 'clients_helper.php', 'pdf_helper.php', 'leads_helper.php']) {
    assert.match(read('application/helpers/' + file), /crm_load_ui_language\(/, file);
  }
  assert.match(read('application/controllers/admin/Staff.php'), /\$lang = crm_ui_language\(\);[\s\S]*before_staff_change_language/);
  assert.doesNotMatch(read('application/controllers/Forms.php'), /input->get\('language'\)/);
  assert.match(read('application/helpers/clients_helper.php'), /set_cookie\('contact_language', crm_ui_language\(\)/);
  assert.ok(fs.existsSync(path.join(root, 'application/language/english/english_lang.php')));
  assert.match(read('application/helpers/ui_language_helper.php'), /load\('english_lang', 'english', true\)/);
});

test('catálogo filtra seleções e escrita sem desregistrar os gateways', () => {
  for (const file of ['application/views/admin/invoices/invoice_template.php',
    'application/views/admin/invoices/record_payment_template.php',
    'application/views/admin/expenses/expense.php', 'application/views/admin/payments/payment.php',
    'application/views/admin/credit_notes/refund.php', 'application/controllers/Invoice.php']) {
    assert.match(read(file), /PaymentGatewayCatalog::forSelection/, file);
  }
  assert.match(read('application/helpers/payment_gateways_helper.php'), /add_filter\('before_invoice_added'/);
  assert.match(read('application/helpers/payment_gateways_helper.php'), /add_filter\('before_update_invoice'/);
  assert.match(read('application/models/Payments_model.php'), /PaymentGatewayCatalog::allowsCheckout/);
  assert.doesNotMatch(read('application/models/Payment_modes_model.php'), /PaymentGatewayCatalog/);
  assert.match(read('modules/ideal/ideal.php'), /register_payment_gateway\(IDEAL_MODULE_GATEWAY_ID, IDEAL_MODULE_NAME\)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /public function webhook\(\): void/);
});

test('flag iDEAL é propagada, desabilitada por padrão e não substitui RBAC', () => {
  for (const file of ['compose.yaml', 'deploy/develop/compose.yaml', 'deploy/production/compose.yaml']) {
    assert.match(read(file), /CRM_ENABLE_IDEAL: \$\{CRM_ENABLE_IDEAL:-false\}/, file);
  }
  for (const file of ['container.env.example', 'deploy/develop/.env.example', 'deploy/production/.env.example']) {
    assert.match(read(file), /^CRM_ENABLE_IDEAL=false$/m, file);
  }
  assert.match(read('application/controllers/admin/Mods.php'), /if \(!is_admin\(\)\)/);
  const mods = read('application/controllers/admin/Mods.php');
  const index = mods.slice(mods.indexOf('public function index()'), mods.indexOf('public function activate('));
  assert.match(index, /foreach \(\$this->app_modules->get\(\) as \$module\)/);
  assert.doesNotMatch(index, /hidden_resources|CRM_HIDDEN_RESOURCES/);
  // The flag may explain availability, but must never filter the visible module list.
  assert.doesNotMatch(index, /\b(?:continue|break|return|array_filter)\b|\bif\s*\(/);
  assert.equal((index.match(/idealEnabled\(\)/g) || []).length, 1);
  assert.match(index, /\$resource\['availability_reason'\] = \$name === 'ideal' && !\\app\\services\\PaymentGatewayCatalog::idealEnabled\(\)\s*\? 'Disponibilidade restrita pela configuração CRM_ENABLE_IDEAL\.'/);
  assert.match(index, /\$data\['resources'\]\[\] = \$resource;\s*}/);
  const activation = mods.slice(mods.indexOf('public function activate('), mods.indexOf('public function deactivate('));
  assert.match(activation, /\$definition = \$this->get_resource_definition\(\$name\);/);
  assert.match(activation, /if \(!\$definition \|\| !\$this->app_modules->get\(\$name\)\)\s*{\s*show_404\(\);/);
  const definition = mods.slice(mods.indexOf('private function get_resource_definition('), mods.indexOf('private function resource_name('));
  assert.match(definition, /if \(!\$forDeactivation && \$name === 'ideal' && !\\app\\services\\PaymentGatewayCatalog::idealEnabled\(\)\)\s*{\s*return false;/);
  assert.match(mods, /\$name === 'ideal' && !\\app\\services\\PaymentGatewayCatalog::idealEnabled\(\)/);
  assert.match(index, /can_manage_availability/);
  assert.match(read('application/libraries/App_modules.php'), /\$name === 'ideal' && !\\app\\services\\PaymentGatewayCatalog::idealEnabled\(\)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /hash_equals\(\$expected, \$provided\)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /method\(true\) !== 'POST'/);
  assert.doesNotMatch(read('modules/ideal/ideal.php'), /href="[^"]*ideal\/(create_webhook|enable_webhook)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /!is_staff_logged_in\(\) \|\| !staff_can\('edit', 'settings'\)/);
});
