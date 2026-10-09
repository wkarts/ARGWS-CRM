const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('O painel mantém nomes funcionais e os slugs internos', () => {
  const list = read('application/views/admin/modules/list.php');
  const controller = read('application/controllers/admin/Mods.php');
  const modules = read('application/libraries/App_modules.php');
  assert.match(list, />Recursos<\/h4>/);
  assert.doesNotMatch(list, /Recursos ARGWS|html_escape\(\$systemName\)/);
  assert.match(controller, /CRM_HIDDEN_RESOURCES/);
  assert.match(controller, /try \{[\s\S]*app_modules->activate/);
  assert.match(controller, /app_modules->activate\(\$name\)/);
  assert.match(modules, /'active'\s*=>\s*0/);
  assert.match(modules, /\$this->modules\[\$name\]\['activated'\] = 1/);
});

test('PT-BR é fixo na interface, nos e-mails e na REST API', () => {
  const localization = read('application/views/admin/settings/includes/localization.php');
  const controller = read('application/controllers/admin/Settings.php');
  const helpers = read('application/helpers/email_templates_helper.php');
  const rest = read('modules/api/config/rest.php');
  assert.doesNotMatch(localization, /<select name="settings\[(active_language|enabled_languages)/);
  assert.match(localization, /Português \(Brasil\)/);
  assert.match(controller, /\['disable_language'\]\s*=\s*'1'/);
  assert.match(helpers, /'language' => 'portuguese_br'/);
  assert.match(rest, /\$config\['rest_language'\]\s*=\s*'portuguese_br'/);
});

test('A migration é aditiva e conserva HTML e personalizações', () => {
  const migration = read('application/migrations/365_version_365.php');
  assert.match(migration, /class Migration_Version_365 extends CI_Migration/);
  assert.match(migration, /function restore_mail_templates/);
  assert.match(migration, /if \(!\$existing\)/);
  assert.match(migration, /'subject', 'message', 'fromname'/);
  assert.doesNotMatch(migration, /\bTRUNCATE\b|\bDROP TABLE\b|->delete\(/);
  assert.match(migration, /if \(\$this->db->table_exists\(\$transactions\) && \$this->db->count_all_results\(\$transactions\) > 0\)/);
  assert.match(migration, /#32c977/);
  const seed = read('install/database.sql');
  assert.match(seed, /\(1, 'R\$', 'BRL', ',', '\.', 'before', 1\)/);
  assert.match(seed, /'disable_language', '1'/);
});

test('Guia da API é local, autenticado e descreve os controladores existentes', () => {
  const view = read('modules/api/views/apidoc.php');
  const menu = read('modules/api/api.php');
  const controller = read('modules/api/controllers/Api.php');
  assert.match(menu, /admin_url\('api\/api_guide'\)/);
  assert.match(controller, /function api_guide\(\)[\s\S]*!is_admin\(\)/);
  assert.match(controller, /load->view\('apidoc'/);
  assert.doesNotMatch(controller, /fopen\(APP_MODULES_PATH/);
  for (const route of ['customers', 'contacts', 'invoices', 'estimates', 'tasks', 'payments', 'common']) {
    assert.match(view, new RegExp("'" + route + "'"));
    assert.ok(fs.existsSync(path.join(root, 'modules/api/controllers',
      route.charAt(0).toUpperCase() + route.slice(1) + '.php')));
  }
});

test('Nota fiscal inclui dependências em Docker e nos ZIPs sem Composer em runtime', () => {
  const docker = read('Dockerfile');
  const workflow = read('.github/workflows/release-packages.yml');
  assert.match(docker, /FROM composer:2 AS einvoice-deps/);
  assert.match(docker, /COPY --from=einvoice-deps[\s\S]*einvoice\/vendor/);
  assert.match(docker, /class_exists\("Mustache_Engine"\)/);
  assert.match(workflow, /composer install --working-dir=modules\/einvoice/);
  assert.match(workflow, /test -s modules\/einvoice\/vendor\/autoload.php/);
});

test('Suporte preserva widget opcional e aceita WhatsApp, e-mail ou site', () => {
  const settings = read('application/controllers/admin/Settings.php');
  const form = read('application/views/admin/settings/includes/argws_platform.php');
  const theme = read('application/helpers/themes_helper.php');
  for (const option of ['support_contact_channel', 'support_whatsapp', 'support_email', 'support_site_url']) {
    assert.match(settings, new RegExp(option));
    assert.match(form, new RegExp('settings\\[' + option + '\\]'));
  }
  assert.match(settings, /CRM_SUPPORT_WHATSAPP/);
  assert.match(settings, /CRM_SUPPORT_EMAIL/);
  assert.match(settings, /CRM_SUPPORT_SITE_URL/);
  assert.match(theme, /function support_contact_link\(\)/);
  assert.match(theme, /https:\/\/wa\.me\//);
  assert.match(theme, /mailto:/);
  assert.match(theme, /noopener noreferrer nofollow/);
  assert.match(theme, /echo argws_support_widget_script\(\)/);
});
