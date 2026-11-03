'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('official Settings admits the complete native estimate and proposal forms', () => {
  const forms = read('application/services/NativeCompanySettingsForms.php');
  assert.match(forms, /\['general','company','localization','certificates','email','sales_general','invoices','estimates','proposals','clients'/);
  assert.match(forms, /'estimates','proposals'=>NativeCompanySalesSettings::fields\(\$group\)/);
  assert.match(forms, /in_array\(\$group,\['estimates','proposals'\],true\)/);

  const controller = read('application/services/NativeCompanySettingsController.php');
  assert.match(controller, /native_settings_estimate_values/);
  assert.match(controller, /native_settings_proposal_values/);
  assert.match(controller, /definitionsForSalesSettings/);

  for (const view of ['estimates.php', 'proposals.php']) {
    const source = read(`application/views/admin/settings/includes/${view}`);
    assert.match(source, /native_settings_/);
    assert.match(source, /settings\[/);
  }
});

test('sales settings preserve issuer-owned sequence and revision contracts', () => {
  const settings = read('application/services/NativeCompanySalesSettings.php');
  assert.match(settings, /NativeCompanySalesSequencePreferences/);
  assert.match(settings, /saveInOwnedTransaction/);
  assert.match(settings, /native_sales_settings_stale/);
  assert.match(settings, /public static function validate\(/);
  assert.match(settings, /self::validate\(\$group,\$values\)/);
});
