'use strict';
// Fast presentation-only diagnosis: actual PHP views/assets, no DB, login or provider.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {overflowGeometry} = require('./companies/asaas_settings_real_browser.cjs');
const root = path.resolve(__dirname, '..');
const assetsRoot = fs.realpathSync(path.join(root, 'assets'));
const styles = ['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/builds/vendor-admin.css',
  'assets/plugins/font-awesome/css/fontawesome.min.css', 'assets/plugins/font-awesome/css/brands.min.css',
  'assets/plugins/font-awesome/css/solid.min.css', 'assets/plugins/font-awesome/css/regular.min.css',
  'assets/builds/tailwind.css', 'assets/css/style.min.css'];
const scripts = ['assets/plugins/jquery/jquery.js', 'assets/plugins/bootstrap/js/bootstrap.js', 'assets/plugins/bootstrap-select/js/bootstrap-select.js'];
function render(name) { return execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'support', name)], {encoding: 'utf8'}); }
function html(header, summary) {
  // Parent grid matches admin/settings/all.php. The tested header/selector and URL controls are rendered from their real PHP views.
  const content = summary ? '<div id="wrapper"><div class="content"><div class="row"><div class="col-xs-12 col-md-12 col-lg-10"><div class="row"><div class="col-md-4 col-lg-3"><h4>Configurações</h4></div><div class="col-md-8 col-lg-9"><h4>Meios de Pagamento</h4><form id="settings-form"><div class="panel_s"><div class="panel-body"><div id="online_payments_asaas_tab">' + summary + '</div></div></div></form></div></div></div></div></div></div>' : '';
  return '<!doctype html><html lang="pt-BR" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="http://fixture.test/">' + styles.map(file => '<link rel="stylesheet" href="' + file + '">').join('') + '</head><body class="admin">' + header + content + scripts.map(file => '<script src="' + file + '"></script>').join('') + '<script>$(function(){ $(".selectpicker").selectpicker(); if(innerWidth <= 768)document.body.classList.add("page-small"); });document.addEventListener("submit",function(event){event.preventDefault();});</script></body></html>';
}
async function run() {
  const {chromium} = require(process.env.ASAAS_PLAYWRIGHT_MODULE || 'playwright');
  const output = path.resolve(process.env.NATIVE_HEADER_ARTIFACTS || '/tmp/native-header-layout-evidence');
  fs.mkdirSync(output, {recursive: true});
  const header = render('NativeHeaderFixture.php'), summary = render('AsaasUrlsFixture.php');
  const browser = await chromium.launch({headless: true, ...(process.env.CHROMIUM_BIN ? {executablePath: process.env.CHROMIUM_BIN} : {})});
  const results = [], failures = [];
  let pageerrors = 0, blockedRequests = 0;
  try {
    for (const width of [1280, 390]) {
      const context = await browser.newContext({viewport: {width, height: 900}});
      try {
        await context.route('**/*', route => {
          const url = new URL(route.request().url()), rel = decodeURIComponent(url.pathname).replace(/^\//, ''), file = path.resolve(root, rel);
          if (route.request().method() !== 'GET' || url.origin !== 'http://fixture.test' || !rel.startsWith('assets/') || !file.startsWith(assetsRoot + path.sep) || !fs.existsSync(file) || !fs.realpathSync(file).startsWith(assetsRoot + path.sep)) {
            blockedRequests++; return route.abort();
          }
          return route.fulfill({body: fs.readFileSync(file), contentType: {'.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf'}[path.extname(file)] || 'application/octet-stream'});
        });
        const page = await context.newPage();
        page.on('pageerror', () => { pageerrors++; });
        for (const mode of ['header', 'urls', 'combined']) {
          await page.setContent(html(mode === 'urls' ? '' : header, mode === 'header' ? '' : summary), {waitUntil: 'load'});
          if (mode !== 'urls') await page.locator('.company-selector .bootstrap-select').waitFor({state: 'visible', timeout: 10000});
          if (width <= 768) await page.waitForFunction(() => document.body.classList.contains('page-small'), null, {timeout: 10000});
          await page.evaluate(() => document.fonts.ready);
          const layout = await page.evaluate('(' + overflowGeometry.toString() + ')(document, window)');
          const fontLoaded = await page.evaluate(() => Array.from(document.fonts).some(font => font.family.includes('Inter') && font.status === 'loaded'));
          const result = {mode, width, fontLoaded, ...layout};
          results.push(result);
          fs.writeFileSync(path.join(output, width + '-' + mode + '.json'), JSON.stringify(result, null, 2));
          await page.screenshot({path: path.join(output, width + '-' + mode + '.png'), fullPage: true});
          if (!fontLoaded || layout.pageWidth > width + 1) failures.push({mode, width, pageWidth: layout.pageWidth, fontLoaded});
        }
      } finally { await context.close(); }
    }
    fs.writeFileSync(path.join(output, 'coverage.json'), JSON.stringify({surface: 'synthetic actual native views', results, failures, pageerrors, blockedRequests}, null, 2));
    console.log(JSON.stringify({test: 'native-header-layout', failures, pageerrors, blockedRequests}));
    assert.equal(pageerrors, 0, 'actual view fixture has no page errors');
    assert.equal(blockedRequests, 0, 'fixture loads only bundled assets');
    assert.deepEqual(failures, [], 'native header and Asaas URL groups fit desktop/mobile without page overflow');
  } finally { await browser.close(); }
}
module.exports = {html, styles, scripts};
if (require.main === module) run().catch(() => { console.error('Native header layout failed; inspect geometry JSON and synthetic screenshots.'); process.exitCode = 1; });
