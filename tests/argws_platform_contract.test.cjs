const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const findFiles = (directory, name) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
  const candidate = path.join(directory, entry.name);
  return entry.isDirectory() ? findFiles(candidate, name) : (entry.name === name ? [candidate] : []);
});

test('PT-BR é o único idioma selecionável e o padrão da instalação', () => {
  const app = read('application/libraries/App.php');
  const config = read('application/config/config.php');
  const settings = read('application/controllers/admin/Settings.php');
  const dump = read('install/database.sql');
  assert.match(config, /\$config\['language'\]\s*=\s*'portuguese_br'/);
  assert.equal((app.match(/return \['portuguese_br'\];/g) || []).length, 2);
  assert.match(settings, /\['active_language'\]\s*=\s*'portuguese_br'/);
  assert.match(dump, /'active_language'.*'portuguese_br'/s);
  assert.match(dump, /'enabled_languages'.*portuguese_br/s);
  const installer = read('install/index.php');
  assert.match(installer, /A instalação do ARGWS CRM já foi concluída/);
  assert.doesNotMatch(installer, /The installation is already finished|delete all tables/i);
});

test('suporte fica em settings administrativos e envia apenas configuração pública', () => {
  const settings = read('application/controllers/admin/Settings.php');
  const view = read('application/views/admin/settings/includes/argws_platform.php');
  const helper = read('application/helpers/themes_helper.php');
  assert.match(settings, /group === 'argws_platform' && !is_admin\(\)/);
  assert.match(settings, /strtolower\(\$parts\['scheme'\] \?\? ''\) !== 'https'/);
  assert.match(view, /argws_support_public_token/);
  assert.match(view, /argws_terminology_policy/);
  assert.match(helper, /JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT/);
  assert.match(helper, /argws_support_public_token/);
  assert.doesNotMatch(helper, /HUB_BASE_URL|HUB_TOKEN|\benv\(/);
  assert.match(helper, /function app_customers_footer\(\)[\s\S]*argws_support_widget_script\(\)/);
});

test('estado nativo dos recursos mantém dependências e bloqueia upload/desinstalação', () => {
  const catalog = read('application/config/argws_resources.php');
  const controller = read('application/controllers/admin/Mods.php');
  assert.match(catalog, /'connect_api_connector'.*'requires'\s*=>\s*\[\],\s*'optional'\s*=>\s*\['connect_api_manager'\]/);
  assert.match(catalog, /'connect_api_chat'.*'requires'\s*=>\s*\['connect_api_connector'\]/);
  assert.match(controller, /foreach \(\$definition\['requires'\] as \$dependency\)/);
  assert.match(controller, /Desative primeiro o recurso dependente/);
  assert.match(controller, /public function upload\(\)\s*\{\s*show_404\(\);/);
  assert.match(controller, /public function uninstall\(\$name\)\s*\{\s*show_404\(\);/);
  assert.match(controller, /Banco de dados do recurso atualizado/);
});

test('telemetria de licenciamento e do SDK Stripe fica desativada', () => {
  const app = read('application/libraries/App.php');
  const updates = read('application/controllers/admin/Auto_update.php');
  const tracking = read('application/services/ViewsTracking.php');
  assert.match(app, /Stripe::setEnableTelemetry\(false\)/);
  assert.match(app, /function get_update_info\(\)[\s\S]*ARGWS_RELEASES_URL/);
  assert.doesNotMatch(app, /curl_init|file_get_contents\(['"]https?:/);
  assert.match(updates, /show_404\(\)/);
  assert.match(tracking, /function create\([\s\S]*return false;/);

  const securityHook = read('application/hooks/EnhanceSecurity.php');
  assert.doesNotMatch(securityHook, /GuzzleHttp|raw\.githubusercontent\.com|->get\(/);
  assert.match(securityHook, /return is_array\(\$cache\)/);
  assert.doesNotMatch(read('modules/page_builder/assets/vvvebjs/js/components-extra.js'), /docs\.perfextosaas\.com/);

  const oldVerificationRoutes = [
    ...findFiles(path.join(root, 'modules'), 'Gtsverify.php'),
    ...findFiles(path.join(root, 'modules'), 'Env_ver.php'),
  ];
  assert.ok(oldVerificationRoutes.length > 0);
  for (const file of oldVerificationRoutes) {
    const source = fs.readFileSync(file, 'utf8');
    assert.match(source, /show_404\(\)/, path.relative(root, file));
    assert.doesNotMatch(source, /curl_init|file_get_contents|purchase_key|identification_key/i);
  }
});

test('migrations novas preservam valores e não reescrevem migrations antigas', () => {
  const core = read('application/migrations/342_version_342.php');
  const asaas = read('modules/asaas/migrations/145_version_145.php');
  assert.match(core, /argws_migration_342_backup/);
  assert.match(core, /translations_purchase_code/);
  assert.match(core, /si_custom_theme_activated/);
  assert.match(core, /si_custom_theme_activation_code/);
  assert.match(core, /was_present/);
  assert.match(core, /expected_option_value/);
  assert.match(core, /if \(\$current && \$current->\{\$languageColumn\} === 'portuguese_br'\)/);
  assert.match(asaas, /argws_asaas_145_schema_state/);
  assert.match(asaas, /client_id_added/);
  assert.match(asaas, /perfex_client_id/);
  assert.match(asaas, /DROP COLUMN `client_id`/);
});

test('FrankenPHP é versionado, genérico e mantém a configuração fora da imagem', () => {
  const dockerfile = read('Dockerfile');
  const compose = read('compose.yaml');
  const caddy = read('docker/Caddyfile');
  const workflow = read('.github/workflows/container-publish.yml');
  assert.match(dockerfile, /ARG ARGWS_FRANKENPHP_IMAGE=ghcr\.io\/wkarts\/argws-crm-base:1-php8\.3-bookworm/);
  assert.match(dockerfile, /ARG ARGWS_COMPOSER_IMAGE=ghcr\.io\/composer\/docker:2/);
  assert.match(dockerfile, /FROM \$\{ARGWS_COMPOSER_IMAGE\} AS einvoice-deps/);
  assert.match(dockerfile, /php -l/);
  assert.match(dockerfile, /www-data/);
  assert.match(compose, /ghcr\.io\/wkarts\/argws-crm/);
  assert.match(compose, /installation_config:\/var\/lib\/argws-crm\/config/);
  assert.doesNotMatch(compose, /HUB_BASE_URL|HUB_TOKEN/);
  assert.match(caddy, /path_regexp module_php \^\/modules\/.*\\\.php\$/);
  assert.match(workflow, /platform: linux\/amd64/);
  assert.match(workflow, /platform: linux\/arm64/);
  assert.match(workflow, /docker buildx imagetools create -t "\$\{repo\}:develop"/);
  const releaseWorkflow = read('.github/workflows/release-packages.yml');
  assert.match(releaseWorkflow, /platform: linux\/amd64/);
  assert.match(releaseWorkflow, /platform: linux\/arm64/);
  assert.match(releaseWorkflow, /docker buildx imagetools create/);
  assert.match(releaseWorkflow, /-t "\$\{repo\}:\$\{version\}"/);
  assert.match(releaseWorkflow, /-t "\$\{repo\}:latest"/);
});


test('ZIP da primeira release usa a base legado e permite reparar os anexos existentes', () => {
  assert.equal(
    read('.github/first-release-base.txt').trim(),
    '6a8803b6f4f18f3462f738a3ca1e78c24c12e5f8'
  );
  const releaseWorkflow = read('.github/workflows/release-packages.yml');
  const repairWorkflow = read('.github/workflows/repair-release-assets.yml');
  assert.ok(releaseWorkflow.includes('first-release-base.txt'));
  assert.ok(!releaseWorkflow.includes('git rev-parse "$GITHUB_SHA^"'));
  assert.ok(repairWorkflow.includes('git archive "$SOURCE_SHA"'));
  assert.ok(repairWorkflow.includes('gh release upload "$RELEASE_TAG"'));
  assert.ok(repairWorkflow.includes('--clobber'));
  assert.ok(repairWorkflow.includes('branches: [main]'));
  assert.ok(repairWorkflow.includes('.github/workflows/repair-release-assets.yml'));
});
