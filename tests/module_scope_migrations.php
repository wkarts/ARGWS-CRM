<?php
/** Run separately for api, exports and einvoice because native migration class names are global. */
require __DIR__ . '/module_scope_migration_support.php';

$fresh = scopeFixture(null, false);
scopeCheck($fresh->app_modules->activate($moduleName) === true, 'fresh native activation succeeds');
scopeCheck(scopeRow()->installed_version === $target && (int) scopeRow()->active === 1, 'fresh tracking and active state confirmed');
$versionWrites = array_values(array_filter($fresh->db->writes, static fn($write) => isset($write[2]['installed_version'])));
scopeCheck(array_column(array_column($versionWrites, 2), 'installed_version') === [$target, $baseline, $target], 'real App_modules prewrite followed by baseline and native target write');
scopeCheck($fresh->load->forgeLoads === 1, 'native migration executor invoked');
scopeCheck(in_array('module_activated', hooks()->trace, true), 'existing lifecycle completion hook preserved');
scopeCheck($GLOBALS['scopeLogs'] === [], 'existing template synchronization finishes without error');
if ($moduleName === 'api') {
    scopeCheck(count(scopeDdl()) === 2, 'only two original fresh-install CREATE statements executed');
    scopeCheck($fresh->db->field_exists('permission_enable', 'scope_user_api'), 'legacy API schema completed before finalizer');
} else { scopeCheck(scopeDdl() === [], 'code-only activation has no DDL'); }
if ($moduleName === 'einvoice') {
    scopeCheck(count($fresh->db->get('scope_options')->result_array()) === 6, 'five existing e-invoice defaults retained without duplicates');
    scopeCheck($fresh->db->where('name', 'einvoice_default_invoice_template')->get('scope_options')->row()->value === 'custom-template', 'custom e-invoice option preserved');
}
hooks()->do_action('admin_init');
scopeCheck(count($fresh->app_menu->entries) === ($moduleName === 'api' ? 3 : 1), 'existing module menus retained');
if ($moduleName === 'einvoice') {
    scopeCheck(count($fresh->app->settings) === 1 && count($GLOBALS['scopeCapabilities']) === 1, 'settings and capabilities retained');
}
$business = scopeBusinessSnapshot();
$fresh->db->queries = [];
scopeCheck($fresh->app_modules->activate($moduleName) === true, 'already-active replay succeeds');
scopeCheck(scopeDdl() === [] && scopeBusinessSnapshot() === $business, 'already-active replay preserves business data');
// Deactivation/reactivation passes through the actual hook again.
$fresh->db->where('module_name', $moduleName)->update('scope_modules', ['active' => 0]);
$reflection = new ReflectionClass(App_modules::class);
$modules = $reflection->getProperty('modules')->getValue($fresh->app_modules);
$modules[$moduleName]['activated'] = 0;
$reflection->getProperty('modules')->setValue($fresh->app_modules, $modules);
$reflection->getProperty('active_modules')->setValue($fresh->app_modules, []);
$fresh->db->queries = [];
scopeCheck($fresh->app_modules->activate($moduleName) === true, 'reactivation reruns native NOOP');
scopeCheck(scopeRow()->installed_version === $target && $fresh->load->forgeLoads === 2, 'reactivation confirms tracking');
scopeCheck(scopeDdl() === [] && scopeBusinessSnapshot() === $business, 'reactivation preserves all existing rows');

$upgrade = scopeFixture($baseline);
$before = scopeBusinessSnapshot();
scopeCheck($upgrade->app_modules->upgrade_database($moduleName) === true, 'native supported upgrade happy path');
scopeCheck(scopeRow()->installed_version === $target, 'native upgrade persisted marker independently verified');
scopeCheck(scopeDdl() === [] && scopeBusinessSnapshot() === $before, 'upgrade has no DDL or business mutation');
$writeCount = count($upgrade->db->writes);
scopeCheck($upgrade->app_modules->upgrade_database($moduleName) === true && count($upgrade->db->writes) === $writeCount, 'native upgrade replay is no-op');

foreach (['failVersion', 'ignoreVersion'] as $failure) {
    foreach (['baseline', 'target'] as $stage) {
        $ci = scopeFixture($target);
        $ci->db->$failure = $stage === 'baseline' ? $baseline : $target;
        $expected = $stage === 'baseline' ? ($failure === 'failVersion' ? 'activation_version_write_failed' : 'activation_baseline_unconfirmed') : 'migration_failed';
        scopeThrows(static fn() => $ci->app_modules->activate($moduleName), $moduleName . '_' . $expected, $failure . ' at ' . $stage . ' fails closed');
        scopeCheck((int) scopeRow()->active === 0 && !in_array('module_activated', hooks()->trace, true), 'failed tracking never activates or announces success');
        $ci->db->$failure = null;
        scopeCheck($ci->app_modules->activate($moduleName) === true && scopeRow()->installed_version === $target, 'retry after tracking fault succeeds');
        scopeCheck(scopeDdl() === [], 'fault/retry never replays historical DDL');
    }
}
// Native executor failure is propagated, with recovery after the file is restored.
$ci = scopeFixture($target);
$migrationFile = $modulePath . 'migrations/' . str_replace('.', '', $target) . '_version_' . str_replace('.', '', $target) . '.php';
rename($migrationFile, $migrationFile . '.disabled');
try {
    scopeThrows(static fn() => $ci->app_modules->activate($moduleName), $moduleName . '_migration_failed', 'native runner failure is propagated');
    scopeCheck(scopeRow()->installed_version === $baseline && (int) scopeRow()->active === 0, 'runner failure keeps baseline and inactive state');
} finally { rename($migrationFile . '.disabled', $migrationFile); }
scopeCheck($ci->app_modules->activate($moduleName) === true, 'recovery after native runner fault');
scopeCheck(scopeDdl() === [], 'native runner recovery never replays historical DDL');

foreach (['0.0.0', '9.9.9'] as $version) {
    $ci = scopeFixture($version);
    scopeThrows(static fn() => $ci->app_modules->activate($moduleName), $moduleName . '_activation_version_invalid', 'unsupported installed version rejected');
    scopeCheck(scopeRow()->installed_version === $version && (int) scopeRow()->active === 0, 'invalid marker not rewritten');
}
$ci = scopeFixture(null);
$ci->db->failInsert = true;
scopeCheck($ci->app_modules->activate($moduleName) === false && scopeRow() === null, 'prewrite insertion failure prevents activation');

if ($moduleName === 'api') {
    $ci = scopeFixture($target, false);
    scopeThrows(static fn() => $ci->app_modules->activate($moduleName), 'api_legacy_install_unconfirmed', 'finalizer cannot attest missing API schema');
    scopeCheck((int) scopeRow()->active === 0, 'unconfirmed legacy API schema never activates');
    $migrationIds = array_map(static fn($path) => (int) substr(basename($path), 0, 3), glob($modulePath . 'migrations/*.php'));
    scopeCheck(array_values(array_filter($migrationIds, static fn($id) => $id > 103 && $id < 200)) === [], 'API historical gap 104–199 remains unfilled');
    scopeCheck(array_diff([101, 102, 103, 200, 201, 202], $migrationIds) === [], 'API historical migration set is preserved');
    scopeCheck(in_array(203, $migrationIds, true), 'approved API identity migration 203 is present');
}
echo $moduleName . ': ' . $GLOBALS['scopeChecks'] . " lifecycle checks passed (real App_modules and native runner; SQLite; SDK autoload adapter).\n";
