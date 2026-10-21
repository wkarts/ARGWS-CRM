<?php
/** Native module runner for disposable HTTP proofs; never rewrites an installed marker. */
function asaasHttpNativeTarget($db): string
{
    if (PHP_SAPI !== 'cli' || getenv('ISSUERS_BROWSER_DB_NAME') !== 'argws_upgrade_ci' || APP_DB_NAME !== 'argws_upgrade_ci') {
        throw new RuntimeException('disposable_http_database_required');
    }
    $header = file_get_contents(APP_MODULES_PATH.'asaas/asaas.php');
    if (preg_match_all('/^Version:\s*([1-9]\.[0-9]\.[0-9])\s*$/m', $header, $matches) !== 1) {
        throw new RuntimeException('trusted_asaas_release_header_required');
    }
    $target = $matches[1][0];
    $level = (int) str_replace('.', '', $target);
    $migrations = [];
    foreach (glob(APP_MODULES_PATH.'asaas/migrations/*_version_*.php') as $path) {
        if (!preg_match('/^(\d{3})_version_\1\.php$/', basename($path), $m)) throw new RuntimeException('invalid_asaas_migration_name');
        $migrations[(int)$m[1]] = $path;
    }
    if ($level < 152 || !$migrations || max(array_keys($migrations)) !== $level) throw new RuntimeException('asaas_release_migration_target_mismatch');
    foreach (range(146, $level) as $number) {
        if (!isset($migrations[$number]) || !preg_match('/class\s+Migration_Version_'.$number.'\s+extends\s+App_module_migration\b/', file_get_contents($migrations[$number]))) throw new RuntimeException('asaas_native_migration_gap');
    }
    $rows = $db->query("SELECT installed_version FROM tblmodules WHERE module_name='asaas'")->result_array();
    if (!$rows) {
        // I/O adapter for App_modules::activate_with_migrations initial registration only.
        // The real install hook supplies legacy DDL and performs its native migration/readback.
        // No existing module marker is changed or replayed through activation.
        $db->query("INSERT INTO tblmodules(module_name,installed_version,active) VALUES('asaas',?,0)", [$target]);
        require APP_MODULES_PATH.'asaas/install.php';
        $db->query("UPDATE tblmodules SET active=1 WHERE module_name='asaas'");
        $rows = $db->query("SELECT installed_version FROM tblmodules WHERE module_name='asaas'")->result_array();
    }
    if (count($rows) !== 1 || version_compare((string)$rows[0]['installed_version'], $target, '>')) throw new RuntimeException('installed_asaas_baseline_required_no_downgrade');
    // Existing native installs upgrade/replay through the real runner, without activation's tracking reset.
    $runner = new App_module_migration('asaas');
    if ($runner->to_latest() === false) throw new RuntimeException('asaas_native_migration_failed: '.$runner->error_string());
    $installed = $db->query("SELECT installed_version FROM tblmodules WHERE module_name='asaas'")->result_array();
    if (count($installed) !== 1 || $installed[0]['installed_version'] !== $target) throw new RuntimeException('asaas_native_target_readback_failed');
    require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCheckoutSchema.php';
    require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperationsSchema.php';
    require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefundSchema.php';
    \Asaas\CompanySchema::ensure($db, 'tbl', false);
    \Asaas\CompanyRemoteOperationsSchema::ensure($db, 'tbl', false);
    \Asaas\CompanyCheckoutSchema::ensure($db, 'tbl', false);
    \Asaas\CompanyRefundSchema::ensure($db, 'tbl', false);
    if ($level >= 153) {
        require_once APPPATH.'services/CompanyAsaasRefundSchema.php';
        \CompanyAsaasRefundSchema::ensure($db, 'tbl', false);
    }
    return $target;
}
