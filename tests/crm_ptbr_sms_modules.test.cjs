const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const ROOT = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(ROOT, relative), 'utf8');
const json = (relative) => JSON.parse(read(relative));

const definitions = json('application/services/email_templates_ptbr/templates_modules.json');
const translations = json('application/services/email_templates_ptbr/phrases_modules.json');

function htmlTokens(value) {
  return value.match(/<[^>]*>/g) || [];
}
function mergeTokens(value) {
  return value.match(/\{[^{}]+\}/g) || [];
}
function translate(value) {
  return value.split(/(<[^>]*>)/g).map((piece) => {
    if (!piece || piece[0] === '<') return piece;
    const vars = mergeTokens(piece);
    const key = piece.replace(/\{[^{}]+\}/g, '%s')
      .replace(/&nbsp;|&#160;/gi, ' ')
      .replace(/\s+/g, ' ').trim();
    if (!key || !/[\p{L}]/u.test(key.replaceAll('%s', ''))) return piece;
    assert.ok(Object.hasOwn(translations, key) ||
      ['phrases_01.json','phrases_02.json','phrases_03.json']
        .some(p => Object.hasOwn(json('application/services/email_templates_ptbr/'+p),key)),
        'Falta frase traduzida: ' + key);
    const dictionaries = ['phrases_01.json','phrases_02.json','phrases_03.json'];
    const base = Object.assign({}, ...dictionaries.map(p=>json('application/services/email_templates_ptbr/'+p)));
    const translated = (translations[key] ?? base[key]);
    assert.equal((translated.match(/%s/g) || []).length, vars.length);
    let i = 0;
    return (piece.match(/^\s*/)?.[0] || '') +
      translated.replace(/%s/g, () => vars[i++]) +
      (piece.match(/\s*$/)?.[0] || '');
  }).join('');
}

test('todos os modelos dos módulos mapeados são PT-BR e preservam HTML e variáveis', () => {
  assert.equal(Object.keys(definitions).length, 19);
  assert.equal(Object.values(definitions).filter(d => d.module === 'purchase').length, 11);
  assert.equal(Object.values(definitions).filter(d => d.module === 'products').length, 2);
  assert.equal(Object.values(definitions).filter(d => d.module === 'timesheets').length, 4);
  assert.equal(Object.values(definitions).filter(d => d.module === 'invoices_builder').length, 1);
  assert.equal(Object.values(definitions).filter(d => d.module === 'webhooks').length, 1);
  for (const [slug, model] of Object.entries(definitions)) {
    assert.ok(model.name && model.subject && model.source_name && model.source_subject, slug);
    assert.notEqual(model.name, model.source_name, slug);
    assert.deepEqual(mergeTokens(model.subject), mergeTokens(model.source_subject), slug);
    const original = model.source_message || '';
    const localized = translate(original);
    assert.deepEqual(htmlTokens(localized), htmlTokens(original), slug);
    assert.deepEqual(mergeTokens(localized), mergeTokens(original), slug);
    if (original.trim()) assert.notEqual(localized, original, 'Não traduzido: ' + slug);
    else assert.equal(slug, 'webhook-failed', 'Somente o webhook pode ter corpo vazio.');
  }
});

test('módulos antigos e novos usam PT-BR e reconciliação sem apagar personalizações', () => {
  const service = read('application/services/EmailTemplatesPtBr.php');
  const controller = read('application/controllers/admin/Emails.php');
  const modules = read('application/libraries/App_modules.php');
  const migration = read('application/migrations/371_version_371.php');
  assert.match(service, /function synchronizeModuleTemplates\(/);
  assert.match(service, /function defaultOnlyChanges\(/);
  assert.match(service, /if \(\$current !== \$source\)/);
  assert.match(service, /\$copy\['language'\] = 'portuguese_br'/);
  assert.match(service, /unset\(\$copy\['emailtemplateid'\]\)/);
  assert.doesNotMatch(service, /->delete\(|TRUNCATE|DROP TABLE/);
  assert.match(controller, /EmailTemplatesPtBr::synchronizeModuleTemplates\(\$this->db\)/);
  assert.match(modules, /EmailTemplatesPtBr::synchronizeModuleTemplates\(\$this->ci->db, \$name\)/);
  assert.match(migration, /class Migration_Version_371 extends CI_Migration/);
  assert.match(migration, /EmailTemplatesPtBr::synchronizeModuleTemplates\(\$this->db\)/);
  assert.doesNotMatch(migration, /->delete\(|TRUNCATE|DROP TABLE/);

  assert.match(read('modules/products/products.php'), /'language' => 'portuguese_br'/);
  assert.match(read('modules/purchase/helpers/purchase_helper.php'), /'language' => 'portuguese_br'/);
  assert.match(read('modules/purchase/purchase.php'), /function update_email_lang_for_vendor[\s\S]*return 'portuguese_br'/);
  assert.match(read('modules/webhooks/webhooks.php'), /'language' => 'portuguese_br'/);
  assert.match(read('application/helpers/email_templates_helper.php'), /EmailTemplatesPtBr::get/);
});

test('o rótulo é LGPD e o item de configurações é somente Plataforma', () => {
  const labels = read('application/language/portuguese_br/portuguese_br_lang.php');
  assert.match(labels, /\$lang\['gdpr_short'\]\s*=\s*'LGPD'/);
  assert.match(labels, /\$lang\['gdpr'\]\s*=\s*'Lei Geral de Proteção de Dados \(LGPD\)'/);
  const settings = read('application/views/admin/settings/all.php');
  assert.match(settings, /<span>Plataforma<\/span>/);
  assert.doesNotMatch(settings, /Plataforma ARGWS/);
});

test('os gatilhos existentes mantêm IDs e mensagens; novos são desativados por padrão', () => {
  const sms = read('application/libraries/sms/App_sms.php');
  assert.match(sms, /function trigger_enabled_option_name/);
  assert.match(sms, /function is_trigger_enabled/);
  assert.match(sms, /'default_enabled'\] = false/);
  assert.match(sms, /'sms_trigger_' \. \$trigger/);
  assert.match(sms, /trim\(\(string\) \$this->get_trigger_value\(\$trigger\)\) !== ''/);
  assert.match(sms, /\$stored === '0'/);
  assert.doesNotMatch(sms, /'info'\s*=>\s*'Trigger when/i);
  assert.equal((sms.match(/'default_message' =>/g) || []).length, 10);
  assert.equal((sms.match(/'group' =>/g) || []).length, 10);
  assert.equal((sms.match(/'default_enabled'\] = false/g) || []).length, 1);

  const ui = read('application/views/admin/settings/includes/sms.php');
  assert.match(ui, /type="hidden" name="settings\[/);
  assert.match(ui, /type="checkbox"/);
  assert.match(ui, /html_escape\(\(string\) \(\$trigger_opts\['value'\] \?\? ''\)\)/);
  assert.match(ui, /sms-use-default/);
  assert.match(ui, /Ver variáveis disponíveis/);
  assert.match(ui, /Desativar um gatilho não/);
});

test('novos 10 eventos correspondem a hooks existentes e usam Connect|API', () => {
  const events = read('application/helpers/sms_events_helper.php');
  const helper = read('application/helpers/sms_helper.php');
  const triggers = [
    ['contact_created','contact_created_notice'],
    ['lead_created','lead_created_notice'],
    ['ticket_created','ticket_created_notice'],
    ['estimate_sent','estimate_sent_notice'],
    ['proposal_sent','proposal_sent_notice'],
    ['proposal_accepted','proposal_accepted_notice'],
    ['task_assignee_added','task_assignee_added_notice'],
    ['task_status_changed','task_completed_notice'],
    ['project_status_changed','project_finished_notice'],
    ['invoice_marked_as_cancelled','invoice_cancelled_notice'],
  ];
  assert.equal((events.match(/hooks\(\)->add_action\(/g)||[]).length, 10);
  for (const [hook, trigger] of triggers) {
    assert.ok(events.includes("hooks()->add_action('"+hook+"'"), hook);
    assert.ok(events.includes("'"+trigger+"'"), trigger);
  }
  assert.match(events, /if \(!\$CI->app_sms->get_active_gateway\(\) \|\| !\$CI->app_sms->is_trigger_active\(\$trigger\)\)/);
  assert.match(events, /'task_assigned'/);
  assert.match(events, /staffid/);
  assert.match(events, /sms_notice_dispatch/);
  assert.match(helper, /helpers\/sms_events_helper\.php/);
  assert.doesNotMatch(events, /twilio|clickatell|msg91/);
});
