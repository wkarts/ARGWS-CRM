'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('estimates and proposals use the official sales adapter for the main flows', () => {
  const adapter = read('application/services/NativeCompanySalesAdminHttp.php');
  assert.match(adapter, /public function index\(string \$kind/);
  assert.match(adapter, /\['edit','copy','review_copy'\]/);
  assert.match(adapter, /\['accepted','declined','cancelled'\]/);
  assert.match(adapter, /CompanySalesExtrasHttp::post/);
  assert.match(adapter, /CompanySalesExtrasHttp::download/);

  for (const [controller, kind, list, form] of [
    ['Estimates.php', 'estimates', 'list_estimates', 'estimate'],
    ['Proposals.php', 'proposals', 'list_proposals', 'proposal'],
  ]) {
    const source = read(`application/controllers/admin/${controller}`);
    assert.match(source, /if \(CompanyRequestScope::nativeFor\(\$this\)\)/);
    assert.match(source, new RegExp(`NativeCompanySalesAdminHttp\\(\\$this\\)\\)->index\\('${kind}'`));
    assert.match(source, new RegExp(`public function ${list}\\(`));
    assert.match(source, new RegExp(`public function ${form}\\(`));
    assert.match(source, /public function pdf\(/);
  }
});

test('sales routes admit only the adapter-owned verbs and attest the principal path', () => {
  const routes = read('application/services/NativeCompanyRoutes.php');
  for (const [controller, list] of [['estimates', 'list_estimates'], ['proposals', 'list_proposals']]) {
    assert.match(routes, new RegExp(`'${controller}'\\s*=>`));
    assert.match(routes, new RegExp(`'${list}'\\s*=>\\['GET','HEAD','POST'\\]`));
    assert.match(routes, new RegExp(`'${controller}'\\s*=>[\\s\\S]*?'pdf'\\s*=>\\['GET','HEAD'\\]`));
  }
  const scope = read('application/services/CompanyRequestScope.php');
  assert.match(scope, /\$sharedOwnedSales\s*=\s*\$native/);
  assert.match(scope, /native_sales_method_invalid/);
});
