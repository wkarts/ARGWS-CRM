<?php
defined('BASEPATH') or exit('No direct script access allowed');

// No copying or unlinking application code. The framework loads the module library.
$CI = &get_instance();
if ((int) $CI->app->get_current_db_version() < 473) {
    throw new RuntimeException('efi_activation_core_upgrade_required');
}
foreach (['module_version_adoptions'=>['source_version','source_header_sha256'],
    'efi_charge_bindings'=>['account_hash','sandbox','invoice_id','issuer_id','amount_minor','payment_id'],
    'efi_charge_intents'=>['invoice_id','account_hash','state','provider_id','amount_minor']] as $suffix=>$columns) {
    $table = db_prefix() . $suffix;
    $metadata = $CI->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
    $engine = $metadata ? $metadata->row() : null;
    if (!$engine || strtoupper((string) $engine->ENGINE) !== 'INNODB') {
        throw new RuntimeException('efi_activation_schema_required');
    }
    foreach ($columns as $column) {
        if (!$CI->db->field_exists($column, $table)) { throw new RuntimeException('efi_activation_schema_required'); }
    }
}

$module = $CI->app_modules->get('gerencianet_gateway');
$row = $CI->db->where('module_name', 'gerencianet_gateway')->get(db_prefix() . 'modules')->row();
if (!$module || !$row || (string) $module['headers']['version'] !== '5.1.5'
    || (string) $row->installed_version !== '5.1.5') {
    throw new RuntimeException('efi_activation_explicit_adoption_required');
}
// Fresh installation is premarked by the framework; run the actual first step,
// not a fictitious legacy baseline. The step is idempotent and owns no DDL.
require_once __DIR__ . '/migrations/515_version_515.php';
(new Migration_Version_515())->up();
$migration = new App_module_migration('gerencianet_gateway');
if ($migration->to_latest() === false) { throw new RuntimeException('efi_activation_migration_failed'); }
$after = $CI->db->where('module_name', 'gerencianet_gateway')->get(db_prefix() . 'modules')->row();
if (!$after || (string) $after->installed_version !== '5.1.5') {
    throw new RuntimeException('efi_activation_tracking_unconfirmed');
}
