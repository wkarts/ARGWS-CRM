'use strict';
// Real delivered CSS/navigation in Chromium; no application, database or provider.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const {measureNativeMobileLayout} = require('./helpers/native_mobile_layout.cjs');
const root = path.resolve(__dirname, '..');

(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage({viewport: {width: 1360, height: 980}});
    await page.route('**/*', route => route.abort());
    await page.setContent('<body class="admin native-company-admin"><div id="header"></div><aside id="menu"><ul id="side-menu"></ul></aside><div id="wrapper"><div class="content"><div class="company-settings-card"><section class="company-settings-card purchase-card"><div class="purchase-grid"><label>Código do fornecedor<input class="form-control"></label></div></section></div></div></div></body>');
    for (const asset of ['assets/builds/vendor-admin.css', 'assets/css/style.min.css', 'assets/css/native-operational.css']) {
      await page.addStyleTag({path: path.join(root, asset)});
    }
    const purchaseView = fs.readFileSync(path.join(root, 'application/views/admin/expenses/native_purchases.php'), 'utf8');
    for (const style of purchaseView.matchAll(/<style>(.*?)<\/style>/gs)) await page.addStyleTag({content: style[1]});
    for (const asset of ['assets/plugins/jquery/jquery.min.js', 'assets/plugins/metisMenu/metisMenu.min.js', 'assets/js/native-admin-navigation.js']) {
      await page.addScriptTag({path: path.join(root, asset)});
    }
    await page.evaluate(() => NativeAdminNavigation.init({isMobile: () => false}));
    for (const width of [390, 320, 390, 320]) {
      await page.setViewportSize({width: 1360, height: 980});
      await page.waitForFunction(() => !document.body.classList.contains('page-small'));
      await page.setViewportSize({width, height: 844});
      const geometry = await measureNativeMobileLayout(page);
      assert.equal(geometry.width, width);
      assert(geometry.scrollWidth <= geometry.width + 1, JSON.stringify(geometry));
    }
    // A genuinely overflowing fixture must remain visible to the original assertion.
    await page.evaluate(() => {
      const wide = document.createElement('div');
      wide.id = 'synthetic-real-overflow';
      wide.style.width = '1000px';
      wide.style.height = '20px';
      document.body.append(wide);
    });
    const overflow = await measureNativeMobileLayout(page);
    assert(overflow.scrollWidth >= 1000);
    assert(overflow.overflowing.some(element => element.id === 'synthetic-real-overflow'));
    assert.throws(() => assert(overflow.scrollWidth <= overflow.width + 1), assert.AssertionError);
    const missing = await browser.newPage({viewport: {width: 390, height: 844}});
    await missing.setContent('<body>Missing native resize handler</body>');
    await assert.rejects(measureNativeMobileLayout(missing, 200), /Timeout/);
    console.log('Native mobile measurement PASS: desktop/mobile resize at 390/320; real overflow retained; absent handler rejected.');
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error.stack); process.exitCode = 1;});
