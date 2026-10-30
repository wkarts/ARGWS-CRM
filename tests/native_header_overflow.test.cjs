'use strict';
const assert = require('node:assert/strict');
const {test} = require('node:test');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {html, styles, scripts} = require('./native_header_overflow.browser.cjs');
const assetRoot=process.env.CRM_TEST_ROOT ? path.resolve(process.env.CRM_TEST_ROOT) : path.resolve(__dirname,'..');
const phpBinary = process.env.PHP_BIN || 'php';
const phpAvailable = (() => {
  try { execFileSync(phpBinary, ['-v'], {stdio: 'ignore'}); return true; }
  catch { return false; }
})();
const render = file => execFileSync(phpBinary, [path.join(__dirname, 'support', file)], {encoding: 'utf8'});
const phpTest = (name, fn) => test(name, {skip: !phpAvailable && 'PHP runtime unavailable in this workspace'}, fn);
phpTest('native header renders actual translated controls without a company selector', () => {
  const header = render('NativeHeaderFixture.php');
  assert.equal((header.match(/id="header"/g) || []).length, 1);
  assert.equal((header.match(/id="company-context-select"/g) || []).length, 0);
  assert.doesNotMatch(header, /company-selector/);
  assert.match(header, /id="top_search"/);
  assert.match(header, /id="search_input"/);
  assert.match(header, /class="mobile-menu /);
  assert.doesNotMatch(header, /<script|<style/);
});
phpTest('URL fixture renders real Asaas groups and no credential field', () => {
  const summary = render('AsaasUrlsFixture.php');
  assert.equal((summary.match(/class="input-group"/g) || []).length, 2);
  assert.match(summary, /data-asaas-settings-section="authentication"/);
  for (const [profile, endpoint] of [['production', 'https://api.asaas.com/v3'], ['sandbox', 'https://api-sandbox.asaas.com/v3']]) {
    assert.ok(summary.includes('data-asaas-profile-fragment="' + profile + '"'));
    assert.ok(summary.includes('id="asaas-api-url-' + profile + '" class="form-control" type="url" readonly value="' + endpoint + '"'));
    assert.ok(summary.includes('data-asaas-copy="asaas-api-url-' + profile + '"'));
  }
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
  const shared=fs.readFileSync(path.join(assetRoot,'assets/js/native-admin-tables.js'),'utf8');
  const start=shared.indexOf('function mainWrapperHeightFix()'),end=shared.indexOf('function initDataTableInline(',start);
  assert.ok(start>=0&&end>start,'shared function boundaries exist');
  const code=shared.slice(start,end).trim();
  assert.ok(code,'real delivered shared wrapper function must exist');
  assert.match(code,/NativeAdminNavigation\.height/);
  const helper=fs.readFileSync(path.join(assetRoot,'application/helpers/assets_helper.php'),'utf8');
  assert.match(helper,/'assets\/js\/native-admin-tables\.js'/);
  for (const file of ['main.js', 'main.min.js']) {
    const source = fs.readFileSync(path.join(assetRoot, 'assets/js', file), 'utf8');
    assert.doesNotMatch(source,/function\s+mainWrapperHeightFix\s*\(/,'main must use the delivered single shared definition');
    assert.match(source,/mainWrapperHeightFix\(/,'real main still invokes shared geometry');
    for (const [height, expected] of [[114, 886], [57, 943], [0, 943]]) {
      const writes = [];
      const box = name => ({height: () => name === 'sidebar' ? 600 : 1000, css: (property, value) => writes.push({name, property, value})});
      const document = {}, window = {}, wrapper = box('wrapper');
      const context = {document, window, side_bar: box('sidebar'), setup_menu: box('setup'), content_wrapper: wrapper, is_mobile: () => false, isRTL: 'false',
        $: target => target === '#header' ? {outerHeight: () => height} : target === document ? {outerHeight: () => 1000} : target === window ? {height: () => 1000} : target === '#wrapper' ? {...wrapper,find: () => ({height: () => 800})} : target === '#side-menu' ? box('sidebar') : target === '#setup-menu-wrapper' ? box('setup') : box('menu')};
      if(code.includes('NativeAdminNavigation.height')) {
        window.jQuery=context.$;window.NativeDevice={isMobile:()=>false};
        vm.runInNewContext(fs.readFileSync(path.join(assetRoot,'assets/js/native-admin-navigation.js'),'utf8'),context);
        context.NativeAdminNavigation=window.NativeAdminNavigation;
      }
      vm.runInNewContext(code + ';mainWrapperHeightFix();', context);
      assert.equal(writes.filter(write => write.name === 'wrapper' && write.property === 'min-height').at(-1).value, expected + 'px', file + ': wrapper follows current header');
      assert.equal(writes.find(write => write.name === 'setup').value, (1000 - 2 * (height || 57)) + 'px');
    }
  }
});
test('responsive header rules are scoped and source/built styles remain aligned', () => {
  const source = fs.readFileSync(path.join(assetRoot,'assets/css/style.css'), 'utf8');
  const bundled = fs.readFileSync(path.join(assetRoot,'assets/css/style.min.css'), 'utf8');
  const rules = source.slice(source.indexOf('/* Company selection lives'));
  assert.ok(source.indexOf('/* Company selection lives')>=0,'scoped source rules exist');
  const canonical=css=>css.replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s*([{}:;,~])\s*/g,'$1').replace(/;}/g,'}').replace(/@media\s+\(/g,'@media(').trim();
  const expected=canonical(rules);
  assert.ok(expected.length>200,'all scoped geometry remains covered');
  assert.ok(canonical(bundled).includes(expected),'compiled CSS preserves every scoped selector, media condition and declaration after minification');
  assert.ok(!canonical(bundled).includes(expected.replace('height:57px','height:58px')),'geometry comparison does not accept altered measurements');
  assert.match(rules, /max-width: 1199px/);
  assert.match(rules, /#header\.native-company-header ~ #setup-menu-wrapper/);
  assert.match(rules, /#header\.native-company-header ~ #menu/);
  assert.match(rules, /\.bootstrap-select\.company-context-select \.dropdown-menu/);
  assert.doesNotMatch(rules, /overflow[^;]*hidden|display:\s*none/);
});
phpTest('optional full shell, long names and mobile controls render without changing the default fixture', () => {
  const shell = execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support', 'NativeHeaderFixture.php'), '--full-shell', '--long-name', '--mobile-user'], {encoding: 'utf8'});
  for (const id of ['header', 'company-selector-toggle', 'menu', 'setup-menu-wrapper', 'setup-menu']) assert.equal((shell.match(new RegExp('id="' + id + '"', 'g')) || []).length, 1);
  assert.match(shell, /data-company-current="1"/);
  assert.ok(shell.indexOf('data-company-selector-toggle') < shell.lastIndexOf('sidebar-user-profile'));
  assert.match(shell, /native-company-header/);
  assert.match(shell, /sidebar-user-profile/);
  assert.match(shell, /data-company-choice="1"/);
});
