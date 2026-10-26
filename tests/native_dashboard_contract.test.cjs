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
 assert.match(charts,/private function displayCurrency\(int \$issuer,int \$currency\)/);assert.match(charts,/company_currency_snapshot_required/);assert.match(charts,/SELECT id FROM '\.\$this->p\.'invoices WHERE issuer_id=\? AND currency=\?/,'missing snapshot only falls back when this issuer has no invoice in that currency');assert.match(charts,/catalog_display_only/);
});
test('request seal and pre-emission authorization retained',()=>{
 const c=read('application/controllers/admin/Dashboard.php');assert.match(c,/CompanyRequestScope::nativeFor\(\$this\)/);assert.match(c,/\$presenter->confirm\(\)/);assert.match(c,/\$reader->confirm\(\)/);assert.match(c,/Cache-Control: no-store/);
 const nativeRender=c.split('private function renderCompanyDashboard(): void')[1].split('private function companyPaymentChart')[0];assert.match(nativeRender,/NativeCompanyAdminPresentation::label\('dashboard_string'\)/);assert.doesNotMatch(nativeRender,/_l\(/,'isolated dashboard must not call global translation helpers that are intentionally not loaded');
 assert.match(c,/preg_match\('\/\^\[a-z\]\[a-z0-9_\]\{0,99\}\$\/D', \$reason\)/);assert.match(c,/error_log\(\$safeMessage\)/);
 const preview=c.indexOf('AdminDatabaseUpgrade::peekContextSuccess($this');const render=c.indexOf("$this->load->view('admin/dashboard/dashboard',$data,true)");const confirm=c.indexOf('$presenter->confirm()');const consume=c.indexOf('AdminDatabaseUpgrade::consumeContextSuccess($this');assert.ok(preview>=0&&render>preview&&confirm>render&&consume>confirm,'upgrade feedback remains on the native Dashboard path and consumes only after render plus scope recheck');
 const p=read('application/services/CompanyDashboardPresenter.php');assert.match(p,/company_dashboard_schema_unavailable/);assert.match(p,/company_dashboard_stale/);
});

test('original dashboard alerts use issuer-scoped announcements and POST dismissal in native requests',()=>{
 const controller=read('application/controllers/admin/Dashboard.php');assert.match(controller,/\$auxiliary->dashboardAnnouncements\(\)/);assert.match(controller,/company_dashboard_can_edit_announcements/);
 assert.match(controller,/\$this->load->helper\(\['func','widgets','invoices','estimates','proposals','sales'\]\)/);
 const service=read('application/services/NativeCompanyAuxiliaryUi.php');assert.match(service,/function dashboardAnnouncements\(int \$limit=100\)/);assert.match(service,/\$this->owned->dashboardAnnouncements\(\$limit\)/);assert.match(service,/\$this->legacy->dashboardAnnouncements\(\$limit\)/);
 const view=read('application/views/admin/includes/alerts.php');assert.match(view,/CompanyRequestScope::nativeFor\(get_instance\(\)\)/);assert.match(view,/\$nativeCompanyDashboard\?\(is_array\(\$company_dashboard_announcements/);assert.match(view,/form_open\(CompanyRequestScope::adminUrl\(get_instance\(\),'misc\/dismiss_announcement/);assert.match(view,/if\(!\$nativeCompanyDashboard\)\{hooks\(\)->do_action/);
 const routes=read('application/services/NativeCompanyRoutes.php');assert.match(routes,/'misc'\s*=>\s*\[\s*'dismiss_announcement'=>\['POST'\]/);
});

test('isolated native shell loads shared view helpers and scoped PT-BR labels without forcing HTML responses',()=>{
 const admin=read('application/core/AdminController.php');assert.match(admin,/load->helper\(\['admin', 'general', 'app_branding'\]\)/);
 const presentation=read('application/services/NativeCompanyAdminPresentation.php');assert.match(presentation,/\$ci->lang->language=array_merge\(\$ci->lang->language,self::\$language\)/);
 const general=read('application/helpers/general_helper.php');assert.match(general,/\$nativeIsolatedRequest=.*CompanyRequestScope/s);assert.match(general,/if\(!\$nativeIsolatedRequest\)header\('Content-Type: text\/html; charset=utf-8'\)/);
});
test('dashboard preferences stay actor + issuer bound and use native CSRF',()=>{
 const service=read('application/services/NativeCompanyDashboardPreferences.php');assert.match(service,/'company_dashboard:'\.\$this->issuer/);assert.match(service,/WHERE staff_id=\? AND meta_key=\?/);assert.ok(!service.includes('unserialize('));assert.match(service,/IssuerTransaction::run/);
 const c=read('application/controllers/admin/Staff.php');assert.match(c,/CompanyRequestScope::assertNativeMutation\(\$this\)/);
});

test('in-progress widget never aliases historically active projects',()=>{const widget=fs.readFileSync('application/views/admin/dashboard/widgets/top_stats.php','utf8');assert.match(widget,/\$total_projects_in_progress=\(\$counts\['in_progress'\]\?\?0\)\+\(\$counts\['legacy_2'\]\?\?0\)/);assert.doesNotMatch(widget,/\$total_projects_in_progress=\$counts\['active'\]/);});
