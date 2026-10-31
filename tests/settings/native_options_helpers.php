<?php
require __DIR__ . '/native_settings.php';
require APPPATH . 'helpers/settings_helper.php';
require APPPATH . 'libraries/App.php';
require APPPATH . 'helpers/admin_helper.php';
$fresh = essentialFixture('1.0.2', 1); $d = $fresh->db; $prefix = $fresh->prefix;
IssuerCore::reconcile($d, $prefix); IssuerCore::bindInstallationMaster($d, $prefix, 7);
$d->query("INSERT INTO {$prefix}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(41,'Company A','12345678000195',1,'{}',7,'2026-10-18','complete'),(82,'Company B','52998224725',1,'{}',7,'2026-10-18','complete')");
$d->query("UPDATE {$prefix}options SET value='synthetic-global-secret' WHERE name='smtp_password'");
function get_staff_user_id() { return 7; }
$app = (new ReflectionClass(App::class))->newInstanceWithoutConstructor();
CompanySettingsRuntime::run($d,$prefix,41,7,function()use($app){
    essentialCheck(get_option('smtp_password') === '', 'native helper does not inherit global secret');
    essentialCheck(staff_can('edit','settings'), 'proven master has contextual rights');
    essentialCheck(is_admin(), 'proven master retains admin-only controls');
    essentialCheck(!staff_can('edit', null), 'context requires explicit resource');
    essentialCheck(add_option('smtp_password','synthetic-company-a'), 'native add writes company A');
    essentialCheck(!add_option('smtp_password','overwrite'), 'native add preserves existing option');
    essentialCheck($app->get_option('smtp_password') === 'synthetic-company-a', 'direct App getter uses scope');
    essentialCheck(($app->get_options()['smtp_password'] ?? null) === 'synthetic-company-a', 'App snapshot does not use global cache');
    essentialCheck(update_option('companyname','Renamed A'), 'native identity write updates canonical record');
    essentialCheck(get_option('invoice_company_name') === 'Renamed A', 'identity aliases share canonical value');
});
$d->query("INSERT INTO {$prefix}staff VALUES(19,1,1,NULL)");
CompanySettingsRuntime::run($d,$prefix,82,7,function()use($app){
    essentialCheck(!is_admin(19), 'ordinary global administrator cannot bypass contextual ACL via is_admin');
    essentialCheck(!staff_can('edit','settings',19), 'ordinary global admin without membership cannot bypass context');
    essentialCheck(!option_exists('smtp_password'), 'company B has independent existence');
    essentialCheck($app->get_option('smtp_password') === '', 'reused App does not leak company A cache');
    essentialCheck(!isset($app->get_options()['smtp_password']), 'B snapshot excludes foreign secret');
    essentialCheck(add_option('smtp_password','synthetic-b'), 'B writes its own option');
    essentialCheck(delete_option('smtp_password'), 'B deletion scoped');
});
CompanySettingsRuntime::run($d,$prefix,41,7,function(){ essentialCheck(get_option('smtp_password') === 'synthetic-company-a', 'B delete preserves A'); });
essentialCheck((string)$d->query("SELECT value FROM {$prefix}options WHERE name='smtp_password'")->row()->value === 'synthetic-global-secret','global legacy secret unchanged');
$reflection = new ReflectionClass(App::class);
$reflection->getProperty('ci')->setValue($app, $fresh->ci);
$reflection->getProperty('options')->setValue($app, ['companyname'=>'stale cached identity','invoice_company_name'=>'stale cached identity']);
$primaryId=(new IssuerResourceAccess($d,$prefix))->primaryId();
(new CompanySettingsIdentity($d,$prefix))->save($primaryId,7,['legal_name'=>'Canonical principal']);
essentialCheck($app->get_option('companyname')==='Canonical principal','legacy principal read does not reuse stale identity cache');
essentialCheck($app->get_options()['invoice_company_name']==='Canonical principal','legacy option snapshot shares canonical fiscal identity');
echo 'Native company option helpers: ' . $GLOBALS['essentialChecks'] . ' total checks passed.' . PHP_EOL;
