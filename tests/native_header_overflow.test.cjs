'use strict';
const assert = require('node:assert/strict');
const {test} = require('node:test');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {html, styles, scripts} = require('./native_header_overflow.browser.cjs');
const render = file => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support', file)], {encoding: 'utf8'});
test('native header fixture renders actual translated controls and fixed-width selector', () => {
  const header = render('NativeHeaderFixture.php');
  assert.equal((header.match(/id="header"/g) || []).length, 1);
  assert.equal((header.match(/id="company-context-select"/g) || []).length, 1);
  assert.match(header, /data-width="230px"/);
  assert.match(header, /Empresa sintética CI/);
  assert.match(header, /id="top_search"/);
  assert.match(header, /id="search_input"/);
  assert.match(header, /class="mobile-menu /);
  assert.doesNotMatch(header, /<script|<style/);
});
test('URL fixture renders real Asaas groups and no credential field', () => {
  const summary = render('AsaasUrlsFixture.php');
  assert.equal((summary.match(/class="input-group"/g) || []).length, 2);
  assert.match(summary, /URLs da API por ambiente/);
  assert.match(summary, /Homologação/);
  assert.match(summary, /type="url" readonly/);
  assert.doesNotMatch(summary, /type="password"|access_token|api_key/);
});
test('fixture keeps native asset order and loads real selectpicker', () => {
  assert.ok(styles.indexOf('assets/builds/tailwind.css') < styles.indexOf('assets/css/style.min.css'));
  assert.deepEqual(scripts, ['assets/plugins/jquery/jquery.js', 'assets/plugins/bootstrap/js/bootstrap.js', 'assets/plugins/bootstrap-select/js/bootstrap-select.js']);
  const page = html('<div id="synthetic-header"></div>', '<div id="synthetic-summary"></div>');
  assert.match(page, /col-md-8 col-lg-9/);
  assert.match(page, /id="settings-form"/);
  assert.match(page, /id="synthetic-header"/);
  assert.match(page, /id="synthetic-summary"/);
  assert.doesNotMatch(page, /overflow[^;\n]*hidden/);
});
test('diagnostic runner measures all isolated cases before enforcing unchanged page overflow gate', () => {
  const runner = fs.readFileSync(__dirname + '/native_header_overflow.browser.cjs', 'utf8');
  assert.match(runner, /\['header', 'urls', 'combined'\]/);
  assert.match(runner, /layout\.pageWidth > width \+ 1/);
  assert.match(runner, /assert\.deepEqual\(failures, \[\]/);
  assert.ok(runner.indexOf("'coverage.json'") < runner.indexOf('assert.deepEqual(failures'));
});
