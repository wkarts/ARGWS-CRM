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
  assert.match(runner, /\['header', 'urls', 'combined', 'mobile-long-name'\]/);
  assert.match(runner, /layout\.pageWidth > width \+ 1/);
  assert.match(runner, /assert\.deepEqual\(failures, \[\]/);
  assert.ok(runner.indexOf("'coverage.json'") < runner.indexOf('assert.deepEqual(failures'));
});
test('native source and shipped bundle use the measured header height for wrapper offsets', () => {
  const vm = require('node:vm');
  for (const file of ['main.js', 'main.min.js']) {
    const source = fs.readFileSync(path.join(__dirname, '../assets/js', file), 'utf8');
    const start = source.indexOf('function mainWrapperHeightFix()');
    const rest = source.slice(start, source.indexOf('function set_body_small()', start));
    const code = rest.slice(0, rest.lastIndexOf('}') + 1);
    for (const [height, expected] of [[114, 886], [57, 943], [0, 943]]) {
      const writes = [];
      const box = name => ({height: () => name === 'sidebar' ? 600 : 1000, css: (property, value) => writes.push({name, property, value})});
      const document = {}, window = {}, wrapper = box('wrapper');
      const context = {document, window, side_bar: box('sidebar'), setup_menu: box('setup'), content_wrapper: wrapper, is_mobile: () => false, isRTL: 'false',
        $: target => target === '#header' ? {outerHeight: () => height} : target === document ? {outerHeight: () => 1000} : target === window ? {height: () => 1000} : target === '#wrapper' ? {find: () => ({height: () => 800})} : box('menu')};
      vm.runInNewContext(code + ';mainWrapperHeightFix();', context);
      assert.equal(writes.filter(write => write.name === 'wrapper' && write.property === 'min-height').at(-1).value, expected + 'px', file + ': wrapper follows current header');
      assert.equal(writes.find(write => write.name === 'setup').value, (1000 - 2 * (height || 57)) + 'px');
    }
  }
});
test('responsive header rules are scoped and source/built styles remain aligned', () => {
  const source = fs.readFileSync(__dirname + '/../assets/css/style.css', 'utf8');
  const bundled = fs.readFileSync(__dirname + '/../assets/css/style.min.css', 'utf8');
  const rules = source.slice(source.indexOf('/* A scoped company selector'));
  assert.ok(bundled.endsWith(rules));
  assert.match(rules, /max-width: 1199px/);
  assert.match(rules, /#header\.native-company-header ~ #setup-menu-wrapper/);
  assert.match(rules, /#header\.native-company-header ~ #menu/);
  assert.match(rules, /\.bootstrap-select\.company-context-select \.dropdown-menu/);
  assert.doesNotMatch(rules, /overflow[^;]*hidden|display:\s*none/);
});
test('optional full shell, long names and mobile controls render without changing the default fixture', () => {
  const shell = execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support', 'NativeHeaderFixture.php'), '--full-shell', '--long-name', '--mobile-user'], {encoding: 'utf8'});
  for (const id of ['header', 'company-context-select', 'menu', 'setup-menu-wrapper', 'setup-menu']) assert.equal((shell.match(new RegExp('id="' + id + '"', 'g')) || []).length, 1);
  assert.match(shell, /company-context-select/);
  assert.match(shell, /native-company-header/);
  assert.match(shell, /sidebar-user-profile/);
  assert.match(shell, /selectpicker company-context-select/);
});
