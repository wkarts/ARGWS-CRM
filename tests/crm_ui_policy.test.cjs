const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

test('produto CRM e fornecedor ARGWS mantêm seus papéis sem renomear contratos', () => {
  for (const file of ['application/config/config.php', 'application/helpers/template_helper.php',
    'application/views/admin/settings/includes/update.php', 'install/index.php', 'install/html.php',
    'install/install.class.php', 'install/finish.php']) {
    assert.doesNotMatch(read(file), /ARGWS CRM/, file);
  }
  assert.match(read('application/views/admin/settings/includes/argws_platform.php'), /CRM · Grupo fornecedor: ARGWS/);
  assert.match(read('application/helpers/template_helper.php'), /get_option\('companyname'\)/);
  assert.match(read('application/config/constants.php'), /ARGWS_RELEASES_URL/);
  assert.match(read('application/config/constants.php'), /https:\/\/github.com\/wkarts\/argws-crm\/releases/);
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
  assert.match(read('application/controllers/admin/Mods.php'), /\$hidden\['ideal'\] = true/);
  assert.match(read('application/libraries/App_modules.php'), /\$name === 'ideal' && !\\app\\services\\PaymentGatewayCatalog::idealEnabled\(\)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /hash_equals\(\$expected, \$provided\)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /method\(true\) !== 'POST'/);
  assert.doesNotMatch(read('modules/ideal/ideal.php'), /href="[^"]*ideal\/(create_webhook|enable_webhook)/);
  assert.match(read('modules/ideal/controllers/Ideal.php'), /!is_staff_logged_in\(\) \|\| !staff_can\('edit', 'settings'\)/);
});
