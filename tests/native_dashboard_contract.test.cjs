'use strict';
const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');
const root=path.resolve(__dirname,'..');const read=p=>fs.readFileSync(path.join(root,p),'utf8');
test('same dashboard controller and native thirteen partials; no alternative panel',()=>{
 const page=read('application/views/admin/dashboard/dashboard.php');
 for(const widget of ['top_stats','finance_overview','user_data','upcoming_events','calendar','payments_chart','todos','leads_chart','projects_chart','tickets_chart','projects_activity','contracts_expiring','tickets_report'])assert.ok(page.includes("'"+widget+"'"),widget);
 assert.ok(!page.includes('company_context'));assert.ok(!fs.existsSync(path.join(root,'application/views/admin/dashboard/widgets/_company_state.php')));
 assert.ok(!read('application/services/CompanyDashboardPresenter.php').includes('adapter_pending'));
});
test('native charts and financial template retain canonical APIs',()=>{
 const finance=read('application/views/admin/dashboard/widgets/finance_overview.php');assert.ok(finance.includes('admin/invoices/invoices_total_template'));
 const charts=read('application/services/CompanyDashboardPayments.php');assert.match(charts,/->payments\(\$issuer,\$actor/);assert.match(charts,/i\.issuer_id=\?/);assert.match(charts,/i\.issuer_id IS NULL/);assert.match(charts,/current_effective_local_receipts_by_original_receipt_date_not_bank_cashflow/);
});
test('request seal and pre-emission authorization retained',()=>{
 const c=read('application/controllers/admin/Dashboard.php');assert.match(c,/CompanyRequestScope::nativeFor\(\$this\)/);assert.match(c,/\$presenter->confirm\(\)/);assert.match(c,/\$reader->confirm\(\)/);assert.match(c,/Cache-Control: no-store/);
 const p=read('application/services/CompanyDashboardPresenter.php');assert.match(p,/company_dashboard_schema_unavailable/);assert.match(p,/company_dashboard_stale/);
});
test('dashboard preferences stay actor + issuer bound and use native CSRF',()=>{
 const service=read('application/services/NativeCompanyDashboardPreferences.php');assert.match(service,/'company_dashboard:'\.\$this->issuer/);assert.match(service,/WHERE staff_id=\? AND meta_key=\?/);assert.ok(!service.includes('unserialize('));assert.match(service,/IssuerTransaction::run/);
 const c=read('application/controllers/admin/Staff.php');assert.match(c,/CompanyRequestScope::assertNativeMutation\(\$this\)/);
});

test('in-progress widget never aliases historically active projects',()=>{const widget=fs.readFileSync('application/views/admin/dashboard/widgets/top_stats.php','utf8');assert.match(widget,/\$total_projects_in_progress=\(\$counts\['in_progress'\]\?\?0\)\+\(\$counts\['legacy_2'\]\?\?0\)/);assert.doesNotMatch(widget,/\$total_projects_in_progress=\$counts\['active'\]/);});
