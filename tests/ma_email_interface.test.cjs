const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('MA uses the shared label dictionary without translating merge-field keys', () => {
  for (const file of ['modules/ma/views/settings/email_templates/email_template_design.php', 'modules/ma/views/channels/emails/email_design.php']) {
    const source = read(file);
    assert.ok(source.includes('EmailTemplateLocale::groupLabel'));
    assert.ok(source.includes('EmailTemplateLocale::fieldLabel'));
    assert.ok(source.includes("html_escape($_field['key'])"));
    assert.ok(!source.includes('ucfirst($key)'));
  }
});
test('preview chooses the configured language visually and labels persisted languages accurately', () => {
  for (const file of ['modules/ma/views/settings/email_templates/email_template_detail.php', 'modules/ma/views/channels/emails/email_detail.php']) {
    const source = read(file);
    assert.ok(source.includes('ma_email_preview_key('));
    assert.ok(source.includes('$key === $previewDesignKey'));
    assert.ok(source.includes('EmailTemplateLocale::languageLabel'));
    assert.ok(!source.includes("ucfirst($design['language'])"));
  }
  const controller = read('modules/ma/controllers/Ma.php');
  const start = controller.indexOf('public function get_email_template_preview(');
  const preview = controller.slice(start, controller.indexOf('    /**', start));
  assert.ok(preview.includes('ma_email_preview_key('));
  assert.ok(preview.includes('echo $html;'));
  assert.ok(!preview.includes('echo html_entity_decode($html)'));
});
test('Unlayer interface locale is PT-BR without changing stored design language', () => {
  for (const file of ['modules/ma/assets/js/emails/email_template_design_js.php', 'modules/ma/assets/js/channels/email_design_js.php']) {
    const source = read(file);
    assert.ok(source.includes("locale: 'pt-BR'"));
    assert.ok(source.includes('JSON.parse(JSON.parse('));
    assert.ok(!source.includes('setCurrentLanguage'));
  }
});
test('fresh seed is prefix-safe and activation does not repair user-deleted variants', () => {
  const source = read('modules/ma/install.php');
  assert.ok(source.includes('MaEmailTemplateDefaults::install($CI->db, db_prefix())'));
  assert.ok(!source.includes("query(file_get_contents(FCPATH . 'modules/ma/database/email_template_default.sql'))"));
  assert.ok(!source.includes('MaEmailTemplateDefaults::upgrade('));
  assert.ok(source.indexOf('MaEmailTemplateDefaults::install(') > source.indexOf('CREATE TABLE \' . db_prefix() . "ma_email_template_designs'));
});
test('library defaults do not pretend to own campaign subjects or image translations', () => {
  const catalogue = JSON.parse(read('modules/ma/resources/email_templates_ptbr.json'));
  assert.equal(catalogue.templates.length, 7);
  for (const item of catalogue.templates) {
    assert.equal(item.source.language, 'english');
    assert.equal(item.target.language, 'portuguese_br');
    assert.equal(item.target.description, '');
    assert.ok(!Object.hasOwn(item.target, 'subject'));
    assert.equal(typeof JSON.parse(item.target.data_html), 'string');
    assert.equal(typeof JSON.parse(JSON.parse(item.target.data_design)), 'object');
    const urls = html => [...JSON.parse(html).matchAll(/https?:\/\/[^\s<>"']+/g)].map(match => match[0]);
    assert.deepEqual(urls(item.target.data_html), urls(item.source.data_html));
  }
});
